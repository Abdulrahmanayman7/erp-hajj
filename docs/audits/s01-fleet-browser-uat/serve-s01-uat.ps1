$env:DB_CONNECTION="mysql"
$env:DB_HOST="127.0.0.1"
$env:DB_PORT="3306"
$env:DB_DATABASE="erp_hajj_s01_uat"
$env:DB_USERNAME="root"
$env:DB_PASSWORD=""
$env:APP_URL="http://127.0.0.1:8000"
Set-Location "d:\erp-hajj-1\backend"
& "C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000

