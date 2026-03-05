# PeachTrack — Shift, Tips & Reporting Dashboard (PHP + MySQL)

PeachTrack is a role-based web app built for a salon/beauty bar workflow. Employees can start/end shifts and log tips, while managers can monitor active shifts in near real-time and generate reports with charts.

## Features

### Employee
- Secure login
- Start/End shift with live duration timer
- Log tips (cash or electronic) + sales amount
- View recent tips

### Admin / Manager
- Live view of **active shifts** (updates automatically)
- Manage employees (create employee, reset password)
- Manage shifts (force-end active shifts, edit shifts)
- Create shifts for any employee
- Reports dashboard:
  - Tips & Sales by Employee (bar)
  - Tips by Day (line)
  - Sales by Day (line)
  - Cash vs Electronic (doughnut)
  - Filters by date range and employee
  - Print → Save as PDF

## Tech Stack
- PHP (server-rendered)
- MySQL
- MAMP (local dev)
- Chart.js (reports charts)

## Screenshots / Demo
- Add screenshots to: `docs/screenshots/`
- Recommended: include a short demo video and link it here.

## Getting Started (Local)

### 1) Put the app into MAMP htdocs
Copy `src/` into:
- `/Applications/MAMP/htdocs/peachtrack/` (or any folder name)

### 2) Create the database
Using phpMyAdmin or CLI, create DB and import:
1. `sql/schema.sql`
2. `sql/seed.sql`

Database name default: `peachtrack`

### 3) Configure DB connection
Edit `src/db_config.php` for your environment.

This repo supports environment variables (optional). See `.env.example`.

### 4) Run
Start MAMP (Apache + MySQL), then open:
- `http://localhost:8888/peachtrack/login.php`

## Notes
- Passwords use `password_hash()` + `password_verify()` (bcrypt).
- “Real-time” admin active shifts uses a lightweight polling endpoint: `src/api/active_shifts.php`.

## What I learned / highlights (suggested for portfolio)
- Role-based access control (employee vs manager)
- Database-driven shift state (active shift = `End_Time IS NULL`)
- Reporting queries + KPIs + charts
- UI/UX improvements with a glassmorphism dashboard layout

