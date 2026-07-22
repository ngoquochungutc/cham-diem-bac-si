@echo off
chcp 65001 >nul
title He Thong Danh Gia Nhan Vien v2 - SETUP

echo.
echo  ================================================
echo   He Thong Danh Gia Nhan Vien v2
echo   XAMPP + MySQL - Script Cai Dat Tu Dong
echo  ================================================
echo.
echo  Dang khoi chay voi quyen Administrator...
echo.

PowerShell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process PowerShell -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File ""%~dp0scripts\setup-v2.ps1""' -Verb RunAs -Wait"

echo.
echo  Xong! Nhan phim bat ky de dong.
pause >nul
