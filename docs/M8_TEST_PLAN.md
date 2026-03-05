# PeachTrack — Milestone 8 Test Plan (Coding & Testing)

**Project:** PeachTrack (Fuzzy Peach Salon — Lethbridge)

**Purpose:** Verify PeachTrack meets the Milestone 6/7 design requirements and supports the salon’s business process (shift start/end, per-service entry tips + sales, manager review, weekly/biweekly payroll payout).

## 1) Scope
### In scope
- Authentication (Manager/Admin, Employee)
- Employee shift workflow (start shift, log entry, end shift)
- Data integrity rules (no negative amounts, no double active shifts)
- Manager workflows (manage users, manage shifts, reports, payroll/payout)
- Exports/printing (Print to PDF, CSV export)
- Mobile responsiveness (employee phone use)

### Out of scope (if applicable)
- Hosted deployment to public server (if demo uses localhost)
- Payment processing integration (POS)

## 2) Test environments
- **Local (Developer Mac / MAMP):** http://localhost:8888/UX-Code-design/
- **Mobile (same Wi‑Fi):** http://<MAC_IP>:8888/UX-Code-design/
- **Browsers:** Chrome (primary), Safari (secondary)
- **Database:** MySQL (MAMP)

## 3) Test data setup
Create accounts:
- Manager/Admin account (role 101)
- 2–3 Employee accounts (role 102)

Seed realistic data:
- At least 2 weeks of shifts
- Multiple tips per shift (cash + electronic)
- A few payouts marked as paid

## 4) Testing approach
### 4.1 Stub testing (high level)
Goal: ensure pages load and navigation works before deeper testing.
- Verify each page loads without fatal errors
- Verify menu links work
- Verify About page displays required content

### 4.2 Unit testing (feature-by-feature)
Goal: verify each feature behaves correctly with valid + invalid inputs.
- Validation rules
- CRUD operations
- Role permissions

### 4.3 System testing (end-to-end business scenarios)
Goal: verify full salon workflow works as a complete system.
- Employee logs entries through a shift
- Manager reviews reports and prepares payroll
- Manager marks payouts as paid and exports payroll totals

## 5) Test cases (Requirements Coverage)
> Mark each as **PASS/FAIL** and capture evidence (screenshots or exported files).

### A) Login & Roles
**A1 — Login (valid)**
- Steps: Login with valid manager credentials
- Expected: Dashboard loads, manager menu items visible

**A2 — Login (invalid)**
- Steps: wrong password
- Expected: friendly error message, no crash

**A3 — Permissions**
- Steps: employee tries to open manager pages (Reports/Payroll/Manage Users)
- Expected: redirected/blocked

### B) Employee Shift Workflow
**B1 — Start shift**
- Steps: employee → Start Shift
- Expected: active shift created, status changes to Active

**B2 — Prevent double active shift**
- Steps: Start Shift twice
- Expected: error “already active shift”

**B3 — Log entry (tip + sale)**
- Steps: enter tip + sale, choose method, submit
- Expected: saved, appears in Recent Tips, shift totals update

**B4 — Validation: negative sale**
- Steps: sale amount = -1
- Expected: friendly error, not saved

**B5 — End shift**
- Steps: End Shift
- Expected: End time saved; summary totals shown; no SQL errors

### C) Manager Shifts
**C1 — View active shifts**
- Expected: list updates; show employee + start time

**C2 — Force-end shift**
- Expected: shift ends; audit saved (if schema enabled)

### D) Reports
**D1 — Reports filter**
- Steps: choose range presets and custom dates
- Expected: charts/tables reflect range; no blank/buggy UI

**D2 — No records message**
- Steps: pick date range with no data
- Expected: clear message (not blank page)

### E) Payroll / Tip Payout
**E1 — Payroll totals (unpaid)**
- Expected: totals by employee display correctly

**E2 — Mark range as paid**
- Steps: click “Mark as Paid”
- Expected: unpaid → paid changes; prevents double pay

**E3 — Pay single employee**
- Expected: only that employee tips marked as paid

**E4 — Details view**
- Expected: shows shifts + tip entries, time field meaningful

**E5 — Export CSV**
- Expected: downloads .csv; opens in Excel without HTML markup

### F) UI/UX (Milestone requirements)
**F1 — Consistency**
- Expected: consistent fonts, button styles, labels

**F2 — Mobile usability**
- Steps: open employee dashboard on phone
- Expected: buttons tappable, no broken layout

## 6) Evidence to submit
- Completed test case checklist with PASS/FAIL
- Screenshots:
  - Employee shift start/log/end
  - Reports page results
  - Payroll page + mark paid
  - Payroll CSV opened in Excel
  - Mobile screenshots
- Copies of exported CSV/PDF

## 7) Defects & fixes process
- Log issue → reproduce steps → expected vs actual → severity → fix → re-test

---
**Prepared by:** <Team Name>
**Date:** <YYYY-MM-DD>
