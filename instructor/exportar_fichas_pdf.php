<?php
// instructor/exportar_fichas_pdf.php
require_once '../conexion.php';
require_once '../display_helper.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['usuario_id']) || strtolower(trim($_SESSION['rol_nombre'] ?? '')) !== 'instructor') {
    die('Acceso denegado');
}

$idLote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;
if ($idLote === 0) {
    die('Lote no válido');
}

$usuarioId = intval($_SESSION['usuario_id']);
$stmtLoteCheck = $pdo->prepare("SELECT ID_SOLICITANTE FROM lote_requerimiento WHERE ID_LOTE = ?");
$stmtLoteCheck->execute([$idLote]);
$idSolicitanteLote = $stmtLoteCheck->fetchColumn();
if ($idSolicitanteLote === false || (int) $idSolicitanteLote !== $usuarioId) {
    die('Acceso denegado');
}

$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica ft
        INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
        WHERE mi.ID_LOTE = ? ORDER BY ft.ID_FICHA_TECNICA ASC");
$stmt->execute([$idLote]);
$fichas = $stmt->fetchAll();

if (empty($fichas)) {
    die('No hay fichas técnicas para exportar en este lote.');
}

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fichas Técnicas del Lote #' . numero_visible_lote($pdo, $idLote, $usuarioId) . '</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .ficha-container { width: 100%; margin: 0; padding: 0; page-break-after: always; }
        .ficha-container:last-child { page-break-after: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; vertical-align: top; }
        .header-main { background-color: #f0f0f0; text-align: center; font-size: 14px; font-weight: bold; }
        .section-title { background-color: #d8d8d8; font-weight: bold; text-align: center; }
        .label-col { width: 30%; background-color: #f0f0f0; font-weight: bold; font-size: 10px; }
        .value-col { width: 70%; }
        .full-value { text-align: center; }
        img.ref-img { max-height: 130px; max-width: 150px; object-fit: contain; }
    </style>
</head>
<body>';

foreach ($fichas as $index => $ficha) {
    $imgHtml = '<div style="color: #999; text-align: center; margin-top: 20px;">Sin imagen</div>';
    if (!empty($ficha['IMAGEN'])) {
        $imgPath = __DIR__ . '/../' . $ficha['IMAGEN'];
        if (file_exists($imgPath)) {
            $type = pathinfo($imgPath, PATHINFO_EXTENSION);
            $data = file_get_contents($imgPath);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            $imgHtml = '<div style="text-align: center;"><img src="' . $base64 . '" class="ref-img"><br><i style="font-size:9px; color:#555;">Imagen de referencia</i></div>';
        }
    }

    $html .= '
    <div class="ficha-container">
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="header-main">FICHA TÉCNICA DE PRODUCTO (N° ' . ($index + 1) . ')</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label-col">NOMBRE DEL ÍTEM</td>
                    <td class="value-col">' . htmlspecialchars($ficha['NOMBRE_ITEM']) . '</td>
                </tr>
                <tr>
                    <td class="label-col">CÓDIGO UNSPSC</td>
                    <td class="value-col">' . htmlspecialchars($ficha['CODIGO_UNSPSC_FK'] ?: 'SIN_ASIGNAR') . '</td>
                </tr>
                <tr>
                    <th colspan="2" class="section-title">DENOMINACIÓN TÉCNICA DEL BIEN</th>
                </tr>
                <tr>
                    <td colspan="2" class="full-value">' . htmlspecialchars($ficha['DENOMINACION_TECNICA_BIEN']) . '</td>
                </tr>
                <tr>
                    <th colspan="2" class="section-title">UNIDAD DE MEDIDA</th>
                </tr>
                <tr>
                    <td colspan="2" class="full-value">' . htmlspecialchars($ficha['UNIDAD_MEDIDA']) . '</td>
                </tr>
                <tr>
                    <th colspan="2" class="section-title">DESCRIPCIÓN GENERAL</th>
                </tr>
                <tr>
                    <td colspan="2">
                        <table style="width: 100%; border: none; margin: 0; padding: 0;">
                            <tr>
                                <td style="border: none; width: 70%; padding: 0; padding-right: 15px;">' . nl2br(htmlspecialchars($ficha['DESCRIPCION_GENERAL'])) . '</td>
                                <td style="border: none; width: 30%; padding: 0; text-align: center;">' . $imgHtml . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <th colspan="2" class="section-title">COMENTARIOS / ESPECIFICACIONES ADICIONALES</th>
                </tr>
                <tr>
                    <td colspan="2" style="height: 60px;">' . nl2br(htmlspecialchars($ficha['COMENTARIOS'] ?: 'Sin comentarios adicionales.')) . '</td>
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
    </div>';
}

$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Fichas_Lote_" . $idLote . "_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;
