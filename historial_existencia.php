<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Página placeholder para historial de existencia
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Existencia</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header>
    <div class="topbar">
        <div class="brand">
            <img src="imagenes/sena-logo.png" alt="SENA">
            <h1>BICERGAM | <span>SENA</span></h1>
        </div>
        <div class="user-actions">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></strong>
            <a href="logout.php" class="logout-link">Cerrar Sesión</a>
        </div>
    </div>
</header>

<div class="container fade-in">
    <h2>Historial de Existencia</h2>
    <p>Esta vista muestra el historial de existencias por ítem. (Placeholder - implementar filtros y búsquedas según requisitos)</p>
    <p><a href="instructor_dashboard.php" class="btn">Volver al Panel</a></p>
</div>
</body>
</html>
