<?php
require_once '../conexion.php';
require_once '../csrf.php';

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
            // Actualizar estado del lote a Aprobado
            $updateStmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Aprobado' WHERE ID_LOTE = ?");
            $updateStmt->execute([$idLote]);

            // Registrar la decisión en la tabla de auditoría
            $auditStmt = $pdo->prepare("INSERT INTO aprobacion_rechazo_lote (ID_LOTE, ID_COORDINADOR, ESTADO_DECISION, JUSTIFICACION) VALUES (?, ?, 'Aprobado', ?)");
            $auditStmt->execute([$idLote, intval($_SESSION['usuario_id']), 'Lote aprobado por coordinador']);

            $mensaje = 'Lote aprobado exitosamente.';
            $tipoMensaje = 'success';

            // Redirigir después de 2 segundos
            header("Refresh: 2; url=index.php");
        } catch (Exception $e) {
            error_log('Error aprobando lote: ' . $e->getMessage());
            $mensaje = 'Error al aprobar el lote: ' . $e->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Aprobar Lote</span></h1>
            <div class="user-greeting">Coordinador: <strong><?= $usuarioNombre ?></strong></div>
        </div>
    </div>
    <div class="header-right">
        <a href="revisar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-secondary">Volver</a>
    </div>
</header>

<div class="container fade-in" style="margin: 30px auto; max-width: 700px;">
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

</body>
</html>
