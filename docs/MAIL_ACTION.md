# Mail: what to do, in ten minutes

Written 17 September 2026. The platform sends nothing today: `MAIL_MAILER=log`.
Every administration screen shows a red banner saying so, and it will keep
showing it until a sending driver is set. Nothing below has been done for you,
because every option needs a credential only you can issue.

## Recommendation: Brevo, because paneo.tech is already set up there

The paneo.tech DNS already has everything Brevo needs:

| Record | Type | Value (as it is today) |
|---|---|---|
| `brevo1._domainkey.paneo.tech` | CNAME | `b1.paneo-tech.dkim.brevo.com` |
| `brevo2._domainkey.paneo.tech` | CNAME | `b2.paneo-tech.dkim.brevo.com` |
| `_dmarc.paneo.tech` | TXT | `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com` |
| `paneo.tech` | TXT | `brevo-code:ef2b26e2…` (domain verification) |

So it needs **no DNS change**. Messages are DKIM-signed as paneo.tech, which
satisfies Gmail's and Microsoft's sender rules. The relay
`smtp-relay.brevo.com:587` is reachable from this server; tested
17 September 2026, 03:40 UTC. Brevo refused a deliberately wrong key, which
shows the whole path works up to authentication. The free plan allows 300
messages a day, far more than the workshop needs.

**Steps (about 5 minutes):**

1. Sign in at <https://app.brevo.com> with the account that verified paneo.tech.
2. Check **Senders, Domains & Dedicated IPs → Domains**: paneo.tech must say
   *Authenticated*. If it does not, click *Authenticate* and follow Brevo's
   prompt. The records above are already in Cloudflare.
3. Go to **SMTP & API → SMTP** and click **Generate a new SMTP key**. Name it
   `crasadev`. Copy the key; Brevo shows it once. Note the **Login** shown on the
   same page (it looks like `xxxxxxx@smtp-brevo.com`).
4. On the server:
   ```
   sudo crasa-mail-configure brevo you@your-inbox.example
   ```
   Paste the login, then the key (it is not echoed). Press Enter to accept the
   From address `crasadev@paneo.tech`, or type another address on paneo.tech.
5. The script:
   - backs up `.env`;
   - sets `MAIL_MAILER=smtp`, the host, port, login, key and From;
   - restarts the queue worker;
   - sends one real message with `php artisan mail:check`.

   If the relay refuses, it **puts `.env` back** and says why.
6. Find the message titled "CRASA mail check …" in the inbox (check spam too).
   Reload any admin page: the red banner is gone.

## If you cannot get into that Brevo account: Gmail app password (10 minutes, no DNS)

Messages come *from your Gmail address*, not paneo.tech. That is fine for a
workshop and wrong for launch.

1. Your Google account needs 2-Step Verification. Turn it on at
   <https://myaccount.google.com/security> if it is off.
2. Go to <https://myaccount.google.com/apppasswords>, create one named `CRASA`,
   and copy the 16 letters.
3. Run `sudo crasa-mail-configure gmail you@your-inbox.example`. The login is
   your Gmail address and the password is the 16 letters. The From address
   defaults to the login; Gmail rewrites any other From.
4. Limit: about 500 messages a day.

## Other options (not recommended for the 23rd)

**Postmark.**
- Sign up at <https://postmarkapp.com>, create a Server, and add paneo.tech
  under *Sender Signatures → Domains*.
- Add the two records Postmark generates in Cloudflare (DNS only, grey cloud):
  - a TXT at `<date>pm._domainkey.paneo.tech`, value shown by Postmark;
  - a CNAME `pm-bounces.paneo.tech` → `pm.mtasv.net`.
- SMTP login **and** password are both the *Server API Token*. Run
  `sudo crasa-mail-configure postmark …`.
- **Catch:** until Postmark approves the account (a human review, often a
  day), it only delivers to addresses at paneo.tech.

**Amazon SES (eu-west-1).**
- Region choice: `af-south-1` is **not reachable** from this server on 587,
  so use Ireland.
- Setup:
  1. Go to *Verified identities → Create identity → Domain* for paneo.tech,
     with Easy DKIM.
  2. Add the three CNAMEs SES lists
     (`<token>._domainkey.paneo.tech` → `<token>.dkim.amazonses.com`).
  3. Go to *SMTP settings → Create SMTP credentials*.
  4. Run `sudo crasa-mail-configure ses …`.
- **Catch:** a new account is in the sandbox and delivers only to addresses
  you have verified one by one. Production access is a request that can take
  24 hours.

**Direct from this server, no relay. Tried; it does not work, and should not be forced.**
- A real test to Gmail on 17 September 2026 was refused:
  `550 5.7.26 ... SPF [crasadev.paneo.tech] with ip: [102.68.86.17] = did not pass`.
- Making it work would need all of the following:
  - an SPF record `v=spf1 ip4:102.68.86.17 -all` on crasadev.paneo.tech;
  - a DKIM key published in DNS;
  - a proper reverse-DNS name from the hosting provider (today
    102.68.86.17 resolves to `ai.`);
  - an MTA installed on a machine PSC Hub shares. A system `sendmail`
    appearing could change what PSC Hub's own mail does, which is outside
    what this server may touch.

## Already done

- `CRASA_ENQUIRY_RECIPIENT` is set in the server `.env` to the platform owner's
  address (emiletg@gmail.com). The contact form therefore cannot reach the
  real Secretariat (`crasa@crasa.org`, the code's default). Change it at
  handover.
- Every account on the platform today is a sample account at
  `@crasadev.invalid`. Switching mail on cannot reach a real person by
  accident. It also means **no demonstration account can receive a password
  reset or notification**, even with mail working. To show one live, create an
  account with your own address (`php artisan user:create`, marked sample).
- `php artisan mail:check you@example.org` sends one message synchronously
  and prints the relay's answer. Use it after any change to the mail settings.

## What changes on the 23rd if nothing is done

See DEMO_SCRIPT.md section 8. Password reset emails, the contact form's
delivery and every notification email cannot be shown. The screens that
trigger them still work, and the messages are written to
`storage/logs/laravel.log`. Two-factor sign-in is unaffected: it uses an
authenticator app, not email.
