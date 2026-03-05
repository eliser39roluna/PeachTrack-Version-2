# PeachTrack — Free Local Setup (No MAMP)

This setup uses **Homebrew PHP + MariaDB** (free) on macOS.

## 1) Install dependencies
```bash
brew install php mariadb
```

## 2) Start MariaDB
```bash
brew services start mariadb
```

## 3) Create database + app user
```bash
/opt/homebrew/opt/mariadb/bin/mariadb -e "CREATE DATABASE IF NOT EXISTS peachtrack; CREATE USER IF NOT EXISTS 'peach'@'localhost' IDENTIFIED BY 'peach123'; GRANT ALL PRIVILEGES ON peachtrack.* TO 'peach'@'localhost'; FLUSH PRIVILEGES;"
```

## 4) Import schema + seed data
From repo root:
```bash
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/schema.sql
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/seed.sql
```

## 5) Apply migrations (recommended)
```bash
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/alter_employee_deactivate.sql
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/alter_tip_time.sql
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/alter_tip_audit_softdelete.sql
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/alter_tip_payroll.sql
/opt/homebrew/opt/mariadb/bin/mariadb -u peach -ppeach123 peachtrack < sql/alter_shift_audit_force_end.sql
```

## 6) Run the web app
From repo `src/`:
```bash
cd src
/opt/homebrew/opt/php/bin/php -S 0.0.0.0:8888 -t .
```

Open in browser:
- Mac: http://localhost:8888/login.php
- Phone (same Wi‑Fi): http://<YOUR_MAC_IP>:8888/login.php

## Notes
- DB settings are in `src/db_config.php` (defaults to `peachtrack` / user `peach`).
- You can override DB settings with environment variables:
  - `DB_HOST`, `DB_PORT`, `DB_SOCKET`, `DB_NAME`, `DB_USER`, `DB_PASS`
