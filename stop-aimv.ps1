# يوقف مكوّنات AI Media Verifier التي شغّلها start-aimv.ps1 (لا يوقف MariaDB لأنها مشتركة مع مشاريع أخرى)
$ErrorActionPreference = 'SilentlyContinue'

function Stop-Tree([int]$ProcessId) {
    Get-CimInstance Win32_Process -Filter "ParentProcessId = $ProcessId" | ForEach-Object { Stop-Tree $_.ProcessId }
    Stop-Process -Id $ProcessId -Force
}

$supervisors = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" | Where-Object { $_.CommandLine -like '*AIMV-SUPERVISOR:*' }
foreach ($s in $supervisors) { Stop-Tree $s.ProcessId }

# أي عملية بقيت على منفذي الموقع وخدمة التحليل
foreach ($port in 8001, 8010) {
    Get-NetTCPConnection -State Listen -LocalPort $port | ForEach-Object { Stop-Tree $_.OwningProcess }
}

Write-Host "أُوقفت خدمة التحليل والموقع وعامل الطابور ($($supervisors.Count) مشرف)." -ForegroundColor Green
