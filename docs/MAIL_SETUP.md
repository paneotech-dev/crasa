# Outbound mail: what to set, who provides it, and how to prove it arrives

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Prepared 9 September 2026

---

## The finding this answers

The security review of 9 September left two items open for a decision. This
is the second.

**The platform sends no mail.** `MAIL_MAILER=log` on a server where
`APP_ENV=production`. Every message the platform produces is written to
`storage/logs/laravel.log` and delivered to nobody.

The from address is also still the framework's placeholder,
`hello@example.com`, which most receiving servers reject or file as spam even
once a real driver is configured.

### Why this one is worse than it sounds

It fails silently and completely. Nothing throws, no queued job fails, no
page shows an error, no log line says "delivery failed" — because nothing
failed. The log driver did exactly what it was asked to do.

A member state representative submits their quarterly figures. The platform
tells them the Secretariat has been notified. The Secretariat is not
notified. Both parties believe the other has the message.

That state persists until somebody thinks to check, which on this project
would most likely be the validation workshop of 23 to 25 September, in front
of fourteen regulators, when a delegate asks why they never received the
email.

### What the platform sends today

| What | To whom | When |
|---|---|---|
| Enquiry received | The Secretariat address in `crasa.enquiry_recipient` | Somebody uses the contact form |
| Submission state changed | The submitting representative, and the Secretariat reviewers | A submission moves between draft, submitted, validated and returned |
| Password reset | The account holder | They ask for one |
| Backup failure warnings | Whoever is configured | If backups are set up — see `docs/BACKUP_SETUP.md` |

The last one is worth reading twice. **The backup system's only way of
reporting that it has stopped working is an email.** Mail should be
configured before backups, or at the same time, or the backup monitoring is
decorative.

### What the platform now does about it

As of 9 September the platform says so rather than staying quiet:

- **A red banner on every back office screen** naming the driver and the
  environment, whenever the mail driver is one that delivers nowhere and the
  environment is not local. An amber banner if the driver is real but the
  from address is still a placeholder.
- **An error-level line in the log on every boot**, naming the file to change
  and this document.

It does not block anything and it never throws. A platform that refused to
serve pages because mail was misconfigured would be a worse outcome than one
that delivers no mail. The banner disappears by itself when the setting is
correct.

---

## Option 1: cPanel SMTP on this server

**Recommended if CRASA's domain email is already hosted here or on a
mailbox CRASA controls, and the expected volume stays low.** It needs no new
supplier, no new account and no procurement.

### What CRASA must provide

| Item | Detail |
|---|---|
| A mailbox | Created in cPanel on the CRASA domain, for example `no-reply@crasa.org` |
| Its password | Sent separately from everything else |
| The mail host | Usually the server hostname, or `mail.crasa.org` |
| The port | 587 with STARTTLS. Not 25 |
| A reply-to address | A real monitored mailbox, so a regulator replying to a notification reaches a person |

### What we set

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.crasa.org
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=no-reply@crasa.org
MAIL_PASSWORD=<the mailbox password>
MAIL_FROM_ADDRESS=no-reply@crasa.org
MAIL_FROM_NAME="CRASA"
```

### What it does not do

- **Reputation.** A shared hosting IP is shared with every other account on
  it, and this one has already had two web shells dropped on a sibling
  account. If any of those accounts sends spam, CRASA's notifications are
  filed with it. This is the main argument for Option 2.
- **Delivery reporting.** SMTP tells us the message was accepted by the
  server. It does not tell us it reached an inbox. There is no bounce
  handling and no way to see what happened afterwards.
- **Volume.** cPanel accounts carry an hourly send limit, typically a few
  hundred messages. That is comfortable for this platform's traffic and would
  not survive a mailing to every delegate.

---

## Option 2: a transactional mail service

**Recommended if CRASA can hold a supplier account.** Postmark, Amazon SES,
Mailgun and Brevo are all appropriate; SES is usually cheapest and the
others are usually easier to set up.

At this platform's volume — a few hundred messages a month — every one of
them is free or close to it.

### What CRASA must provide

| Item | Detail | Notes |
|---|---|---|
| An account with the chosen service | | CRASA's own, not ours. The credentials must outlive this contract |
| An API key or SMTP credentials | Sent separately | |
| Access to the DNS for the CRASA domain | To add three records | This is the part that usually takes longest |
| A verified sending domain | The service walks through it | |

### The DNS records, and why they matter more than the driver

The service will ask for three records on the sending domain:

| Record | What it does |
|---|---|
| SPF | States which servers may send as this domain |
| DKIM | Signs each message so a receiver can prove it was not altered |
| DMARC | Tells receivers what to do with mail that fails the first two |

**Without these, mail from a new sender to a government domain is filed as
spam or dropped without a bounce.** Fourteen national regulators are exactly
the kind of recipient whose mail servers are strict about this. A working
driver with no DNS records looks like success on our side and silence on
theirs, which is the same failure this document exists to prevent, one step
further down the chain.

Whoever administers DNS for the CRASA domain needs to be in this
conversation from the start. If that is COMESA IT rather than the
Secretariat, please tell us early.

### What we set

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com     # or the chosen service's host
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=<the service's token>
MAIL_PASSWORD=<the service's token>
MAIL_FROM_ADDRESS=no-reply@crasa.org
MAIL_FROM_NAME="CRASA"
```

### What production uses: Brevo over its HTTP API

Since 30 October 2026 production (crasa.africa) sends through Brevo's HTTP
API rather than SMTP. The transport is `symfony/brevo-mailer`, registered as
the `brevo` mailer in `App\Providers\MailTransportServiceProvider`; the key
is read from `services.brevo.key`. The domain crasa.africa is authenticated
in Brevo (DKIM and DMARC published). Staging keeps `MAIL_MAILER=log`.

```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY=<the Brevo API key>
MAIL_FROM_ADDRESS=admin@crasa.africa
MAIL_FROM_NAME="CRASA"
```

Every "e-mail is switched off" badge and notice reads
`App\Support\MailStatus::isOn()`, so they disappear once the mailer is
`brevo` and stay on staging.

---

## The queue, which is the other half of this

Enquiry mail is queued rather than sent during the request, so a slow mail
server never holds up a contact form. Queued mail needs a worker, and this
application has no cron entry at all — the same gap that stops backups
running. See `docs/BACKUP_SETUP.md`.

Two lines in the `ptechweb` user's crontab cover both:

```cron
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/ptechweb/crasadev/artisan schedule:run >> /dev/null 2>&1
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/ptechweb/crasadev/artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

The PHP path is not optional: the default `php` on this account is 8.2 and
this application requires 8.3.

**Until the worker runs, queued mail is written to the `jobs` table and stays
there.** That is a different silent failure from the log driver and it looks
identical from the outside, so both need fixing before either can be called
done.

---

## How to prove mail is delivered

Configuring mail is easy. Knowing it arrives is the part people skip.

### 1. The platform stops complaining

The red banner in the back office disappears, and the boot warning stops
appearing in `storage/logs/laravel.log`. That confirms the setting only. It
proves nothing about delivery.

### 2. Send to a real external address

```bash
php artisan tinker
>>> Mail::raw('Delivery test from the CRASA platform, '.now(), fn ($m) => $m->to('someone@gmail.com')->subject('CRASA delivery test'));
```

**Use an address on a mail provider CRASA does not control** — a Gmail or
Outlook account. Mail from a server to a mailbox on the same server often
never leaves the machine, so a test to `@crasa.org` can pass while every
message to the outside world fails.

### 3. Check where it landed, not just that it arrived

Open the message and look at the headers.

- **In the inbox, not spam.** Junk is a fail, not a pass.
- `spf=pass`, `dkim=pass`, `dmarc=pass` in the authentication results. Any
  of them failing means the mail is arriving today on the receiver's
  goodwill and will stop when that receiver tightens its rules.

Gmail shows this under "Show original". Outlook shows it under "View message
source".

### 4. Test the real thing, not a raw message

The `Mail::raw` test proves the transport. It does not prove the platform's
own messages render, or that they carry the right sender, or that a link in
them resolves. So:

1. Send an enquiry through the contact form on the live site.
2. Confirm the Secretariat address receives it.
3. Move a submission from submitted to returned in the back office.
4. Confirm the submitting representative receives that notification, and that
   the link in it opens the right submission.

Step 4 is the one that matters. It exercises the queue, the notification, the
mail driver and the URL generation together, which is the combination that
runs in production and the one no unit test covers end to end.

### 5. Write down the date

Record when mail was verified and to which external provider. When a
regulator says three months from now that they never received a notification,
the first question will be whether mail has ever been proven to work, and the
answer should be a date rather than a recollection.

---

## Summary of what is needed from CRASA

| Decision | Options | Blocks |
|---|---|---|
| Which mail route | cPanel SMTP, or a transactional service | Every notification the platform sends |
| A mailbox or an API key | Depends on the above | The same |
| DNS access for SPF, DKIM and DMARC | Option 2 only, and strongly advised in both cases | Whether the mail arrives rather than whether it is sent |
| A reply-to address | A monitored mailbox | Whether a regulator replying to a notification reaches a person |
| The cron entries | Server access, which we have | Queued mail, and backups |
