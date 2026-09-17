# Cutover: crasadev.paneo.tech to 102.68.86.17

Written 17 September 2026. **Nothing here has been run.** Run it in order,
with someone watching the server. About thirty minutes, most of it checking.

The server already serves the site for `crasadev.paneo.tech` on 443 with a
self-signed certificate (valid to 16 October 2026). That certificate stays
on disk throughout, for `--resolve` testing and for rollback.

---

## Cloudflare SSL mode: set it to **Full (strict)**, for this hostname only

| Mode | What Cloudflare does | Result with this server |
|---|---|---|
| Off / Flexible | Connects to the origin over plain HTTP on port 80 | Port 80 redirects to HTTPS, Cloudflare follows it over HTTP again: **redirect loop** (`ERR_TOO_MANY_REDIRECTS`) |
| Full | HTTPS to the origin, **accepts any certificate** | Works, but anyone who can intercept between Cloudflare and the server can impersonate it |
| **Full (strict)** | HTTPS to the origin, **requires a valid certificate for the hostname**; a Cloudflare Origin Certificate counts | **Correct**, once the Origin Certificate below is installed |

**What goes wrong, and how it looks:**

- **525 (SSL handshake failed).** Cloudflare reached port 443 but could not
  complete TLS: nothing listening with TLS for that name, no shared cipher,
  or the origin answering with no certificate for the requested SNI. This is
  the error the old server threw. Here it would mean the nginx block is not
  loaded (check `nginx -t`, `ls /etc/nginx/sites-enabled/`) or port 443 is
  blocked.
- **526 (invalid SSL certificate).** Full (strict) with a certificate
  Cloudflare does not accept: the self-signed one, an expired one, or one
  whose name does not include `crasadev.paneo.tech`. **If you switch to Full
  (strict) before step 2, you get 526.**
- **Redirect loop.** Flexible mode, as above.

**Set it with a Configuration Rule, not the zone setting.** The zone-wide
SSL/TLS mode applies to every paneo.tech site, and changing it can break
the others. Cloudflare dashboard → paneo.tech → Rules → Configuration Rules
→ Create rule:

- Name: `crasadev full strict`
- When: Hostname **equals** `crasadev.paneo.tech`
- Then: SSL → **Full (strict)**
- Also in the same rule, if offered: **Rocket Loader: Off**, **Email
  Obfuscation: Off** (both rewrite pages; the comparator's script and the
  contact page do not need them).

---

## 0. Before you start (5 minutes)

1. **Write down the current DNS record** for `crasadev.paneo.tech`:
   Cloudflare → DNS → Records. Note its **type, target and proxy status**.
   That is the rollback.
2. Confirm this server is healthy:
   ```
   curl -sk --resolve crasadev.paneo.tech:443:127.0.0.1 -o /dev/null -w '%{http_code}\n' https://crasadev.paneo.tech/en
   systemctl is-active nginx php8.3-fpm crasa-queue crasa-scheduler.timer
   ```
   Expect `200` and four `active`.
3. Take a backup: `sudo systemctl start crasa-backup.service && sudo tail -3 /var/backups/crasa/backup.log`.
4. Confirm the firewall admits HTTPS: `sudo ufw status` lists `80` and
   `443` as ALLOW (true on 17 September; PSC Hub is served on them too).

## 1. Create the Origin Certificate (you, in Cloudflare, 3 minutes)

Cloudflare → paneo.tech → SSL/TLS → **Origin Server** → **Create
Certificate**.

- Generate private key and CSR with Cloudflare, key type **RSA (2048)**.
- Hostnames: **`crasadev.paneo.tech`** only (remove the defaults if you want
  the certificate to cover nothing else).
- Validity: 15 years.
- Format: **PEM**. Leave the page open: the private key is shown once.

## 2. Install it on the server (5 minutes)

```
sudo install -d -m 0750 /etc/ssl/crasa

sudo tee /etc/ssl/crasa/crasadev-origin.crt >/dev/null
#   paste the Origin Certificate, including BEGIN and END lines, then Ctrl-D

sudo tee /etc/ssl/crasa/crasadev-origin.key >/dev/null
#   paste the Private Key, then Ctrl-D
sudo chmod 600 /etc/ssl/crasa/crasadev-origin.key
```

**Check the pair before nginx sees it:**

```
sudo openssl x509 -in /etc/ssl/crasa/crasadev-origin.crt -noout -subject -issuer -enddate -ext subjectAltName
sudo openssl x509 -in /etc/ssl/crasa/crasadev-origin.crt -noout -pubkey | sha256sum
sudo openssl pkey -in /etc/ssl/crasa/crasadev-origin.key -pubout | sha256sum
```

The issuer mentions **CloudFlare Origin**, the names include
`crasadev.paneo.tech`, and **the two sums are identical**. If they differ,
the key and certificate were pasted from different generations: stop and
paste again.

**Point nginx at it** (the old lines are kept in a backup file):

```
sudo cp -p /etc/nginx/sites-available/crasadev.paneo.tech /root/crasadev.nginx.before-cutover
sudo sed -i \
  -e 's|/etc/ssl/crasa/crasadev-selfsigned.crt|/etc/ssl/crasa/crasadev-origin.crt|' \
  -e 's|/etc/ssl/crasa/crasadev-selfsigned.key|/etc/ssl/crasa/crasadev-origin.key|' \
  /etc/nginx/sites-available/crasadev.paneo.tech
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` must say **syntax is ok** and **test is successful**. A reload is
graceful for every site on this server, PSC Hub included; wait ten seconds
before testing, because old workers finish their requests first.

**Test the origin directly** (still no DNS change):

```
curl -s -o /dev/null -w '%{http_code} %{ssl_verify_result}\n' \
  --resolve crasadev.paneo.tech:443:127.0.0.1 -k https://crasadev.paneo.tech/en
echo | openssl s_client -connect 127.0.0.1:443 -servername crasadev.paneo.tech 2>/dev/null | openssl x509 -noout -issuer
```

Expect `200`, and an issuer that mentions CloudFlare. (`-k` is still needed
locally: an Origin Certificate is trusted by Cloudflare, not by browsers or
curl.) Also confirm PSC Hub is unaffected:

```
curl -s -o /dev/null -w '%{http_code} %{time_total}\n' --resolve ai.pschub.africa:443:127.0.0.1 https://ai.pschub.africa/
```

## 3. Set the SSL mode (you, in Cloudflare, 2 minutes)

Create the Configuration Rule described at the top: **Full (strict)** for
`crasadev.paneo.tech`. Do this **now**, after step 2 and before step 4.

## 4. The DNS change (you, in Cloudflare, 1 minute)

Cloudflare → DNS → edit the `crasadev` record:

- Type **A**, name `crasadev`, IPv4 **`102.68.86.17`**
- Proxy status: **Proxied** (orange cloud). Required: the origin certificate
  is only accepted by Cloudflare, and the server trusts visitor addresses
  only from Cloudflare's ranges.
- TTL: Auto.

If the record was a CNAME, delete it and create the A record. Because the
record is proxied, the change takes effect at Cloudflare's edge within
seconds; browsers keep seeing Cloudflare's addresses throughout.

## 5. Verify (10 minutes)

From your laptop, no `--resolve`, no `-k`:

```
curl -sI https://crasadev.paneo.tech/en | grep -iE '^(HTTP|server|cf-ray|set-cookie)'
```

Expect `HTTP/2 200`, `server: cloudflare`, a `cf-ray`, and
`set-cookie: crasa_session=...; secure; httponly; samesite=strict`.

On the server:

```
# The request above arrived here, with your real address rather than Cloudflare's:
sudo tail -3 /var/log/nginx/crasa.access.log

# Nothing new in the error logs:
sudo tail -5 /var/log/nginx/crasa.error.log
sudo grep -c ' ERROR' /var/www/crasa/storage/logs/laravel.log

# The whole site, through Cloudflare, as a visitor in all three languages:
sudo -u crasa php /var/www/crasa/artisan qa:crawl --max=300
```

`qa:crawl` must end with **No failures.**

Then, in a browser:

1. `https://crasadev.paneo.tech` loads, padlock valid (Cloudflare's edge
   certificate).
2. Sign in as the administrator; the two-factor challenge appears and
   accepts a code.
3. The comparator: preset, add two states, Export PDF downloads.
4. A document download from the library.
5. French and Portuguese switch.

**If step 1 shows 526**, the SSL rule is Full (strict) but nginx is still
presenting the self-signed certificate: repeat the check in step 2.
**525**: nginx is not answering TLS for the name; run `sudo nginx -t` and
check `sites-enabled`. **Redirect loop**: the rule did not apply and the zone
mode is Flexible; check the rule's hostname.

## 6. After a day

- `sudo journalctl -u crasa-queue --since yesterday | tail` and
  `systemctl list-timers crasa-*`: the worker is up and the scheduler and
  backup have run.
- The old server: once the new one has served for a day, take the old
  virtual host offline so the old code cannot be reached by IP. See
  `INCIDENT_2026-09-15.md` for why that matters.
- Optional hardening: allow HTTPS for this server block only from
  Cloudflare's ranges (nginx `geo` on `$realip_remote_addr`), so the origin
  cannot be reached around Cloudflare. It must not be done in the firewall:
  PSC Hub shares ports 80 and 443 and may not sit behind Cloudflare.

---

## Rollback (2 minutes)

In this order:

1. **DNS:** Cloudflare → DNS → set the `crasadev` record back to the type,
   target and proxy status written down in step 0.
2. **SSL rule:** disable the `crasadev full strict` Configuration Rule, so
   the old origin gets whatever mode it had before.
3. **nginx** (only if the certificate itself is the problem; it does no
   harm to leave the Origin Certificate in place):
   ```
   sudo cp -p /root/crasadev.nginx.before-cutover /etc/nginx/sites-available/crasadev.paneo.tech
   sudo nginx -t && sudo systemctl reload nginx
   ```
4. Check the old site answers:
   `curl -sI https://crasadev.paneo.tech/ | head -3`.

Nothing in the application or its database changes during the cutover, so
there is nothing to roll back on the CRASA side.
