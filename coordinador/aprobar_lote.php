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

    // Evitar doble aprobación o procesar lotes que no estén en estado "Enviado"
    if ($lote['ESTADO_TRAMITE'] !== 'Enviado') {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching lote: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Procesar aprobación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $mensaje = 'Token CSRF inválido.';
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

            // Actualizar estado del lote a Aprobado
            $updateStmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Aprobado' WHERE ID_LOTE = ?");
            $updateStmt->execute([$idLote]);

            // Registrar la decisión en la tabla de auditoría
            $auditStmt = $pdo->prepare("INSERT INTO aprobacion_rechazo_lote (ID_LOTE, ID_COORDINADOR, ESTADO_DECISION, JUSTIFICACION) VALUES (?, ?, 'Aprobado', ?)");
            $auditStmt->execute([$idLote, intval($_SESSION['usuario_id']), 'Lote aprobado por coordinador']);

            $pdo->commit();

            crear_notificacion(
                $pdo,
                intval($lote['ID_SOLICITANTE']),
                "Tu lote '" . $lote['LOTE_NOMBRE'] . "' fue aprobado.",
                "../instructor/mis_lotes.php"
            );
            notificar_por_rol(
                $pdo,
                'Almacenista',
                "El lote '" . $lote['LOTE_NOMBRE'] . "' fue aprobado y ya puede certificarse.",
                "../almacenista/index.php?tab=instructor"
            );

            header("Location: revisar_lotes.php?msg=aprobado");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error aprobando lote: ' . $e->getMessage());
            $mensaje = 'Error al aprobar el lote. Intente de nuevo más tarde.';
            $tipoMensaje = 'error';
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
    <title>Aprobar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Coordinador</span></h1>
            <div class="user-greeting">Coordinador de Compras: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Coordinador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
                <h2>Aprobar Lote: <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></h2>
                <p>ID: <?= htmlspecialchars($lote['ID_LOTE']) ?></p>
            </div>

            <div class="panel-card" style="margin-top: 20px;">
                <?php if (!empty($mensaje)): ?>
                    <div style="padding: 12px; border-radius: 6px; margin-bottom: 20px; <?= $tipoMensaje === 'error' ? 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' ?>">
                        <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>

                <div style="background: #e8f5e9; border: 1px solid #c8e6c9; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; color: #2e7d32;">¿Estás seguro de que deseas aprobar este lote?</h4>
                    <p style="margin: 8px 0; color: #558b2f;">
                        <strong>Lote:</strong> <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?><br>
                        <strong>ID:</strong> <?= htmlspecialchars($lote['ID_LOTE']) ?><br>
                        <strong>Estado Actual:</strong> <?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?>
                    </p>
                </div>

                <form method="POST" action="aprobar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <button type="submit" class="btn" style="padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Confirmar Aprobación</button>
                        <a href="revisar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                    </div>
                </form>

                <div style="background: #f5f5f5; padding: 12px; border-radius: 6px; border-left: 4px solid #ffc107;">
                    <p style="margin: 0; font-size: 13px; color: #666;">
                        <strong>Nota:</strong> Una vez aprobado, el lote pasará al siguiente estado del proceso. Esta acción se registrará en el historial.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
