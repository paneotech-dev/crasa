# Security review

**CRASA Digital Anchor Ecosystem**
Reviewed 9 September 2026, against the live host `crasadev.paneo.tech`

---

## Why this was not routine

A sibling account on this shared host had two web shells dropped on it in
the week before this review. The first finding below is the mechanism by
which that happens, and it was present on this platform.

Every finding was verified against the running site rather than by reading
configuration. Where the review says something executed, it was made to
execute.

---

## Findings

| # | Finding | Severity | State |
|---|---|---|---|
| 1 | The uploads directory executed PHP | **Critical** | Fixed and verified |
| 2 | Debug mode on, on a public host | **Critical** | Fixed and verified |
| 3 | No security headers of any kind | **High** | Fixed and verified |
| 4 | Session cookie not marked secure | **High** | Fixed and verified |
| 5 | Three public write paths unthrottled | **Medium** | Fixed |
| 6 | No off-site backups | **Medium** | Not fixed. Needs a decision |
| 7 | Mail is written to a log file | **Low** | Not fixed. Needs credentials |

---

## 1. The uploads directory executed PHP — Critical

**What was wrong.** `storage/app/public` is symlinked into the web root so
that images can be served. It had no rules preventing code execution.

**How it was verified.** A file was placed in that directory and requested
over HTTPS:

```
GET https://crasadev.paneo.tech/storage/probe/canary.php
200  EXECUTED:8.3.33
```

It also executed as `.phtml`. That is a web shell with one mistaken file
type check between it and an attacker, on a host where two have already been
found this month.

**What was done.** Rules were installed at the root of the uploads
directory, in three layers because a cPanel host may run PHP through any of
several handlers and only one layer works for each:

- the interpreter is switched off for the tree
- every handler and type mapping for executable extensions is removed
- and requests for those extensions are refused outright

Directory listing is off, `X-Content-Type-Options: nosniff` is set, and a
restrictive content policy is applied to anything served from there.

**Re-verified after the change:**

```
GET https://crasadev.paneo.tech/storage/probe/canary.php    403
GET https://crasadev.paneo.tech/storage/probe/canary.phtml  403
GET https://crasadev.paneo.tech/storage/thumbnails/1.jpg    200
```

Code refused, legitimate files unaffected.

**The part that mattered as much as the fix.** Those rules live in a
directory the repository deliberately excludes, because it holds user
content. A protection stored only there disappears the first time the
platform is deployed to a fresh directory, and nobody notices until
something is uploaded that should not have been.

The canonical copy is now in the repository at
`resources/security/uploads.htaccess`, `php artisan security:harden-uploads`
installs it, and `--check` reports whether it is in place. **That command
must run after every deployment.**

---

## 2. Debug mode was on, on a public host — Critical

**What was wrong.** `APP_ENV=local` and `APP_DEBUG=true` on a host whose
application URL is a public HTTPS domain.

Laravel's debug page does not merely show a stack trace. It prints the
environment, which on this host includes the database password, the mail
credentials and the AWS keys. Any error on any page would have shown them to
whoever caused it.

**What was done.** `APP_ENV=production`, `APP_DEBUG=false`.

**Verified.** A request for a page that does not exist now returns the
platform's own 404 with no stack trace, no framework paths and no
environment values.

---

## 3. No security headers of any kind — High

**What was wrong.** Responses carried no `Content-Security-Policy`, no
`X-Frame-Options`, no `X-Content-Type-Options`, no `Referrer-Policy`, no
`Permissions-Policy` and no `Strict-Transport-Security`.

In practice that means the administration interface could be loaded inside a
frame on another site and clicked through; a browser could be talked into
treating an upload as something more interesting than its declared type; and
administration URLs, which name member states and documents, leaked to
whatever a reader clicked through to.

**What was done.** A middleware now sets all of them on every response.

The content policy is the substantial one, and it costs nothing here because
it states what is already true: this platform serves every script,
stylesheet, font and image from its own origin by design. A script injected
into a page by any means will not run.

`Strict-Transport-Security` is set only over TLS, so a development machine
cannot pin itself to HTTPS in someone's browser for a year.

**Verified on the live host**, and a test asserts that no page loads a
resource the policy would block, so the policy cannot be quietly outgrown.

---

## 4. The session cookie was not marked secure — High

**What was wrong.** `SESSION_SECURE_COOKIE` was unset, which means the
session cookie was sent over plain HTTP as well as HTTPS. On a platform
holding confidential committee papers and country-scoped data, that is a
session anyone on the network path can take.

`SESSION_ENCRYPT` was also off, and the cookie was named after the framework.

**What was done.** The cookie is now `secure`, `httponly`,
`samesite=strict`, encrypted at rest, and named `crasa_session`.

**Verified on the wire:**

```
set-cookie: crasa_session=...; secure; httponly; samesite=strict
```

---

## 5. Three public write paths were unthrottled — Medium

**What was wrong.** Sign-in, the two-factor challenge and the single
sign-on callback had no route-level rate limit. The sign-in and challenge
controllers do throttle per account, which is the more important half, but a
single address trying one password against many accounts never reaches a
per-account counter. Delegate registration and password reset, both public
forms that write a row, had no limit at all.

**What was done.** Route-level limits on all of them, loose enough that a
delegate who mistypes a date three times is not locked out.

A test now walks every route in the application and fails if a public write
path is added without a limit, so this cannot recur by omission.

---

## 6. No off-site backups — Medium, needs a decision

**What is in place.** A weekly whole-account backup runs through cPanel on
Saturdays at 11:52. That is the host's own facility and it is better than
nothing, but it is weekly, it is on the same infrastructure, and it is not
something this project controls.

**What is not in place.** Hourly off-site backups. AWS credentials exist in
the environment, but the S3 driver package is not installed, so nothing can
write to S3 today:

```
Error: Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found
```

**What is needed, exactly:**

1. `composer require league/flysystem-aws-s3-v3`
2. Confirmation that the AWS credentials in the environment are current and
   that the bucket exists and is writable. They were not tested against a
   live bucket because doing so would have written to a bucket this review
   does not own.
3. A backup command, and one cron entry to run it. Roughly half a day's
   work once the above are settled.

**Updated 9 September 2026.** The full procedure — what to install, what
CRASA must provide, the retention schedule, the restore steps, how to prove a
restore actually works, and three alternatives if CRASA cannot provision S3
— is now written up in `docs/BACKUP_SETUP.md` and can be sent to CRASA as it
stands. The decision itself is still CRASA's.

**One related finding.** This application has no cron entry at all. The
crontab on this account runs a scheduler and a queue worker for a different
site, and nothing for this one. Nothing in the platform currently depends on
a queue: notifications are sent synchronously and thumbnails can be
generated with `--sync`. But **any hourly backup will need that cron entry
created**, and it should be created at the same time.

---

## 7. Mail is written to a log file — Low, needs credentials

`MAIL_MAILER=log`. Password reset links and submission notifications are
written to the application log instead of being sent.

That is correct for a development host and wrong for go-live: a member state
representative asking for a password reset would receive nothing, and the
platform would report success.

**What is needed.** SMTP credentials, or an API key for whichever service
CRASA uses, and the from-address the Secretariat wants on outgoing mail.

**Updated 9 September 2026.** Two things changed.

First, the platform no longer fails quietly. It writes an error-level line on
every boot naming the driver, the environment and the file to change, and
shows a red banner on every back office screen until mail is configured. This
was the worst property of the finding: nothing threw, no job failed, and the
platform reported success to a representative whose notification went
nowhere. It could have persisted until the validation workshop.

Second, `docs/MAIL_SETUP.md` sets out both routes — cPanel SMTP and a
transactional service — with what each needs, the SPF, DKIM and DMARC records
without which mail to fourteen government domains is filed as spam, and a
verification procedure that ends at a real external mailbox rather than at
"the command did not error".

The severity is unchanged. The credentials are still outstanding.

---

## What was checked and found correct

| Check | Result |
|---|---|
| `.env`, `.git`, `vendor`, `composer.json`, `artisan` reachable from the web | No. All 404 |
| `docs/` reachable from the web | No |
| Database dumps reachable from the web | No |
| Directory listings | Off |
| Documents served from the filesystem | No. Every byte goes through a controller that checks clearance, country and committee first |
| Country scope on reads | Enforced as a global scope, tested |
| Country scope on writes | Enforced by policy, tested |
| Confidential documents | Gated on committee seat, tested |
| Two-factor for administrators | Mandatory, enforced by middleware, tested |
| Password hashing | bcrypt, 12 rounds |
| Two-factor secrets and recovery codes at rest | Encrypted |
| Sensitive fields at rest | Encrypted with the application key |
| Secrets in the repository | None. `.env` is ignored and a test asserts it |
| CSRF | On every state-changing form |
| Audit trail | Append only. Update and delete throw at the model |
| Uploads: type restriction, EXIF stripping, renaming | All present |
| Archives as uploads | Refused |

---

## What must happen before go-live

1. **Run `php artisan security:harden-uploads` on the production host**, and
   verify from outside by requesting a planted `.php` file. Do not skip the
   verification: the check that reads the configuration is not the same as
   the check that requests the file.
2. Confirm `APP_DEBUG=false` in the production environment.
3. Install the S3 package, confirm the credentials and create the hourly
   backup cron entry.
4. Supply mail credentials.
5. Run `php artisan content:strip-samples` to remove the demonstration data.
6. Re-run this review against the production host, since it is a different
   environment from this one.
