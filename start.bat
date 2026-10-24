@echo off
set PHP_BIN=php
where php >nul 2>&1
if %errorlevel% neq 0 (
    if exist "D:\xampp\php\php.exe" (
        set PHP_BIN=D:\xampp\php\php.exe
    ) else if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    )
)
echo Dang khoi dong server tai http://127.0.0.1:8104 ...
"%PHP_BIN%" -S 127.0.0.1:8104 -t public public/router.php
pause

