<?php
/**
 * Smoke test – hits every GET route as the super-admin user and reports status.
 * Usage:  php tests/smoke-test.php
 */

$baseUrl  = 'http://localhost/HR%20System/public';
$login    = 'admin';
$password = 'admin@123';  // default seeded password
$cookieJar = tempnam(sys_get_temp_dir(), 'hr_cookie_');

// ── helpers ──────────────────────────────────────────────────────────────────

function http(string $method, string $url, array $postFields = [], string $cookieJar = ''): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_HEADER         => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($raw, $headerSize);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $finalUrl, 'error' => $err];
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

// ── Step 1: Login ────────────────────────────────────────────────────────────

echo "=== HR System Smoke Test ===\n\n";

// Get CSRF token from login page
$res = http('GET', "$baseUrl/login", [], $cookieJar);
if ($res['code'] !== 200) {
    echo "FATAL: Cannot reach login page (HTTP {$res['code']}). Is Apache running?\n";
    exit(1);
}
$csrf = extractCsrf($res['body']);
if ($csrf === '') {
    echo "FATAL: No CSRF token found on login page.\n";
    exit(1);
}
echo "[OK] Login page reachable, CSRF obtained.\n";

// Post login
$res = http('POST', "$baseUrl/login", [
    '_token'   => $csrf,
    'login'    => $login,
    'password' => $password,
], $cookieJar);

// After login we should land on /dashboard (200)
if (strpos($res['url'], '/dashboard') === false && $res['code'] !== 200) {
    echo "FATAL: Login failed (HTTP {$res['code']}, landed at {$res['url']}).\n";
    echo "Body snippet: " . substr(strip_tags($res['body']), 0, 300) . "\n";
    exit(1);
}
echo "[OK] Logged in as '{$login}', landed at {$res['url']}\n\n";

// ── Step 2: Hit every GET route ──────────────────────────────────────────────

$routes = [
    // Dashboard
    '/dashboard',
    // Employees
    '/employees',
    '/employees/create',
    '/employees/1',
    '/employees/1/edit',
    '/employees/1/history',
    '/employees/2',
    '/employees/2/edit',
    '/employees/2/history',
    '/employees/1/archive',
    // Structure
    '/admin/structure',
    '/admin/companies',
    '/admin/companies/1',
    '/admin/branches',
    '/admin/departments',
    '/admin/teams',
    '/admin/job-titles',
    '/admin/designations',
    '/admin/reporting-lines',
    // Leave
    '/leave/my',
    '/leave/balances',
    '/leave/requests',
    '/leave/calendar',
    '/leave/request',
    '/leave/approvals',
    '/admin/leave/types',
    '/admin/leave/policies',
    '/admin/leave/holidays',
    '/admin/leave/weekends',
    // Documents
    '/documents',
    '/documents/categories',
    '/documents/expiring',
    '/employees/1/documents/upload',
    // Onboarding
    '/onboarding',
    '/onboarding/templates',
    '/onboarding/create/1',
    // Offboarding
    '/offboarding',
    '/offboarding/create/1',
    // Announcements
    '/announcements',
    // Notifications
    '/notifications',
    // Reports
    '/reports',
    '/reports/headcount',
    '/reports/department',
    '/reports/leave-usage',
    '/reports/new-joiners',
    '/reports/exits',
    '/reports/documents',
    '/reports/audit',
    // Settings
    '/settings',
    '/settings/attendance',
    '/settings/attendance/records',
    '/settings/attendance/assignments',
    '/settings/shifts',
    '/settings/schedules',
    '/settings/attendance-statuses',
    // Admin
    '/admin/users',
    '/admin/users/create',
    '/admin/users/1/edit',
    '/admin/roles',
];

$pass = 0; $fail = 0; $errors = [];

foreach ($routes as $route) {
    $res = http('GET', $baseUrl . $route, [], $cookieJar);
    $ok = ($res['code'] >= 200 && $res['code'] < 400);

    // Check for PHP fatal/parse errors in body
    $hasPhpError = (bool) preg_match('/(Fatal error|Parse error|Warning:|Notice:|Undefined|Uncaught)/i', $res['body']);
    $hasFlashError = (bool) preg_match('/class="alert alert-danger[^"]*"/', $res['body']);

    if ($ok && !$hasPhpError) {
        $status = "\033[32m[PASS]\033[0m";
        $pass++;
    } else {
        $status = "\033[31m[FAIL]\033[0m";
        $fail++;
        $detail = "HTTP {$res['code']}";
        if ($hasPhpError) {
            preg_match('/(Fatal error|Parse error|Warning|Notice)[^<\n]{0,200}/i', $res['body'], $errMatch);
            $detail .= ' | PHP: ' . ($errMatch[0] ?? 'error in body');
        }
        if ($hasFlashError) {
            $detail .= ' | Flash error present';
        }
        $errors[] = ['route' => $route, 'detail' => $detail];
    }

    $extra = $hasFlashError ? ' ⚠ flash-error' : '';
    echo "$status {$res['code']}  $route$extra\n";
}

echo "\n══════════════════════════════════════\n";
echo "  PASS: $pass   FAIL: $fail   TOTAL: " . ($pass + $fail) . "\n";
echo "══════════════════════════════════════\n";

if ($fail > 0) {
    echo "\nFailed routes:\n";
    foreach ($errors as $e) {
        echo "  ✗ {$e['route']}  →  {$e['detail']}\n";
    }
}

// ── Step 3: POST workflow tests ──────────────────────────────────────

echo "\n=== POST Workflow Tests ===\n\n";

$postPass = 0; $postFail = 0; $postErrors = [];

function postTest(string $label, string $getUrl, string $postUrl, array $fields, string $cookieJar, string $baseUrl, string $expectRedirect = ''): array
{
    // Get CSRF token from the form page
    $page = http('GET', $baseUrl . $getUrl, [], $cookieJar);
    $csrf = extractCsrf($page['body']);
    if ($csrf === '') {
        return ['ok' => false, 'detail' => 'No CSRF token on ' . $getUrl];
    }

    $fields['_token'] = $csrf;
    $res = http('POST', $baseUrl . $postUrl, $fields, $cookieJar);

    // Check for PHP errors in the response body
    $hasPhpError = (bool) preg_match('/(Fatal error|Parse error|Warning:|Notice:|Uncaught)/i', $res['body']);
    if ($hasPhpError) {
        preg_match('/(Fatal error|Parse error|Warning|Notice)[^<\n]{0,200}/i', $res['body'], $errMatch);
        return ['ok' => false, 'detail' => 'PHP error: ' . ($errMatch[0] ?? 'unknown')];
    }

    // A successful POST typically redirects (302→200) or returns 200
    if ($res['code'] < 200 || $res['code'] >= 500) {
        return ['ok' => false, 'detail' => 'HTTP ' . $res['code']];
    }

    // Check the final page for flash success or absence of flash error
    $hasFlashSuccess = (bool) preg_match('/class="alert alert-success[^"]*"/', $res['body']);
    $hasFlashError = (bool) preg_match('/class="alert alert-danger[^"]*"/', $res['body']);

    if ($hasFlashError && !$hasFlashSuccess) {
        // Extract error message
        preg_match('/alert-danger[^"]*"[^>]*>(.*?)<\/div/s', $res['body'], $errMsg);
        $msg = strip_tags(trim($errMsg[1] ?? 'unknown error'));
        $msg = preg_replace('/\s+/', ' ', $msg);
        return ['ok' => false, 'detail' => 'Flash error: ' . substr($msg, 0, 150)];
    }

    return ['ok' => true, 'detail' => $hasFlashSuccess ? 'success flash' : 'HTTP ' . $res['code']];
}

$postWorkflows = [
    [
        'label' => 'Create Announcement',
        'getUrl' => '/announcements',
        'postUrl' => '/announcements',
        'fields' => [
            'title' => 'Smoke Test Announcement ' . time(),
            'content' => 'This is an automated test announcement.',
            'priority' => 'normal',
            'status' => 'draft',
        ],
    ],
    [
        'label' => 'Create Leave Type',
        'getUrl' => '/admin/leave/types',
        'postUrl' => '/admin/leave/types',
        'fields' => [
            'name' => 'Smoke Test Leave ' . time(),
            'code' => 'SMOKE' . time(),
            'description' => 'Automated test leave type',
            'is_paid' => 1,
            'requires_balance' => 1,
            'requires_attachment' => 0,
            'requires_hr_approval' => 0,
            'allow_half_day' => 1,
            'default_days' => 10,
            'carry_forward_allowed' => 0,
            'carry_forward_limit' => 0,
            'notice_days_required' => 0,
            'max_days_per_request' => 5,
            'status' => 'active',
        ],
    ],
    [
        'label' => 'Create Holiday',
        'getUrl' => '/admin/leave/holidays',
        'postUrl' => '/admin/leave/holidays',
        'fields' => [
            'name' => 'Smoke Test Holiday ' . time(),
            'holiday_date' => '2026-12-25',
            'holiday_type' => 'public',
            'company_id' => '1',
            'branch_id' => '',
            'is_recurring' => 0,
        ],
    ],
];

foreach ($postWorkflows as $wf) {
    $result = postTest($wf['label'], $wf['getUrl'], $wf['postUrl'], $wf['fields'], $cookieJar, $baseUrl);
    if ($result['ok']) {
        $postPass++;
        echo "\033[32m[PASS]\033[0m {$wf['label']}  ({$result['detail']})\n";
    } else {
        $postFail++;
        echo "\033[31m[FAIL]\033[0m {$wf['label']}  ({$result['detail']})\n";
        $postErrors[] = ['test' => $wf['label'], 'detail' => $result['detail']];
    }
}

echo "\n══════════════════════════════════════\n";
echo "  POST PASS: $postPass   FAIL: $postFail   TOTAL: " . ($postPass + $postFail) . "\n";
echo "══════════════════════════════════════\n";

if ($postFail > 0) {
    echo "\nFailed POST tests:\n";
    foreach ($postErrors as $e) {
        echo "  ✗ {$e['test']}  →  {$e['detail']}\n";
    }
}

// ── Step 4: Role-based access tests ─────────────────────────────────

echo "\n=== Role-Based Access Tests ===\n\n";

$rolePass = 0; $roleFail = 0; $roleErrors = [];

// Logout current session (POST with CSRF token)
$res = http('GET', "$baseUrl/dashboard", [], $cookieJar);
$csrf = extractCsrf($res['body']);
$res = http('POST', "$baseUrl/logout", ['_token' => $csrf], $cookieJar);

// Test as Manager (fadi.chehade)
$res = http('GET', "$baseUrl/login", [], $cookieJar);
$csrf = extractCsrf($res['body']);
$res = http('POST', "$baseUrl/login", ['_token' => $csrf, 'login' => 'fadi.chehade', 'password' => 'admin@123'], $cookieJar);

$isManager = strpos($res['url'], '/dashboard') !== false;
if ($isManager) {
    echo "[OK] Logged in as Manager (fadi.chehade)\n";
} else {
    echo "[WARN] Manager login may have failed (landed at {$res['url']})\n";
}

$managerAllowed = ['/dashboard', '/leave/my', '/leave/request', '/notifications'];
$managerDenied  = ['/admin/users', '/admin/roles', '/settings', '/admin/leave/types'];

foreach ($managerAllowed as $route) {
    $res = http('GET', $baseUrl . $route, [], $cookieJar);
    $landedOnLogin = strpos($res['url'], '/login') !== false;
    $is403 = $res['code'] === 403;
    if (!$landedOnLogin && !$is403 && $res['code'] >= 200 && $res['code'] < 400) {
        $rolePass++;
        echo "\033[32m[PASS]\033[0m Manager CAN access $route\n";
    } else {
        $roleFail++;
        echo "\033[31m[FAIL]\033[0m Manager SHOULD access $route (HTTP {$res['code']})\n";
        $roleErrors[] = ['test' => "Manager allowed: $route", 'detail' => "HTTP {$res['code']}"];
    }
}

foreach ($managerDenied as $route) {
    $res = http('GET', $baseUrl . $route, [], $cookieJar);
    $is403 = $res['code'] === 403;
    $redirectedAway = strpos($res['url'], $route) === false;
    if ($is403 || $redirectedAway) {
        $rolePass++;
        echo "\033[32m[PASS]\033[0m Manager DENIED $route (HTTP {$res['code']})\n";
    } else {
        $roleFail++;
        echo "\033[31m[FAIL]\033[0m Manager should NOT access $route but got HTTP {$res['code']}\n";
        $roleErrors[] = ['test' => "Manager denied: $route", 'detail' => "HTTP {$res['code']} — access was granted"];
    }
}

// Test as Employee (shehryar.masoom) — logout Manager first
$res = http('GET', "$baseUrl/dashboard", [], $cookieJar);
$csrf = extractCsrf($res['body']);
$res = http('POST', "$baseUrl/logout", ['_token' => $csrf], $cookieJar);
$res = http('GET', "$baseUrl/login", [], $cookieJar);
$csrf = extractCsrf($res['body']);
$res = http('POST', "$baseUrl/login", ['_token' => $csrf, 'login' => 'shehryar.masoom', 'password' => 'admin@123'], $cookieJar);

$isEmployee = strpos($res['url'], '/dashboard') !== false;
if ($isEmployee) {
    echo "\n[OK] Logged in as Employee (shehryar.masoom)\n";
} else {
    echo "\n[WARN] Employee login may have failed (landed at {$res['url']})\n";
}

$employeeAllowed = ['/dashboard', '/leave/my', '/notifications'];
$employeeDenied  = ['/admin/users', '/admin/roles', '/settings', '/employees', '/reports', '/admin/leave/types'];

foreach ($employeeAllowed as $route) {
    $res = http('GET', $baseUrl . $route, [], $cookieJar);
    $landedOnLogin = strpos($res['url'], '/login') !== false;
    $is403 = $res['code'] === 403;
    if (!$landedOnLogin && !$is403 && $res['code'] >= 200 && $res['code'] < 400) {
        $rolePass++;
        echo "\033[32m[PASS]\033[0m Employee CAN access $route\n";
    } else {
        $roleFail++;
        echo "\033[31m[FAIL]\033[0m Employee SHOULD access $route (HTTP {$res['code']})\n";
        $roleErrors[] = ['test' => "Employee allowed: $route", 'detail' => "HTTP {$res['code']}"];
    }
}

foreach ($employeeDenied as $route) {
    $res = http('GET', $baseUrl . $route, [], $cookieJar);
    $is403 = $res['code'] === 403;
    $redirectedAway = strpos($res['url'], $route) === false;
    if ($is403 || $redirectedAway) {
        $rolePass++;
        echo "\033[32m[PASS]\033[0m Employee DENIED $route (HTTP {$res['code']})\n";
    } else {
        $roleFail++;
        echo "\033[31m[FAIL]\033[0m Employee should NOT access $route but got HTTP {$res['code']}\n";
        $roleErrors[] = ['test' => "Employee denied: $route", 'detail' => "HTTP {$res['code']} — access was granted"];
    }
}

echo "\n══════════════════════════════════════\n";
echo "  ROLE PASS: $rolePass   FAIL: $roleFail   TOTAL: " . ($rolePass + $roleFail) . "\n";
echo "══════════════════════════════════════\n";

if ($roleFail > 0) {
    echo "\nFailed role tests:\n";
    foreach ($roleErrors as $e) {
        echo "  ✗ {$e['test']}  →  {$e['detail']}\n";
    }
}

// ── Final Summary ───────────────────────────────────────────────────

$totalPass = $pass + $postPass + $rolePass;
$totalFail = $fail + $postFail + $roleFail;

echo "\n\n██████████████████████████████████████\n";
echo "  OVERALL: PASS $totalPass / " . ($totalPass + $totalFail) . "   FAIL: $totalFail\n";
echo "██████████████████████████████████████\n";

// Cleanup
@unlink($cookieJar);
echo "\nDone.\n";

