<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if (!in_array($rol, ['instructor', 'coordinacion'])) {
    header('Location: index.php');
    exit;
}

require_once 'instructor_profile.php';
