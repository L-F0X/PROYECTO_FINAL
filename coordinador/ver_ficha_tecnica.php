<?php
require_once '../conexion.php';
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

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$idFicha = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idFicha === 0) {
    header('Location: fichas_tecnicas_coordinador.php');
    exit;
}

// Cargar la ficha técnica
$stmtFicha = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
$stmtFicha->execute([$idFicha]);
$ficha = $stmtFicha->fetch();

if (!$ficha) {
    die("Ficha técnica no encontrada.");
}

// Obtener datos del lote e ítem si existen
$loteNombre = 'N/A';
$itemNombre = 'N/A';
if ($ficha['ID_MATRIZ_ITEM']) {
    $stmtItem = $pdo->prepare("
        SELECT mi.DESCRIPCION_BIEN, lr.LOTE_NOMBRE
        FROM matriz_item mi
        INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
        WHERE mi.ID_MATRIZ_ITEM = ? LIMIT 1
    ");
    $stmtItem->execute([$ficha['ID_MATRIZ_ITEM']]);
    $itemInfo = $stmtItem->fetch();
    if ($itemInfo) {
        $loteNombre = $itemInfo['LOTE_NOMBRE'];
        $itemNombre = $itemInfo['DESCRIPCION_BIEN'];
    }
}

// Cargar foto de perfil
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
    <title>Ver Ficha Técnica - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .ficha-table-view {
            width: 100%;

            margin: 20px 0;
            font-family: Arial, sans-serif;
            border: 2px solid #333;
            background-color: #fff;
        }

        .ficha-table-view th, .ficha-table-view td {
            border: 1px solid #333;
            padding: 10px 12px;
            font-size: 13px;
        }

        .ficha-header-main {
            background-color: #f0f0f0;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px !important;
        }

        .ficha-section-title {
            background-color: #d8d8d8;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ficha-label-col {
            width: 30%;
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }

        .ficha-value-col {
            width: 70%;
            background-color: #ffffff;
        }

        .ficha-full-value {
            text-align: center;
            background-color: #ffffff;
            font-size: 13px;
        }

        .ficha-desc-container {
            display: flex;
            gap: 15px;
            align-items: stretch;
        }

        .ficha-desc-text {
            flex: 1;
            white-space: pre-line;
            font-size: 13px;
        }

        .ficha-desc-image-box {
            width: 160px;
            border: 1px dashed #999;
            background: #f7f7f7;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px;
            font-size: 11px;
            color: #666;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
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
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link sidebar-link--primary active">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="actions-bar">
            <div>
                <h2>Ficha Técnica del Producto</h2>
                <p class="dashboard-subtitle">Visualización y exportación de la ficha técnica seleccionada.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="window.print()" class="btn btn-sena" style="padding: 10px 18px; font-weight: bold; background-color: #39A900;">
                    📥 Exportar a PDF
                </button>
                <a href="fichas_tecnicas_coordinador.php" class="btn btn-secondary" style="text-decoration: none; padding: 10px 18px; border: 1px solid #ccc; background-color: #eee; color: #333;">
                    Volver al Catálogo
                </a>
            </div>
        </div>

        <div class="panel-card" style="max-width: 820px; width: 100%; margin: 0 auto; padding: 20px;">
            <table class="ficha-table-view">
                <thead>
                    <tr>
                        <th colspan="2" class="ficha-header-main">FICHA TÉCNICA DE PRODUCTO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ficha-label-col">LOTE DESTINO</td>
                        <td class="ficha-value-col"><?= htmlspecialchars($loteNombre) ?></td>
                    </tr>
                    <tr>
                        <td class="ficha-label-col">ASOCIAR A UN ÍTEM EXISTENTE</td>
                        <td class="ficha-value-col"><?= htmlspecialchars($itemNombre) ?></td>
                    </tr>
                    <tr>
                        <td class="ficha-label-col">NOMBRE DEL ÍTEM</td>
                        <td class="ficha-value-col"><?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?></td>
                    </tr>
                    <tr>
                        <td class="ficha-label-col">CÓDIGO UNSPSC</td>
                        <td class="ficha-value-col"><?= htmlspecialchars($ficha['CODIGO_UNSPSC_FK'] ?: 'SIN_ASIGNAR') ?></td>
                    </tr>

                    <tr>
                        <th colspan="2" class="ficha-section-title">DENOMINACIÓN TÉCNICA DEL BIEN</th>
                    </tr>
                    <tr>
                        <td colspan="2" class="ficha-full-value">
                            <?= htmlspecialchars($ficha['DENOMINACION_TECNICA_BIEN']) ?>
                        </td>
                    </tr>

                    <tr>
                        <th colspan="2" class="ficha-section-title">UNIDAD DE MEDIDA</th>
                    </tr>
                    <tr>
                        <td colspan="2" class="ficha-full-value">
                            <?= htmlspecialchars($ficha['UNIDAD_MEDIDA']) ?>
                        </td>
                    </tr>

                    <tr>
                        <th colspan="2" class="ficha-section-title">CANTIDAD</th>
                    </tr>
                    <tr>
                        <td colspan="2" class="ficha-full-value">
                            <?= htmlspecialchars($ficha['CANTIDAD']) ?>
                        </td>
                    </tr>

                    <tr>
                        <th colspan="2" class="ficha-section-title">DESCRIPCIÓN GENERAL</th>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="ficha-desc-container">
                                <div class="ficha-desc-text">
                                    <?= nl2br(htmlspecialchars($ficha['DESCRIPCION_GENERAL'])) ?>
                                </div>
                                <div class="ficha-desc-image-box" style="<?= $ficha['IMAGEN'] ? 'border:none; background:none;' : '' ?>">
                                    <?php if ($ficha['IMAGEN']): ?>
                                        <img src="../<?= htmlspecialchars($ficha['IMAGEN']) ?>" alt="Imagen de referencia" style="max-width: 100%; max-height: 140px; object-fit: contain;" />
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="1.5" style="margin-bottom: 5px;">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                        Imagen de referencia
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-weight: bold; font-size: 11px; color: #444; text-align: center; margin-top: 8px; font-style: italic;">
                                La imagen es de referencia
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th colspan="2" class="ficha-section-title">COMENTARIOS / ESPECIFICACIONES ADICIONALES</th>
                    </tr>
                    <tr>
                        <td colspan="2" style="min-height: 50px; background-color: #ffffff;">
                            <?= nl2br(htmlspecialchars($ficha['COMENTARIOS'] ?: 'Sin comentarios adicionales.')) ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="ficha-label-col">MARCA OFRECIDA</td>
                        <td class="ficha-value-col">
                            N/A
                        </td>
                    </tr>
                    <tr>
                        <td class="ficha-label-col">FIRMA DEL PROPONENTE</td>
                        <td class="ficha-value-col">
                            N/A
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
