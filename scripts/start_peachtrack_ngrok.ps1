# ============================================================
#  PeachTrack - Start PHP server + ngrok public tunnel
#  Usage: Right-click -> Run with PowerShell
#         OR in terminal: .\scripts\start_peachtrack_ngrok.ps1
# ============================================================

$PHP    = "C:\php\php.exe"
$NGROK  = "C:\ngrok\ngrok.exe"
$SRC    = Join-Path $PSScriptRoot "..\src"
$PORT   = 8080

# -- Resolve absolute path ------------------------------------------
$SRC = (Resolve-Path $SRC).Path

Write-Host ""
Write-Host "  PeachTrack Launcher" -ForegroundColor Cyan
Write-Host "  -------------------------------------------" -ForegroundColor DarkGray

# -- Check dependencies ---------------------------------------------
if (!(Test-Path $PHP))   { Write-Host "  [ERROR] PHP not found at $PHP"   -ForegroundColor Red; pause; exit 1 }
if (!(Test-Path $NGROK)) { Write-Host "  [ERROR] ngrok not found at $NGROK" -ForegroundColor Red; pause; exit 1 }

# -- Check ngrok auth token -----------------------------------------
$ngrokConfig = & $NGROK config check 2>&1
if ($ngrokConfig -match "ERR_NGROK_4018|authentication|authtoken") {
    Write-Host ""
    Write-Host "  [SETUP NEEDED] ngrok requires a free auth token." -ForegroundColor Yellow
    Write-Host "  1. Go to: https://dashboard.ngrok.com/signup" -ForegroundColor White
    Write-Host "  2. Copy your authtoken from: https://dashboard.ngrok.com/get-started/your-authtoken" -ForegroundColor White
    Write-Host "  3. Run this command once:" -ForegroundColor White
    Write-Host "     C:\ngrok\ngrok.exe config add-authtoken YOUR_TOKEN_HERE" -ForegroundColor Green
    Write-Host ""
    pause
    exit 1
}

# -- Kill any existing php / ngrok on same port ---------------------
Get-Process -Name "php","ngrok" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue

# -- Start PHP server in background ---------------------------------
Write-Host "  Starting PHP server on port $PORT ..." -ForegroundColor Green
$phpProc = Start-Process -FilePath $PHP `
    -ArgumentList "-S", "0.0.0.0:$PORT", "-t", "`"$SRC`"" `
    -WindowStyle Minimized -PassThru
Start-Sleep -Seconds 1

if ($phpProc.HasExited) {
    Write-Host "  [ERROR] PHP server failed to start." -ForegroundColor Red
    pause; exit 1
}
Write-Host "  PHP server running (PID $($phpProc.Id))  ->  http://localhost:$PORT" -ForegroundColor Green

# -- Start ngrok tunnel ---------------------------------------------
Write-Host "  Starting ngrok tunnel ..." -ForegroundColor Green
$ngrokProc = Start-Process -FilePath $NGROK `
    -ArgumentList "http", $PORT, "--log=stdout" `
    -WindowStyle Minimized -PassThru
Start-Sleep -Seconds 3

# -- Fetch public URL from ngrok local API --------------------------
try {
    $tunnels = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -ErrorAction Stop
    $publicUrl = ($tunnels.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1).public_url
    if (!$publicUrl) {
        $publicUrl = ($tunnels.tunnels | Select-Object -First 1).public_url
    }
} catch {
    $publicUrl = $null
}

Write-Host ""
Write-Host "  ===========================================" -ForegroundColor Cyan
if ($publicUrl) {
    Write-Host "  PUBLIC URL:  $publicUrl" -ForegroundColor Yellow
    Write-Host "  Login page:  $publicUrl/login.php" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Share the URL above - anyone can access PeachTrack" -ForegroundColor White
    Write-Host "  from any device, anywhere." -ForegroundColor White
} else {
    Write-Host "  ngrok is running. Check your public URL at:" -ForegroundColor Yellow
    Write-Host "  http://localhost:4040  (ngrok dashboard)" -ForegroundColor Yellow
}
Write-Host "  ===========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Press ENTER to stop both servers and exit." -ForegroundColor DarkGray
Read-Host | Out-Null

# -- Cleanup --------------------------------------------------------
Write-Host "  Stopping servers..." -ForegroundColor DarkGray
Stop-Process -Id $phpProc.Id  -ErrorAction SilentlyContinue
Stop-Process -Id $ngrokProc.Id -ErrorAction SilentlyContinue
Write-Host "  Done. Goodbye!" -ForegroundColor Cyan
