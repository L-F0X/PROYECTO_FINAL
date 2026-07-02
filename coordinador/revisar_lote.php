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

$idLote = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idLote <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener información del lote y del instructor
try {
    $sql = "SELECT lr.*, u.NOMBRE, u.APELLIDO, u.EMAIL
            FROM lote_requerimiento lr
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            WHERE lr.ID_LOTE = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idLote]);
    $lote = $stmt->fetch();

    if (!$lote) {
        header('Location: index.php');
        exit;
    }

    // Obtener items del lote
    $sqlItems = "SELECT mi.*, ft.NOMBRE_ITEM, ft.DENOMINACION_TECNICA_BIEN, 
                 cu.CODIGO_UNSPSC, iva.PORCENTAJE
                 FROM matriz_item mi
                 LEFT JOIN ficha_tecnica ft ON mi.ID_FICHA_TECNICA = ft.ID_FICHA_TECNICA
                 LEFT JOIN codigo_unspsc cu ON mi.ID_CODIGO_UNSPSC = cu.ID_CODIGO
                 LEFT JOIN iva ON mi.ID_IVA = iva.ID_IVA
                 WHERE mi.ID_LOTE = ?
                 ORDER BY mi.ID_MATRIZ_ITEM";
    
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([$idLote]);
    $items = $stmtItems->fetchAll();

    // Obtener historial de decisiones
    $sqlHistorial = "SELECT * FROM aprobacion_rechazo_lote WHERE ID_LOTE = ? ORDER BY FECHA_DECISION DESC";
    $stmtHistorial = $pdo->prepare($sqlHistorial);
    $stmtHistorial->execute([$idLote]);
    $historial = $stmtHistorial->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching lote details: ' . $e->getMessage());
    header('Location: index.php');
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
    <title>Revisar Lote - BICERGAM</title>
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
            <a href="index.php" class="btn btn-secondary">Volver</a>
        </div>
    </div>
</header>

<div class="container fade-in" style="margin: 30px auto; max-width: 1100px;">
    <div class="role-banner role-coordinador">
        <h2>Lote: <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></h2>
        <p>ID: <?= htmlspecialchars($lote['ID_LOTE']) ?> | Estado: <?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></p>
    </div>

    <!-- Información del Instructor -->
    <div class="panel-card" style="margin-top: 20px;">
        <h3>Información del Instructor Solicitante</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">Nombre:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['NOMBRE'] . ' ' . $lote['APELLIDO']) ?></td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">Correo:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['EMAIL']) ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Rol:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">Instructor</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Fecha Creación:</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
            </tr>
        </table>
        <div style="margin-top: 15px;">
            <a href="instructores.php?id=<?= htmlspecialchars($lote['ID_SOLICITANTE']) ?>" class="btn btn-sena" style="padding: 8px 16px;">Ver más instructores</a>
        </div>
    </div>

    <!-- Items del Lote -->
    <div class="panel-card" style="margin-top: 20px;">
        <h3>Items del Lote (<?= count($items) ?>)</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">ID Item</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Descrición</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Cantidad</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Unidad</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">No hay items registrados en este lote.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></td>
                            <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($item['ESTADO_ITEM']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Historial de Decisiones -->
    <?php if (!empty($historial)): ?>
        <div class="panel-card" style="margin-top: 20px;">
            <h3>Historial de Decisiones</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Fecha</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Decisión</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Justificación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $decision): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= htmlspecialchars($decision['FECHA_DECISION']) ?></td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; <?= $decision['ESTADO_DECISION'] === 'Aprobado' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                    <?= htmlspecialchars($decision['ESTADO_DECISION']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;"><?= htmlspecialchars($decision['JUSTIFICACION'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Acciones -->
    <?php if ($lote['ESTADO_TRAMITE'] === 'Enviado'): ?>
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="aprobar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;">Aprobar Lote</a>
            <a href="rechazar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px;">Rechazar Lote</a>
        </div>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary" style="padding: 10px 20px; margin-top: 20px; display: inline-block;">Volver a Lotes</a>
</div>

</body>
</html>
