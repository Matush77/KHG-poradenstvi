# Booking and payments: test plan before going live

Goal: one list that, when every line passes, lets us say "the booking is ready". Three stages:

1. **Test site, Stripe test mode** (`test.khgporadenstvi.cz`, free): every scenario, including the
   ones that are hard to cause with real money. This is where almost all the testing happens.
2. **Live site, real money** (3 payments at the real price, refunded): only what test mode can't prove.
3. **First two weeks live**: watching, not testing.

Mark each line ✅ / ❌ with the date. A ❌ is fixed and the line is run again before moving on.

---

## 0. Before starting

| # | Check | ✅ |
|---|---|---|
| 0.1 | Test site is on the latest `redesign-ship` commit (`git pull` in `~/test_html`), and `php84 booking/tests/run.php` there passes. | |
| 0.2 | E-mail works: one test e-mail reaches Gmail, Seznam.cz and Outlook, **not in spam**. In Gmail, "Zobrazit originál" shows SPF, DKIM and DMARC all **PASS**. (mail-tester.com score 9/10 or better.) | |
| 0.3 | `LAB_MAIL_TO` in `~/.config/khg-test/booking.env` lists every inbox used below (the test site sends real e-mail only to these). `LAB_OWNER_TO` is an inbox the therapist reads during the test. | |
| 0.4 | Stripe dashboard (test mode) → Webhooks → the test site's endpoint listens to: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`, `refund.created`, `refund.updated`, `refund.failed`, `charge.refund.updated`, `charge.refunded`. | |
| 0.5 | The Scheduled Task (cron) runs every 5 minutes: `booking.log` gets a `SWEEP` line or the cron output shows `{}` at least every 5 minutes. | |
| 0.6 | The hours page (`/rezervace/hodiny.html`) shows the **same** weekly hours as her Google booking pages today. | |
| 0.7 | Have ready: two phones (one iPhone if possible), one computer, two browsers, the therapist's Google Calendar open. | |

Stripe test cards (any future expiry date, any CVC, any name):

| Card | Behaviour |
|---|---|
| `4242 4242 4242 4242` | pays normally |
| `4000 0000 0000 0002` | declined |
| `4000 0000 0000 9995` | declined, insufficient funds |
| `4000 0000 0000 0069` | declined, expired card |
| `4000 0027 6000 3184` | asks for bank confirmation (3-D Secure): approve or fail it on Stripe's test screen |
| `4000 0000 0000 5126` | pays; a later refund **fails** a while afterwards |

---

## 1. Test site: paying

| # | Do | Expect | ✅ |
|---|---|---|---|
| 1.1 | Online, pick a time, fill name + e-mail, pay with `4242…` on a **phone**. | Back on the page: "Termín je rezervovaný", the right day and time. Event in the "KHG test rezervace" calendar with a Meet link. Client e-mail with the Meet link, the time and the move/cancel link. Therapist e-mail "Nová rezervace" with her own link. | |
| 1.2 | In person, fill a phone number, pay with `4242…`. | Same, with the address Vítkovická 3299/3a, no Meet link. The client e-mail goes to the address typed on Stripe's page. | |
| 1.3 | Pay with `4000 0000 0000 0002`, then `…9995`, then `…0069`. | Stripe shows the decline in Czech, nothing is booked, no e-mail. Paying again with `4242…` on the same page works. | |
| 1.4 | Pay with `4000 0027 6000 3184` and **approve** on the test screen. | Booked as in 1.1. | |
| 1.5 | Same card, **fail** the bank check. | Not booked. You can try again or go back. | |
| 1.6 | On Stripe's page press Stripe's back arrow. Then the phone's back button on another try. | Back on step 2 with what you typed. The time is **free again at once**: open the booking page in another browser and see it offered. | |
| 1.7 | Go to Stripe's page, then wait 35 minutes without paying. | The time comes back on its own (Stripe's page expires; `WEBHOOK checkout.session.expired` in the log). | |
| 1.8 | Pay with `4242…` and **close the tab** on Stripe's success, before the page comes back. | Within a minute: booked, both e-mails (the webhook did it: `WEBHOOK checkout.session.completed … "booked"` in the log). | |
| 1.9 | In Stripe's dashboard, open the event from 1.8 and press **Resend**. | Nothing new: no second booking, no second e-mail. | |
| 1.10 | Two browsers, the same time: open the booking page in browser B first. Start paying for a time in browser A; then pick the same time in B (page loaded before) and press pay. | B is told "Tenhle termín si právě někdo rezervuje"; after a reload B no longer sees the time. A can pay. | |
| 1.11 | One browser, two tabs, the same time: open Stripe's page in tab 1, then in tab 2. | Tab 2 replaces tab 1: paying in tab 1 is no longer possible (Stripe says the page expired); tab 2 pays and books. Never two payments. | |
| 1.12 | Pay with Apple Pay (iPhone, Safari) or Google Pay (Android, Chrome). | Booked as in 1.1. | |
| 1.13 | Book a time, then create a private event in the therapist's own Google Calendar at another free time. Reload the booking page. | The time with her private event is no longer offered. | |
| 1.14 | Look at 24 and 25 December and a public holiday. | Not offered. | |
| 1.15 | Try to book less than 24 h ahead or more than 70 days ahead. | Not offered. | |

## 2. Test site: client's move and cancel (link in the confirmation e-mail)

For the 36-hour tests, either book a time 24–36 h ahead, or set `LAB_FREE_CANCEL_HOURS` on the test
site for an hour and put it back afterwards.

| # | Do | Expect | ✅ |
|---|---|---|---|
| 2.1 | Open the link, **Přesunout termín**, pick another time. | "Termín přesunut". Event moved in Google, Meet link the same. Client e-mail "Změna termínu" with a new calendar file. Therapist e-mail that the client moved it. Old time offered again. | |
| 2.2 | Move the same booking again. | Works (no limit while 36+ h ahead); her e-mail shows the count. | |
| 2.3 | More than 36 h ahead: **Zrušit termín**. | Page says the money comes back. Stripe dashboard: a refund of 1 100 Kč. Event deleted from Google. Client e-mail (cancelled, refunded) and therapist e-mail. Time offered again. | |
| 2.4 | 24–36 h ahead: **Zrušit termín**. | Page says beforehand that the money does **not** come back; after: cancelled, no refund in Stripe, e-mails say so. Move is not offered. | |
| 2.5 | Open the cancel page while still more than 36 h ahead; wait until the 36 h pass (or change `LAB_FREE_CANCEL_HOURS`); then press cancel. | "Mezitím uplynula lhůta 36 hodin, platba by se už nevrátila. Termín zůstává, nic se nezměnilo." Nothing cancelled. | |
| 2.6 | Press cancel twice fast, or in two tabs. | One cancellation, one refund, one set of e-mails. | |
| 2.7 | Open the link of a cancelled booking. | "Termín zrušen", no buttons. | |
| 2.8 | Change one character of the link's `t=`. | "Odkaz nefunguje"; nothing shown about the booking. | |

## 3. Test site: the therapist

| # | Do | Expect | ✅ |
|---|---|---|---|
| 3.1 | From "Nová rezervace": **Přesunout termín** to another time. | As 2.1, but the client's e-mail says she moved it. | |
| 3.2 | From the hours page: move a booking to a time outside her usual hours (7:00–20:00). | Works; refused onto another session or within an hour of one. | |
| 3.3 | In Google Calendar, **drag** a booked event to another time. | Within ~5 minutes: the booking follows, client gets "Změna termínu". | |
| 3.4 | In Google Calendar, **delete** a booked event. | Within ~5 minutes: booking cancelled, time free, therapist e-mail with a refund button. **No** refund yet. Pressing the button: refund in Stripe, client e-mail. | |
| 3.5 | From "Nová rezervace": **Zrušit** (cancel with refund). | Refund in Stripe, event gone, client e-mail. | |
| 3.6 | Book with `4000 0000 0000 5126`, then cancel 36+ h ahead (or she refunds). Wait for Stripe to fail the refund (minutes to an hour in test mode). | Therapist e-mail that the refund did not go through, with a button to try again. Refund status "failed" on her page. | |
| 3.7 | Online booking: as the client, **decline** the Google invitation in the client's calendar. | Therapist e-mail that the client declined. The booking stays. | |
| 3.8 | Hours page: change a weekday's hours, **Uložit**. Reload the booking page. | New hours offered, removed ones gone. On a second device: the page warns when the other device saved meanwhile. | |
| 3.9 | Owner moves a booking to the next whole hour, e.g. at 13:20 to 14:00 (hours page). | The 10-minute reminder arrives about 10 minutes before. | |
| 3.10 | A booking made 2+ days ahead. | The day-before reminder arrives about 24 h before. | |

## 4. Test site: when things go wrong

| # | Do | Expect | ✅ |
|---|---|---|---|
| 4.1 | Stripe dashboard: **disable** the webhook. Pay with `4242…` and close the tab (as 1.8). Wait 10 minutes. Re-enable the webhook. | Cron books it within 5–10 minutes (`SWEEP … "booked"` in the log), both e-mails. | |
| 4.2 | Put a wrong SMTP password in `smtp.env` for a few minutes; make a booking; put it back. | Booking works; the page says the confirmation will arrive (not that it arrived). E-mails arrive within ~5 minutes after the password is back. | |
| 4.3 | Open `/booking/api.php`, `/booking/src/config.php`, `/booking/tests/run.php`, `/%62ooking/api.php`. | All 404. `/api/slots` answers JSON. | |
| 4.4 | Re-send an old `checkout.session.completed` event of a cancelled booking from the Stripe dashboard. | Nothing changes, no e-mail. | |

## 5. E-mail content (once, on the e-mails from sections 1–3)

| # | Check | ✅ |
|---|---|---|
| 5.1 | Gmail, Seznam.cz, Outlook (web and phone app): nothing in spam; Czech letters correct; buttons work; the calendar file opens in the phone's calendar at the right time (also for a date after the switch to summer time, if any is bookable). | |
| 5.2 | Sender shows as the practice, replies go to the therapist. | |
| 5.3 | No e-mail reveals the therapist's link to the client, or the client's note to anyone but her. | |

---

## 6. Live site, real money (after everything above passes)

Same code, live keys. Use the real price (1 100 Kč) with your own card and refund it: no code change
is needed for a test price. Each refund still costs Stripe's fee, about 25 Kč.

| # | Check | ✅ |
|---|---|---|
| 6.0 | Live config: `BOOKING_MODE=live`, `sk_live_…` key (the site refuses to start with a test key), live webhook with the events from 0.4 pointing to `https://khgporadenstvi.cz/api/webhook`, cron for `public_html`, the therapist's real booking calendar, hours entered. `/api/slots` answers. | |
| 6.1 | **Payment 1**, online, on a computer, return to the page: booked, real calendar event with Meet, e-mails in the real inboxes. Card statement shows the practice's name. | |
| 6.2 | **Payment 2**, in person, Apple Pay or Google Pay on a phone, **close the tab** before returning: the live webhook books it within a minute. | |
| 6.3 | Cancel payment 1 more than 36 h ahead from the client link: refund appears in Stripe at once and **on the bank account** within 5–10 working days. | |
| 6.4 | **Payment 3**: the therapist cancels it from "Nová rezervace": refund, client e-mail. | |
| 6.5 | Stripe dashboard → Webhooks: every delivery succeeded (no red). `booking.log`: no `ERROR` lines. | |
| 6.6 | Cancel or refund whatever is left; delete the test events if any remain. | |

---

## 7. First two weeks live

| # | Check | ✅ |
|---|---|---|
| 7.1 | Keep her Google booking pages active but unlinked: switching back is one link change. | |
| 7.2 | Daily: every payment in Stripe has a booking (and an event in her calendar), and every booking has a payment. `booking.log`: no `ERROR`, `FAILED` or `MAIL_ERROR` lines. | |
| 7.3 | Stripe → Settings → notifications on for failed webhooks and disputes. | |
| 7.4 | A free uptime monitor (e.g. UptimeRobot) on `https://khgporadenstvi.cz/api/slots`, alerting by e-mail. | |
| 7.5 | Ask the first few clients whether the e-mails arrived and the Meet link worked. | |

## Go / no-go

**Ready** when: sections 0–5 all ✅ on the test site, section 6 all ✅ live, and no unexplained
line in `booking.log`. Then link "Objednat se" to `/rezervace.html` and start section 7.

**Not ready** if any of these fails: 0.2 (e-mail), 1.8 or 4.1 (booking without the return page),
1.10 or 1.11 (double booking), 2.3 or 6.3 (refunds), 3.3 or 3.4 (her calendar edits).

Known limits, accepted (not tested here):
- A refund that Stripe first reports as succeeded and only later fails reaches her by the webhook
  only (3.6 checks that path). The safety net rechecks only refunds still pending.
- Several people behind one mobile-network address share a limit of 3 open payments; a 4th ends the
  oldest. Rare; raise if it shows up.
