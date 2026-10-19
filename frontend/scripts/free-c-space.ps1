$ErrorActionPreference = 'SilentlyContinue'
$log = 'D:\erp-hajj-1\.disk-check.txt'
"start CFree=$((Get-PSDrive C).Free)" | Set-Content -LiteralPath $log

$targets = @(
  "$env:TEMP\*",
  "$env:LOCALAPPDATA\Temp\*",
  "$env:LOCALAPPDATA\npm-cache\*",
  "$env:USERPROFILE\.npm\_cacache\*",
  "$env:LOCALAPPDATA\Vite\*",
  "$env:LOCALAPPDATA\vitest\*"
)

foreach ($pattern in $targets) {
  Remove-Item -Path $pattern -Recurse -Force -ErrorAction SilentlyContinue
  "cleared $pattern" | Add-Content -LiteralPath $log
}

# Cursor project terminals/agent-tools are regenerable
Remove-Item -LiteralPath 'C:\Users\abdul\.cursor\projects\d-erp-hajj-1\agent-tools\*' -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath 'd:\erp-hajj-1\frontend\dist' -Recurse -Force -ErrorAction SilentlyContinue
"cleared agent-tools and frontend dist" | Add-Content -LiteralPath $log

"end CFree=$((Get-PSDrive C).Free)" | Add-Content -LiteralPath $log
"end DFree=$((Get-PSDrive D).Free)" | Add-Content -LiteralPath $log
