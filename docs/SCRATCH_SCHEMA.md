# Create the restore-rehearsal database

**Five minutes in cPanel. Nothing to interpret — every value is given.**

This is the second database `php artisan backup:rehearse` needs so it can
prove a restore rather than only prove a dump. See Question 10 in
`docs/QUESTIONS.md` for why.

---

## What to create

| | |
|---|---|
| **Database name** | `ptechweb_restoretest` |
| **User** | `ptechweb_crasadev` — the existing one. **Do not create a new user.** |
| **Privileges** | ALL PRIVILEGES |

The existing user keeps its own password, so nothing goes in `.env` that is
not there already and no new credential exists to be leaked.

---

## In cPanel

1. **MySQL® Databases.**
2. Under **Create New Database**, the field already shows `ptechweb_`. Type
   `restoretest` after it, so the full name reads `ptechweb_restoretest`.
   Press **Create Database**, then **Go Back**.
3. Scroll to **Add User To Database**.
   - **User:** `ptechweb_crasadev`
   - **Database:** `ptechweb_restoretest`
   - Press **Add**.
4. On the privileges screen, tick **ALL PRIVILEGES** and press **Make
   Changes**.

That is all. There is no third step and nothing to configure on the site.

---

## Then tell me, and I will run it

The rehearsal reads the name from config, which reads it from `.env`:

```dotenv
DB_REHEARSAL_DATABASE=ptechweb_restoretest
```

I will add that line and run:

```bash
php artisan backup:rehearse
```

It will dump, restore into `ptechweb_restoretest`, count the rows in both,
compare them table by table, and drop what it restored. If the numbers do not
match, it fails and says which table.

---

## What it will never do

**It will never write to `ptechweb_crasadev`.** The command refuses to run if
the rehearsal database name matches the live one, and it refuses to drop
tables from anything but the rehearsal schema. Both are guarded by test.

**Nothing on the live site changes.** The rehearsal reads the live database
and writes only to the scratch one.

---

## If you would rather not

The download half of the backup is already proven and rehearsed. What the
scratch schema buys is proving the *restore* automatically and repeatedly,
rather than the once it was proven by hand on 9 September.

That is the difference between a restore procedure that has been performed
successfully many times and one that will be attempted for the first time
under pressure. It is worth five minutes, and it is not a blocker for the
workshop.
