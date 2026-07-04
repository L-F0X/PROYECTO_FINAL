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

$usuarioId = intval($_SESSION['usuario_id']);
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

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
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
            <a href="crear_ficha_tecnica.php" class="sidebar-link">Ficha Técnica</a>
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
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
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

                <div style="display: flex; gap: 12px; margin-top: 15px;">
                    <a href="../index.php" class="btn btn-secondary" style="border-radius: 7px; padding: 11px 22px;">Cancelar</a>
                    <button type="submit" class="btn btn-sena" style="border-radius: 7px; padding: 11px 22px;">Actualizar Requerimiento</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="../javascript.js"></script>
</body>
</html>
