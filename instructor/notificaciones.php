<?php
// instructor/notificaciones.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

asegurar_tabla_notificacion($pdo);
limpiar_notificaciones_antiguas($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'eliminar') {
        eliminar_notificacion($pdo, $usuarioId, intval($_POST['id_notificacion'] ?? 0));
    } elseif ($accion === 'eliminar_todas') {
        eliminar_notificaciones_usuario($pdo, $usuarioId);
    }
    header('Location: notificaciones.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notificacion WHERE ID_USUARIO = ? ORDER BY FECHA DESC");
$stmt->execute([$usuarioId]);
$notificaciones = $stmt->fetchAll();

// Marcar todas como leídas al visitar la bandeja
$pdo->prepare("UPDATE notificacion SET LEIDA = 1 WHERE ID_USUARIO = ? AND LEIDA = 0")->execute([$usuarioId]);

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
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Instructor Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
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
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link">Mis Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link sidebar-link--primary active">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <h2>Notificaciones</h2>
            <p>Avisos sobre tus lotes y certificados de existencia.</p>

            <div class="panel-card" style="margin-top: 20px;">
                <?php if (empty($notificaciones)): ?>
                    <p style="text-align: center; color: #999; padding: 30px 0;">No tienes notificaciones todavía.</p>
                <?php else: ?>
                    <form method="POST" action="notificaciones.php" style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar_todas">
                        <button type="submit" class="btn btn-danger js-confirm-submit" data-confirm-title="Eliminar todas" data-confirm-message="¿Eliminar todas tus notificaciones? Esta acción no se puede deshacer." data-confirm-label="Eliminar" style="padding: 5px 12px; font-size: 12px;">Eliminar Todas</button>
                    </form>
                    <?php foreach ($notificaciones as $n): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px; padding: 14px 10px; border-bottom: 1px solid #eee; <?= !$n['LEIDA'] ? 'background: #f0fdf4;' : '' ?>">
                            <div>
                                <div><?= htmlspecialchars($n['MENSAJE']) ?></div>
                                <div style="font-size: 12px; color: #888; margin-top: 4px;"><?= htmlspecialchars($n['FECHA']) ?></div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center; white-space:nowrap;">
                                <?php if ($n['ENLACE']): ?>
                                    <a href="<?= htmlspecialchars($n['ENLACE']) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 12px; text-decoration: none;">Ver</a>
                                <?php endif; ?>
                                <form method="POST" action="notificaciones.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_notificacion" value="<?= htmlspecialchars($n['ID_NOTIFICACION']) ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" title="Eliminar">✕</button>
                                </form>
                            </div>
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
