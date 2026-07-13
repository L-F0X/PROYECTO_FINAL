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

// Verificación global de sesión para no permitir acceso por URL a archivos protegidos
if (php_sapi_name() !== 'cli') {
    $current_file = basename($_SERVER['PHP_SELF']);
    $public_files = ['login.php', 'recuperar_password.php', 'restablecer_password.php', 'password_reset.php', 'logout.php'];
    
    if (!in_array($current_file, $public_files) && !isset($_SESSION['usuario_id'])) {
        $prefix = '';
        if (strpos($_SERVER['PHP_SELF'], '/instructor/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/coordinador/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/almacenista/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/Administrador/') !== false ||
            strpos($_SERVER['PHP_SELF'], '/ajax/') !== false) {
            $prefix = '../';
        }
        header("Location: " . $prefix . "login.php");
        exit;
    }

    // Inactividad de 5 minutos en el backend
    if (isset($_SESSION['usuario_id'])) {
        $prefix = '';
        if (strpos($_SERVER['PHP_SELF'], '/instructor/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/coordinador/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/almacenista/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/Administrador/') !== false ||
            strpos($_SERVER['PHP_SELF'], '/ajax/') !== false) {
            $prefix = '../';
        }
        
        $inactivity_limit = 300; // 5 minutos
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
            session_unset();
            session_destroy();
            header("Location: " . $prefix . "login.php?msg=inactivo");
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
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