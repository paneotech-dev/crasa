# Backups: what to install, what CRASA must provide, and how to prove it works

**CRASA Digital Anchor Ecosystem**
Contract ZM-COMESA-CRASA/WEBSITE/25/07/2025-as
Prepared 9 September 2026

---

## The finding this answers

The security review of 9 September recorded two items left open for a
decision rather than fixed on the spot. This is the first of them.

**There are no off-site backups of this platform, and there is no schedule
that would create any.** Three separate things are missing, and each one on
its own would be enough to lose the platform:

| What | State on the server today |
|---|---|
| A backup package | Not installed |
| An off-site destination | Not provisioned. `AWS_BUCKET` and both keys are empty in `.env` |
| A cron entry running Laravel's scheduler | **Absent for this application.** A sibling application on the same account has one; this one has none, so any scheduled task written into the platform would never run |

The third is the one that would be missed. It is possible to install a backup
package, configure it correctly, see the command succeed when run by hand,
and still never take a single backup, because nothing ever calls it.

**What is at risk.** 40 tables and about 3 MB of database, which is small and
recreatable in principle — except that it holds the submission history, the
audit log and every instrument version, none of which can be reconstructed
from anywhere else. And 195 MB in `storage/app`, which is the uploaded
documents themselves: the publications, the extranet files, the instrument
PDFs. Those exist nowhere but this disk.

**What exists is not a backup of this platform.** cPanel's account-level
backup and the weekly Softaculous job on this account cover the hosting
account, on the same machine, and are useful against a mistake. They are not
useful against the failure that matters: this host had two web shells
dropped on a sibling account in the week before this document was written. A
backup that lives on the compromised machine is a backup an attacker can
delete.

---

## Recommended: Spatie Laravel Backup to S3-compatible storage

This is one package, two credentials and one cron line. It is the option we
recommend and the rest of this document assumes it unless CRASA chooses the
alternative in the last section.

### 1. What we install

```bash
composer require spatie/laravel-backup
composer require league/flysystem-aws-s3-v3 "^3.0"
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

Both are standard, both are widely used, and neither needs anything the host
does not already have. `mysqldump`, `gzip` and `tar` are all present and
verified on this server.

`league/flysystem-aws-s3-v3` is what is missing today. Without it Laravel
knows what an S3 disk is but cannot talk to one, and the error is
`Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found`.

**This needs CRASA's agreement before we install it**, because adding a
dependency is a decision the contract puts with the client.

### 2. What CRASA must provide

This is the whole list. Nothing else is needed from CRASA for backups.

| Item | Detail | Who typically holds it |
|---|---|---|
| An S3-compatible bucket | Region and bucket name | COMESA IT, or a CRASA account with AWS, Backblaze B2, Wasabi or DigitalOcean Spaces |
| An access key ID | | The same |
| A secret access key | | The same |
| The endpoint URL | Only if the provider is not AWS itself | The same |
| A retention decision | How long backups are kept. Our recommendation is below | CRASA |

**Send the two keys separately from everything else, and not by email if it
can be avoided.** They grant write access to the bucket. If email is the only
practical channel, send the key ID and the secret in two different messages.

**The bucket should be private, versioned, and its credentials should be
write-only if the provider supports it.** A key that can write a backup but
cannot delete one is the difference between an attacker destroying the site
and an attacker destroying the site and its backups.

Storage needed: about 250 MB per full backup today, so a 30-day retention at
one backup a day is roughly 8 GB. That is a few dollars a month at every
provider named above. It grows with the document library, not with traffic.

### 3. What we configure

**Retention.** Our recommendation, which balances the cost above against the
realistic detection time for a quiet compromise:

| Kept | For |
|---|---|
| Every daily backup | 7 days |
| One daily backup | 16 days |
| One weekly backup | 8 weeks |
| One monthly backup | 4 months |
| One yearly backup | 2 years |

The monthly and yearly copies matter more than they look. A web shell can sit
unnoticed for weeks, and a seven-day retention would mean every backup
already contains it.

### The backup set, exactly

Three things, and the second is the one a database-only backup misses.

| Include | Why |
|---|---|
| The full database | 40 tables. The submission history, the audit log and every instrument version, none of it reconstructible |
| `storage/app/private` | **The uploaded documents themselves.** Publications, extranet files, instrument PDFs. They exist nowhere else, and the database only holds their paths |
| `storage/app/public` | Media library originals and the responsive derivatives. About 194 MB |

| Exclude | Why |
|---|---|
| `vendor/`, `node_modules/` | Reinstalled from the lock file |
| `.env` | It holds the database password. Putting a live credential in an off-site bucket is a worse risk than the one the backup mitigates. Documented separately, restored by hand |

**`storage/app/public/images/derivatives` is a judgement call.** It is
regenerated by `php artisan images:derivatives` and it is most of the 194 MB.
Excluding it halves every backup; including it means a restore is complete
without running a command. Include it: the extra storage costs a few dollars
a year, and a restore that needs a follow-up command is a restore somebody
performs incorrectly at three in the morning.

**Not `.env`.** It holds the database password and would place a live
credential in an off-site bucket. The environment file is documented
separately and restored by hand — see the restore procedure below.

**Failure notification.** The package emails when a backup fails and when
none has been taken recently. That requires working mail: see
`docs/MAIL_SETUP.md`. **A backup system whose only failure signal is an email
that cannot be sent is not a backup system**, so mail should be configured
first, or at the same time.

### 4. The cron entry, which is the part that is actually missing

One line, in the `ptechweb` user's crontab:

```cron
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/ptechweb/crasadev/artisan schedule:run >> /dev/null 2>&1
```

Note the PHP binary. The default `php` on this account is 8.2 and this
application requires 8.3; the full path is not optional. A sibling
application on the same account already runs its scheduler this way, so the
mechanism is proven on this host.

That single entry runs everything the platform schedules, now and later.
Without it, `schedule:list` is a list of things that will never happen.

**Verify it is running** — do not assume:

```bash
php artisan schedule:list          # the tasks the platform expects to run
grep crasadev /var/log/cron | tail # the system's record that it called them
```

---

## The restore procedure

Write this down somewhere that is not this server.

### Restoring the database

### The order matters

Files first, then the database, then the caches. Restoring the database
before the files leaves a window in which the site is serving rows whose
documents are not there yet, and that window is exactly the state the
9 September rehearsal was stuck in: every page healthy, every download 404.

```bash
# 0. Credentials for mysql and mysqldump. A password passed on the command
#    line does not survive shell quoting on this account, and would be in
#    the shell history besides.
CNF=$(mktemp) && chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword="%s"\nhost=%s\n' "$DB_USER" "$DB_PASS" "$DB_HOST" > "$CNF"
# ... use --defaults-extra-file="$CNF" on every command below, then:
# rm -f "$CNF"

# 1. Fetch the backup from the bucket to a working directory.
php artisan backup:list                    # confirm what exists and when
# download the chosen archive from the bucket

# 2. Unpack it.
unzip crasa-2026-11-03-02-00-00.zip -d /tmp/restore

# 3. Put the site into maintenance so nothing writes during the restore.
php artisan down

# 4. Restore. This replaces the current database entirely.
mysql -u ptechweb_crasadev -p ptechweb_crasadev < /tmp/restore/db-dumps/mysql-ptechweb_crasadev.sql

# 5. Restore the uploaded documents. BEFORE the database, not after: a
#    database restored ahead of its files serves rows whose documents are
#    not there yet, and every download 404s in the meantime.
rsync -a /tmp/restore/storage/app/private/ /home/ptechweb/crasadev/storage/app/private/
rsync -a /tmp/restore/storage/app/public/  /home/ptechweb/crasadev/storage/app/public/

# 6. Recreate the environment file by hand from the documented values,
#    then clear every cache that holds a stale copy of it.
php artisan config:clear && php artisan cache:clear && php artisan view:clear

# 7. Regenerate the image derivatives, which are deliberately not in the
#    backup.
php artisan images:derivatives

# 8. Back up.
php artisan up
```

### What a rehearsal on 9 September proved

This procedure was rehearsed against the live database on 9 September 2026:
full dump, purge, verify, restore. Three things it found, all now fixed, and
all of them things a first run on go-live day would have found instead.

**mysqldump needs a defaults file, not a command-line password.** Reading
`DB_PASSWORD` out of `.env` in a shell and passing it inline fails on this
account: the value does not survive shell quoting. Write a temporary file with
`0600` permissions, use `--defaults-extra-file`, delete it after. The
procedure below does that.

**A database-only restore leaves every download broken.** The purge deletes a
demonstration document's file as well as its row, and restoring the database
brought the rows back and not the files. Every page looked healthy and every
download returned 404. This is precisely why the verification below ends at a
file download rather than at a row count.

**The purge left 337 orphaned rows.** Translations are polymorphic, so there
is no foreign key and nothing cascades. Every other relationship on the
platform cascaded correctly — 76 rows in three pivot and version tables went
with their parents — and the translations stayed. That is now fixed at the
model, and the purge sweeps historical orphans as well.

### Rehearse it with one command

```bash
php artisan backup:rehearse            # dump, verify, report
php artisan backup:rehearse --keep     # and leave the dump on disk
```

It takes a real dump with the real command, checks every table is in it,
checks every table with rows has an INSERT, checks both file-store
directories are present, and checks no document row has lost its file. It
exits non-zero if any of that fails, so it can run on a schedule.

**It skips the restore step on this host, and says so.** The database user
holds ALL PRIVILEGES on one schema and USAGE on everything else, so it cannot
create a scratch schema to restore into, and restoring into the live one to
prove a point is not a rehearsal. Given a scratch schema it does the full
cycle:

```bash
php artisan backup:rehearse --database=ptechweb_restoretest
```

Asking the host for that second schema is Question 10 in
`docs/QUESTIONS.md`. It costs nothing and it is the difference between a
restore procedure that is tested and one that is described.

### Proving a restore works, which is the only part that counts

**An untested backup is a hypothesis.** The failure mode is not that the
backup is missing; it is that the backup exists, has been running nightly for
a year, and restores to a database missing a table because the dump was
taken with the wrong flags.

Do this once before go-live and once a quarter afterwards:

1. Create an empty database, `ptechweb_restoretest`.
2. Restore the most recent backup into it by the procedure above.
3. Run these four checks and record the numbers:

```sql
SELECT COUNT(*) FROM submissions;          -- against the live count
SELECT COUNT(*) FROM audit_logs;           -- against the live count
SELECT COUNT(*) FROM instrument_versions;  -- against the live count
SELECT MAX(created_at) FROM audit_logs;    -- how recent the backup really is
```

4. Point a scratch copy of the application at the restored database, open the
   homepage, open one publication and download its file. **The file download
   is the check that matters**: it is the only one that proves the database
   restore and the `storage/app` restore agree with each other. A restored
   database whose file paths point at documents that were not restored looks
   perfectly healthy until somebody clicks a download.
5. Drop the test database.

Record the date and the four counts in the Secretariat's own records. A
restore test nobody wrote down did not happen.

---

## If CRASA will not or cannot provision S3

This is a realistic outcome. Procurement for a cloud storage account can take
longer than the remaining project time, and some public bodies cannot hold a
commercial cloud account at all.

The options below are in order of preference. **All three are better than
what exists today, and none of them is as good as the S3 option**, because
each keeps at least one copy closer to the machine being protected.

### Option A: a second server, over SSH

If COMESA or CRASA has any other Linux server anywhere — a different host, a
different provider, an office machine with a fixed address — that is a valid
off-site destination and needs no commercial account.

Needed: a hostname, an SSH user, and a public key we generate here and CRASA
installs there. The same Spatie package writes to it through an SFTP disk
(`league/flysystem-sftp-v3` instead of the S3 driver).

The destination server should hold the backups in a directory the backup user
can write to and **cannot delete from**, for the same reason as the write-only
S3 key.

### Option B: cPanel's own remote destination

cPanel is installed on this host and its backup configuration accepts a
remote destination over SFTP, FTP or S3. It backs up the whole hosting
account rather than this application specifically, which is coarser but not
worthless.

Needed: WHM or cPanel access, and the same destination credentials as Option
A. This does not need our involvement and CRASA's host may be able to do it
today.

Its weakness is that it is configured outside the application, so nothing in
this platform can tell whether it ran. It should be checked by hand monthly.

### Option C: local backups, downloaded manually

The lowest tier, and it is only a stopgap. The same package writes to the
local disk on a nightly schedule, and a named person at the Secretariat
downloads the most recent archive once a week and keeps it somewhere else.

This still protects against an application-level mistake — a bad import, a
wrong deletion — which is the most common cause of data loss in practice. It
protects against nothing that reaches the server.

**If this is the choice, it should be recorded as an accepted risk with a
named owner and a review date**, not left as an assumption. We will draft
that entry.

### What we do while CRASA decides

Nothing here is blocked on the decision except the destination. We will:

1. Add the cron entry, which costs nothing and is needed for every option
   including the ones that do not exist yet.
2. Install the package and configure the schedule and retention against a
   local disk.
3. Run a full restore test against that local backup and record the four
   counts, so the procedure above is proven rather than written.

Then the destination is a change of two lines in `.env` on the day CRASA
provides the credentials, and everything around it has already been tested.
