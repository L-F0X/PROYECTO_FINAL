<?php
// editar.php — Edición de Lote (Insert/Update CRUD)
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';
require_once '../display_helper.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit;
}

$id = intval($_GET['id']);
$usuarioId = intval($_SESSION['usuario_id']);

// Obtener datos actuales del lote (solo si pertenece al instructor autenticado)
$stmt = $pdo->prepare("SELECT * FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
$stmt->execute([$id, $usuarioId]);
$lote = $stmt->fetch();

if (!$lote) {
    header("Location: ../index.php");
    exit;
}

// Solo los lotes en Borrador pueden editarse: una vez enviados, aprobados
// o rechazados, el trámite ya está en manos del coordinador.
if ($lote['ESTADO_TRAMITE'] !== 'Borrador') {
    header("Location: mis_lotes.php?msg=no_editable");
    exit;
}

// Procesar actualización
$errorMensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $nombre = trim($_POST['lote_nombre'] ?? '');

    if ($nombre === '') {
        $errorMensaje = 'El nombre del lote no puede estar vacío.';
    } elseif (strlen($nombre) > 100) {
        $errorMensaje = 'El nombre del lote no puede tener más de 100 caracteres.';
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM lote_requerimiento WHERE LOTE_NOMBRE = ? AND ID_LOTE != ?");
            $stmtCheck->execute([$nombre, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errorMensaje = 'Ya existe un lote con el nombre "' . htmlspecialchars($nombre) . '".';
            } else {
                $sql = "UPDATE lote_requerimiento SET LOTE_NOMBRE = ? WHERE ID_LOTE = ? AND ID_SOLICITANTE = ? AND ESTADO_TRAMITE = 'Borrador'";
                $pdo->prepare($sql)->execute([$nombre, $id, $usuarioId]);
                header("Location: mis_lotes.php?msg=editado");
                exit;
            }
        } catch (\PDOException $e) {
            error_log('Editar lote error: ' . $e->getMessage());
            die('Error al actualizar el lote. Contacte al administrador.');
        }
    }

    // Si hubo un error de validación, se conserva el valor ingresado para no perderlo.
    $lote['LOTE_NOMBRE'] = $nombre;
}

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
    <title>Editar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); $wsToken = generar_ws_token($pdo, intval($_SESSION['usuario_id']), $_SESSION['rol_nombre'] ?? ''); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <h2>Editar Lote #<?= numero_visible_lote($pdo, (int) $lote['ID_LOTE'], (int) $lote['ID_SOLICITANTE']) ?></h2>

            <?php if ($errorMensaje !== ''): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin: 15px 0; font-weight: 500; font-size: 14px; background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;">
                    ✗ <?= htmlspecialchars($errorMensaje) ?>
                </div>
            <?php endif; ?>

            <form id="formLote" action="editar.php?id=<?= htmlspecialchars($id) ?>" method="POST" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <div class="form-group">
                    <label for="lote_nombre">Nombre del Lote:</label>
                    <input type="text" id="lote_nombre" name="lote_nombre" class="form-control" value="<?= htmlspecialchars($lote['LOTE_NOMBRE']) ?>" required maxlength="100" style="border-radius: 7px; padding: 10px 14px;">
                </div>

                <div style="display: flex; gap: 12px; margin-top: 15px;">
                    <a href="mis_lotes.php" class="btn btn-secondary" style="border-radius: 7px; padding: 11px 22px;">Cancelar</a>
                    <button type="submit" class="btn btn-sena" style="border-radius: 7px; padding: 11px 22px;">Actualizar Requerimiento</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="../js/apartados.js"></script>
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
