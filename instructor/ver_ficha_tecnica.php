<?php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$idFicha = isset($_GET['id']) ? intval($_GET['id']) : 0;
$idLote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;

if ($idFicha === 0) {
    header("Location: index.php");
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
$matriz_item = null;
$necesidad = null;
$iva_info = null;
$instructor_apoyo_nombre = 'Ninguno';

if ($ficha['ID_MATRIZ_ITEM']) {
    $stmtItem = $pdo->prepare("
        SELECT mi.*, lr.LOTE_NOMBRE 
        FROM matriz_item mi 
        INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE 
        WHERE mi.ID_MATRIZ_ITEM = ? LIMIT 1
    ");
    $stmtItem->execute([$ficha['ID_MATRIZ_ITEM']]);
    $itemInfo = $stmtItem->fetch();
    if ($itemInfo) {
        $loteNombre = $itemInfo['LOTE_NOMBRE'];
        $itemNombre = $itemInfo['DESCRIPCION_BIEN'];
        $matriz_item = $itemInfo;
        
        // Load IVA
        $stmtIva = $pdo->prepare("SELECT * FROM iva WHERE ID_IVA = ? LIMIT 1");
        $stmtIva->execute([$matriz_item['ID_IVA']]);
        $iva_info = $stmtIva->fetch();
        
        // Load Instructor de Apoyo
        if ($matriz_item['INSTRUCTOR_APOYO']) {
            $stmtUser = $pdo->prepare("SELECT NOMBRE, APELLIDO FROM usuario WHERE ID_USUARIO = ? LIMIT 1");
            $stmtUser->execute([$matriz_item['INSTRUCTOR_APOYO']]);
            $uApoyo = $stmtUser->fetch();
            if ($uApoyo) {
                $instructor_apoyo_nombre = $uApoyo['NOMBRE'] . ' ' . $uApoyo['APELLIDO'];
            }
        }
        
        // Load Necesidad
        if ($matriz_item['ID_NECESIDAD']) {
            $stmtNec = $pdo->prepare("SELECT * FROM necesidad WHERE ID_NECESIDAD = ? LIMIT 1");
            $stmtNec->execute([$matriz_item['ID_NECESIDAD']]);
            $necesidad = $stmtNec->fetch();
        }
    }
}

// Cargar foto de perfil
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

        /* A4 Page View styling */
        .ficha-a4-container {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 20px auto;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            box-sizing: border-box;
            border-radius: 4px;
            position: relative;
        }

        @media print {
            body {
                background: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .header-main, .dashboard-sidebar, .actions-bar, .dashboard-topbar {
                display: none !important;
            }
            .dashboard-main {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .dashboard-page {
                display: block !important;
            }
            .panel-card {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: none !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .ficha-a4-container {
                width: 210mm !important;
                height: 297mm !important;
                padding: 15mm 20mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                box-sizing: border-box !important;
                page-break-after: always;
            }
            @page {
                size: A4;
                margin: 0;
            }
        }
    </style>
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Instructor Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></div>
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
        <div class="actions-bar">
            <div>
                <h2>Ficha Técnica del Producto</h2>
                <p class="dashboard-subtitle">Visualización y exportación de la ficha técnica seleccionada.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="configurar_matriz.php?id=<?= $idFicha ?>" class="btn btn-sena" style="text-decoration: none; padding: 10px 18px; font-weight: bold; background-color: #00324D;">
                    ⚙️ Configurar Matriz
                </a>
                <button type="button" onclick="window.print()" class="btn btn-sena" style="padding: 10px 18px; font-weight: bold; background-color: #39A900;">
                    📥 Exportar a PDF
                </button>
                <a href="fichas_tecnicas_creadas.php?lote=<?= $idLote ?>" class="btn btn-secondary" style="text-decoration: none; padding: 10px 18px; border: 1px solid #ccc; background-color: #eee; color: #333;">
                    Volver al Catálogo
                </a>
            </div>
        </div>

        <div class="panel-card ficha-a4-container">
            <table class="ficha-table-view">
                <thead>
                    <tr>
                        <th colspan="2" class="ficha-header-main">FICHA TÉCNICA DE PRODUCTO</th>
                    </tr>
                </thead>
                <tbody>
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
</body>
</html>
