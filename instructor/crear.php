<?php
// crear.php — Creación de Lote de Requerimiento (Insert CRUD)
require_once '../conexion.php';
require_once '../csrf.php';

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
    if ($nombreLote !== '') {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO lote_requerimiento (ID_SOLICITANTE, LOTE_NOMBRE, ESTADO_TRAMITE, FECHA_CREACION) VALUES (?, ?, 'Borrador', ?)");
            $stmtInsert->execute([$usuarioId, $nombreLote, date('Y-m-d')]);
            header("Location: ../index.php");
            exit;
        } catch (\PDOException $e) {
            error_log('Error al crear lote: ' . $e->getMessage());
            $errorLote = 'Error al crear el lote. Verifique que los datos sean correctos.';
        }
    } else {
        $errorLote = 'El nombre del lote no puede estar vacío.';
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

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
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
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
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
                    <input type="text" id="lote_nombre" name="lote_nombre" class="form-control" placeholder="Ej: LOTE REDES 2026" required style="border-radius: 7px; padding: 12px 14px; font-size: 15px;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="../index.php" class="btn btn-secondary" style="border-radius: 7px; padding: 11px 22px;">Cancelar</a>
                    <button type="submit" name="btn_crear_lote" class="btn btn-sena" style="border-radius: 7px; padding: 11px 22px;">Registrar Lote</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="../javascript.js"></script>
</body>
</html>
