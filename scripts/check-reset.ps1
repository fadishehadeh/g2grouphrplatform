param(
    [string]$BaseUrl = 'http://localhost/HR%20System/public',
    [string]$Email = 'employee1@acmehr.local',
    [string]$Username = 'employee1',
    [string]$OriginalPassword = 'Admin@123',
    [string]$ResetPassword = 'TempPass123!',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$MySqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$DatabaseName = 'hr_system'
)

$ErrorActionPreference = 'Stop'
$resetApplied = $false
$restoreComplete = $false

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

function Get-Location([string]$HeadersText) {
    $match = [regex]::Match($HeadersText, '(?im)^Location:\s*(.+)$')
    if ($match.Success) { return $match.Groups[1].Value.Trim() }
    return ''
}

function Invoke-Get([string]$CookieFile, [string]$Url) {
    $headersFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.headers')
    $bodyFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.body')
    try {
        & curl.exe -s -D $headersFile -o $bodyFile -b $CookieFile -c $CookieFile $Url | Out-Null
        $headers = Get-Content $headersFile -Raw
        [pscustomobject]@{
            Status = Get-HttpStatus $headers
            Location = Get-Location $headers
            Body = Get-Content $bodyFile -Raw
        }
    }
    finally {
        Remove-Item $headersFile, $bodyFile -ErrorAction SilentlyContinue
    }
}

function Invoke-Post([string]$CookieFile, [string]$Url, [string[]]$FormArgs) {
    $headersFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.headers')
    $bodyFile = Join-Path $env:TEMP ([guid]::NewGuid().ToString() + '.body')
    try {
        & curl.exe -s -D $headersFile -o $bodyFile -b $CookieFile -c $CookieFile -X POST $Url @FormArgs | Out-Null
        $headers = Get-Content $headersFile -Raw
        [pscustomobject]@{
            Status = Get-HttpStatus $headers
            Location = Get-Location $headers
            Body = Get-Content $bodyFile -Raw
        }
    }
    finally {
        Remove-Item $headersFile, $bodyFile -ErrorAction SilentlyContinue
    }
}

function Test-PasswordPolicy([string]$Password) {
    return $Password.Length -ge 10 -and $Password -cmatch '[A-Z]' -and $Password -cmatch '[a-z]' -and $Password -match '[0-9]' -and $Password -match '[^A-Za-z0-9]'
}

function Get-ResetToken([string]$CookieFile) {
    $forgotPage = Invoke-Get $CookieFile "$BaseUrl/forgot-password"
    if ($forgotPage.Status -ne 200) { throw "Forgot-password page returned [$($forgotPage.Status)]." }

    $csrf = Get-CsrfToken $forgotPage.Body
    $forgotPost = Invoke-Post $CookieFile "$BaseUrl/forgot-password" @('--data-urlencode', "_token=$csrf", '--data-urlencode', "email=$Email")
    if ($forgotPost.Status -ne 302) { throw "Forgot-password POST returned [$($forgotPost.Status)]." }
    if ($forgotPost.Location -notmatch '/forgot-password$') { throw "Forgot-password POST redirected to [$($forgotPost.Location)]." }

    $previewPage = Invoke-Get $CookieFile "$BaseUrl/forgot-password"
    if ($previewPage.Status -ne 200) { throw "Forgot-password preview page returned [$($previewPage.Status)]." }

    $match = [regex]::Match($previewPage.Body, 'href="[^"]*/reset-password/([0-9a-fA-F]+)"')
    if (-not $match.Success) { throw 'Reset preview link not found.' }
    return $match.Groups[1].Value
}

function Reset-Password([string]$CookieFile, [string]$Token, [string]$NewPassword) {
    $resetPage = Invoke-Get $CookieFile "$BaseUrl/reset-password/$Token"
    if ($resetPage.Status -ne 200) { throw "Reset page returned [$($resetPage.Status)]." }

    $csrf = Get-CsrfToken $resetPage.Body
    $resetPost = Invoke-Post $CookieFile "$BaseUrl/reset-password" @('--data-urlencode', "_token=$csrf", '--data-urlencode', "token=$Token", '--data-urlencode', "password=$NewPassword", '--data-urlencode', "password_confirmation=$NewPassword")
    if ($resetPost.Status -ne 302) { throw "Reset-password POST returned [$($resetPost.Status)]." }
    if ($resetPost.Location -notmatch '/login$') { throw "Reset-password POST redirected to [$($resetPost.Location)] instead of login." }
}

function Assert-Login([string]$CookieFile, [string]$PasswordToTest, [string]$Label) {
    Remove-Item $CookieFile -ErrorAction SilentlyContinue

    $loginPage = Invoke-Get $CookieFile "$BaseUrl/login"
    if ($loginPage.Status -ne 200) { throw "$Label login page returned [$($loginPage.Status)]." }

    $csrf = Get-CsrfToken $loginPage.Body
    $loginPost = Invoke-Post $CookieFile "$BaseUrl/login" @('--data-urlencode', "_token=$csrf", '--data-urlencode', "login=$Username", '--data-urlencode', "password=$PasswordToTest")
    if ($loginPost.Status -ne 302) { throw "$Label login POST returned [$($loginPost.Status)]." }

    $dashboard = Invoke-Get $CookieFile "$BaseUrl/dashboard"
    if ($dashboard.Status -ne 200) { throw "$Label dashboard returned [$($dashboard.Status)]." }
}

function Restore-OriginalPasswordDirect() {
    $escapedPassword = $OriginalPassword.Replace('\', '\\').Replace("'", "\\'")
    $hash = & $PhpPath -r "echo password_hash('$escapedPassword', PASSWORD_DEFAULT);"
    & $MySqlPath -u root -e "USE $DatabaseName; UPDATE users SET password_hash='$hash', must_change_password=0, last_password_change_at=NOW() WHERE username='$Username'; UPDATE password_resets SET used_at=NOW() WHERE user_id=(SELECT id FROM users WHERE username='$Username') AND used_at IS NULL;"
}

try {
    $resetCookie = Join-Path $env:TEMP 'hr-reset-check.cookies.txt'
    $verifyCookie = Join-Path $env:TEMP 'hr-reset-verify.cookies.txt'
    $restoreCookie = Join-Path $env:TEMP 'hr-reset-restore.cookies.txt'
    Remove-Item $resetCookie, $verifyCookie, $restoreCookie -ErrorAction SilentlyContinue

    $token = Get-ResetToken $resetCookie
    Reset-Password $resetCookie $token $ResetPassword
    $resetApplied = $true
    Assert-Login $verifyCookie $ResetPassword 'Reset password'

    if (Test-PasswordPolicy $OriginalPassword) {
        $restoreToken = Get-ResetToken $restoreCookie
        Reset-Password $restoreCookie $restoreToken $OriginalPassword
        Assert-Login $verifyCookie $OriginalPassword 'Restored password'
        $restoreComplete = $true
        Write-Output 'RESET_FLOW_E2E_OK'
        Write-Output 'RESTORE_METHOD=app'
    }
    else {
        Restore-OriginalPasswordDirect
        Assert-Login $verifyCookie $OriginalPassword 'DB-restored original password'
        $restoreComplete = $true
        Write-Output 'RESET_FLOW_E2E_OK'
        Write-Output 'RESTORE_METHOD=db_fallback_original_password_not_policy_compliant'
    }
}
finally {
    Remove-Item $resetCookie, $verifyCookie, $restoreCookie -ErrorAction SilentlyContinue
    if ($resetApplied -and -not $restoreComplete) {
        Restore-OriginalPasswordDirect
        Write-Output 'RESET_FLOW_E2E_RESTORED_BY_FALLBACK'
    }
}