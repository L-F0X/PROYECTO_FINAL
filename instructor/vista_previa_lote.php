<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$idLote = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idLote <= 0) {
    header('Location: mis_lotes.php');
    exit;
}

// Obtener info del lote
$stmtLote = $pdo->prepare("SELECT LOTE_NOMBRE FROM lote_requerimiento WHERE ID_LOTE = ?");
$stmtLote->execute([$idLote]);
$loteNombre = $stmtLote->fetchColumn();

if (!$loteNombre) {
    header('Location: mis_lotes.php');
    exit;
}

// Obtener los datos de la matriz
$sql = "SELECT 
            mi.ID_MATRIZ_ITEM,
            ft.CODIGO_UNSPSC_FK,
            ft.NOMBRE_ITEM,
            ft.UNIDAD_MEDIDA,
            n.CANTIDAD_REGULAR,
            n.CANTIDAD_CAMPESINA_COMPLEMENTARIA,
            n.CANTIDAD_CAMPESINA_TITULADA,
            n.CANTIDAD_VULNERABLE,
            n.CANTIDAD_MEDIA_TECNICA,
            n.CANTIDAD_FIC,
            n.CANTIDAD_ECONOMIA_POPULAR,
            n.CANTIDAD_ENI,
            n.CANTIDAD_FC_CAMPESINA,
            n.CANTIDAD_NESECIDAD as CANTIDAD_TOTAL,
            mi.OFERTA_1,
            mi.OFERTA_2,
            mi.OFERTA_3
        FROM matriz_item mi
        JOIN ficha_tecnica ft ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
        LEFT JOIN necesidad n ON n.ID_MATRIZ = mi.ID_MATRIZ_ITEM
        WHERE mi.ID_LOTE = ?
        ORDER BY mi.ID_MATRIZ_ITEM ASC";

$stmtItems = $pdo->prepare($sql);
$stmtItems->execute([$idLote]);
$items = $stmtItems->fetchAll();

$isExport = isset($_GET['export']) && $_GET['export'] === 'excel';

if ($isExport) {
    $filename = "Lote_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $loteNombre) . "_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    // BOM for UTF-8 compatibility in Excel
    echo "\xEF\xBB\xBF";
}
?>
<?php if (!$isExport): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Previa Exportación - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .preview-container { margin: 20px auto; width: 95%; background: white; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); overflow-x: auto; }
        .actions-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-export { border-collapse: collapse; width: 100%; font-size: 11px; font-family: Arial, sans-serif; }
        .table-export th, .table-export td { border: 1px solid #000; padding: 5px; text-align: center; vertical-align: middle; }
        .header-sena { background-color: #92d050; color: #000; font-weight: bold; font-size: 14px; }
        .header-title { font-weight: bold; font-size: 16px; background-color: #fff; height: 50px; }
        .col-header { font-weight: bold; background-color: #e2efda; }
        .col-header-blue { font-weight: bold; background-color: #ddebf7; }
        .col-header-green { font-weight: bold; background-color: #e2efda; }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="actions-header no-print">
            <h2>Vista Previa del Lote: <?= htmlspecialchars($loteNombre) ?></h2>
            <div style="display: flex; gap: 10px;">
                <a href="mis_lotes.php" class="btn btn-secondary" style="padding: 10px 15px; border: 1px solid #ccc; text-decoration: none; color: #333; background: #eee;">Volver</a>
                <a href="vista_previa_lote.php?id=<?= $idLote ?>&export=excel" class="btn btn-sena" style="padding: 10px 15px; background: #39A900; text-decoration: none; color: white; font-weight: bold;">📥 Descargar Excel</a>
            </div>
        </div>
<?php endif; ?>

<table class="table-export" border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px;">
    <thead>
        <!-- Title Rows -->
        <tr>
            <th colspan="21" class="header-sena" style="background-color: #92d050; text-align: center; font-size: 12px; height: 30px;">SERVICIO NACIONAL DE APRENDIZAJE SENA - COMPLEJO TECNOLÓGICO AGROINDUSTRIAL, PECUARIO Y TURÍSTICO - APARTADÓ ANTIOQUIA</th>
        </tr>
        <tr>
            <th colspan="21" class="header-title" style="text-align: center; font-size: 16px; height: 50px;">LISTADO DE MATERIALES LOTE <?= strtoupper(htmlspecialchars($loteNombre)) ?></th>
        </tr>
        
        <!-- Headers 1 -->
        <tr>
            <th rowspan="2" class="col-header" style="background-color: #e2efda; width: 40px;">ITEM</th>
            <th rowspan="2" class="col-header" style="background-color: #e2efda; width: 100px;">CÓDIGO UNSPSC</th>
            <th rowspan="2" class="col-header" style="background-color: #e2efda; width: 300px;">DESCRIPCIÓN DE BIEN</th>
            <th rowspan="2" class="col-header" style="background-color: #e2efda; width: 120px;">UNIDAD DE MEDIDA</th>
            
            <th rowspan="2" class="col-header" style="background-color: #e2efda; width: 80px;">CANTIDAD REGULAR</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD CAMPESINA COMPLEMENTARIA</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD CAMPESINA TITULADA</th>
            <th rowspan="2" class="col-header-blue" style="background-color: #ddebf7; width: 100px;">CANTIDAD VULNERABLE</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD MEDIA TECNICA</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD FIC</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD ECONOMIA POPULAR</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD ENI</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 80px;">CANTIDAD FC CAMPESINA</th>
            
            <th colspan="2" class="col-header-blue" style="background-color: #ddebf7;">EMPRESA 1</th>
            <th colspan="2" class="col-header-blue" style="background-color: #ddebf7;">EMPRESA 2</th>
            <th colspan="2" class="col-header-blue" style="background-color: #ddebf7;">EMPRESA 3</th>
            
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 100px;">VALOR UNITARIO PROMEDIO</th>
            <th rowspan="2" class="col-header-green" style="background-color: #e2efda; width: 100px;">VALOR TOTAL PROMEDIO</th>
        </tr>
        <!-- Headers 2 -->
        <tr>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR UNITARIO</th>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR TOT. CALCULADO</th>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR UNITARIO</th>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR TOT. CALCULADO</th>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR UNITARIO</th>
            <th class="col-header-blue" style="background-color: #ddebf7; width: 100px;">VALOR TOT. CALCULADO</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $counter = 1;
        foreach ($items as $item): 
            $cantTotal = intval($item['CANTIDAD_TOTAL'] ?? 0);
            if ($cantTotal === 0) $cantTotal = intval($item['CANTIDAD_REGULAR'] ?? 0);

            $o1 = floatval($item['OFERTA_1']);
            $o2 = floatval($item['OFERTA_2']);
            $o3 = floatval($item['OFERTA_3']);

            $t1 = $o1 > 0 ? $o1 * $cantTotal : 0;
            $t2 = $o2 > 0 ? $o2 * $cantTotal : 0;
            $t3 = $o3 > 0 ? $o3 * $cantTotal : 0;

            // Calcular promedio unitario
            $ofertasValidas = 0;
            $sumaOfertas = 0;
            if ($o1 > 0) { $sumaOfertas += $o1; $ofertasValidas++; }
            if ($o2 > 0) { $sumaOfertas += $o2; $ofertasValidas++; }
            if ($o3 > 0) { $sumaOfertas += $o3; $ofertasValidas++; }

            $promedioUnitario = $ofertasValidas > 0 ? ($sumaOfertas / $ofertasValidas) : 0;
            $promedioTotal = $promedioUnitario * $cantTotal;

            $formatMoney = function($val) {
                return $val > 0 ? "$ " . number_format($val, 2, ',', '.') : "$ -";
            };
        ?>
        <tr>
            <td><?= $counter++ ?></td>
            <td><?= htmlspecialchars($item['CODIGO_UNSPSC_FK']) ?></td>
            <td style="text-align: left;"><?= htmlspecialchars($item['NOMBRE_ITEM']) ?></td>
            <td><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
            
            <td><?= intval($item['CANTIDAD_REGULAR']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_CAMPESINA_COMPLEMENTARIA']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_CAMPESINA_TITULADA']) ?: '' ?></td>
            <td style="background-color: #ddebf7;"><?= intval($item['CANTIDAD_VULNERABLE']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_MEDIA_TECNICA']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_FIC']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_ECONOMIA_POPULAR']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_ENI']) ?: '' ?></td>
            <td style="background-color: #e2efda;"><?= intval($item['CANTIDAD_FC_CAMPESINA']) ?: '' ?></td>

            <td><?= $formatMoney($o1) ?></td>
            <td><?= $formatMoney($t1) ?></td>
            
            <td><?= $formatMoney($o2) ?></td>
            <td><?= $formatMoney($t2) ?></td>
            
            <td><?= $formatMoney($o3) ?></td>
            <td><?= $formatMoney($t3) ?></td>
            
            <td style="font-weight: bold;"><?= $formatMoney($promedioUnitario) ?></td>
            <td style="font-weight: bold; background-color: #e2efda;"><?= $formatMoney($promedioTotal) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!$isExport): ?>
    </div>
</body>
</html>
<?php endif; ?>
