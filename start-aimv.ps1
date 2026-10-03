# ============================================================================
#  AI Media Verifier — تشغيل كل المكوّنات ومراقبتها
#  1) قاعدة البيانات MariaDB (XAMPP) إن لم تكن تعمل
#  2) خدمة الذكاء الاصطناعي FastAPI على المنفذ 8001 (تحمّل النماذج عند البدء)
#  3) موقع Laravel على المنفذ 8010
#  4) عامل الطابور الذي ينفّذ التحليلات
#  كل مكوّن (عدا قاعدة البيانات) يعمل داخل «مشرف» مخفي يعيد تشغيله تلقائيًا إذا توقف.
#  الاستخدام:  powershell -ExecutionPolicy Bypass -File start-aimv.ps1
#  الإيقاف:    powershell -ExecutionPolicy Bypass -File stop-aimv.ps1
# ============================================================================
param([switch]$Quiet)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Web  = Join-Path $Root 'web'
$Ai   = Join-Path $Root 'ai-service'
$Logs = Join-Path $Root 'logs'
New-Item -ItemType Directory -Force $Logs | Out-Null

$Php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $Php) { $Php = 'C:\xampp\php\php.exe' }
$Py = Join-Path $Ai '.venv\Scripts\python.exe'
$MysqlExe = 'C:\xampp\mysql\bin\mysqld.exe'
$MysqlIni = 'C:\xampp\mysql\bin\my.ini'

function Say($text, $color = 'Gray') { if (-not $Quiet) { Write-Host $text -ForegroundColor $color } }

function Test-Port([int]$Port) {
    [bool](Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
}

function Test-Supervisor([string]$Name) {
    [bool](Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
        Where-Object { $_.CommandLine -like "*AIMV-SUPERVISOR:$Name*" })
}

# يشغّل الأمر داخل حلقة PowerShell مخفية تعيد تشغيله بعد 5 ثوانٍ إذا توقف لأي سبب
function Start-Supervised([string]$Name, [string]$Command, [int]$Port = 0) {
    if (Test-Supervisor $Name) { Say "  [$Name] يعمل مسبقًا تحت المراقبة" 'DarkGray'; return }
    if ($Port -and (Test-Port $Port)) { Say "  [$Name] المنفذ $Port مستخدم مسبقًا — أوقفه أولًا بـ stop-aimv.ps1 ليعمل تحت المراقبة" 'Yellow'; return }
    $log = Join-Path $Logs "$Name.log"
    $loop = "# AIMV-SUPERVISOR:$Name`n" +
            "Set-Location '$Root'`n" +
            "while (`$true) { & { $Command } *>> '$log'; Add-Content '$log' ('[' + (Get-Date -Format s) + '] stopped, restarting in 5s'); Start-Sleep 5 }"
    Start-Process powershell -WindowStyle Hidden -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $loop) | Out-Null
    Say "  [$Name] بدأ التشغيل (السجل: logs\$Name.log)" 'Green'
}

function Wait-Http([string]$Url, [int]$Seconds) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $deadline) {
        try { Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5 | Out-Null; return $true } catch { Start-Sleep 3 }
    }
    return $false
}

Say "`nتشغيل AI Media Verifier..." 'Cyan'

# 1) قاعدة البيانات: لا نراقبها لأنها مشتركة مع مشاريع أخرى في XAMPP، نشغّلها فقط إن كانت متوقفة
if (Test-Port 3306) {
    Say '  [mariadb] تعمل مسبقًا' 'DarkGray'
} elseif (Test-Path $MysqlExe) {
    Start-Process $MysqlExe -ArgumentList @("--defaults-file=$MysqlIni", '--standalone') -WindowStyle Hidden | Out-Null
    Say '  [mariadb] بدأ التشغيل' 'Green'
} else {
    Say "  [mariadb] لم يُعثر على $MysqlExe — شغّل MySQL من لوحة XAMPP" 'Yellow'
}

# 2) خدمة الذكاء الاصطناعي
Start-Supervised 'ai-service' "& '$Py' -m uvicorn app.main:app --app-dir '$Ai' --host 127.0.0.1 --port 8001" 8001

# 3) موقع Laravel
Start-Supervised 'web' "& '$Php' -d upload_max_filesize=128M -d post_max_size=130M -d memory_limit=512M '$Web\artisan' serve --host=127.0.0.1 --port=8010" 8010

# 4) عامل الطابور (ينتظر قاعدة البيانات قبل البدء حتى لا يفشل فورًا)
Start-Supervised 'queue' "while (-not (Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction SilentlyContinue)) { Start-Sleep 3 }; & '$Php' -d memory_limit=512M '$Web\artisan' queue:work --queue=analysis,default --timeout=900 --sleep=2 --max-time=3600"

# التحقق
Say "`nانتظار جاهزية الخدمات (تحميل النماذج قد يستغرق دقيقة أو دقيقتين في أول مرة)..." 'Cyan'
$aiOk  = Wait-Http 'http://127.0.0.1:8001/health' 240
$webOk = Wait-Http 'http://127.0.0.1:8010/up' 60
$dbOk  = Test-Port 3306

Say ''
Say ("  قاعدة البيانات   : " + ($(if ($dbOk) { 'تعمل' } else { 'متوقفة' })))  $(if ($dbOk) { 'Green' } else { 'Red' })
Say ("  خدمة التحليل    : " + ($(if ($aiOk) { 'تعمل  http://127.0.0.1:8001' } else { 'لم تجهز بعد — راجع logs\ai-service.log' }))) $(if ($aiOk) { 'Green' } else { 'Yellow' })
Say ("  الموقع          : " + ($(if ($webOk) { 'يعمل  http://127.0.0.1:8010' } else { 'لم يجهز بعد — راجع logs\web.log' }))) $(if ($webOk) { 'Green' } else { 'Yellow' })
Say ''
