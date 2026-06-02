# Employee Intake Module - Deployment Package

**Version:** 1.0
**Date:** 2026-05-05
**For:** HR System Production Deployment

---

## 📦 Package Contents

This archive contains all files needed to deploy the Employee Intake module to your live server.

### Files Included:
- routes/intake.php
- app/Modules/Intake/IntakeController.php
- app/Modules/Intake/IntakeRepository.php
- app/Views/layouts/intake.php
- app/Views/intake/form.php
- app/Views/intake/success.php
- app/Views/intake/review-list.php
- app/Views/intake/review-show.php
- app/Core/Database.php (with transaction fix)
- database/intake_migration.sql
- database/intake_photo_company_migration.sql
- Bootstrap 5 assets (CSS + fonts + JS)

---

## 🚀 Quick Deployment (3 Steps)

### Step 1: Extract Archive
Extract intake-deploy-temp to your project root.

### Step 2: Run SQL Migrations
`
mysql -u root -p hr_system < database/intake_migration.sql
mysql -u root -p hr_system < database/intake_photo_company_migration.sql
`

### Step 3: Manual Updates
Edit these two files:
- public-hr/index.php: Add equire BASE_PATH . '/routes/intake.php';
- app/Views/partials/sidebar.php: Add intake nav link

See MANUAL_UPDATES.md for details.

---

## 📋 Files to Deploy

**New files (copy to your server):**
- routes/intake.php
- app/Modules/Intake/IntakeController.php
- app/Modules/Intake/IntakeRepository.php
- app/Views/layouts/intake.php
- app/Views/intake/*

**Modified files (apply changes):**
- app/Core/Database.php (transaction fix - already included)
- public-hr/index.php (add 1 line)
- app/Views/partials/sidebar.php (add nav link)

**Assets (copy to all 3 roots):**
- public/assets/css/bootstrap.min.css
- public/assets/css/bootstrap-icons.min.css
- public/assets/css/fonts/
- public/assets/js/bootstrap.bundle.min.js

Repeat for public-hr and public-careers directories.

---

## 🔐 Safety

- All code tested and working
- Nested transaction fix applied
- CSRF protection on all forms
- Role-based access control (HR only)
- PII encryption with AES-256-CBC

---

See INTAKE_DEPLOYMENT.md for full technical documentation.
