<?php
/* Google Calendar: free/busy of the owner's calendars and events (with Meet) in the booking calendar.
   Scopes of the stored refresh token: calendar.freebusy and calendar.app.created. */
declare(strict_types=1);

function google_token(): string
{
    static $tok = null, $until = 0;
    if ($tok !== null && time() < $until - 60) return $tok;
    $c = cfg();
    [$code, $j] = http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['client_id' => $c['google_id'], 'client_secret' => $c['google_secret'],
            'refresh_token' => $c['google_refresh'], 'grant_type' => 'refresh_token']));
    if ($code !== 200 || empty($j['access_token'])) throw new RuntimeException('Google token refresh failed: HTTP ' . $code);
    $tok = $j['access_token'];
    $until = time() + (int)($j['expires_in'] ?? 3600);
    return $tok;
}

function gcal(string $method, string $path, ?array $body = null, array $query = []): array
{
    $url = 'https://www.googleapis.com/calendar/v3/' . $path . ($query ? '?' . http_build_query($query) : '');
    [$code, $j] = http($method, $url, ['Authorization: Bearer ' . google_token(), 'Content-Type: application/json'],
        $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
   Its own reminder, a phone notification 10 minutes before (as her Google booking page sessions had), whatever the calendar's defaults. */
function gcal_create(DateTimeImmutable $start, string $summary, string $description, bool $online, ?string $guest): array
{
    $end = $start->modify('+50 minutes');
    $ev = ['summary' => $summary, 'description' => $description,
        'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
        'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => 'Europe/Prague'],
        'reminders' => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 10]]]];
    if (!$online) $ev['location'] = PLACE;
    if ($online && $guest) {
        $ev['attendees'] = [['email' => $guest]];
        $ev['guestsCanSeeOtherGuests'] = false;
        $ev['guestsCanInviteOthers'] = false;
    }
    if ($online) $ev['conferenceData'] = ['createRequest' => ['requestId' => bin2hex(random_bytes(16)),
        'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]];
    return gcal('POST', 'calendars/' . rawurlencode(cfg()['calendar']) . '/events', $ev,
        ['conferenceDataVersion' => 1, 'sendUpdates' => 'none']);
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

