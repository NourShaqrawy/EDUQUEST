# Run this as Administrator
$currentPath = [System.Environment]::GetEnvironmentVariable("PATH", "Machine")
$php84Path = "C:\php\php-8.4"
$xamppPhpPath = "C:\xampp\php"

# Remove XAMPP PHP from system PATH
$entries = $currentPath -split ";" | Where-Object { $_ -ne $xamppPhpPath -and $_ -ne $php84Path -and $_ -ne "" }

# Add PHP 8.4 at the start
$newPath = ($php84Path + ";" + ($entries -join ";"))

[System.Environment]::SetEnvironmentVariable("PATH", $newPath, "Machine")
Write-Host "Done! PHP 8.4 is now the default system PHP." -ForegroundColor Green
Write-Host "Restart your terminal to apply changes." -ForegroundColor Yellow
