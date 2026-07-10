<?php
// crear.php — Creación de Lote de Requerimiento (Insert CRUD)
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
$errorLote = '';

// Procesar la creación de un nuevo lote si se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_crear_lote'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $nombreLote = trim($_POST['lote_nombre'] ?? '');
    if ($nombreLote === '') {
        $errorLote = 'El nombre del lote no puede estar vacío.';
    } elseif (strlen($nombreLote) > 100) {
        $errorLote = 'El nombre del lote no puede tener más de 100 caracteres.';
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM lote_requerimiento WHERE LOTE_NOMBRE = ?");
            $stmtCheck->execute([$nombreLote]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errorLote = 'Ya existe un lote con el nombre "' . htmlspecialchars($nombreLote) . '".';
            } else {
                $stmtMaxLote = $pdo->query("SELECT COALESCE(MAX(ID_LOTE), 0) + 1 FROM lote_requerimiento");
                $newIdLote = intval($stmtMaxLote->fetchColumn());

                $stmtInsert = $pdo->prepare("INSERT INTO lote_requerimiento (ID_LOTE, ID_SOLICITANTE, LOTE_NOMBRE, ESTADO_TRAMITE, FECHA_CREACION) VALUES (?, ?, ?, 'Borrador', ?)");
                $stmtInsert->execute([$newIdLote, $usuarioId, $nombreLote, date('Y-m-d')]);
                header("Location: ../index.php");
                exit;
            }
        } catch (\PDOException $e) {
            error_log('Error al crear lote: ' . $e->getMessage());
            $errorLote = 'Error al crear el lote. Verifique que los datos sean correctos.';
        }
    }
}

// Foto de perfil
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
    <meta name="description" content="Creación de lotes de requerimiento en BICERGAM.">
    <title>Crear Nuevo Lote - BICERGAM</title>
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
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
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
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Registrar Nuevo Lote</h2>
                <p class="dashboard-subtitle">Complete el nombre del lote para iniciar un nuevo trámite de requerimiento.</p>
            </div>
        </div>

        <?php if ($errorLote): ?>
            <div class="error-msg" style="margin-bottom: 20px;">
                <?= htmlspecialchars($errorLote) ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <h3>Datos del Lote</h3>
            <form method="POST" action="crear.php" style="margin-top: 15px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="lote_nombre" style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 8px;">Nombre del Lote:</label>
                    <input type="text" id="lote_nombre" name="lote_nombre" class="form-control" placeholder="Ej: LOTE REDES 2026" required maxlength="100" style="border-radius: 7px; padding: 12px 14px; font-size: 15px;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="../index.php" class="btn btn-secondary" style="border-radius: 7px; padding: 11px 22px;">Cancelar</a>
                    <button type="submit" name="btn_crear_lote" class="btn btn-sena" style="border-radius: 7px; padding: 11px 22px;">Registrar Lote</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="../js/apartados.js"></script>
</body>
</html>
