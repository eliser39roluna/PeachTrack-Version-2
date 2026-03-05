# PeachTrack — Backup & Recovery (Milestone 8)

## Goal
Protect project data and enable recovery after corruption/mistakes.

## What to back up
- Database: `peachtrack` (schema + data)
- Application source code: `src/` folder

## Recommended backup schedule (simple)
- **Daily:** database export (SQL)
- **Weekly:** full database export + zip of `src/`
- Keep at least **2 weeks** of backups.

## How to back up the database (phpMyAdmin)
1. Open MAMP phpMyAdmin.
2. Select the `peachtrack` database.
3. Click **Export**.
4. Choose **Quick** (or Custom if needed), Format: **SQL**.
5. Click **Go** and save the `.sql` file.

Name backups like:
- `peachtrack_YYYY-MM-DD.sql`

## How to restore the database
1. In phpMyAdmin, create an empty database named `peachtrack` (if needed).
2. Select the database.
3. Click **Import**.
4. Choose the backup `.sql` file.
5. Click **Go**.

## Source code backup
- Zip the project folder or push to GitHub.
- Tag milestone versions with releases/tags.
