@echo off

echo Running migrations...
php artisan migrate

echo Seeding admin user...
php artisan db:seed --class=AdminSeeder

echo.
echo Admin setup complete!
echo Login credentials:
echo Email: admin@jetpicker.com
echo Password: JetPicker@dmin2026!
echo.
pause
