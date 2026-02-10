# PowerShell Script to Install WordPress
# Location: c:\wamp64\www\PetsGo\install-wp.ps1

$wpPath = "C:\wamp64\www\PetsGo\WordPress"
$zipPath = "C:\wamp64\www\PetsGo\latest.zip"
$url = "https://wordpress.org/latest.zip"

Write-Host "Checking for WordPress installation..."

If (Test-Path "$wpPath\wp-load.php") {
    Write-Host "WordPress appears to be already installed." -ForegroundColor Yellow
} Else {
    Write-Host "Downloading WordPress..."
    Try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $zipPath
        Write-Host "Download complete." -ForegroundColor Green
        
        Write-Host "Extracting..."
        Expand-Archive -Path $zipPath -DestinationPath "C:\wamp64\www\PetsGo\TempWP" -Force
        
        Write-Host "Moving files..."
        # Move internal wordpress folder content to target
        Copy-Item -Path "C:\wamp64\www\PetsGo\TempWP\wordpress\*" -Destination $wpPath -Recurse -Force
        
        # Cleanup
        Remove-Item "C:\wamp64\www\PetsGo\TempWP" -Recurse -Force
        Remove-Item $zipPath -Force
        
        Write-Host "WordPress files installed successfully!" -ForegroundColor Green
    } Catch {
        Write-Host "Error downloading or installing WordPress: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "Please download manually from https://wordpress.org/download/ and extract to $wpPath"
    }
}
