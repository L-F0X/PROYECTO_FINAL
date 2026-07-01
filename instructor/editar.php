<?php
// editar.php — Edición de Lote (Insert/Update CRUD)
require_once '../conexion.php';
require_once '../csrf.php';

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

// Obtener datos actuales del lote
$stmt = $pdo->prepare("SELECT * FROM lote_requerimiento WHERE ID_LOTE = ?");
$stmt->execute([$id]);
$lote = $stmt->fetch();

if (!$lote) {
    header("Location: ../index.php");
    exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $nombre = trim($_POST['lote_nombre']);
    $solicitante = intval($_POST['id_solicitante']);
    $estado = trim($_POST['estado_tramite']);
    
    $sql = "UPDATE lote_requerimiento SET ID_SOLICITANTE = ?, LOTE_NOMBRE = ?, ESTADO_TRAMITE = ? WHERE ID_LOTE = ?";
    try {
        $pdo->prepare($sql)->execute([$solicitante, $nombre, $estado, $id]);
        header("Location: ../index.php");
        exit;
    } catch (\PDOException $e) {
        error_log('Editar lote error: ' . $e->getMessage());
        die('Error al actualizar el lote. Contacte al administrador.');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left">
        <h1 class="header-title">BICERGAM | <span class="accent-color">SENA</span></h1>
    </div>
    <div class="header-center">
        Usuario: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
    </div>
    <div class="header-right" style="display: flex; gap: 15px; align-items: center;">
        <a href="../index.php" style="color: var(--verde-sena); text-decoration: none; font-weight: bold;">← Volver</a>
        <a href="../logout.php" class="btn-logout" style="padding: 6px 14px; text-decoration: none;">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in" style="max-width: 600px;">
    <h2>Editar Lote #<?= htmlspecialchars($lote['ID_LOTE']) ?></h2>
    
    <form id="formLote" action="editar.php?id=<?= htmlspecialchars($id) ?>" method="POST" style="margin-top: 20px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <div class="form-group">
            <label for="lote_nombre">Nombre del Lote:</label>
            <input type="text" id="lote_nombre" name="lote_nombre" class="form-control" value="<?= htmlspecialchars($lote['LOTE_NOMBRE']) ?>" required style="border-radius: 7px; padding: 10px 14px;">
        </div>

        <div class="form-group">
            <label for="id_solicitante">ID Instructor Solicitante:</label>
            <input type="number" id="id_solicitante" name="id_solicitante" class="form-control" value="<?= $lote['ID_SOLICITANTE'] ?>" required style="border-radius: 7px; padding: 10px 14px;">
        </div>

        <div class="form-group">
            <label for="estado_tramite">Estado del Trámite:</label>
            <select id="estado_tramite" name="estado_tramite" class="form-control" style="border-radius: 7px; padding: 10px 14px;">
                <option value="Borrador" <?= $lote['ESTADO_TRAMITE'] == 'Borrador' ? 'selected' : '' ?>>Borrador</option>
                <option value="Enviado" <?= $lote['ESTADO_TRAMITE'] == 'Enviado' ? 'selected' : '' ?>>Enviado</option>
                <option value="Aprobado" <?= $lote['ESTADO_TRAMITE'] == 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                <option value="Rechazado" <?= $lote['ESTADO_TRAMITE'] == 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
            </select>
        </div>

        <button type="submit" class="btn btn-sena" style="width: 100%; border-radius: 7px; padding: 12px; margin-top: 10px;">Actualizar Requerimiento</button>
    </form>
</div>

<script src="../javascript.js"></script>
</body>
</html>
