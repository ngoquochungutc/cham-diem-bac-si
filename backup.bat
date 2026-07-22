@echo off
chcp 65001 >nul
title Backup Database v2

for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set dt=%%a
set FNAME=evaldb_v2_%dt:~0,8%_%dt:~8,6%.sql
if not exist "%~dp0backups" mkdir "%~dp0backups"

set /p ROOTPW="Nhap mat khau MySQL root (Enter neu trong): "
if "%ROOTPW%"=="" (
    C:\xampp\mysql\bin\mysqldump -h 127.0.0.1 -u root evaldb > "%~dp0backups\%FNAME%"
) else (
    C:\xampp\mysql\bin\mysqldump -h 127.0.0.1 -u root -p%ROOTPW% evaldb > "%~dp0backups\%FNAME%"
)

if errorlevel 1 (
    echo [LOI] Backup that bai!
) else (
    echo [OK]  Da backup: backups\%FNAME%
)
pause
