# Rebuilding the CRASA platform on a clean host

**Written 15 September 2026, after the development host was compromised.**
See `docs/INCIDENT_2026-09-15.md` for what happened.

This document stands the platform up from three things and nothing else:
the git bundle, the scraped archive of crasa.org, and the two image-library
zips CRASA supplied. **Nothing is copied from the old server**: not its
database, not its `storage` folder, not its `.env`, not its `vendor`.

It assumes the person following it has never seen this project.

---

## 0. What you need in hand

| File | What it is | SHA-256 |
|---|---|---|
| `crasadev-2026-09-15.bundle` | The whole git history, including the rescue commit | In `SHA256SUMS` beside the bundle, and in the hand-over message sent off the old server |
| `scraped-assets.zip` | Images from crasa.org, scraped and supplied by the project owner | `e55d2f5be157e64b145cbec3d2326c913dabff716df0432b539afdbe6dba2f3b` |
| `public-css.patch` | 58 lines of CSS the rescued work needs, kept out of the rescue commit | `9fc2485f8c0cc1df640cda5fc67bc919fcc166f92418a32ffac122023b286ff2` |
| `crasa-images.zip` | Image library, batch one, supplied by CRASA | `b9b2b9b3152c2b5361c1aba5e9fcf898aaa99b16dfa6e9f02fd200b928410831` |
| `crasa-images-2.zip` | Image library, batch two, supplied by CRASA | `32907c31abca0fb2a0ffb26b26229106b6baffea4efd247268bde227828b167f` |

**The two image-library zips are not in git and not in the scraped archive.**
Take them from wherever CRASA delivered them, not from the old server, and
check the hashes above. Without them the homepage hero and seven other
photographs have nothing to show.

Check everything before starting:

```bash
sha256sum crasadev-2026-09-15.bundle scraped-assets.zip public-css.patch \
          crasa-images.zip crasa-images-2.zip
```

---

## 1. The host

**Give the platform its own hosting account.** The old server was reached
through a shared cPanel account holding about 29 unrelated sites, not
through the application.

| Need | Version | Why |
|---|---|---|
| Linux, Apache 2.4 with `mod_rewrite` | | The upload protection is an `.htaccess` file. On nginx, write the equivalent rule by hand |
| PHP, CLI and FPM | 8.3 (built and tested on 8.3.33) | `composer.json` requires `^8.3` |
| MariaDB | 10.11 (tested on 10.11.19) | |
| Composer | 2 (tested on 2.9.7) | |
| ImageMagick with Ghostscript | | Photograph derivatives and page-1 thumbnails of PDFs |
| `git`, `unzip`, `curl`, `cron` | | |

**PHP extensions.** Laravel's own: `ctype`, `curl`, `dom`, `fileinfo`,
`filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`,
`tokenizer`, `xml`. This platform also calls these directly:

| Extension | Used for |
|---|---|
| `pdo_mysql` | The database |
| `pdo_sqlite` | The test suite, which runs on an in-memory SQLite database |
| `imagick` | Derivatives, thumbnails, stripping EXIF from imported photographs |
| `zip` | Reading the scraped archive during the logo import |
| `intl` | Normalising accented filenames during the logo import |
| `iconv` | The same |

**Node is not needed.** Every stylesheet, script and font is committed under
`public/`. `package.json` and `vite.config.js` are Laravel's defaults and no
template loads a Vite asset.

**Outbound HTTPS** to `www.crasa.org`, `events.crasa.org` and
`cdn.prod.website-files.com`, for the content import.

---

## 2. Restore and review the code

```bash
git clone crasadev-2026-09-15.bundle crasadev
cd crasadev
git fsck --full
git log --oneline -5
```

The bundle carries the full history, so it needs nothing else to clone.

**Review the rescue commit before trusting it.** It is the most recent
commit. Its message lists what it carries. Read it in full:

```bash
git show --stat HEAD~1   # or whichever commit is titled "…, rescued"
git show HEAD~1
```

**Apply the CSS patch**, once you have read it:

```bash
git apply --check ../public-css.patch
git apply ../public-css.patch
git add public/css/admin.css public/css/blocks.css
git commit -m "the stylesheet rules the rescued work needs"
```

Without it, `AuthorityLogoDisplayTest` and `InteractiveSurfacesTest` fail,
and the notification preferences, the register's sector filter and the
authority logos render unstyled.

**Push to a private remote now**, before anything else, and write down the
commit identifier somewhere that is not the server.

---

## 3. The database

Create the application schema and an empty second schema for the backup
rehearsal. Generate a new password; do not reuse anything from the old
server.

```sql
CREATE DATABASE crasa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE crasa_rehearsal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crasa'@'localhost' IDENTIFIED BY '<generate a new one>';
GRANT ALL PRIVILEGES ON crasa.* TO 'crasa'@'localhost';
GRANT ALL PRIVILEGES ON crasa_rehearsal.* TO 'crasa'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. The environment file

```bash
cp .env.example .env
chmod 600 .env
php artisan key:generate
```

`chmod 600` matters: the old `.env` was world-readable.

**Every key, and what to set.** "Generate" means create a new value now.
Nothing in this table may be copied from the old server.

| Key | Set to |
|---|---|
| `APP_NAME` | `CRASA` |
| `APP_ENV` | `production` |
| `APP_KEY` | **Generate**, with `php artisan key:generate`. Never reuse the old key: it decrypts the old two-factor secrets |
| `APP_DEBUG` | `false` |
| `APP_URL` | The new site's `https://` address |
| `APP_LOCALE` | `en` |
| `APP_FALLBACK_LOCALE` | `en` |
| `APP_FAKER_LOCALE` | As in `.env.example`. Only the demonstration seeders read it |
| `APP_MAINTENANCE_DRIVER` | As in `.env.example` |
| `BCRYPT_ROUNDS` | As in `.env.example` |
| `LOG_CHANNEL` | `stack` |
| `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL` | As in `.env.example` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT` | The database server, normally `127.0.0.1` and `3306` |
| `DB_DATABASE` | `crasa` |
| `DB_USERNAME` | `crasa` |
| `DB_PASSWORD` | **Generate** |
| `DB_REHEARSAL_DATABASE` | `crasa_rehearsal`. Read by `backup:rehearse`; not in `.env.example` |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN` | As in `.env.example` |
| `SESSION_SECURE_COOKIE` | `true`. Not in `.env.example`; add it |
| `SESSION_SAME_SITE` | `lax`. Not in `.env.example`; add it |
| `SESSION_COOKIE` | A name for the session cookie. Not in `.env.example`; add it |
| `BROADCAST_CONNECTION` | `log` |
| `FILESYSTEM_DISK` | `local` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `MEMCACHED_HOST`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT` | As in `.env.example`. Neither is used |
| `REDIS_PASSWORD` | Leave empty unless Redis is introduced |
| `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT` | Per `docs/MAIL_SETUP.md` |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | **Generate**: a new mailbox password |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Per `docs/MAIL_SETUP.md` |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | **Generate**: new keys for the backup bucket, per `docs/BACKUP_SETUP.md` |
| `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | Per `docs/BACKUP_SETUP.md` |
| `VITE_APP_NAME` | As in `.env.example`. Unused |

---

## 5. Install

```bash
composer install --no-dev --optimize-autoloader
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=MenuSeeder --force
php artisan db:seed --class=HeaderMenuSeeder --force
```

**The two menu seeders are not called by `DatabaseSeeder`** and must be run by
hand, in that order. `MenuSeeder` writes the header and footer menus;
`HeaderMenuSeeder` then replaces the header with its current structure.

**`db:seed` creates demonstration data**, all flagged `is_sample`: sample
news, publications, indicator values and the workflow states the workshop
demonstrates. It is removed before go-live with
`php artisan content:strip-samples`.

Do not run `php artisan route:cache`: some routes are closures and it fails.
The old host ran with no config, route or view cache.

**Keep `public/` read-only to the web server** wherever the host lets the
application run as a different user from the one that owns the files. The
attack wrote its shells into `public/`. Only `storage/` and
`bootstrap/cache/` need to be writable.

---

## 6. The image library

```bash
cd storage/app/public/images
unzip ../../../../../crasa-images.zip       # creates crasa-images/
unzip ../../../../../crasa-images-2.zip     # creates crasa-images-2/

# Batch two's accepted photographs join batch one's folder.
cp -r crasa-images-2/data crasa-images-2/hero crasa-images/
cp crasa-images-2/institutional/*   crasa-images/institutional/
cp crasa-images-2/sector-telecom/*  crasa-images/sector-telecom/
cp crasa-images-2/_review-needed/*  crasa-images/_review-needed/

# Keep batch two's rights record with the library.
cp crasa-images-2/LICENCES.csv crasa-images/LICENCES-batch-2.csv
cp crasa-images-2/README.md    crasa-images/README-batch-2.md
rm -r crasa-images-2
cd -
```

Adjust the `unzip` paths to wherever the zips are. Then check the eight
photographs the site actually shows:

```bash
cd storage/app/public/images/crasa-images && sha256sum -c <<'SUMS'
8b9dac7a9703dc92b85994c12aa3a185a020fc6b01000846e072de08c3aa9211  hero/hero-delegates-boardroom-01.jpg
63071164afb9af23210bcb8da81f35223ab8b264fa2669d0f6de0f958099a7d5  people/people-courier-mobile-01.jpg
c2c7bacb51f94b7aeadb56615063886692168b87a4b40d96c6a8d6f6dd7dc822  sector-telecom/telecom-equipment-testing-01.jpg
39df2c68765e4d664f46909c70e4174733647c1089af7636548bbd0042ef6d94  sector-telecom/telecom-home-broadband-router-01.jpg
3e55fde49da0c5065b59fc3f5b72790f73ca95dd05eb4b1975f73ddb1dec3745  sector-postal/postal-parcel-handover-01.jpg
8b9dac7a9703dc92b85994c12aa3a185a020fc6b01000846e072de08c3aa9211  institutional/institutional-delegates-boardroom-01.jpg
4cb5ba1797f2bdc65f20c9c23a5f5ba34a29c12dfebe9a9aced6af15703d73f8  institutional/institutional-two-person-meeting-01.jpg
7b11131b1a6425d4aeeaf9d0303fbf15ed94bc796260bbf3a7902e75c33f4746  data/data-team-reviewing-charts-01.jpg
SUMS
cd -
```

The hero and the institutional boardroom photograph are the same file under
two names, so their hashes match on purpose.

---

## 7. Re-verify the scraped archive before using it

The archive sat on the compromised server. Its contents are public, so
nothing in it is secret, but it could have been altered. Check it against
the live site before any of it is used.

Put it at `storage/app/public/images/scraped-assets.zip` and confirm the hash
from section 0. Then save the script below as `verify-archive.php` outside
the project and run it from the project folder:

```bash
php ../verify-archive.php storage/app/public/images/scraped-assets.zip 40
```

It checks every logo file in the archive, twenty in all: the fourteen
authority logos the platform imports, plus CRASA's own marks. It then
checks a random 40 further files. Each is downloaded from the address the
manifest recorded and compared, byte for byte, with the copy in the
archive.

```php
<?php
// Compares the scraped archive with the live crasa.org CDN.
// Usage: php verify-archive.php <archive.zip> [sample size]

$archive = $argv[1] ?? 'storage/app/public/images/scraped-assets.zip';
$sample = (int) ($argv[2] ?? 40);

$zip = new ZipArchive;
if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
    fwrite(STDERR, "Cannot open {$archive}\n");
    exit(2);
}

$manifest = json_decode($zip->getFromName('scraped-assets/crasa.org-images/manifest.json'), true);
if (! is_array($manifest)) {
    fwrite(STDERR, "The archive has no readable manifest.\n");
    exit(2);
}

$entries = [];
foreach ($manifest as $row) {
    $path = str_replace('\\', '/', $row['local_path']);
    $at = strpos($path, 'scraped-assets/');
    if ($at === false) {
        continue;
    }
    $entries[substr($path, $at)] = $row['url'];
}

// Every authority logo, whatever the sample, because those are imported.
$logos = array_filter(
    $entries,
    fn ($url, $entry) => ! preg_match('/-p-\d+\./', $entry)
        && preg_match('/logo|INACOM|BOCRA|ICASA|CRAN|Congo|Mocambique|Lesotho|Mauritius|Malawi/i', $entry),
    ARRAY_FILTER_USE_BOTH
);

$keys = array_keys($entries);
shuffle($keys);
$check = $logos + array_intersect_key($entries, array_flip(array_slice($keys, 0, $sample)));

$match = 0;
$mismatch = 0;
$unreachable = 0;

foreach ($check as $entry => $url) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'CRASA-rebuild-verification/1.0',
    ]);
    $live = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($live === false || $status !== 200) {
        $unreachable++;
        echo "UNREACHABLE  {$entry}\n";
    } elseif (hash('sha256', $live) === hash('sha256', (string) $zip->getFromName($entry))) {
        $match++;
    } else {
        $mismatch++;
        echo "MISMATCH     {$entry}\n";
    }

    usleep(500000);
}

printf("Checked %d files: %d match, %d mismatch, %d unreachable.\n", count($check), $match, $mismatch, $unreachable);
exit($mismatch > 0 ? 1 : 0);
```

**How to read the result.**

- **Every file matches:** use the archive.
- **Any mismatch:** do not use the archive. The photographs do not need it,
  because `legacy:assets` downloads them from the live site in section 8.
  For the logos, save the fourteen from the live About page into a folder
  and run `php artisan authorities:import-logos --source=<that folder>`.
- **Unreachable:** the live file has moved or crasa.org is down. Those files
  cannot be verified; decide whether to trust them.

On 15 September, before the old server was reimaged, all 212 manifest
entries were confirmed to map to a file in the archive with the size the
manifest records. That proves the script reads the archive correctly. It
does not prove the archive matches the live site: only this check does.

---

## 8. Import CRASA's content

Run these in order, after section 5. Each is safe to re-run. The crawls are
rate-limited to one request a second and resume where they stopped.

```bash
php artisan legacy:import                  # crawls crasa.org's listings
php artisan legacy:content                 # fetches every page for its content
php artisan legacy:events                  # the events calendar, from its JSON API
php artisan legacy:assets                  # documents and photographs, EXIF stripped
php artisan legacy:map --dry-run           # shows what would be written
php artisan legacy:map                     # writes the records
php artisan legacy:photographs             # describes the photographs
php artisan content:strip-samples --only=NewsItem,Meeting --force
php artisan authorities:import-logos --dry-run
php artisan authorities:import-logos
php artisan content:audit
```

| Command | Needs | On 10 September it produced |
|---|---|---|
| `legacy:import` | Network | 91 pages, 35 documents, 101 images found |
| `legacy:content` | The output of `legacy:import` | 98 pages parsed |
| `legacy:events` | Network | 43 events |
| `legacy:assets` | The output of `legacy:content` | 39 documents, 65 photographs, 30 skipped as site furniture |
| `legacy:map` | Both manifests; the About page from `db:seed` | 35 publications, 38 news items, 46 meetings, 4 projects, 141 legacy URLs redirected |
| `legacy:photographs` | The output of `legacy:assets` | Rewrites `docs/LEGACY_PHOTOGRAPHS.md` and adds rows to `docs/LICENCES.csv` |
| `content:strip-samples --only=NewsItem,Meeting` | The import | Removes the demonstration news and events, now that real ones exist |
| `authorities:import-logos` | The verified archive, at the path in section 7 | 14 logos. Refuses the whole run if any authority is unmatched |
| `content:audit` | | A read-only report in `storage/app/private/content-audit/` |

The counts will differ if crasa.org has changed. `legacy:photographs`
rewrites two files in `docs/`; commit them or discard them.

Everything these commands write is CRASA's own material and is **not**
flagged `is_sample`. The go-live purge must never reach it.

---

## 9. Accounts

```bash
php artisan user:create --role=administrator --email=<address> --name="<name>"
php artisan user:create --role=member_state_rep --country=bw --authority=BOCRA \
                        --email=<address> --name="<name>"
```

The command prompts for the password; it deliberately has no password
option. **Administrators must enrol in two-factor authentication at their
first sign-in**, and the platform enforces it.

**Demonstration accounts for the workshop** add `--sample --generate`. The
password is printed once. Put it straight into a password manager. **Never
write passwords into a file on the server**: the old server held the test
accounts' passwords in a text file, and the attacker could read it.

None of the old server's seven accounts is recreated from its database.

---

## 10. Derivatives and thumbnails

```bash
php artisan images:derivatives
php artisan documents:thumbnails --sync
```

`legacy:assets` already builds derivatives for the imported photographs.
`images:derivatives` builds them for the image library.

---

## 11. Hardening

```bash
php artisan security:harden-uploads
php artisan security:harden-uploads --check
chmod 600 .env
```

- **The web server's document root is `public/`**, and nothing else.
- **`APP_ENV=production` and `APP_DEBUG=false`.**
- **No other website on the same hosting account.**
- **DNS through Cloudflare**, pointed at the new server, with SSL set to Full (strict).

---

## 12. Scheduler and queue

Two crontab lines, and only two. Replace the paths with the real ones.

```cron
* * * * * /usr/bin/php /srv/crasa/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /srv/crasa/artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

| What | How it runs |
|---|---|
| `data:check`, daily at 03:20 | The scheduler |
| `notifications:digest`, daily at 06:40 | The scheduler |
| The delegate registration email | The queue |
| The contact form email | The queue |
| Page-1 thumbnails of uploaded PDFs | The queue |

The old server had neither line for this application, so none of these ever
ran there.

```bash
php artisan schedule:list
crontab -l
```

**`crontab -l` must show exactly these two lines.** The attack added 36; a
routine check of this output would have found them.

---

## 13. Mail and backups

- **Mail:** `docs/MAIL_SETUP.md`. The old server had no mail connected.
- **Backups:** `docs/BACKUP_SETUP.md`, **before the content import in
  section 8**, so the first backup is of a clean platform. It installs a
  new dependency, which needs CRASA's agreement.

---

## 14. Verify before the domains go back online

Do every step. Each one checks something the incident or an earlier fault
showed can go wrong.

| # | Check | Pass |
|---|---|---|
| 1 | `php artisan about` | Environment `production`, debug `OFF` |
| 2 | In a separate checkout on the same host: `composer install` (with dev dependencies), then `php artisan test` | 1,580 tests passing, the count on 15 September with the CSS patch applied |
| 3 | `php artisan security:harden-uploads --check` | Rules in place |
| 4 | The uploads canary in `docs/SECURITY_REVIEW.md`: put `canary.php` and `canary.phtml` in `storage/app/public/probe/`, request each, then delete the folder | Both `403` |
| 5 | Request `/.env`, `/.git/config`, `/composer.json`, `/artisan`, `/vendor/autoload.php`, `/storage/logs/laravel.log`, `/docs/REBUILD.md` | Every one `404` or `403` |
| 6 | Request an address that does not exist | The site's own 404 page, with no stack trace |
| 7 | `find public -name '*.php'` | Only `public/index.php` |
| 8 | `crontab -l` | The two lines in section 12, nothing else |
| 9 | `php artisan content:audit`, then read the report | No stored markup shown as text; the four placeholder agendas listed |
| 10 | `php artisan data:check` | No orphaned records |
| 11 | `php artisan backup:rehearse` | Dump, restore into `crasa_rehearsal`, verify |
| 12 | Request `/post-articles/sadc-roaming-guidelines` | `301` to the publication's new address |
| 13 | Walk `docs/DEMO_SCRIPT.md` end to end, in English, French and Portuguese | Every step as written |
| 14 | Push to the private remote and record the commit identifier off the server | |

Requests for `.php` addresses come back as a `301` to the same address
without `.php`. That is a Cloudflare rule on the domain, not the
application. Test the extensionless address.

---

## Known defects to fix after the rebuild

### 1. The admin dark theme hides headings and the primary button

On the admin dark theme, the page title, card headings and the primary
button render in `--c-navy` on the navy ground and are invisible. They
should use `--a-ink` and the admin button token, which flip with the theme.
Audit every admin and extranet surface in both themes for the same class of
fault; the fix is one token substitution per rule.

**What the stylesheets show, measured on 15 September.**

- **Headings.** The shell loads `public/css/base.css`, which sets
  `h1, h2, h3, h4 { color: var(--c-navy) }` at line 23. The admin heading
  rule in `public/css/admin.css`, at line 50, sets the typeface but no
  colour, so every admin and extranet heading stays navy in both themes:
  **1.08:1** on the dark ground and **1.04:1** on a dark card, against the
  4.5:1 AA requires. `h4` is not covered by the admin rule at all.
  `--a-ink` measures 14.19:1 on a dark card. `--a-ink-strong` also flips,
  and it keeps light-theme headings navy exactly as screen 09 draws them;
  `--a-ink` would turn them charcoal.
- **The primary button.** `.a-btn--primary` is `--c-green-dark`, not navy.
  It fails in two other ways: its hover background is `--a-ink-strong`,
  which is white in the dark theme, under white text (**1.00:1**); and its
  resting fill measures **1.88:1** against a dark card, against the 3:1
  SC 1.4.11 requires for a control's boundary.
- **There is no admin button token.** Adding one is adding a design token,
  which `CLAUDE.md` says needs sign-off first.

**Rules to check in both themes.** A static scan of `admin.css` found these
using a fixed palette colour on a surface that changes with the theme. The
sidebar and extranet rail are navy in both themes by design and are left
out. Line numbers are with the CSS patch applied.

| Line | Rule | Declaration |
|---|---|---|
| 50 | `body[data-surface="admin"] h1, h2, h3` | no colour set, so inherits navy from `base.css` |
| 166 | `.a-identity__initials` | `background: var(--c-navy)` |
| 265 | `.a-btn--primary:hover` | `background: var(--a-ink-strong)` under white text |
| 840 | `.a-sample__how code` | fixed white background |
| 1213 | `.s-stage[aria-current="step"]` | `border-color: var(--c-navy)` |
| 1242 | `.s-comment` | `background: var(--c-white)` |
| 1256 | `.s-comment__text` | `color: var(--c-charcoal)` |
| 1411 | `.e-file__format` | `background: var(--c-navy)` |
| 1626 | `.e-cal__meeting` | `color: var(--c-navy)` |
| 1930 | `.a-top` | `background: var(--c-white)` |
| 1952 | `.a-top__field` | `background: var(--c-white)` |
| 1971, 1980, 2032, 2221, 2374 | hover states | `background: var(--c-cream)` under theme-flipping ink |
| 1972 | `.a-pills__item.is-current` | `background: var(--c-navy)` |
| 2180 | `.a-table tbody tr:hover` | `background: var(--c-cream)` under `--a-ink`, which is near-white in the dark theme |
| 2336 | `.a-proposal__status` | `background: var(--c-cream)` |
| 2482 | `.a-chip.is-current` | `background: var(--c-navy)` |

A scan finds declarations, not faults. Each row needs looking at on screen,
in both themes.

### 2. The queue worker and the scheduler never ran

The old server had no cron entry for this application, so queued mail,
PDF thumbnails, the notification digest and the nightly integrity check
never ran. Section 12 fixes it. Confirm it after a day: a thumbnail exists
for an uploaded PDF, and the log shows `data:check` ran.

### 3. Mail is not connected

Everything that emails a person fails silently until `docs/MAIL_SETUP.md`
is done. `docs/PRE_WORKSHOP.md` item 1.

### 4. No backup system is installed

`spatie/laravel-backup` is recommended in `docs/BACKUP_SETUP.md` and was
never installed, pending CRASA's agreement to the dependency.

### 5. Four images inside imported articles have no local copy

The content renderer drops them rather than linking to the old CDN:
`CRASA Logo - High Resolution.jpg`, `CRASA LOGS.png`, `Doc2.png` and
`LOGOS.png`. All four are in the scraped archive. Import them into
`storage/app/public/images/legacy/` once the archive has passed section 7.

### 6. `.env.example` is missing keys

`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`DB_REHEARSAL_DATABASE`, `SESSION_COOKIE`, `SESSION_SAME_SITE` and
`SESSION_SECURE_COOKIE` are read but not listed. Add them, with no values.

### 7. The rescued work's CSS is outside git until the patch is applied

Section 2. Until then two tests fail and several components are unstyled.
