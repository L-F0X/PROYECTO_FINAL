<?php
require_once 'conexion.php';

// Redirigir a login si no hay sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Redirigir según el rol
$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? 'instructor'));

if ($rolNombre === 'coordinacion' || $rolNombre === 'coordinador') {
    header("Location: coordinador/index.php");
} else {
    // Por defecto, redirigir al instructor
    header("Location: instructor/index.php");
}
exit;
