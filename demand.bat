@echo off
cd /d C:\path\to\your-laravel-project
php artisan demand:generate-next-month
if %errorlevel% equ 0 (
    echo Next month’s demands generated successfully!
) else (
    echo No new demands were generated or an error occurred.
)
pause
