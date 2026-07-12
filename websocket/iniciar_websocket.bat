@echo off
REM Inicia el servidor WebSocket de BICERGAM (notificaciones en tiempo real).
REM Debe quedar corriendo en esta ventana mientras uses el sistema; ciérrala
REM para detenerlo. Requiere que XAMPP/MySQL ya estén encendidos.
cd /d "%~dp0.."
C:\xampp\php\php.exe websocket\ws_server.php
pause
