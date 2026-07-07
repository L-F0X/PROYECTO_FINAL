<?php
// mail_config.example.php
// Copia este archivo como mail_config.php y completa tus propios datos.
// mail_config.php está en .gitignore porque contiene credenciales reales.

return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls', // 'tls', 'ssl' o '' (sin cifrado)
    'username'   => 'tu-correo@gmail.com',
    'password'   => 'contraseña-de-aplicacion-de-16-caracteres', // myaccount.google.com/apppasswords
    'from_email' => 'tu-correo@gmail.com',
    'from_name'  => 'BICERGAM',
];
