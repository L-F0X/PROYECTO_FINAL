<?php
require_once 'conexion.php';

// Redirigir a login si no hay sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Redirigir según el rol
$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? 'instructor'));

// Reenviar el parámetro de notificación (si viene) a la página de destino
$msgSuffix = isset($_GET['msg']) ? '?msg=' . urlencode($_GET['msg']) : '';

if ($rolNombre === 'coordinacion' || $rolNombre === 'coordinador') {
    header("Location: coordinador/index.php" . $msgSuffix);
} elseif ($rolNombre === 'almacenista') {
    header("Location: almacenista/index.php" . $msgSuffix);
} elseif ($rolNombre === 'administrador') {
    header("Location: Administrador/index.php" . $msgSuffix);
} else {
    // Por defecto, redirigir al instructor
    header("Location: instructor/index.php" . $msgSuffix);
}
exit;
