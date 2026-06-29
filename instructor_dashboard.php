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
    <title>Dashboard Instructor - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header>
    <h1>BICERGAM | <span>SENA</span></h1>
    <div style="text-align: right; color: white;">
        Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> (<?= htmlspecialchars($_SESSION['rol_nombre']) ?>) |
        <a href="logout.php" style="color: var(--alerta-rojo); text-decoration: none; font-weight: bold; margin-left: 10px;">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM | SENA</span>
        </div>

        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
            <a href="crear.php" class="sidebar-link">+ Crear Nuevo Lote</a>
        </div>

        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="historial_existencia.php" class="sidebar-link">Historial de Existencia</a>
            <a href="matriz.php" class="sidebar-link">Consulta de Matrices</a>
        </div>

        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Instructor</h2>
                <p class="dashboard-subtitle">Accede a tus herramientas: ficha técnica, historial de existencia y consulta de matrices.</p>
            </div>
            <div class="profile-top-right">
                <?php if ($photoPath): ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-top-avatar">
                <?php else: ?>
                    <div class="profile-top-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'],0,1)) ?></div>
                <?php endif; ?>
                <div class="profile-top-text">
                    <div class="profile-name"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
                    <div class="profile-role"><?= htmlspecialchars($_SESSION['rol_nombre']) ?></div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <h3>Resumen</h3>
            <p class="panel-description">Aquí puedes ver tus lotes recientes y acceder rápidamente a acciones relacionadas.</p>

            <section class="recent-lotes">
                <h4>Tus lotes recientes</h4>
                <?php
                    $stmt = $pdo->prepare("SELECT * FROM lote_requerimiento WHERE ID_SOLICITANTE = ? ORDER BY FECHA_CREACION DESC LIMIT 8");
                    $stmt->execute([$usuarioId]);
                    $lotes = $stmt->fetchAll();
                ?>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr><td colspan="5" style="text-align:center">No hay lotes recientes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $l): ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['ID_LOTE']) ?></td>
                                    <td><?= htmlspecialchars($l['LOTE_NOMBRE']) ?></td>
                                    <td><?= htmlspecialchars($l['ESTADO_TRAMITE']) ?></td>
                                    <td><?= htmlspecialchars($l['FECHA_CREACION']) ?></td>
                                    <td><a href="matriz.php?lote=<?= htmlspecialchars($l['ID_LOTE']) ?>" class="btn">Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
</div>

<script src="javascript.js"></script>
</body>
</html>
