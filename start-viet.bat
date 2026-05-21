@echo off
title Musik KITA - Vite Build Server

timeout /t 15 /nobreak

cd /d C:\laragon\www\musik-kita-ops

call npm run build

pause