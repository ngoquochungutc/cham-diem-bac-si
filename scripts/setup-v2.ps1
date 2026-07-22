# ============================================================
#  setup-v2.ps1  –  XAMPP + MySQL  (Hệ thống v2)
#  Chạy: Right-click SETUP.bat → Run as administrator
# ============================================================
$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
chcp 65001 | Out-Null

function Info  { param($m) Write-Host "  [INFO]  $m" -ForegroundColor Cyan }
function OK    { param($m) Write-Host "  [ OK ]  $m" -ForegroundColor Green }
function Warn  { param($m) Write-Host "  [WARN]  $m" -ForegroundColor Yellow }
function Step  { param($m) Write-Host "`n  --- $m ---" -ForegroundColor Blue }
function Fail  { param($m) Write-Host "  [FAIL]  $m" -ForegroundColor Red; Read-Host "Nhan Enter de thoat"; exit 1 }

Clear-Host
Write-Host ""
Write-Host "  ================================================" -ForegroundColor Blue
Write-Host "   He Thong Danh Gia Nhan Vien v2               " -ForegroundColor Blue
Write-Host "   XAMPP + MySQL – Setup Script                  " -ForegroundColor Blue
Write-Host "  ================================================" -ForegroundColor Blue

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir   = Split-Path $scriptDir

# --- Doc .env ---
$envPath = Join-Path $rootDir ".env"
if (-not (Test-Path $envPath)) { Fail ".env khong tim thay tai: $envPath" }

$cfg = @{}
Get-Content $envPath | ForEach-Object {
    $l = $_.Trim()
    if ($l -and -not $l.StartsWith("#")) {
        $p = $l -split "=", 2
        if ($p.Length -eq 2) { $cfg[$p[0].Trim()] = ($p[1] -split "#")[0].Trim() }
    }
}
$DB_NAME  = if ($cfg["DB_NAME"])  { $cfg["DB_NAME"]  } else { "evaldb" }
$DB_USER  = if ($cfg["DB_USER"])  { $cfg["DB_USER"]  } else { "root" }
$DB_PASS  = if ($cfg["DB_PASS"])  { $cfg["DB_PASS"]  } else { "" }
$ADMIN_PW = if ($cfg["ADMIN_PASSWORD"]) { $cfg["ADMIN_PASSWORD"] } else { "admin@2024" }
Info "DB: $DB_NAME | User: $DB_USER"

# --- Tim XAMPP ---
Step "Tim XAMPP"
$xamppPaths = @("C:\xampp","D:\xampp","E:\xampp")
$xamppDir = $null
foreach ($p in $xamppPaths) { if (Test-Path "$p\htdocs") { $xamppDir=$p; break } }
if (-not $xamppDir) { Fail "Khong tim thay XAMPP! Hay cai vao C:\xampp" }
OK "XAMPP tai: $xamppDir"

# --- Tim mysql.exe ---
Step "Tim MySQL"
$mysqlBin = $null
$candidates = @("$xamppDir\mysql\bin\mysql.exe","$xamppDir\mariadb\bin\mysql.exe")
foreach ($c in $candidates) { if (Test-Path $c) { $mysqlBin=$c; break } }
if (-not $mysqlBin) { Fail "Khong tim thay mysql.exe trong XAMPP!" }
OK "mysql: $mysqlBin"

# --- Nhap mat khau root ---
Write-Host ""
Write-Host "  Nhap mat khau MySQL root (XAMPP mac dinh: de trong, nhan Enter):" -ForegroundColor Yellow
$rootPwSecure = Read-Host "  Mat khau root" -AsSecureString
$rootPw = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPwSecure))

$mysqlArgs = @("-h","127.0.0.1","-P","3306","-u","root")
if ($rootPw -ne "") { $mysqlArgs += "-p$rootPw" }

$test = echo "SELECT 1;" | & $mysqlBin @mysqlArgs 2>&1
if ($test -notmatch "1") { Fail "Ket noi MySQL that bai! Kiem tra XAMPP dang chay va mat khau root." }
OK "Ket noi MySQL thanh cong."

# --- Tao Database & User ---
Step "Tao database va user"
$sqlSetup = @"
CREATE DATABASE IF NOT EXISTS ``$DB_NAME`` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON ``$DB_NAME``.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON ``$DB_NAME``.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
$sqlSetup | & $mysqlBin @mysqlArgs 2>&1 | Out-Null
OK "Da tao database '$DB_NAME' va user '$DB_USER'."

# --- Chay Schema ---
Step "Khoi tao bang du lieu"
$schemaFile = Join-Path $rootDir "sql\schema.sql"
$dbArgs = $mysqlArgs + @("$DB_NAME")
Get-Content $schemaFile -Raw | & $mysqlBin @dbArgs 2>&1 | Out-Null
OK "Da tao bang: employees, emp_scores, council_scores, submissions."

# --- Copy source vao htdocs ---
Step "Copy source vao htdocs"
$destDir = "$xamppDir\htdocs\eval-system"
if (Test-Path $destDir) {
    Info "Xoa thu muc cu $destDir..."
    Remove-Item -Recurse -Force $destDir
}
New-Item -ItemType Directory -Path $destDir | Out-Null
foreach ($f in @("public","src","frontend","sql","scripts")) {
    if (Test-Path "$rootDir\$f") { Copy-Item -Recurse "$rootDir\$f" "$destDir\$f" }
}
Copy-Item "$rootDir\.env"      "$destDir\.env"
Copy-Item "$rootDir\.htaccess" "$destDir\.htaccess"
Copy-Item "$rootDir\index.php" "$destDir\index.php"
OK "Da copy vao $destDir"

# --- Bat mod_rewrite ---
Step "Kich hoat mod_rewrite Apache"
$httpdConf = "$xamppDir\apache\conf\httpd.conf"
if (Test-Path $httpdConf) {
    $conf = Get-Content $httpdConf -Raw
    $conf = $conf -replace "#LoadModule rewrite_module", "LoadModule rewrite_module"
    $conf = $conf -replace "(AllowOverride\s+)None", '${1}All'
    Set-Content $httpdConf $conf -Encoding UTF8
    OK "mod_rewrite bat, AllowOverride All."
} else { Warn "Khong tim thay httpd.conf. Hay bat mod_rewrite thu cong." }

# --- Bat pdo_mysql ---
Step "Bat PHP extension pdo_mysql"
$phpIni = "$xamppDir\php\php.ini"
if (Test-Path $phpIni) {
    $ini = Get-Content $phpIni -Raw
    $ini = $ini -replace ";extension=pdo_mysql", "extension=pdo_mysql"
    $ini = $ini -replace ";extension=zip",       "extension=zip"
    # Xoa dong trung lap mysqli neu co
    $lines = $ini -split "`n"
    $seen = @{}; $clean = @()
    foreach ($line in $lines) {
        $key = $line.Trim()
        if ($key -match "^extension=mysqli$") {
            if (-not $seen[$key]) { $clean += $line; $seen[$key] = $true }
        } else { $clean += $line }
    }
    $ini = $clean -join "`n"
    Set-Content $phpIni $ini -Encoding UTF8
    OK "Da bat pdo_mysql, zip trong php.ini."
}

# --- Hoan thanh ---
Write-Host ""
Write-Host "  ================================================" -ForegroundColor Green
Write-Host "   Thiet lap hoan tat!  OK                       " -ForegroundColor Green
Write-Host "  ================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Buoc tiep theo:" -ForegroundColor Yellow
Write-Host "  1. XAMPP Control Panel → STOP Apache → START Apache" -ForegroundColor White
Write-Host "  2. Mo trinh duyet: http://localhost/eval-system"     -ForegroundColor Cyan
Write-Host "  3. Dang nhap Admin: admin / $ADMIN_PW"               -ForegroundColor White
Write-Host ""
Write-Host "  Tai khoan nhan vien:" -ForegroundColor Yellow
Write-Host "  - Mat khau mac dinh = Ma NV (VD: NV001)" -ForegroundColor White
Write-Host "  - Se yeu cau doi mat khau khi dang nhap lan dau" -ForegroundColor White
Write-Host ""
Read-Host "  Nhan Enter de dong"
