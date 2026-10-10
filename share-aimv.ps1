# ============================================================================
#  AI Media Verifier — رابط عام للموقع تفتحه من أي مكان (جوال، جهاز آخر، أي دولة)
#  الاستخدام:  powershell -ExecutionPolicy Bypass -File share-aimv.ps1
#  الإيقاف:    Ctrl+C أو أغلق النافذة (يتوقف الرابط، ويبقى الموقع يعمل على جهازك)
#
#  يعمل عبر Cloudflare Tunnel المجاني بدون حساب:
#  - الرابط يعمل ما دام جهازك مشغّلًا وهذه النافذة مفتوحة.
#  - يتغيّر الرابط في كل مرة تشغّل فيها هذا الملف.
# ============================================================================
$ErrorActionPreference = 'Stop'
$Root  = Split-Path -Parent $MyInvocation.MyCommand.Path
$Web   = Join-Path $Root 'web'
$Tools = Join-Path $Root 'tools'
$Logs  = Join-Path $Root 'logs'
New-Item -ItemType Directory -Force $Tools, $Logs | Out-Null

$Php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $Php) { $Php = 'C:\xampp\php\php.exe' }

Write-Host "`nإنشاء رابط عام لـ AI Media Verifier..." -ForegroundColor Cyan

# 1) تشغيل الموقع (يشغّل قاعدة البيانات أيضًا) إن لم يكن يعمل
Write-Host "`n1) الموقع" -ForegroundColor Cyan
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8010 -ErrorAction SilentlyContinue)) {
    & (Join-Path $Root 'start-aimv.ps1') -Quiet
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8010 -ErrorAction SilentlyContinue)) {
    Write-Host 'الموقع لا يعمل على المنفذ 8010. شغّل start-aimv.bat وراجع logs\web.log' -ForegroundColor Red; exit 1
}
Write-Host '  الموقع يعمل على http://127.0.0.1:8010' -ForegroundColor Green

# 2) فحص الأمان قبل النشر: يوقف وضع التصحيح ويغيّر كلمات مرور الحسابات التجريبية المنشورة
Write-Host "`n2) فحص الأمان" -ForegroundColor Cyan
Push-Location $Web
& $Php artisan aimv:public-check --fix
$ok = $LASTEXITCODE -eq 0
Pop-Location
if (-not $ok) { Write-Host 'لن يُنشأ الرابط حتى تُصلح المشاكل أعلاه.' -ForegroundColor Red; exit 1 }

# 3) cloudflared: يُنزَّل مرة واحدة إلى tools\ (برنامج Cloudflare الرسمي)
$Cf = (Get-Command cloudflared -ErrorAction SilentlyContinue).Source
if (-not $Cf) {
    $Cf = Join-Path $Tools 'cloudflared.exe'
    if (-not (Test-Path $Cf)) {
        Write-Host "`n3) تنزيل cloudflared (مرة واحدة فقط)..." -ForegroundColor Cyan
        $arch = if ([Environment]::Is64BitOperatingSystem) { 'amd64' } else { '386' }
        Invoke-WebRequest -UseBasicParsing -OutFile $Cf `
            -Uri "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-$arch.exe"
    }
}

# 4) فتح النفق وانتظار الرابط
Write-Host "`n4) فتح الرابط العام..." -ForegroundColor Cyan
$log = Join-Path $Logs 'tunnel.log'
Remove-Item $log -ErrorAction SilentlyContinue
$proc = Start-Process $Cf -ArgumentList @('tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:8010') `
    -RedirectStandardError $log -RedirectStandardOutput (Join-Path $Logs 'tunnel.out.log') -WindowStyle Hidden -PassThru

$url = $null
$deadline = (Get-Date).AddSeconds(60)
while (-not $url -and (Get-Date) -lt $deadline -and -not $proc.HasExited) {
    Start-Sleep 1
    if (Test-Path $log) {
        $m = Select-String -Path $log -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
        if ($m) { $url = $m.Matches[0].Value }
    }
}
if (-not $url) {
    Write-Host 'تعذّر إنشاء الرابط. آخر أسطر logs\tunnel.log:' -ForegroundColor Red
    if (Test-Path $log) { Get-Content $log -Tail 15 }
    if (-not $proc.HasExited) { Stop-Process -Id $proc.Id -Force }
    exit 1
}

try { Set-Clipboard $url } catch { }
Write-Host ''
Write-Host '  ================================================================' -ForegroundColor Green
Write-Host "    الرابط العام:  $url" -ForegroundColor Green
Write-Host '    (نُسخ إلى الحافظة — افتحه من الجوال أو أي جهاز)' -ForegroundColor Green
Write-Host '  ================================================================' -ForegroundColor Green
Write-Host ''
Write-Host '  - يبقى الرابط يعمل ما دامت هذه النافذة مفتوحة وجهازك مشغّلًا.' -ForegroundColor Gray
Write-Host '  - أي شخص لديه الرابط يستطيع فتح الموقع؛ لا تنشره إلا لمن تريد.' -ForegroundColor Gray
Write-Host '  - للإيقاف: اضغط Ctrl+C أو أغلق هذه النافذة.' -ForegroundColor Gray
Start-Process $url

try { Wait-Process -Id $proc.Id }
finally {
    if (-not $proc.HasExited) { Stop-Process -Id $proc.Id -Force }
    Write-Host 'أُغلق الرابط العام.' -ForegroundColor Yellow
}
