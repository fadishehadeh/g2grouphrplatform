# Employee Intake Module — Complete Deployment Package

**Last Updated:** 2026-05-05  
**Status:** Ready for production deployment

---

## 📋 Deployment Checklist

- [ ] Run SQL migrations (see **SQL Migrations** section below)
- [ ] Copy all new files to server (see **New Files** section)
- [ ] Update 3 existing files (see **Modified Files** section)
- [ ] Copy Bootstrap assets to all 3 document roots (see **Assets** section)
- [ ] Test approval flow end-to-end

---

## 🗄️ SQL Migrations

Run these **in order** against `hr_system` database:

### 1. Create Intake Tables
```sql
-- Employee Intake Form — staging tables
-- Run against hr_system database

USE hr_system;

CREATE TABLE IF NOT EXISTS employee_intake_submissions (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token                VARCHAR(64)     NOT NULL COMMENT 'bin2hex(random_bytes(32)) — used in HR review URL',
    first_name           VARCHAR(100)    NOT NULL,
    middle_name          VARCHAR(100)    NULL,
    last_name            VARCHAR(100)    NOT NULL,
    gender               ENUM('male','female','other','prefer_not_to_say') NULL,
    marital_status       ENUM('single','married','divorced','widowed','other') NULL,
    nationality          VARCHAR(100)    NULL,
    second_nationality   VARCHAR(100)    NULL,
    company_id           BIGINT UNSIGNED NULL,
    personal_email       VARCHAR(500)    NULL,
    phone                VARCHAR(500)    NULL,
    alternate_phone      VARCHAR(500)    NULL,
    date_of_birth        VARCHAR(500)    NULL,
    id_number            VARCHAR(500)    NULL,
    passport_number      VARCHAR(500)    NULL,
    profile_photo_path   VARCHAR(255)    NULL,
    address_line_1       VARCHAR(255)    NULL,
    address_line_2       VARCHAR(255)    NULL,
    city                 VARCHAR(100)    NULL,
    state                VARCHAR(100)    NULL,
    country              VARCHAR(100)    NULL,
    postal_code          VARCHAR(20)     NULL,
    status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at          DATETIME        NULL,
    reviewed_by          BIGINT UNSIGNED NULL,
    rejection_reason     TEXT            NULL,
    approved_employee_id BIGINT UNSIGNED NULL,
    submitter_ip         VARCHAR(45)     NULL,
    submitter_ua         VARCHAR(500)    NULL,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_token (token),
    KEY idx_intake_status    (status),
    KEY idx_intake_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS employee_intake_contacts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id   BIGINT UNSIGNED NOT NULL,
    full_name       VARCHAR(150)    NOT NULL,
    relationship    VARCHAR(100)    NOT NULL,
    phone           VARCHAR(30)     NOT NULL,
    alternate_phone VARCHAR(30)     NULL,
    email           VARCHAR(150)    NULL,
    is_primary      TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_intake_contacts_sub (submission_id),
    CONSTRAINT fk_intake_contacts_submission
        FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS employee_intake_documents (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id       BIGINT UNSIGNED NOT NULL,
    document_type_id    BIGINT UNSIGNED NULL,
    category_id         BIGINT UNSIGNED NULL,
    title               VARCHAR(150)    NOT NULL,
    document_number     VARCHAR(100)    NULL,
    original_file_name  VARCHAR(255)    NOT NULL,
    stored_file_name    VARCHAR(255)    NOT NULL,
    file_path           VARCHAR(255)    NOT NULL,
    file_extension      VARCHAR(20)     NULL,
    mime_type           VARCHAR(100)    NULL,
    file_size           BIGINT UNSIGNED NULL,
    issue_date          DATE            NULL,
    expiry_date         DATE            NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_intake_documents_sub (submission_id),
    CONSTRAINT fk_intake_documents_submission
        FOREIGN KEY (submission_id) REFERENCES employee_intake_submissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 📁 New Files to Create

### **routes/intake.php**
Path: `routes/intake.php`

```php
<?php

declare(strict_types=1);

use App\Middleware\AccountStatusMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Intake\IntakeController;

$router = $app->router();

// ── Public (no auth) ─────────────────────────────────────────────────────────
$router->get('/employee-registration',         [IntakeController::class, 'form']);
$router->post('/employee-registration',        [IntakeController::class, 'submit']);
$router->get('/employee-registration/success', [IntakeController::class, 'success']);

// ── HR-protected ─────────────────────────────────────────────────────────────
$hrMid = [
    AuthMiddleware::class,
    AccountStatusMiddleware::class,
    [RoleMiddleware::class, ['super_admin', 'hr_only']],
];

$router->get('/employee-registration/review',                          [IntakeController::class, 'reviewList'], $hrMid);
$router->get('/employee-registration/review/{token}',                  [IntakeController::class, 'reviewShow'], $hrMid);
$router->post('/employee-registration/review/{token}/approve',         [IntakeController::class, 'approve'],    $hrMid);
$router->post('/employee-registration/review/{token}/reject',          [IntakeController::class, 'reject'],     $hrMid);
```

### **app/Modules/Intake/IntakeRepository.php**
Path: `app/Modules/Intake/IntakeRepository.php`

Full file content: **Use file from repo** (405 lines)

### **app/Modules/Intake/IntakeController.php**
Path: `app/Modules/Intake/IntakeController.php`

Full file content: **Use file from repo** (627 lines)

### **app/Views/layouts/intake.php**
Path: `app/Views/layouts/intake.php`

Full file content: **Use file from repo** (66 lines)

### **app/Views/intake/form.php**
Path: `app/Views/intake/form.php`

Full file content: **Use file from repo** (650+ lines - large file)

### **app/Views/intake/success.php**
Path: `app/Views/intake/success.php`

```php
<?php declare(strict_types=1); ?>
<div class="container py-5" style="max-width:640px">
    <div class="intake-card p-5 text-center">

        <div class="mb-4">
            <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);display:inline-flex;align-items:center;justify-content:center">
                <i class="bi bi-check-lg text-success" style="font-size:2.6rem;line-height:1"></i>
            </div>
        </div>

        <h3 class="fw-bold mb-2">Registration Submitted!</h3>
        <p class="text-muted mb-4" style="max-width:420px;margin:0 auto">
            Thank you for completing the employee registration form.<br>
            Your information has been securely received and is now pending review by the HR team.
        </p>

        <div class="alert alert-light border text-start small mb-4" style="max-width:420px;margin:0 auto">
            <p class="fw-semibold mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>What happens next?</p>
            <ol class="mb-0 ps-3">
                <li class="mb-1">HR will review your submitted details and documents.</li>
                <li class="mb-1">Your employee profile will be created upon approval.</li>
                <li>You will be contacted via email once your account is ready.</li>
            </ol>
        </div>

        <hr class="my-4">
        <p class="small text-muted mb-0">
            Questions? Contact HR directly at
            <a href="mailto:hr@greydoha.com" class="text-decoration-none">hr@greydoha.com</a>
        </p>

    </div>
</div>
```

### **app/Views/intake/review-list.php**
Path: `app/Views/intake/review-list.php`

Full file content: **Use file from repo** (100+ lines)

### **app/Views/intake/review-show.php**
Path: `app/Views/intake/review-show.php`

Full file content: **Use file from repo** (464 lines)

---

## ✏️ Modified Existing Files

### **1. app/Core/Database.php**

**Location:** Lines 90–104  
**Change:** Fix nested transaction support

**OLD:**
```php
public function transaction(callable $callback): mixed
{
    $pdo = $this->connection();
    $pdo->beginTransaction();

    try {
        $result = $callback($this);
        $pdo->commit();

        return $result;
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }
}
```

**NEW:**
```php
public function transaction(callable $callback): mixed
{
    $pdo = $this->connection();
    $nested = $pdo->inTransaction();

    if (!$nested) {
        $pdo->beginTransaction();
    }

    try {
        $result = $callback($this);
        if (!$nested) {
            $pdo->commit();
        }
        return $result;
    } catch (\Throwable $throwable) {
        if (!$nested) {
            $pdo->rollBack();
        }
        throw $throwable;
    }
}
```

---

### **2. public-hr/index.php**

**Location:** Add after line 50 (after `require BASE_PATH . '/routes/jobs.php';`)

**Add this line:**
```php
require BASE_PATH . '/routes/intake.php';
```

**Full context (lines 37–52):**
```php
require BASE_PATH . '/routes/auth.php';
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/admin.php';
require BASE_PATH . '/routes/structure.php';
require BASE_PATH . '/routes/employees.php';
require BASE_PATH . '/routes/leaves.php';
require BASE_PATH . '/routes/documents.php';
require BASE_PATH . '/routes/onboarding.php';
require BASE_PATH . '/routes/offboarding.php';
require BASE_PATH . '/routes/announcements.php';
require BASE_PATH . '/routes/reports.php';
require BASE_PATH . '/routes/settings.php';
require BASE_PATH . '/routes/letters.php';
require BASE_PATH . '/routes/jobs.php';
require BASE_PATH . '/routes/intake.php';  // ← ADD THIS LINE
require BASE_PATH . '/routes/api.php';
```

---

### **3. app/Views/partials/sidebar.php**

**Location:** Inside the `has_role(['super_admin', 'hr_only'])` block, in the People section

**Find this section:**
```php
<a href="<?= e(url('/employees')); ?>" class="sidebar-sublink <?= in_array(current_route_prefix(), ['/employees']) ? 'active' : ''; ?>">
    <i class="bi bi-people-fill"></i> Employees
</a>
```

**Add after it:**
```php
<a href="<?= e(url('/employee-registration/review')); ?>" class="sidebar-sublink <?= in_array(current_route_prefix(), ['/employee-registration']) ? 'active' : ''; ?>">
    <i class="bi bi-person-plus-fill"></i> Employee Intake
</a>
```

Also update the `$peopleOpen` variable to include `/employee-registration`:
```php
$peopleOpen = in_array(current_route_prefix(), ['/employees', '/structures', '/letters', '/employee-registration']);
```

---

## 📦 Asset Files

Copy Bootstrap assets to **all 3** document roots:

### Files to copy:
```
public/assets/css/bootstrap.min.css
public/assets/css/bootstrap-icons.min.css
public/assets/css/bootstrap-icons.woff
public/assets/css/bootstrap-icons.woff2
public/assets/js/bootstrap.bundle.min.js

public-hr/assets/css/bootstrap.min.css
public-hr/assets/css/bootstrap-icons.min.css
public-hr/assets/css/bootstrap-icons.woff
public-hr/assets/css/bootstrap-icons.woff2
public-hr/assets/js/bootstrap.bundle.min.js

public-careers/assets/css/bootstrap.min.css
public-careers/assets/css/bootstrap-icons.min.css
public-careers/assets/css/bootstrap-icons.woff
public-careers/assets/css/bootstrap-icons.woff2
public-careers/assets/js/bootstrap.bundle.min.js
```

These are already in your repo — just copy them to the live server.

---

## 🧪 Testing After Deployment

1. **Public form:** `http://your-domain/employee-registration`
   - Load form and verify Bootstrap styling
   - Submit a complete form

2. **HR review list:** `http://your-domain/admin/employee-registration/review`
   - Verify pending submissions appear with status badge
   - Search functionality works

3. **Individual review:** Click on a submission
   - All submitted data displays correctly
   - Employment details form loads
   - Company/branch/department dropdowns populated

4. **Approval workflow:**
   - Fill in required fields (Work Email, Company, Employment Type, Joining Date)
   - Click Approve
   - Verify: status changes to "Approved"
   - Verify: new employee appears in Employees list
   - Verify: staged documents moved to `storage/uploads/documents/employee_{id}/`

5. **Rejection workflow:**
   - Fill in rejection reason
   - Click Reject
   - Verify: status changes to "Rejected"
   - Verify: reason is saved

---

## 📞 Support

- **Routes prefix:** `/employee-registration` (public) and `/employee-registration/review` (HR)
- **Storage:** Intake files at `storage/uploads/intake/{submissionId}/`
- **Database:** All tables in `hr_system` database
- **Session:** Uses existing HR session management
