# Own booking: spec and decision log

Replaces the two Google Calendar booking pages (`CALENDAR_ONLINE`, `CALENDAR_IN_PERSON` in
`js/script.js`) with a booking page in the site's own design, paid through Stripe.

- Status (13 Nov 2026): front-end prototype done, back end not started.
- Prototype: `ui-lab/rezervace-ukazka.html` on branch `redesign` (commit c9313cb). It is a
  mockup: the free times are a copy of the Google pages from 13 Nov, and nothing is booked or
  charged. Comparison pages and rejected variants are in `ui-lab/rezervace/`.
- Keep this file current: when a decision changes, edit the rule and add a line to the
  decision log at the bottom.

## 1. Goal

A client can book and pay for a session on a phone in about a minute, without leaving the
site's look and without a Google account. Booking online is the only way to book, so the page
has to work reliably.

Done when:

- A real client can book online and in person, pay, get the confirmation e-mail, and the session
  is in the owner's calendar with no double booking.
- Three people who have never seen the page book a test session on their own phones without help.
- The live page passes the site's speed rules (see 6) and the indexing check.

## 2. Fixed facts

| | |
|---|---|
| Session | always 50 minutes, 1 100 Kč, one type only (no first-session or couple variants) |
| Formats | Online (Google Meet), Osobně (Vítkovická 3299/3A, Ostrava) |
| Booking window | from now up to 70 days ahead |
| Cancellation | free up to 36 hours before the session, full refund |
| Payment | before the session, through Stripe Checkout (the last step redirects there). The Stripe account is already live: the Google booking pages hand off to it today |
| Time zone | all times are Prague time |
| Calendars | Google marks both formats busy when either is booked (one hour can't be sold twice) |

Still to decide: minimum notice (how soon before a session it can still be booked). See 8.

## 3. Flow

### Step 1: day and time

- Price line at the top: `50 min · 1 100 Kč · Google Meet` or `… · Vítkovická 3299/3A`.
  The page heading "Objednat sezení" is in the page for screen readers but not shown.
- Online / Osobně switch: two-sided pill (switch 8). The chosen side slides over in its format
  colour (sky online, peach in person); 0.3 s, off with reduced motion.
- Month calendar, weeks start Monday. Free days are tinted in the format colour, days with
  nothing free are faded and can't be tapped, the chosen day is coral.
- Opens on the first month with a free day, **with no day chosen**.
- Space under the calendar says "Vyberte den v kalendáři, nebo" and a link
  "nejbližší volný: středa 9. prosince →" (the soonest free day for the current format).
- Changing month (arrows or swipe) clears the day. A month with nothing free says
  "V listopadu už nejsou volné termíny." with a "Nejbližší volný termín" link.
- Switching format keeps the day if it is free in the other format, otherwise clears it.
  If the shown month has nothing free in the new format, it jumps to the first month that does.
- Times: tinted buttons, three per row on phones. Online only: "Časové pásmo: Praha" under them.
- **Tapping a time goes straight to step 2** (no "Pokračovat" button, no note under the times).

### Step 2: details

- Summary box: date, time–end time, format, 50 minut, 1 100 Kč, and the address for in person.
- "‹ Změnit termín" goes back to step 1 with the day and time still chosen.
- Fields, all 16 px, white:
  - Jméno and Příjmení, separate (autofill), required.
  - Online: E-mail, required (the Meet link goes there). In person: Telefon, required.
    Never both.
  - "S čím přicházíte (nepovinné)", empty, no placeholder.
- No consent checkbox. No payment note under the button.
- Errors show under the field on submit, focus goes to the first one, and an error clears as soon
  as the field is edited.
- Button: currently "Zaplatit 1 100 Kč"; it will lead to Stripe, see 5.

### Step 3: confirmation (after Stripe)

- "Termín je rezervovaný", the summary, where the confirmation went, the format's practical note
  (Meet link in the e-mail / address and "zvoňte až v čase vašeho sezení"), and
  "Zrušit zdarma můžete nejpozději 36 hodin před sezením, vrátím celou částku."
- The texts speak as the therapist ("pošlu", "vrátím"), never "we".

### Navigation and state

- Format, day, time and step live in the address (`?forma=osobne&den=2026-12-18&cas=10:00&krok=2`),
  so a refresh keeps the choice. Invalid or outdated values open a clean calendar.
  The confirmation is never restored from an address.
- Step 2 is its own history entry: the phone's back button returns to the calendar. Choices in
  the calendar don't add history entries.

## 4. Design rules

- The redesign theme (`ui-lab/proposal/`): Inter, Fraunces 500 for headings, cream page `#FBF8F3`,
  navy text, coral buttons, sky = online, peach = in person.
- The real site header on top. No card around the booking at any width; cream page everywhere.
- Buttons follow the site button: 56 px, Inter 18/600, pill, full width on phones.
- Light only (`color-scheme: only light`), no blue tap flash.

## 5. Screens still to design (need the back end)

- Loading the free times: placeholder in the reserved space, nothing jumps.
- Times failed to load: "Termíny se nepodařilo načíst" + "Zkusit znovu".
- Slot taken meanwhile: "Tento čas si mezitím někdo zarezervoval", back to the calendar with
  the time gone.
- Hand-off to Stripe: button text (probably "Pokračovat k platbě"), a busy state, no double submit.
- Payment not completed (client cancelled or card failed): explain, keep or release the slot,
  link back.
- Confirmation page reached from Stripe's return URL.

## 6. Quality bar

- Phones first: checked at 320, 375×667 and 375×812, plus tablet and desktop.
- Layout shift 0: the times area always has room for two rows plus the note, and every month
  has six week rows, so nothing moves between days, months or formats.
- No third-party requests on the page itself (Stripe only after the redirect). Self-hosted fonts.
- Tap targets 44 px (dates are 36 px at 320 px wide, accepted for now).
- Keyboard: focus stays on the control after each choice; a screen-reader status line says
  e.g. "Středa 9. prosince, 2 volné časy".

## 7. Back end: what it must do

1. **Free times**: read both Google calendars (free/busy) and return the free 50-minute slots
   within the 70-day window, after the minimum notice, with holidays and blocked days removed.
2. **Hold**: when a client goes to pay, hold the slot for a few minutes, so two people can't pay
   for the same time.
3. **Payment**: create a Stripe Checkout session (1 100 Kč, client e-mail prefilled for online).
   Stripe collects the e-mail for in-person clients too, so they get a receipt.
   What the Google hand-off shows today (seen 14 Nov) and what ours fixes:
   - Line item in English ("Terapie online / Thursday, December 10 · 14:00 – 14:50"):
     ours sets a Czech one, e.g. "Sezení online · čtvrtek 10. prosince · 14:00–14:50".
   - E-mail asked again, empty: ours prefills it.
   - The back arrow leads to Google: ours returns to the booking page (cancel URL), and success
     goes to our confirmation (step 3).
   - Stripe's own texts ("vy@example.com", "Podmínky", "Soukromí") can't be changed.
4. **On payment** (Stripe webhook, not the return page): create the calendar event in the right
   calendar, add a Meet link for online, and send the confirmation e-mail. Next to the webhook, a
   safety net every few minutes: ask Stripe for the paid sessions of the last 24 hours and book any
   that are missing (a client who paid and closed the tab, or a webhook that failed). Booking is
   keyed by the payment id, so the webhook, the return page and the safety net never book twice.
   All times in Europe/Prague (summer time included), never a fixed offset.
5. **Cancellation** (link in the confirmation e-mail): 36+ hours before, refund automatically
   through Stripe; later, no refund. Either way delete the calendar event and free the slot.
   The link is signed (HMAC of the payment id with a server secret), so nobody can cancel someone
   else's booking by guessing. It opens a cancel page that shows the booking and what happens to
   the money before the client confirms (demo: `ui-lab/rezervace/zruseni.html`).
6. **Confirmation e-mail** (demo: `ui-lab/stripe-demo/mail.py`): subject "Potvrzení: sezení v úterý
   22. prosince v 13:00"; date and time, format, the Meet link as a button (online) or the address
   with "Navigovat" (in person), the free-cancel deadline as a date and time ("do pondělí 21. prosince
   1:00"), the cancel link, contact. Plain text and HTML, plus an .ics file to add to the calendar
   (METHOD:PUBLISH, so no invitation flow). From `rezervace@khgporadenstvi.cz`, reply to the
   owner's address. Texts use "vy".
7. **Reminders**: later, not for the first version.

Where it runs: checked 14 Nov in the Webkitty panel (read only). The hosting has PHP up to 8.5
(the site is set to 7.4, unused), with curl, openssl, sodium, PDO MySQL and SQLite; MySQL (0 of 3
databases used); Scheduled Tasks down to every minute; SSH; e-mail with send-only addresses and
SMTP (smtp.khgporadenstvi.cz), DKIM and DMARC. Python exists only for cron, not for web pages.
RECOMMENDED: the back end in PHP 8.4 on the same hosting (same domain, no extra cost), the safety
net and reminders as a cron task, e-mail through a send-only address `rezervace@`.
Checked 14 Nov with a temporary PHP file on the live hosting (owner's OK, no keys, deleted right
after, the site answered 200 throughout): PHP on the server reaches api.stripe.com (HTTP 401, the
normal answer without a key), oauth2.googleapis.com and www.googleapis.com (404/403, same), and the
SMTP ports 587 and 465 of smtp.khgporadenstvi.cz are open. Nothing blocks the back end there.
Web pages run PHP 7.4 today (the setting in "Change PHP Version"); switch to 8.4 before the back end
goes live (the static site does not use PHP, so nothing else changes).
The demo stays in Python; mail.py and the cancel logic are written to port one to one.

## 8. Open questions

- Where the back end runs: a script on the current hosting, or a separate small service.
- Minimum notice before a session (e.g. not within 24 hours).
- How a client cancels: a link in the e-mail, or by writing to the owner.
- What the confirmation e-mail says and who it comes from.
- Refund after the 36 hours: none, partial, at the owner's discretion?
- Receipts / invoices beyond Stripe's receipt.
- Privacy: "S čím přicházíte" can contain health information. Mention it in the privacy policy
  and decide how long it is kept. Check with an adviser whether more is needed.
- Terms: Stripe Checkout can show "I agree to the terms" once a terms page URL is set in Stripe.
  The terms page itself doesn't exist yet.
- Google calendar settings seen on 13 Nov: nothing free before 9 Dec, weekend slots on 19–20 Dec,
  online slots on 24–25 Dec. Intentional?

Stripe dashboard settings (the owner, can be done now, they also improve today's Google hand-off):

- Branding: button and accent colour to the site's coral `#E8927C` (now Stripe blue), logo/icon.
- Payment methods: turn on Apple Pay and Google Pay (only card shows today); decide whether to
  keep Link (the big green button).

## 9. Launch checklist

1. The owner tries the prototype on a real phone: month swipe, back gesture, scroll to the times
   after choosing a day, no tap flash.
2. The owner opens and verifies the new Stripe account (see the decision log, 14 Nov).
   Build the back end and the screens in 5.
3. Port a clean page into the site (no lab switches `?c ?p ?sw ?d ?tz ?step`, no demo data),
   new URL in `sitemap.xml`, own meta description, canonical and Open Graph tags.
4. Point "Objednat se" (header, booking modal, Ceník buttons) to the page, with the format set
   where it is obvious.
5. Back end on the hosting: PHP 8.4 for the site, cron every minute, config outside the web root,
   `.htaccess` blocking `src/`, `tools/`, `tests/`; check that REMOTE_ADDR is the visitor's IP
   (not a proxy's), or the hold limit counts everyone as one visitor; DKIM and DMARC
   for the domain's e-mail; live Stripe keys; the shared password of the hours page
   (`tools/owner-password.php` on the hosting), then the owner's regular week and first changes on the
   page, signed in on each device (PC Windows and Ubuntu, both phones). HTTPS only (the cookie is Secure).
6. Test with three people, fix what they trip on.
7. `python3 scripts/check-indexing.py` before pushing to `main`, `--live` after the deploy,
   PageSpeed (mobile) for the booking page.
8. Off the site (owner): the booking link on Google Business Profile, Firmy.cz, ZnámýLékař,
   Terapie.cz, Živéfirmy.cz, Facebook and Instagram; close or redirect the old Google booking
   pages once the new one works.

## Decision log

13 Nov 2026

- Chosen design: the original month calendar ("Původní"), over the wizard (A, A2) and
  calendar E. Google's own pages kept only as a reference.
- Switch 8 (two-sided pill, deeper tint), later animated. Coral for the chosen day and time.
- No card at any width (dropped on phones first, then everywhere); tablet/desktop layouts
  with a panel, a card or a side column rejected.
- Polish kept: faded unavailable days, tinted time buttons, price line under the heading,
  white form fields. Rejected: coral link line, confirmation tick, narrower desktop.
- Removed: "Objednat sezení" heading (visually), the note under the times, the Stripe payment
  note, the reason placeholder.
- Name split into first and last name. One contact field per format. No consent checkbox.
- No day preselected; "nejbližší volný" shortcut added.
- 36-hour free cancellation, 70-day window, "Časové pásmo: Praha", one voice ("vrátím").
- Back button and refresh keep the choice.
- Plan: ship the redesign separately with the Google booking, build the back end next, then
  port the booking page into the site together with it.

14 Nov 2026

- Payment stays with Stripe. QR payment rejected for good (slow, can't be verified at once);
  Czech gateways (Comgate, GoPay, …) rejected for now (slow onboarding, weaker tooling).
- Payment inside the page, lab mockups in `ui-lab/rezervace/platba.html` (`?pay=0..3`):
  0 redirect to Stripe Checkout (today's plan), 1 card fields under the details on one screen,
  2 payment as its own step in our design, 3 Stripe's embedded form. Verdict pending.
  1 and 2 need Stripe.js on the page (load it only on the details step), Apple Pay domain
  verification, and an optional e-mail field for in-person clients (receipt).

- Stripe test access: restricted test key "KHG demo" (Checkout Sessions, Payment Intents,
  Customers, Charges and Refunds: Write) in `~/.config/khg/stripe-test.env`, outside the repo.
  A sandbox could not be created (the account is managed by Google Calendar), so it is the
  legacy test mode, which shares settings such as branding with live. Branding left as it is.
- Decided: the own booking gets a new Stripe account under the same login (the current one is
  managed by Google Calendar). The owner opens and verifies it when the back-end work starts;
  then the demo moves to its sandbox and the "KHG demo" test key on the old account is deleted.
  The old account stays for the Google booking until it is retired, then for history and
  refunds of old bookings.
- Live payments (owner's screenshot, last 20): Apple Pay 8, Link 7, card typed in 5. Three in
  four clients pay with a wallet, so wallets are a must in any variant. Keep Link (don't hide it).
  Google Pay never shows today because the Google Calendar hand-off doesn't allow it; the own
  booking should turn it on (Stripe Checkout shows it on Android Chrome without extra work).
- DECIDED: variant 0, redirect to Stripe Checkout (tested with real test payments, incl. 3D Secure
  and a declined card). Wallets (Apple Pay, Google Pay, Link) work there with no extra work and no
  Stripe script on our page. Checkout gets the Czech line item, the e-mail prefilled, success back to
  our confirmation (step 3), cancel back to the details (step 2). Variants 1–3 stay only in the lab.
  To check in Stripe settings before launch: Google Pay on, Klarna off (therapy shouldn't be on credit).
- Google stays for version 1 (the owner's diary and Meet). Tested with the owner's Workspace
  account through a Cloud project "khg-rezervace-demo" (consent screen Internal, Desktop OAuth
  client, credentials and token in `~/.config/khg/`). Scopes kept minimal: `calendar.freebusy`
  (busy/free only, no titles) and `calendar.app.created` (writes only to calendars the app made).
  Demo writes only to its own calendar "KHG test rezervace".
- Results: free/busy of the primary calendar works and matches the Google booking pages (all
  slots offered on 13 Nov are free, one booked since). After a paid Stripe Checkout the demo
  creates the event (Meet link for online, address for in person), once per payment even when the
  return page is loaded again.
- Found: the hours set in Google's appointment schedules can't be read through the API (507 free
  calendar hours 8–17 that Google doesn't offer). The own booking needs its own working hours per
  format (settings in the booking system, or an "Ordinační hodiny" calendar). Decide in section 8.
- Meet waiting room checked 14 Nov: a client opening the link from a phone (other account) had to
  ask to join, and the owner's account saw "Čeká 1 host" with Povolit / Zakázat as moderator.
  DECIDED (A, like the Google booking today): online clients are added to the event as a guest
  (no Google invite sent, guests can't see or invite others). Signed in under that e-mail they
  join straight away; anyone else with the link still asks to join and the owner admits them.
- Still to do in the back end: booking from the Stripe webhook instead of the return page.
- Direct join verified 14 Nov: the guest (a real Gmail) opened the link and joined without asking.
  The event is created with guests unable to invite others, see other guests or edit
  (guestsCanInviteOthers/SeeOtherGuests/Modify = false). The owner also switched off "guests can
  invite others" on the live Google booking.
- Cancellation today: the client can cancel any time, the slot frees up, and the owner refunds by
  hand in Stripe when the 36-hour rule is met. Proposed for the own booking: refund automatically
  when cancelled 36+ hours before, no refund later (the slot still frees up); the owner can always
  refund by hand in Stripe. DECIDED 14 Nov: automatic, as proposed.
- Payment tests 14 Nov (demo, Stripe test mode, variant 0):
  - Cancel 36+ hours before (UI button): refund 1 100 Kč succeeded, calendar event deleted; a second
    cancel returns the same result and does not refund again. Also checked on a 3D Secure payment.
  - Cancel 10 hours before (simulated time): no refund, event deleted. After the start: refused.
  - Declined card (4000 0000 0000 0002) and a failed bank check (3D Secure "Fail"): Stripe shows a
    Czech error, the client stays on the payment page and can try again. Passed bank check: paid.
  - Back arrow on Stripe: returns to step 2 with the slot. FIXED: what the client typed was lost;
    it is now kept for that tab (sessionStorage) and forgotten once paid.
  - Paid, then the tab closed (return page never loaded): Stripe paid, no booking. FIXED in the demo
    with the safety net above; in the real back end the webhook plus the safety net.
  - Summer time: the demo used a fixed +01:00, so from April to October the free/busy check and the
    36-hour rule were an hour off. FIXED: Europe/Prague; across the clock change it counts real hours,
    and exactly 36 hours still refunds.
  - Not tested in the pane: Link, Apple Pay and Google Pay (need a real phone, at launch). Refunds go
    through the payment, not the method, so they work the same way.
  - Cost: on a refund Stripe usually keeps its processing fee, so each early cancellation costs it.
- Owner's side, 14 Nov: Google appointment schedules (weekly "Celková dostupnost" plus per-date
  "Aktuální dostupnost") can't be read by the API, so the own booking takes hours from Google
  calendars instead (RECOMMENDED A, no admin page): two calendars, "Ordinace online" and
  "Ordinace osobně" (the hours of the two formats may differ), with weekly repeating events for
  the regular hours; one day changed = that one occurrence edited. Read through free/busy (no new
  permission). A slot is offered for a format when it lies inside that format's hours and nothing
  is busy in "Terapie" or the bookings calendar; a time in both calendars is open for either, and
  whichever books first takes it. Doctor or holiday: a normal busy event in "Terapie" (all-day:
  "Mimo kancelář" or marked busy). No new dashboard: Google Calendar, Stripe, and a "Nová rezervace"
  e-mail to the owner with signed "Zrušit a vrátit peníze" / "Přesunout" links (not in the event
  description: online clients are guests and can read it). Both still to build.
- PHP back end, 14 Nov (lab, `ui-lab/booking-php/`, launch "booking-php" on port 8820, PHP 8.4
  compatible, no mbstring/intl): config and secrets in `~/.config/khg/` (live: outside public_html),
  SQLite for holds and bookings, Stripe and Google over plain HTTPS (no SDKs).
  - Built: checkout with a 30-minute slot hold (Stripe session expires with it; one hold per start
    time, so online and in-person can't both take it), booking after payment from the return page,
    the signed Stripe webhook or the safety net (`cron.php`), all keyed by the payment id; retry of
    failed bookings and unsent e-mails; confirmation e-mail; signed cancel link; refund rule.
    Checkout sessions carry metadata `zdroj=web`; the safety net books only those (the Stripe
    account may also get other payments).
  - Tested: 48 offline checks (`php ui-lab/booking-php/tests/run.php`: time zones, input checks,
    tokens, webhook signatures incl. replay, Czech texts, e-mail and .ics, holds, cancel rules,
    36-hour boundary); live with Stripe test + Google: two clients on one slot at once (second gets
    "Tenhle termín si právě někdo rezervuje", shown under the pay button), payment -> event with
    Meet (client as guest, can't invite) -> e-mail; lost return page -> signed webhook books once,
    forged webhook refused; failed booking -> next safety-net run books it; cancel page -> refund
    1 100 Kč succeeded, event deleted.
  - Found while testing: the old Python demo's safety net booked a PHP payment a second time (both
    share one Stripe test account and calendar). The Python demo now skips `zdroj=web` sessions and
    is stopped; use only "booking-php" from now on.
  - Not built yet: SMTP sending (needs the `rezervace@` mailbox), free times from "Ordinace online" /
    "Ordinace osobně" (the page still uses sample hours), the owner's "Nová rezervace" e-mail with
    cancel/move links, live layout (.htaccess route for /api/, code outside public_html), the
    webhook endpoint in the new Stripe account, the cron task, PHP 8.4 switch.
- Free times, 14 Nov (`src/slots.php`, `GET /api/slots`): one Google free/busy call reads both hours
  calendars, the owner's busy calendars and the bookings calendar for the whole window (~0.5 s).
  Sessions are offered every hour from the start of each hours block (block 8:00–12:00 gives 8, 9, 10,
  11). Left out: anything busy, a time someone is paying for (30-min hold), Czech public holidays
  (setting), times sooner than 24 hours (setting, was 1 hour; open question 8), beyond 70 days. The
  checkout re-checks the time against the same rules (her calendar may have changed since the page
  loaded). Lab: two app-owned demo calendars "KHG test · Ordinace online/osobně" with weekly hours
  roughly like the 13 Nov booking pages (`tools/lab-hours.php`, ids in `google-hours-calendars.json`).
  Live: `HOURS_ONLINE`, `HOURS_OSOBNE` = the ids of her two real calendars, `BUSY_CALENDARS` = her
  own calendar.
  - Tested: one "Pryč" event (20 Nov 10:00) removed 10:00 from online and in person at once; a held
    time disappears and comes back when released; checkout refuses blocked, outside-hours, wrong-day
    and too-soon times; page loads real times with CLS 0 at 375 px, link to a taken time falls back to
    that day; 14 new offline checks (62 total): holidays incl. Easter, blocks, busy edges, notice,
    all-day hours event, summer-time day, overlapping blocks.
  - For the owner: hours events must be "Busy" (Google's default for timed events). An all-day
    event in her own calendar is "Free" by default in Google: for a holiday use "Mimo kancelář" or set
    it to Busy, otherwise it blocks nothing. Not tested: the page's "couldn't load" message.
- Owner side, 14 Nov: "Nová rezervace" e-mail to kristyna@ (reply goes to the client): when, format,
  name, e-mail/phone, what the client wrote in "S čím přicházíte" (kept only in our database and this
  e-mail: not in Stripe, not in the calendar event), a warning when something else is in her calendar
  at that time, and two buttons to her page `ui-lab/rezervace/sprava.html` (signed with a separate
  key, a client's link can't do her actions; no-referrer).
  - "Přesunout termín": free times of the same format (same rules as the booking page), pick one,
    confirm. The Google event moves with its Meet link, the client gets "Změna termínu" (old time
    named, .ics with the same UID and a higher SEQUENCE so calendar apps update the entry), the
    client's cancel link keeps working with the new 36-hour deadline. No new payment.
  - "Zrušit a vrátit peníze": any time, always the full 1 100 Kč; event deleted; client gets
    "Zrušené sezení" with a link to book again. On a booking the client cancelled late (no refund)
    the page offers "Přesto vrátit 1 100 Kč" and the client gets "Vracím vám platbu".
  - When the client cancels from their link she gets "Zrušeno klientem" (lead time in hours or days,
    refunded or not). The client gets an e-mail too (owner, 14 Nov): "Zrušené sezení" with
    "Částka bude vrácena…" or, when cancelled under 36 hours, "Platba se nevrací, termín byl zrušen
    méně než 36 hodin předem.", plus "Vybrat jiný termín". The page says "Potvrzení bylo odesláno e-mailem." No refund button in that e-mail (owner, 14 Nov): if she wants to refund a late
    cancellation anyway, she opens the booking from her "Nová rezervace" e-mail ("Přesto vrátit").
  - Tested end to end (Stripe test, demo calendars): booking with a note -> both e-mails; her move
    button -> event moved in Google (same Meet), client e-mail, old time free again, new time taken;
    her cancel -> refund 110000 succeeded "zruseno terapeutkou", event cancelled, client e-mail; second
    press refunds nothing more; wrong link and the client's link on her actions -> 403; client cancel
    -> "Zrušeno klientem". CLS 0 at 375 px and desktop. 81 offline checks.
  - Fixed on the way: after paying, the booking page fell back to the calendar (the just-paid time
    is no longer free); it now takes the time from the payment.
  - The owner can move only to free times inside her hours and 24 hours ahead.
- Decision 14 Nov (owner), REPLACED the same day (see "Client move" below): moving a session stays with the therapist only. Clients get no move
  button; to change the time they cancel from their link (refund 36+ hours before) and book again,
  or call/write her and she moves it with her button, at any time.
- E-mail sending, 14 Nov (`src/smtp.php`): SMTP smtp.stackmail.com:587 with STARTTLS (TLS 1.3, valid
  certificate), login rezervace@khgporadenstvi.cz (send-only: the domain's MX is Google, so this
  address receives nothing; replies go to kristyna@ or the client via Reply-To). Password only in
  `~/.config/khg/smtp.env` (chmod 600), filled in by the owner. Lab safety switch: real e-mails only
  to LAB_MAIL_TO, the therapist's e-mails redirected to LAB_OWNER_TO, subjects "[TEST]"; every
  e-mail is also kept in the outbox folder. `tools/mail-test.php` sends a sample confirmation and a
  sample "Nová rezervace".
  - Tested: both arrived in Gmail's main inbox (Important, not spam); SPF pass
    (185.151.28.66 relay2.stackmail.com). No DKIM signature, no DMARC record on the domain.
  - To do before launch (owner, in Webkitty DNS): turn on DKIM for khgporadenstvi.cz e-mail if
    StackCP offers it, and add a DMARC record (start with p=none). Helps delivery to Seznam.cz.
  - The rezervace@ password was partly visible in a screenshot on 14 Nov; the owner will change it
    (then update SMTP_PASS in smtp.env, and later on the hosting).
- Test round, 14 Nov (Stripe test, demo calendars, real SMTP):
  - In person: the form asks only for a phone, so her e-mail said the client gave no e-mail and the
    client got no confirmation. Stripe's page always asks for an e-mail: the booking now takes it from
    the payment (`customer_details.email`). Fixed and re-tested.
  - Late client cancellation (lab switch LAB_FREE_CANCEL_HOURS, ignored in live mode): no refund,
    time freed, her e-mail without a refund button; then "Přesto vrátit" on her page refunded 1 100 Kč
    and the client got "Vracím vám platbu".
  - Free times can't be read (Google error): the booking page says so with her phone number, the
    calendar stays inert; her page has its own wording ("zkontroluj kalendáře Ordinace…").
  - She moves a booking to a time a client is paying for right now: refused ("už není volný"),
    booking unchanged.
  - Mail server down: both e-mails stay marked unsent, the next cron run sends them. The cron output
    now lists such bookings as `mail_failed`, not `mailed`.
  - Sample e-mails sent to a Seznam.cz address and to an iPhone (Gmail) for the owner to look at.
  - Security review: signed links (HMAC, constant-time compare, separate owner key), webhook
    signature with a 5-minute window, price only on the server, SMTP with certificate check, client
    text escaped in her page and in e-mails. Fixed: name, phone and note were cut by bytes, so a long
    Czech note (about 750+ letters) could split a letter and be silently dropped; now cut by letters.
    Fixed: the paid confirmation page put the e-mail and phone into the page unescaped (only someone
    who paid could abuse it); now escaped.
  - Open (before launch): nothing limits how many times a stranger can hold slots (each hold blocks
    a time for 30 min). Done the same day, see "Hold limit" below.
- Client move, 14 Nov (owner's wife: it saves her admin). The client's link from the confirmation
  now opens "Přesunout nebo zrušit termín": the client may move to a free time of the same format
  as often as they like (owner, 14 Nov: no limit; first built as one move), while a free cancel is
  still possible (36+ hours before the time booked now). Later only a cancel (or a call to her). The
  client gets "Změna termínu" (new .ics, same Meet link), she gets "Přesunuto klientem", from the
  second move with "Už je to N. přesun od klienta." so someone who keeps postponing stands out. The
  confirmation e-mail says "Termín můžete přesunout nebo zdarma zrušit do …".
  API: GET /api/terminy, POST /api/presunout (client's signed link).
- Her own edits in Google Calendar, 14 Nov (she used to move bookings by editing the event; clients
  sometimes "cancel" by deleting it from their calendar). The cron reads the booking calendar's
  changed events every minute (one Google call):
  - she drags a session to another time: the booking follows, the old time is free, the client gets
    "Změna termínu";
  - she deletes it: the booking is cancelled and the time free, no refund and no client e-mail yet;
    she gets "Smazáno v kalendáři" with "Vrátit peníze a napsat klientovi";
  - an online client declines the invitation (or deletes it from their calendar): she gets "Klient
    odmítl pozvánku"; nothing else changes (no refund, the time stays booked).
  - Google itself may also e-mail the guest when she moves or deletes the event, if she lets it.
  - Tested end to end: client move on the page (Google event moved, both e-mails), a second client
    move with the count in her e-mail; the owner's link on the client route refused; her drag in Google -> booking moved and client
    e-mail within one cron run; decline -> one e-mail; deletion -> cancelled, her page offers the
    refund. 92 offline checks.
- Hold limit, 14 Nov: at most 3 open payments per visitor (keyed hash of the IP, never the IP itself).
  A 4th frees that visitor's oldest hold (its Stripe page is expired) instead of refusing, so going
  back and forth between times never blocks a client, while one visitor can't block more than 3 times.
  A paid session that is still being booked keeps its hold. Tested: 4 checkouts from one browser ->
  the first released and expired on Stripe.
- Reminder e-mails, 14 Nov (owner): like Google's booking page does today (clients told her they get
  one 24 hours before and one 10 minutes before), the same two for everyone with an e-mail.
  - 24 hours before, "Připomínka: sezení zítra v 10:00": the Meet button or the address and navigation,
    and "Kdybyste nemohli přijít, zrušte prosím termín…" with the cancel link (no refund by then).
    Skipped when the booking or a move is under 6 hours old (the confirmation just came) or under
    2 hours are left.
  - 10 minutes before, "Za 10 minut: sezení v 10:00": the Meet button or the address, and "Kdybyste
    se zdrželi, napište mi nebo zavolejte". No cancel link.
  - Sent by the cron, each once per booking and time (a move sends both again for the new time); runs
    before the Stripe part, so a Stripe outage doesn't stop them. (First built as one e-mail 30 minutes
    before; changed the same day.)
  - Tested: both kinds arrived in Gmail, a second cron run sent nothing. 102 offline checks.
- E-mail look, 14 Nov (owner: "more our theme, no extra cuteness"; then: the new UI, not the live one):
  every e-mail on one layout in the redesign's theme: the new header's brand (coral heart, name in
  Fraunces, "PSYCHOLOG A TERAPEUT") as a small PNG drawn from the site's own fonts
  (`ui-lab/booking-php/assets/brand.png`, 6 KB) and sent inside the e-mail (cid), so it shows without
  the live site, which still has the old gold logo (owner kept only this from the new UI: serif
  headings were tried and dropped); a heading in the system font, the session in a sky (online) or peach (in person) panel with the Meet button
  or the address, short facts under it, coral buttons, and a footer with her name, phone, e-mail and
  web. Body text in the system font (e-mail apps don't load Inter). No "Dobrý den", "Těším se na vás", "Kdybyste…"
  lines; the reminders are only the panel (the day-before one with a plain "Zrušit termín" link).
  Her own e-mails have no footer. 104 offline checks.
  Then (owner: "something feels off, check the theme guide"; "just keep the 36 hours, no exact date"):
  sizes and buttons from `ui-lab/theme-proposal.html`: body 17 px, small 15, the format card radius 12
  with 24 padding, buttons pill 56 px / 18 px / 600 at full width; coral only for booking ("Vybrat jiný
  termín"), the Meet button and her buttons navy (solid, then outline); links blue 500; no line inside
  her panel. The session date has no year and the facts read "Online · 50 minut · 1 100 Kč", so they
  fit a phone. The rule says "nejpozději 36 hodin před sezením" in the e-mails and on the client page
  instead of a computed date. 105 offline checks.
  Then (owner: "more of a layout, use the screen, fonts for headings"; "check against our theme and rules"):
  the top of every e-mail is the site's booking panel (sky or peach, radius 24, padding 32, 24 on a phone)
  with the heading in Fraunces 500 (34, 28 on a phone) and the session inside it; the column is 600 px.
  Inter and Fraunces come from the site's own font files (`/fonts/Inter/`, `/fonts/Fraunces/` once the
  redesign ships): Apple Mail and iOS load them, Gmail and Outlook use Georgia and the system sans.
  Until the redesign is live, the Fraunces URL is missing, so every app shows Georgia for headings.
  Then (owner, 14 Nov, from the e-mail preview `ui-lab/booking-php/email-preview.html`): the Meet button
  is coral; the confirmation drops the "Když budete v Googlu přihlášení…" line and says "Zaplatili jste
  1 100 Kč." under the panel (not after a move: nothing new is paid).
  Then the footer went to two rows (name · web, phone · e-mail), the attachment note above it. And the
  client e-mails are passive, no "I" or "you" (owner): "Platba 1 100 Kč byla přijata.", "Přesunout nebo
  zdarma zrušit lze…", the cancel and refund e-mails drop "Sezení musím zrušit, omlouvám se" and say only
  "Celých 1 100 Kč bude vráceno na kartu použitou k platbě, obvykle do 5–10 pracovních dnů."; the refund
  heading is "Vrácení platby" ("Platba se vrací" rejected).
  All client e-mail headings in the nominal style (owner liked "Vrácení platby"): "Potvrzení rezervace",
  "Změna termínu", "Připomínka", "Začátek za 10 minut", "Zrušení sezení", "Vrácení platby".
  The reminders show "Zrušit termín" only while it is 36+ hours to the session (owner: no cancel link under
  36 hours); the day-before one goes out 24 hours ahead, so it has none.
  The refund sentence is shorter: "Částka bude vrácena na kartu použitou k platbě, obvykle do 5–10
  pracovních dnů." (the amount is in the panel).
  In the confirmation "Platba 1 100 Kč byla přijata." is navy with the amount in 600, so it stands out.
  After the first sends to Gmail and Seznam on a phone (14 Nov): the Gmail app ignored the phone media
  query, so the heading broke over two lines and the Meet button label too; the e-mail is now phone-first
  (heading 28, panel padding 24/20, column padding 12, button side padding 12) and a min-width:600 query
  gives desktop apps the 34 heading and 32 padding. Seznam wrapped the footer rows and left a "·" at the
  line end, so the footer items are spaced, not dotted.
  Second send: Seznam shows the footer at body size, so the rows still wrapped; the footer is now three short
  rows (name / phone and web / e-mail). The Gmail app enlarges text about a fifth, so the heading wraps to
  two lines there; first kept, then (owner) the phone heading went to 24 px so it fits one line in Gmail too.
  The client's move/cancel page (`ui-lab/rezervace/zruseni.html`) and the API errors it shows follow
  the same style: no "I" or "you", nominal headings ("Přesun nebo zrušení termínu", "Zrušení termínu",
  "Nový termín"), short passive results ("Termín zrušen", "Termín přesunut"), the refund line as in the
  e-mails, "lze" instead of "můžete", and the phone number instead of "zavolejte mi". The owner's page
  and her e-mails keep "ty".
  In-person e-mails, after a look on the phone: Gmail and Seznam turned the plain address into their own
  map link (sometimes only half of it), next to "Navigovat". The address is now our own plain-looking link,
  and both go to a route to her business profile (destination "Mgr. Kristýna Hriciková Glattová,
  Vítkovická 3299/3a, Ostrava", checked to resolve to the business in Google Maps), not to the bare address.
  Dark mode on the owner's phone (Gmail): the brand PNG kept its navy name on a darkened background, nearly
  invisible. The header is now the coral heart as a 2.5 KB PNG (`assets/heart.png`, cid, file "srdce.png")
  and the name and role as text, which Gmail lightens. Also seen: Gmail's "Přidat do kalendáře" uses the
  user's own default reminder (30 min), not the .ics alarm, and copied the inline image into the event.
  Buttons in dark mode: Gmail (Android) lightened the navy text on the light coral and left the coral, so the
  label went light blue on coral; a background image, -webkit-text-fill-color and an outline-only button were
  tried (only the outline held, the owner disliked it in light mode). Outlook recolours both and stays readable.
  Deep coral #C4614A with white text (what most senders do) was tried and rejected by the owner; the button
  stays the site's coral with navy text.
- Hours page, 14 Nov (`ui-lab/rezervace/hodiny.html`, `src/hours.php`, `src/owner.php`). The owner edits
  Kristýna's hours today in Google's booking schedule ("Terapie online", date-specific hours, about 6 taps) and
  asked for the same or simpler. Google's appointment hours can't be read by the API, and writing repeating
  Google events with exceptions from a page would be fragile, so the hours now live in the booking database
  (`HOURS_SOURCE=db`, the default; `google` keeps the old two hours calendars). Her own Google calendar still
  blocks time as busy.
  - Page: a month (past weeks hidden so the day's hours fit on one phone screen), tap a day, tap hours 8:00–17:00
    (owner: nothing after 18:00); Online/Osobně switch; "Běžný týden" for the regular week. A day of its own
    overrides the week; "Vrátit na běžný týden", "Zavřít celý den". Booked hours are locked and link to her
    booking page. Public holidays start closed but can be opened (owner: she sometimes works on them).
  - Taps are a draft (dashed outline) until "Uložit" or "Zahodit" (owner asked for a save/discard step);
    leaving with unsaved taps asks first. Each save bumps a version: when another device saved meanwhile,
    the page loads its changes, puts only its own taps on top and asks to save again (nothing is lost).
  - Access (owner: PC with Windows and Ubuntu, his phone, maybe hers; one shared password): the password
    once per browser, then a random year-long cookie (HttpOnly, SameSite=Strict, Secure live, path /api/hodiny,
    only its HMAC stored). "Přihlášená zařízení" lists the devices with "Odhlásit"; a new password signs out
    all. 5 wrong passwords per visitor in 15 min, or 30 overall in an hour, lock sign-in for a while. POSTs need
    JSON and our own Origin. Saves, sign-ins, failures and sign-outs are logged with the device.
  - Tested: 27 new offline checks (141 total); in the lab pane at 375 px: wrong and right password, open
    Sat 21 Nov 14:00, save, `/api/slots` offers it online only; two devices saving at once keep both changes
    (first version lost the other device's change, fixed); sign-out kills the cookie; no cookie: 401.
    Layout shift 0. Not tested: a real phone, Windows/Ubuntu browsers, the hosting's HTTPS cookie.
- Payment step, 14 Nov: the owner chose variant 0, the redirect to Stripe Checkout (already built and tested).
  Variants 1–3 (card fields on our page, payment as its own step, Stripe's embedded form) would each need
  Stripe's script on the booking page (against the no-third-party-scripts rule) and a PaymentIntent flow with
  3-D Secure on our page. Variant 2 stays the candidate if clients find the jump to Stripe off-putting.
