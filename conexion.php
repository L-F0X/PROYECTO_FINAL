<?php
// conexion.php
// Configura parámetros de cookie de sesión seguros antes de iniciar sesión
$host = "localhost";
$db   = "bicergam";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexión PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // No mostrar detalles en pantalla; registrar y mostrar mensaje genérico
     error_log('DB connection error: ' . $e->getMessage());
     die('Error de conexión a la base de datos. Contacte al administrador.');
}

// Formatea la fecha actual en español sin depender del locale del sistema operativo
if (!function_exists('fecha_larga_es')) {
    function fecha_larga_es(): string {
        $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
        $meses = ['January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'];
        return $dias[date('l')] . ', ' . date('d') . ' de ' . $meses[date('F')];
    }
}
?>