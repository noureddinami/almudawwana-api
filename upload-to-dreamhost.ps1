# PowerShell FTP Upload Script for deploy.php to DreamHost
# Usage: Run this script in PowerShell to upload deploy.php to DreamHost

# ===== EDIT THESE VALUES =====
$FTP_HOST = "almodawana.dreamhosters.com"
$FTP_USER = "almodawana"                    # Change to your DreamHost username
$FTP_PASS = "YOUR_PASSWORD_HERE"            # Change to your DreamHost FTP password
$LOCAL_FILE = "C:\xampp\htdocs\modawana\almudawwana-api\deploy.php"
$REMOTE_PATH = "/deploy.php"                # Upload to root of your domain
# =============================

Write-Host "=== FTP Upload Script for DreamHost ===" -ForegroundColor Cyan
Write-Host "Host: $FTP_HOST"
Write-Host "User: $FTP_USER"
Write-Host "Local file: $LOCAL_FILE"
Write-Host ""

# Verify file exists
if (-Not (Test-Path $LOCAL_FILE)) {
    Write-Host "ERROR: File not found: $LOCAL_FILE" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Local file found" -ForegroundColor Green

# Create FTP request
$FTP_URI = "ftp://$FTP_HOST$REMOTE_PATH"
Write-Host "Uploading to: $FTP_URI" -ForegroundColor Cyan

try {
    # Create FTP request
    $FtpRequest = [System.Net.FtpWebRequest]::Create($FTP_URI)
    $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FTP_USER, $FTP_PASS)
    $FtpRequest.UseBinary = $true
    $FtpRequest.KeepAlive = $false

    # Read file and upload
    $FileStream = [System.IO.File]::OpenRead($LOCAL_FILE)
    $FtpStream = $FtpRequest.GetRequestStream()
    $FileStream.CopyTo($FtpStream)
    $FtpStream.Close()
    $FileStream.Close()

    # Get response
    $Response = $FtpRequest.GetResponse()
    Write-Host "✓ Upload successful!" -ForegroundColor Green
    Write-Host "Status: $($Response.StatusCode) $($Response.StatusDescription)" -ForegroundColor Green
    $Response.Close()

    # Verify upload
    Write-Host ""
    Write-Host "Testing deploy.php on DreamHost..." -ForegroundColor Cyan
    $TestURL = "https://$FTP_HOST$REMOTE_PATH"
    Write-Host "URL: $TestURL"

    # Wait a moment for the file to be available
    Start-Sleep -Seconds 2

    try {
        $TestResponse = Invoke-WebRequest -Uri $TestURL -UseBasicParsing
        Write-Host "✓ Script is accessible!" -ForegroundColor Green
        Write-Host "Response: $($TestResponse.StatusCode)" -ForegroundColor Green
    } catch {
        Write-Host "⚠ Could not immediately access the script (might need a few seconds)" -ForegroundColor Yellow
        Write-Host "Try accessing it manually in a few moments: $TestURL" -ForegroundColor Yellow
    }

} catch {
    Write-Host "ERROR: Upload failed!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== Next Steps ===" -ForegroundColor Cyan
Write-Host "1. Configure GitHub Webhook:"
Write-Host "   - Go to: https://github.com/noureddinami/almudawwana-api"
Write-Host "   - Settings > Webhooks > Add webhook"
Write-Host "   - Payload URL: https://almodawana.dreamhosters.com/deploy.php"
Write-Host "   - Secret: almudawwana-webhook-secret-2026"
Write-Host "   - Events: Just push"
Write-Host ""
Write-Host "2. Test the webhook in GitHub"
Write-Host "3. Check the deployment log: https://almodawana.dreamhosters.com/deploy.log"
Write-Host ""
Write-Host "Done!" -ForegroundColor Green
