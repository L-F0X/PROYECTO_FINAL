<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = 'uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Historial de existencias por ítem en BICERGAM.">
    <title>Historial de Existencia - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="imagenes/sena-logo.png" alt="SENA">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
        <a href="instructor_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crud_instructor/crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
            <a href="crud_instructor/consulta_lote.php" class="sidebar-link">Consulta de Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="historial_existencia.php" class="sidebar-link active">Historial de Existencia</a>
            <a href="crud_instructor/matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="crud_instructor/certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Historial de Existencia</h2>
                <p class="dashboard-subtitle">Visualiza el historial de existencias de bienes registrados en el sistema.</p>
            </div>
        </div>

        <div class="panel-card" style="padding: 40px; text-align: center; color: #555;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--verde-sena)" stroke-width="1.5" style="margin-bottom: 20px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <h3 style="margin-bottom: 10px; color: #333;">Información de Existencia de Bienes</h3>
            <p style="max-width: 600px; margin: 0 auto 20px; line-height: 1.6;">
                Esta vista muestra el historial detallado de las existencias por ítem. Actualmente es un módulo en desarrollo (Placeholder para la implementación de filtros, búsqueda avanzada e informes de inventario de acuerdo a las necesidades institucionales).
            </p>
            <a href="instructor_dashboard.php" class="btn btn-sena" style="border-radius: 7px;">Volver al Panel</a>
        </div>
    </main>
</div>

<script src="javascript.js"></script>
</body>
</html>
