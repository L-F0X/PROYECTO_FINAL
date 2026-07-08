<?php
// coordinador/notificaciones.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

asegurar_tabla_notificacion($pdo);

$stmt = $pdo->prepare("SELECT * FROM notificacion WHERE ID_USUARIO = ? ORDER BY FECHA DESC");
$stmt->execute([$usuarioId]);
$notificaciones = $stmt->fetchAll();

$pdo->prepare("UPDATE notificacion SET LEIDA = 1 WHERE ID_USUARIO = ? AND LEIDA = 0")->execute([$usuarioId]);

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Coordinador</span></h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Coordinador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"></a>
        <a href="coordinador_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"><span>BICERGAM</span></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link sidebar-link--primary active">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <h2>Notificaciones</h2>
            <p>Avisos sobre lotes enviados por instructores.</p>

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