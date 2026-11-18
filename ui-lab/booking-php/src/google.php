<?php
/* Google Calendar: free/busy of the owner's calendars and events (with Meet) in the booking calendar.
   Scopes of the stored refresh token: calendar.freebusy and calendar.app.created. */
declare(strict_types=1);

/* The access token lasts an hour. It is kept in the booking database (in the config folder, outside the web root)
   for that hour, so a visit asks Google once (free/busy) instead of twice (token, then free/busy): about 0.2 s less
   on every calendar load. Tied to the refresh token, so a new Google connection never reuses an old token.
   $fresh skips the stored one (Google refused it). */
function google_token(bool $fresh = false): string
{
    static $tok = null, $until = 0;
    if (!$fresh && $tok !== null && time() < $until - 60) return $tok;
    $c = cfg();
    $who = substr(hash('sha256', $c['google_refresh']), 0, 16);
    if (!$fresh) {
        try {
            $s = json_decode(state_get('google_access') ?? '', true);
            if (is_array($s) && ($s['who'] ?? '') === $who && !empty($s['token']) && time() < (int)($s['until'] ?? 0) - 60) {
                [$tok, $until] = [(string)$s['token'], (int)$s['until']];
                return $tok;
            }
        } catch (Throwable $e) { /* no database: refresh as before */ }
    }
    [$code, $j] = http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['client_id' => $c['google_id'], 'client_secret' => $c['google_secret'],
            'refresh_token' => $c['google_refresh'], 'grant_type' => 'refresh_token']));
    if ($code !== 200 || empty($j['access_token'])) throw new RuntimeException('Google token refresh failed: HTTP ' . $code);
    $tok = $j['access_token'];
    $until = time() + (int)($j['expires_in'] ?? 3600);
    try { state_set('google_access', json_encode(['token' => $tok, 'until' => $until, 'who' => $who])); }
    catch (Throwable $e) { /* not stored: the next visit refreshes again */ }
    return $tok;
}

function gcal(string $method, string $path, ?array $body = null, array $query = []): array
{
    $url = 'https://www.googleapis.com/calendar/v3/' . $path . ($query ? '?' . http_build_query($query) : '');
    $send = fn(bool $fresh) => http($method, $url, ['Authorization: Bearer ' . google_token($fresh), 'Content-Type: application/json'],
        $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    [$code, $j] = $send(false);
    if ($code === 401) [$code, $j] = $send(true);   // stored token revoked or expired early: one retry with a new one
    if ($code >= 300) throw new RuntimeException("Google $method $path: HTTP $code " . substr(json_encode($j), 0, 300), $code);
    return $j;
}

/* Free/busy of several calendars in one call: [calendar id => [[start ts, end ts], ...]].
   A calendar Google can't read is an error, never "nothing busy". */
function gcal_freebusy(array $ids, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $ids = array_values(array_unique(array_filter($ids)));
    $j = gcal('POST', 'freeBusy', ['timeMin' => $start->format(DATE_ATOM), 'timeMax' => $end->format(DATE_ATOM),
        'timeZone' => 'Europe/Prague', 'items' => array_map(fn($c) => ['id' => $c], $ids)]);
    $out = [];
    foreach ($ids as $id) {
        $cal = $j['calendars'][$id] ?? null;
        if ($cal === null || !empty($cal['errors'])) {
            throw new RuntimeException('Google free/busy: calendar not readable: ' . json_encode($cal['errors'] ?? 'missing'));
        }
        $out[$id] = array_map(fn($b) => [strtotime($b['start']), strtotime($b['end'])], $cal['busy'] ?? []);
    }
    return $out;
}

/* Busy intervals in [start, end) across the owner's calendars and the booking calendar. */
function gcal_busy(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    return array_merge(...array_values(gcal_freebusy(array_merge(cfg()['busy_calendars'], [cfg()['calendar']]), $start, $end)));
}

/* The session as an event. Online: Meet link, the client as a guest who can't invite or see others. No Google e-mails.
   Its own reminder, a phone notification 10 minutes before (as her Google booking page sessions had), whatever the calendar's defaults.
   The event id comes from the payment: if Google made the event but its answer was lost, the retry gets "already
   exists" instead of making a second event (16 Nov review: two events, only the second one known). */
function gcal_event_id(string $payId): string
{
    // Google allows the letters a-v and digits, 5-1024 characters; hex fits
    return 'khg' . substr(hash('sha256', site_copy() . ':' . $payId), 0, 40);
}

function gcal_create(DateTimeImmutable $start, string $summary, string $description, bool $online, ?string $guest, string $payId): array
{
    $end = $start->modify('+50 minutes');
    $id = gcal_event_id($payId);
    $ev = ['id' => $id, 'summary' => $summary, 'description' => $description,
        'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
        'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
        'reminders' => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 10]]],
        // the payment, for looking it up; not shown (it used to stand in the description)
        'extendedProperties' => ['private' => ['platba' => $payId]]];
    // her calendar tells the formats apart by colour, as with Google's booking pages (owner, 18 Nov): the API offers
    // only the 11 classic event colours; 7 Peacock (blue) online, 4 Flamingo (peach) in person
    $ev['colorId'] = $online ? '7' : '4';
    if (!$online) $ev['location'] = PLACE;
    if ($guest) {   // in person too, as Google's booking page does (sendUpdates=none: no Google invitation e-mail)
        $ev['attendees'] = [['email' => $guest, 'responseStatus' => 'accepted']];   // booked and paid: coming (as Google's booking page marks it)
        $ev['guestsCanSeeOtherGuests'] = false;
        $ev['guestsCanInviteOthers'] = false;
    }
    if ($online) $ev['conferenceData'] = ['createRequest' => ['requestId' => bin2hex(random_bytes(16)),
        'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]];
    try {
        return gcal('POST', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events', $ev,
            ['conferenceDataVersion' => 1, 'sendUpdates' => 'none']);
    } catch (RuntimeException $e) {
        if ($e->getCode() !== 409) throw $e;
        return gcal_get($id);   // made by an earlier try whose answer never came: that one is the event
    }
}

/* One event of the booking calendar (for its Meet link, which Google may add a moment after creating it). */
function gcal_get(string $eventId): array
{
    return gcal('GET', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events/' . rawurlencode($eventId));
}

function gcal_delete(string $eventId): void
{
    try {
        gcal('DELETE', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events/' . rawurlencode($eventId), null, ['sendUpdates' => 'none']);
    } catch (RuntimeException $e) {
        if (!in_array($e->getCode(), [404, 410], true)) throw $e;   // already gone is fine
    }
}

/* Moves an event (and its Meet link with it), no Google e-mails. */
function gcal_move(string $eventId, DateTimeImmutable $start): void
{
    gcal('PATCH', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events/' . rawurlencode($eventId), [
        'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
        'end' => ['dateTime' => $start->modify('+50 minutes')->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
    ], ['sendUpdates' => 'none']);
}

/* Events of the booking calendar changed since $since (deleted ones too, with status "cancelled"). */
function gcal_changed(int $since): array
{
    $out = [];
    $q = ['updatedMin' => gmdate('Y-m-d\TH:i:s\Z', $since), 'showDeleted' => 'true', 'singleEvents' => 'true', 'maxResults' => 250];
    do {
        $j = gcal('GET', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events', null, $q);
        array_push($out, ...($j['items'] ?? []));
        $q['pageToken'] = $j['nextPageToken'] ?? null;
    } while ($q['pageToken']);
    return $out;
}

