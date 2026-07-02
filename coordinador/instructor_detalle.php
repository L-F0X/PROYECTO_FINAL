<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

$idInstructor = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idInstructor <= 0) {
    header('Location: instructores.php');
    exit;
}

// Obtener información del instructor
try {
    $sql = "SELECT * FROM usuario WHERE ID_USUARIO = ? AND ID_ROL = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idInstructor]);
    $instructor = $stmt->fetch();

    if (!$instructor) {
        header('Location: instructores.php');
        exit;
    }

    // Obtener lotes del instructor
    $sqlLotes = "SELECT lr.*, 
                (SELECT COUNT(*) FROM matriz_item WHERE ID_LOTE = lr.ID_LOTE) as ITEMS_COUNT
                FROM lote_requerimiento lr
                WHERE lr.ID_SOLICITANTE = ?
                ORDER BY lr.FECHA_CREACION DESC";
    
    $stmtLotes = $pdo->prepare($sqlLotes);
    $stmtLotes->execute([$idInstructor]);
    $lotes = $stmtLotes->fetchAll();

    // Obtener instructores de apoyo con los que ha trabajado
    $sqlApoyo = "SELECT DISTINCT u.* FROM usuario u
                INNER JOIN matriz_item mi ON u.ID_USUARIO = mi.INSTRUCTOR_APOYO
                INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
                WHERE lr.ID_SOLICITANTE = ?";
    
    $stmtApoyo = $pdo->prepare($sqlApoyo);
    $stmtApoyo->execute([$idInstructor]);
    $apoyo = $stmtApoyo->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching instructor details: ' . $e->getMessage());
    header('Location: instructores.php');
    exit;
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
    <title>Detalle Instructor - BICERGAM</title>
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
            Bienvenido: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="coordinador_profile.php" class="header-avatar-link" title="Editar perfil">
                <?php if ($photoPath): ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
                <?php else: ?>
                    <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
                <?php endif; ?>
            </a>
            <a href="instructores.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>
</header>

<div class="container fade-in" style="margin: 30px auto; max-width: 1100px;">
    <div class="role-banner role-coordinador">
        <h2><?= htmlspecialchars($instructor['NOMBRE'] . ' ' . $instructor['APELLIDO']) ?></h2>
        <p><?= htmlspecialchars($instructor['EMAIL']) ?></p>
    </div>

    <!-- Información del Instructor -->
    <div class="panel-card" style="margin-top: 20px;">
        <h3>Información Personal</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">ID Usuario:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['ID_USUARIO']) ?></td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">Documento:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['DOCUMENTO']) ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Correo:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['EMAIL']) ?></td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Estado:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <span style="padding: 4px 8px; border-radius: 4px; <?= $instructor['ESTADO'] === 'Activo' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                        <?= htmlspecialchars($instructor['ESTADO']) ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Lotes del Instructor -->
    <div class="panel-card" style="margin-top: 20px;">
        <h3>Lotes Creados (<?= count($lotes) ?>)</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">ID Lote</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Nombre</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Items</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Estado</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Fecha Creación</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lotes)): ?>
                    <tr>
                        <td colspan="6" style="padding: 20px; text-align: center; color: #999;">Este instructor no ha creado lotes.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lotes as $lote): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                            <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($lote['ITEMS_COUNT']) ?></td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; <?= $lote['ESTADO_TRAMITE'] === 'Aprobado' ? 'background: #d4edda; color: #155724;' : ($lote['ESTADO_TRAMITE'] === 'Rechazado' ? 'background: #f8d7da; color: #721c24;' : 'background: #fff3cd; color: #856404;') ?>">
                                    <?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;"><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                            <td style="padding: 12px;">
                                <a href="revisar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Instructores de Apoyo -->
    <?php if (!empty($apoyo)): ?>
        <div class="panel-card" style="margin-top: 20px;">
            <h3>Instructores de Apoyo Asociados (<?= count($apoyo) ?>)</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Nombre</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Correo</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apoyo as $apoyoInstructor): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= htmlspecialchars($apoyoInstructor['NOMBRE'] . ' ' . $apoyoInstructor['APELLIDO']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($apoyoInstructor['EMAIL']) ?></td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; <?= $apoyoInstructor['ESTADO'] === 'Activo' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                    <?= htmlspecialchars($apoyoInstructor['ESTADO']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <a href="instructores.php" class="btn btn-secondary" style="padding: 10px 20px; margin-top: 20px; display: inline-block;">Volver a Instructores</a>
</div>

</body>
</html>
