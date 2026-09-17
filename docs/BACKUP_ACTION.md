# Backups: what to do, in ten minutes

Written 17 September 2026. For the full background see `BACKUP_SETUP.md`.

## What runs today, with nothing from you

Every night at **01:10 UTC** (`crasa-backup.timer`) the server writes, to
`/var/backups/crasa` (root only):

| File | What | Kept |
|---|---|---|
| `db-DATE.sql.gz` | The whole `crasa` database, with the dump tool's completion marker checked | 14 days |
| `storage-app-DATE.tar.gz` | Every uploaded and imported file: documents, images, thumbnails, the legacy manifests | 7 days |
| `code-DATE.bundle` | A git bundle of every branch of the deployed code | 7 days |
| `offsite/crasa-DATE.tar.age` | **All of the above plus the application `.env`, encrypted** | 7 days |

The encrypted bundle can be opened by **your two SSH keys** (`emile@paneotech`,
`emile@laptop`) and by a verification key that never leaves the server. Every
night the job decrypts it and compares it with what was encrypted; a bundle
that does not round-trip is not kept.

**Why the `.env` and the code are in it.** `APP_KEY` decrypts every
two-factor secret, so a restore without it locks out every enrolled
administrator. The code is there because the deploy key is read-only: the
commits made on this server since `ccd8b9e` exist nowhere else until they are
pushed to GitHub.

**What it does not cover.** `storage/app/rehearsal`, and the original
`scraped-assets.zip` (55 MB, kept in `/home/emile/crasa-rescue`). The MariaDB
container's own configuration is in `/var/www/crasa/.server/mariadb` and is
not in the bundle.

**The gap.** Until one of the options below is set up, **every copy is on this
one disk.** A lost server loses the backups with it.

## Option 1, today, no accounts: pull it to your laptop (one command)

```
ssh emile@102.68.86.17 sudo crasa-backup-export > crasa-latest.tar.age
age -d -i ~/.ssh/id_ed25519 crasa-latest.tar.age | tar -x
```

The first line sends the newest bundle (it refuses to write to a terminal).
The second decrypts it with your own SSH key; install `age` from
<https://age-encryption.org> or `brew install age`. Do it weekly, and before
anything risky. **This is the recommendation until a bucket exists.**

## Option 2, recommended for go-live: an S3-compatible bucket (ten minutes)

Everything on the server is installed and tested (`rclone`, and the push has
been proven against a stand-in bucket on 17 September). You create the
bucket and a key; one command does the rest.

### Amazon S3

1. **Bucket.** S3 console → Create bucket. Name: `crasa-backups-<something>`.
   Region: `eu-west-1` (Ireland) or `af-south-1` (Cape Town). Block all public
   access: **on**. Versioning: on.
2. **Lifecycle rule.** Bucket → Management → Create lifecycle rule →
   "Expire current versions after 35 days", "Permanently delete noncurrent
   versions after 7 days". The server keeps a week; the bucket keeps five.
3. **A key that can only write to that bucket.** IAM → Users → Create user
   `crasa-backup` (no console access) → Attach policy → Create policy → JSON:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {"Effect": "Allow", "Action": ["s3:ListBucket"], "Resource": "arn:aws:s3:::BUCKET"},
       {"Effect": "Allow", "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"], "Resource": "arn:aws:s3:::BUCKET/crasa/*"}
     ]
   }
   ```
   Replace `BUCKET` twice. Then the user → Security credentials → Create
   access key → "Application running outside AWS". Copy both values.
4. **On the server:**
   ```
   sudo crasa-backup-s3-configure
   ```
   Answer: provider `AWS`, endpoint blank, region as above, the bucket name,
   path `crasa/nightly` (Enter), key ID, secret. The command lists the
   bucket, writes and deletes a probe file, saves the settings (mode 600) and
   runs the backup at once. Its last line should read
   `pushed to offsite:BUCKET/crasa/nightly and verified`.

The key cannot delete anything outside `crasa/`, and cannot read any other
bucket. The bundle is encrypted before it leaves the server, so the bucket
never holds a readable copy.

### Cloudflare R2 (you already use Cloudflare)

1. Cloudflare dashboard → R2 → Create bucket `crasa-backups`. Location: auto.
2. Bucket → Settings → Object lifecycle rules → delete objects after 35 days.
3. R2 → Manage R2 API tokens → Create token: permission "Object Read &
   Write", **specific bucket** `crasa-backups`. Copy the access key ID, the
   secret, and the S3 endpoint `https://<account-id>.r2.cloudflarestorage.com`.
4. `sudo crasa-backup-s3-configure`: provider `Cloudflare R2`, the endpoint,
   region `auto`, bucket `crasa-backups`, then the key and secret.

R2 has no egress charge, which matters for a 270 MB nightly file you may need
to download in a hurry.

## Option 3: a second server over SSH

On the other machine, as a user that owns the target directory:

```
mkdir -p ~/crasa-backups
# add this line to ~/.ssh/authorized_keys, all on one line:
command="rrsync ~/crasa-backups",restrict <paste the key below>
```

The key to paste is printed by `sudo cat /etc/crasa-backup/push_key.pub` on
this server. Then on this server:

```
echo 'DEST=user@backup-host:' | sudo tee /etc/crasa-backup/offsite.conf
sudo ssh-keyscan backup-host | sudo tee /etc/crasa-backup/known_hosts
sudo systemctl start crasa-backup.service && sudo tail -3 /var/backups/crasa/backup.log
```

## Restoring, proven on 17 September 2026

From the encrypted bundle, into the rehearsal database and a scratch copy of
the application:

- the database loaded in under a second, and every table's row count matched
  production (the only difference was one session created after the dump);
- all 48 documents, 48 thumbnails, 65 media originals, 268 renditions,
  14 authority logos, 39 hero images and 25 article images resolved on disk;
- over HTTP, all 48 downloads returned real PDF or Office files and all 105
  images returned 200;
- the two-factor secret of an enrolled administrator decrypted with the
  bundled `APP_KEY`.

The procedure is `BACKUP_SETUP.md`, "The restore procedure". After a restore,
run `php artisan documents:thumbnails --sync` and
`php artisan images:derivatives`: both now rebuild any file that is recorded
but missing.
