<?php
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

$idLote = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idLote <= 0) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = '';

// Obtener información del lote
try {
    $sql = "SELECT lr.* FROM lote_requerimiento lr WHERE lr.ID_LOTE = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idLote]);
    $lote = $stmt->fetch();

    if (!$lote) {
        header('Location: index.php');
        exit;
    }

    // Evitar doble rechazo o procesar lotes que no estén en estado "Enviado"
    if ($lote['ESTADO_TRAMITE'] !== 'Enviado') {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching lote: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Procesar rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $mensaje = 'Token CSRF inválido.';
        $tipoMensaje = 'error';
    } else {
        $justificacion = trim($_POST['justificacion'] ?? '');

        if (empty($justificacion)) {
            $mensaje = 'La justificación es requerida.';
            $tipoMensaje = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Re-verificar el estado actual bajo bloqueo, por si otro proceso ya decidió el lote
                $stmtLock = $pdo->prepare("SELECT ESTADO_TRAMITE FROM lote_requerimiento WHERE ID_LOTE = ? FOR UPDATE");
                $stmtLock->execute([$idLote]);
                $estadoActual = $stmtLock->fetchColumn();

                if ($estadoActual !== 'Enviado') {
                    $pdo->rollBack();
                    header('Location: revisar_lotes.php?msg=error');
                    exit;
                }

                // Actualizar estado del lote a Rechazado
                $updateStmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Rechazado' WHERE ID_LOTE = ?");
                $updateStmt->execute([$idLote]);

                // Registrar la decisión en la tabla de auditoría
                $auditStmt = $pdo->prepare("INSERT INTO aprobacion_rechazo_lote (ID_LOTE, ID_COORDINADOR, ESTADO_DECISION, JUSTIFICACION) VALUES (?, ?, 'Rechazado', ?)");
                $auditStmt->execute([$idLote, intval($_SESSION['usuario_id']), $justificacion]);

$pdo->commit();
$motivoCorto = mb_strlen($justificacion) > 150 ? mb_substr($justificacion, 0, 150) . '...' : $justificacion;
crear_notificacion(
    $pdo,
    intval($lote['ID_SOLICITANTE']),
    "Tu lote '" . $lote['LOTE_NOMBRE'] . "' fue rechazado. Motivo: " . $motivoCorto,
    "../instructor/mis_lotes.php"
);

                header("Location: revisar_lotes.php?msg=rechazado");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Error rechazando lote: ' . $e->getMessage());
                $mensaje = 'Error al rechazar el lote. Intente de nuevo más tarde.';
                $tipoMensaje = 'error';
            }
        }
    }
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechazar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Coordinador de Compras: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
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
            <a href="revisar_lotes.php" class="sidebar-link sidebar-link--primary active">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <div class="role-banner role-coordinador">
                <h2>Rechazar Lote: <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></h2>
                <p>ID: <?= htmlspecialchars($lote['ID_LOTE']) ?></p>
            </div>

            <div class="panel-card" style="margin-top: 20px;">
                <?php if (!empty($mensaje)): ?>
                    <div style="padding: 12px; border-radius: 6px; margin-bottom: 20px; <?= $tipoMensaje === 'error' ? 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' ?>">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="rechazar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="justificacion" style="display: block; font-weight: 600; margin-bottom: 8px; color: #264047;">Justificación del Rechazo *</label>
                        <textarea 
                            id="justificacion" 
                            name="justificacion" 
                            rows="8" 
                            required 
                            style="width: 100%; padding: 12px; border: 1px solid #d4dadb; border-radius: 6px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; resize: vertical;"
                            placeholder="Ingresa la razón específica del rechazo..."><?php if ($_SERVER['REQUEST_METHOD'] === 'POST') echo htmlspecialchars($_POST['justificacion'] ?? ''); ?></textarea>
                        <p style="margin: 8px 0 0; color: #6b7780; font-size: 13px;">Explica detalladamente por qué se rechaza este lote para que el instructor pueda hacer correcciones.</p>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn" style="padding: 10px 20px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Confirmar Rechazo</button>
                        <a href="revisar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
