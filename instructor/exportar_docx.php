<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$idFicha = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idFicha === 0) {
    die("ID de ficha técnica no válido.");
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

// Generar nombre de archivo
$filename = "Ficha_Tecnica_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $ficha['NOMBRE_ITEM']) . "_" . $idFicha . ".doc";

// Enviar cabeceras para forzar la descarga en formato Word
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.3;
        }
        table.ficha-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.ficha-table td, table.ficha-table th {
            border: 1px solid #333333;
            padding: 8px 10px;
            font-size: 10pt;
        }
        .header-main {
            background-color: #f0f0f0;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .section-title {
            background-color: #d8d8d8;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .label-col {
            width: 30%;
            background-color: #f0f0f0;
            font-weight: bold;
            text-transform: uppercase;
        }
        .value-col {
            width: 70%;
        }
        .full-value {
            text-align: center;
        }
        .desc-text {
            font-size: 10pt;
        }
        .footer-note {
            font-weight: bold;
            font-size: 8pt;
            color: #444;
            text-align: center;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <table class="ficha-table">
        <thead>
            <tr>
                <th colspan="2" class="header-main" style="padding: 12px;">FICHA TÉCNICA DE PRODUCTO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="label-col">LOTE DESTINO</td>
                <td class="value-col"><?= htmlspecialchars($loteNombre) ?></td>
            </tr>
            <tr>
                <td class="label-col">ASOCIAR A UN ÍTEM EXISTENTE</td>
                <td class="value-col"><?= htmlspecialchars($itemNombre) ?></td>
            </tr>
            <tr>
                <td class="label-col">NOMBRE DEL ÍTEM</td>
                <td class="value-col"><?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?></td>
            </tr>
            <tr>
                <td class="label-col">CÓDIGO UNSPSC</td>
                <td class="value-col"><?= htmlspecialchars($ficha['CODIGO_UNSPSC_FK'] ?: 'SIN_ASIGNAR') ?></td>
            </tr>

            <tr>
                <th colspan="2" class="section-title">DENOMINACIÓN TÉCNICA DEL BIEN</th>
            </tr>
            <tr>
                <td colspan="2" class="full-value">
                    <?= htmlspecialchars($ficha['DENOMINACION_TECNICA_BIEN']) ?>
                </td>
            </tr>

            <tr>
                <th colspan="2" class="section-title">UNIDAD DE MEDIDA</th>
            </tr>
            <tr>
                <td colspan="2" class="full-value">
                    <?= htmlspecialchars($ficha['UNIDAD_MEDIDA']) ?>
                </td>
            </tr>

            <tr>
                <th colspan="2" class="section-title">CANTIDAD</th>
            </tr>
            <tr>
                <td colspan="2" class="full-value">
                    <?= htmlspecialchars($ficha['CANTIDAD']) ?>
                </td>
            </tr>

            <tr>
                <th colspan="2" class="section-title">DESCRIPCIÓN GENERAL</th>
            </tr>
            <tr>
                <td colspan="2">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="width: 75%; border: none; padding: 0; vertical-align: top;">
                                <div class="desc-text">
                                    <?= nl2br(htmlspecialchars($ficha['DESCRIPCION_GENERAL'])) ?>
                                </div>
                            </td>
                            <td style="width: 25%; border: none; padding: 0; text-align: center; vertical-align: middle;">
                                <?php if ($ficha['IMAGEN']): ?>
                                    <img src="http://<?= $_SERVER['HTTP_HOST'] ?>/PROYECTO_FINAL/<?= htmlspecialchars($ficha['IMAGEN']) ?>" style="max-width: 140px; max-height: 140px;" />
                                <?php else: ?>
                                    <div style="border: 1px dashed #999; padding: 10px; font-size: 8pt; color: #666;">
                                        [Imagen de referencia]
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <div class="footer-note">
                        La imagen es de referencia
                    </div>
                </td>
            </tr>

            <tr>
                <th colspan="2" class="section-title">COMENTARIOS / ESPECIFICACIONES ADICIONALES</th>
            </tr>
            <tr>
                <td colspan="2" style="height: 50px;">
                    <?= nl2br(htmlspecialchars($ficha['COMENTARIOS'] ?: 'Sin comentarios adicionales.')) ?>
                </td>
            </tr>

            <tr>
                <td class="label-col">MARCA OFRECIDA</td>
                <td class="value-col">N/A</td>
            </tr>
            <tr>
                <td class="label-col">FIRMA DEL PROPONENTE</td>
                <td class="value-col">N/A</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
