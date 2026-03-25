param(
    [string]$BaseUrl = 'http://localhost/HR%20System/public',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$AdminUser = 'admin',
    [string]$ManagerUser = '',
    [string]$EmployeeUser = '',
    [string]$Password = 'admin@123',
    [int]$ManagerEmployeeId = 0,
    [int]$EmployeeId = 0,
    [switch]$SkipLint,
    [switch]$SkipRoleSmoke
)

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot

function Get-CsrfToken([string]$Html) {
    $match = [regex]::Match($Html, 'name="_token"\s+value="([^"]+)"')
    if (-not $match.Success) { throw 'CSRF token not found.' }
    return $match.Groups[1].Value
}

function Get-HttpStatus([string]$HeadersText) {
    $matches = [regex]::Matches($HeadersText, 'HTTP/[0-9.]+\s+([0-9]{3})')
    if ($matches.Count -eq 0) { throw 'No HTTP status found.' }
    return [int]$matches[$matches.Count - 1].Groups[1].Value
}

function Invoke-Login([string]$User, [string]$UserPassword, [string]$CookieFile) {
    $loginHtml = & curl.exe -s -c $CookieFile "$BaseUrl/login"
    $token = Get-CsrfToken $loginHtml
    $headersFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.headers')
    $bodyFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.body')

    try {
        & curl.exe -s -D $headersFile -o $bodyFile -b $CookieFile -c $CookieFile -X POST "$BaseUrl/login" `
            --data-urlencode "_token=$token" `
            --data-urlencode "login=$User" `
            --data-urlencode "password=$UserPassword" | Out-Null

        $status = Get-HttpStatus (Get-Content $headersFile -Raw)
        if ($status -ne 302) { throw "Login for [$User] returned status [$status]." }
    }
    finally {
        Remove-Item $headersFile, $bodyFile -ErrorAction SilentlyContinue
    }
}

function Invoke-Get([string]$CookieFile, [string]$Path) {
    $headersFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.headers')
    $bodyFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.body')

    try {
        & curl.exe -s -D $headersFile -o $bodyFile -b $CookieFile "$BaseUrl$Path" | Out-Null
        return [pscustomobject]@{
            Path = $Path
            Status = Get-HttpStatus (Get-Content $headersFile -Raw)
            Body = Get-Content $bodyFile -Raw
        }
    }
    finally {
        Remove-Item $headersFile, $bodyFile -ErrorAction SilentlyContinue
    }
}

function Assert-Status($Result, [int]$Expected, [string]$Label) {
    if ($Result.Status -ne $Expected) { throw "$Label expected status [$Expected] but got [$($Result.Status)] for [$($Result.Path)]." }
}

function Assert-Denied($Result, [string]$Label) {
    if (@(302, 403) -notcontains $Result.Status) { throw "$Label expected denied status [302/403] but got [$($Result.Status)] for [$($Result.Path)]." }
}

function Assert-Contains([string]$Body, [string]$Text, [string]$Label) {
    if ($Body -notmatch [regex]::Escape($Text)) { throw "$Label missing expected text [$Text]." }
}

function Invoke-PhpLint() {
    Write-Host '== PHP lint ==' -ForegroundColor Cyan
    $roots = @('app', 'config', 'public', 'routes') | ForEach-Object { Join-Path $RepoRoot $_ }
    $files = Get-ChildItem -Path $roots -Recurse -Filter '*.php' -File | Sort-Object FullName
    foreach ($file in $files) {
        & $PhpPath -l $file.FullName | Out-Host
        if ($LASTEXITCODE -ne 0) { throw "PHP lint failed for [$($file.FullName)]." }
    }
}

function Test-EmployeeRole([string]$CookieFile) {
    Write-Host '== Employee role smoke ==' -ForegroundColor Cyan
    Invoke-Login $EmployeeUser $Password $CookieFile
    $dashboard = Invoke-Get $CookieFile '/dashboard'
    Assert-Status $dashboard 200 'Employee dashboard'
    foreach ($text in @('My Profile', 'My Leave', 'Request Leave', 'My Documents', 'Announcements', 'Notifications')) {
        Assert-Contains $dashboard.Body $text 'Employee dashboard'
    }

    foreach ($path in @('/dashboard', "/employees/$EmployeeId", '/leave/my', '/leave/balances', '/leave/requests', '/leave/calendar', '/leave/request', "/employees/$EmployeeId/documents/upload", '/announcements', '/notifications')) {
        Assert-Status (Invoke-Get $CookieFile $path) 200 "Employee page"
    }

    foreach ($path in @('/leave/approvals', '/documents', '/admin/users', '/settings', '/reports', "/employees/$ManagerEmployeeId")) {
        Assert-Denied (Invoke-Get $CookieFile $path) 'Employee permission boundary'
    }
}

function Test-ManagerRole([string]$CookieFile) {
    Write-Host '== Manager role smoke ==' -ForegroundColor Cyan
    Invoke-Login $ManagerUser $Password $CookieFile
    $dashboard = Invoke-Get $CookieFile '/dashboard'
    Assert-Status $dashboard 200 'Manager dashboard'
    foreach ($text in @('Approvals', 'My Profile', 'My Leave', 'Request Leave', 'My Documents', 'Reports', 'Announcements', 'Notifications')) {
        Assert-Contains $dashboard.Body $text 'Manager dashboard'
    }

    foreach ($path in @('/dashboard', "/employees/$ManagerEmployeeId", '/leave/my', '/leave/balances', '/leave/requests', '/leave/calendar', '/leave/request', '/leave/approvals', "/employees/$ManagerEmployeeId/documents/upload", '/announcements', '/notifications', '/reports', '/reports/headcount')) {
        Assert-Status (Invoke-Get $CookieFile $path) 200 "Manager page"
    }

    foreach ($path in @('/documents', '/admin/users', '/settings', "/employees/$EmployeeId")) {
        Assert-Denied (Invoke-Get $CookieFile $path) 'Manager permission boundary'
    }
}

try {
    if (-not $SkipLint) { Invoke-PhpLint }

    if (-not $SkipRoleSmoke -and $EmployeeUser -ne '' -and $ManagerUser -ne '') {
        $employeeCookie = Join-Path $env:TEMP 'hr-role-smoke-employee.cookies.txt'
        $managerCookie = Join-Path $env:TEMP 'hr-role-smoke-manager.cookies.txt'
        Remove-Item $employeeCookie, $managerCookie -ErrorAction SilentlyContinue
        try {
            Test-EmployeeRole $employeeCookie
            Test-ManagerRole $managerCookie
        }
        finally {
            Remove-Item $employeeCookie, $managerCookie -ErrorAction SilentlyContinue
        }
    } else {
        Write-Host '== Employee role smoke ==' -ForegroundColor Cyan
        Write-Host '== Manager role smoke ==' -ForegroundColor Cyan
    }

    Write-Host 'CHECK_HARNESS_OK' -ForegroundColor Green
}
catch {
    Write-Error $_
    exit 1
}