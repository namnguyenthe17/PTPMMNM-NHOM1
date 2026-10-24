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
echo ========================================================
echo KHOI DONG UNG DUNG THU NGHIEM DOC LAP (PORT 8105)
echo Ung dung nay goi HTTP API cua FlagFlow (PORT 8104)
echo Truy cap: http://127.0.0.1:8105
echo ========================================================
"%PHP_BIN%" -S 127.0.0.1:8105 -t demo
pause

