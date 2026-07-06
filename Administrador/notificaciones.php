<?php
// Administrador/notificaciones.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador');

asegurar_tabla_notificacion($pdo);

$stmt = $pdo->prepare("SELECT * FROM notificacion WHERE ID_USUARIO = ? ORDER BY FECHA DESC");
$stmt->execute([$usuarioId]);
$notificaciones = $stmt->fetchAll();

$pdo->prepare("UPDATE notificacion SET LEIDA = 1 WHERE ID_USUARIO = ? AND LEIDA = 0")->execute([$usuarioId]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Notificaciones</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Administrador</span></h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Administrador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔</a>
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Administración</h4>
            <a href="index.php" class="sidebar-link">Gestión Usuarios</a>
            <a href="importar_unspsc.php" class="sidebar-link">Importar UNSPSC</a>
            <a href="gestionar_iva.php" class="sidebar-link">Gestionar IVA</a>
            <a href="notificaciones.php" class="sidebar-link sidebar-link--primary active">Notificaciones</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="../instructor/index.php" class="sidebar-link">Panel Instructor</a>
            <a href="../coordinador/index.php" class="sidebar-link">Panel Coordinador</a>
            <a href="../almacenista/index.php" class="sidebar-link">Panel Almacenista</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <h2>Notificaciones</h2>
            <p>Por ahora no hay eventos configurados para el rol Administrador.</p>

            <div class="panel-card" style="margin-top: 20px;">
                <?php if (empty($notificaciones)): ?>
                    <p style="text-align: center; color: #999; padding: 30px 0;">No tienes notificaciones todavía.</p>
                <?php else: ?>
                    <?php foreach ($notificaciones as $n): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px; padding: 14px 10px; border-bottom: 1px solid #eee; <?= !$n['LEIDA'] ? 'background: #f0fdf4;' : '' ?>">
                            <div>
                                <div><?= htmlspecialchars($n['MENSAJE']) ?></div>
                                <div style="font-size: 12px; color: #888; margin-top: 4px;"><?= htmlspecialchars($n['FECHA']) ?></div>
                            </div>
                            <?php if ($n['ENLACE']): ?>
                                <a href="<?= htmlspecialchars($n['ENLACE']) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 12px; text-decoration: none; white-space: nowrap;">Ver</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>