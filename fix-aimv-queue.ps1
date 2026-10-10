# ============================================================================
#  AI Media Verifier — تشخيص وإصلاح الطلبات العالقة على «في قائمة الانتظار 5%»
#  ضع هذا الملف بجوار start-aimv.ps1 ثم شغّله:
#      powershell -ExecutionPolicy Bypass -File fix-aimv-queue.ps1
#  للتشخيص فقط دون تشغيل أي شيء:  ... -File fix-aimv-queue.ps1 -NoFix
# ============================================================================
param([switch]$NoFix)

$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Web  = Join-Path $Root 'web'
$Ai   = Join-Path $Root 'ai-service'
$Logs = Join-Path $Root 'logs'

function Good($t) { Write-Host "  [✓] $t" -ForegroundColor Green }
function Bad($t)  { Write-Host "  [✗] $t" -ForegroundColor Red }
function Info($t) { Write-Host "      $t" -ForegroundColor Gray }
function Head($t) { Write-Host "`n$t" -ForegroundColor Cyan }

if (-not (Test-Path (Join-Path $Web 'artisan'))) {
    Bad "لم أجد web\artisan. ضع هذا الملف في مجلد المشروع بجوار start-aimv.ps1"
    exit 1
}
$Php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $Php) { $Php = 'C:\xampp\php\php.exe' }
if (-not (Test-Path $Php)) { Bad "لم أجد php.exe (جرّبت PATH و C:\xampp\php)"; exit 1 }

function Read-EnvValue([string]$File, [string]$Key) {
    if (-not (Test-Path $File)) { return $null }
    $line = Get-Content $File -Encoding UTF8 | Where-Object { $_ -match "^\s*$Key\s*=" } | Select-Object -Last 1
    if (-not $line) { return $null }
    return ($line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

$problems = 0

# ---------------------------------------------------------------- 1) قاعدة البيانات
Head '1) قاعدة البيانات (المنفذ 3306)'
if (Get-NetTCPConnection -State Listen -LocalPort 3306 -ErrorAction SilentlyContinue) {
    Good 'MariaDB/MySQL تعمل'
} else {
    Bad 'قاعدة البيانات متوقفة: شغّل MySQL من لوحة XAMPP'
    $problems++
}

# ---------------------------------------------------------------- 2) خدمة التحليل
Head '2) خدمة التحليل (http://127.0.0.1:8001)'
$aiUrl = Read-EnvValue (Join-Path $Web '.env') 'AI_SERVICE_URL'
if (-not $aiUrl) { $aiUrl = 'http://127.0.0.1:8001' }
try {
    $health = Invoke-RestMethod -Uri ($aiUrl.TrimEnd('/') + '/health') -TimeoutSec 8
    Good "الخدمة تعمل على $aiUrl"
    foreach ($m in @($health.models)) {
        if ($m.loaded) { Info "النموذج $($m.key): محمّل" }
        elseif ($m.error) { Bad "النموذج $($m.key): فشل التحميل — $($m.error)"; $problems++ }
        else { Info "النموذج $($m.key): غير محمّل بعد (سيُحمّل عند أول طلب ويؤخره)" }
    }
} catch {
    Bad "خدمة التحليل لا تستجيب على $aiUrl — لهذا يعود الطلب إلى 5% ويعيد المحاولة"
    Info 'شغّلها بـ start-aimv.bat، وأول تشغيل قد يأخذ دقائق لتنزيل النماذج'
    $log = Join-Path $Logs 'ai-service.log'
    if (Test-Path $log) { Info 'آخر أسطر logs\ai-service.log:'; Get-Content $log -Tail 12 | ForEach-Object { Info "  $_" } }
    $problems++
}

# ---------------------------------------------------------------- 3) تطابق المفتاح السري
Head '3) المفتاح السري AI_SERVICE_KEY'
$kWeb = Read-EnvValue (Join-Path $Web '.env') 'AI_SERVICE_KEY'
$kAi  = Read-EnvValue (Join-Path $Ai '.env') 'AI_SERVICE_KEY'
if (-not $kWeb -or -not $kAi) { Bad 'المفتاح فارغ في web\.env أو ai-service\.env'; $problems++ }
elseif ($kWeb -ne $kAi)       { Bad 'المفتاح مختلف بين web\.env و ai-service\.env (يجب أن يتطابق)'; $problems++ }
else                          { Good 'المفتاح متطابق في الملفين' }

$preload = Read-EnvValue (Join-Path $Ai '.env') 'AI_PRELOAD'
if ($preload -ne '1') {
    Info 'للسرعة: ضع AI_PRELOAD=1 في ai-service\.env ثم أعد تشغيل الخدمة، فتُحمَّل النماذج قبل أول صورة'
}

# ---------------------------------------------------------------- 4) عامل الطابور
Head '4) عامل الطابور (هو من ينفّذ التحليل)'
$workers = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -match 'artisan.+queue:(work|listen)' })
$good = @($workers | Where-Object { $_.CommandLine -match '--queue=[^ ]*analysis' })
if ($good.Count -gt 0) {
    Good "عامل الطابور يعمل ويستمع إلى طابور analysis ($($good.Count))"
} elseif ($workers.Count -gt 0) {
    Bad 'عامل الطابور يعمل لكنه لا يستمع إلى طابور analysis، لذلك لا يلتقط طلبات التحليل أبداً'
    Info 'هذا هو سبب التوقف على 5% عند تشغيله بـ «php artisan queue:work» بدون --queue=analysis,default'
    $problems++
} else {
    Bad 'لا يوجد عامل طابور يعمل، فلا أحد ينفّذ طلبات التحليل'
    $problems++
}

# ---------------------------------------------------------------- 5) الطلبات المنتظرة
Head '5) المهام المنتظرة في الطابور'
Push-Location $Web
& $Php artisan queue:monitor 'database:analysis,database:default' 2>&1 | ForEach-Object { Info $_ }
Pop-Location

# ---------------------------------------------------------------- 6) الإصلاح
if ($NoFix) { Head "انتهى التشخيص: $problems مشكلة."; exit 0 }

if ($good.Count -eq 0) {
    Head '6) الإصلاح: تشغيل عامل الطابور الصحيح في نافذة جديدة'
    $cmd = "Set-Location '$Web'; Write-Host 'عامل طابور AIMV — لا تغلق هذه النافذة' -ForegroundColor Cyan; " +
           "& '$Php' -d memory_limit=512M artisan queue:work --queue=analysis,default --timeout=900 --sleep=1"
    Start-Process powershell -ArgumentList @('-NoExit', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $cmd) | Out-Null
    Good 'بدأ عامل الطابور. الطلبات العالقة ستُنفَّذ الآن تلقائياً'

    Start-Sleep 10
    Head 'الطابور بعد 10 ثوانٍ:'
    Push-Location $Web
    & $Php artisan queue:monitor 'database:analysis,database:default' 2>&1 | ForEach-Object { Info $_ }
    Pop-Location
} else {
    Head '6) عامل الطابور سليم.'
    if ($problems -gt 0) { Info 'المشكلة في البنود الحمراء أعلاه (غالباً خدمة التحليل). أصلحها، وسيعيد الطابور المحاولة تلقائياً.' }
}

Write-Host "`nحدّث صفحة الطلب في المتصفح بعد قليل." -ForegroundColor Cyan
