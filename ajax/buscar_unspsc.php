<?php
// ajax/buscar_unspsc.php
require_once '../conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

if (ctype_digit($q)) {
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO, SEGMENTO_TITULO, FAMILIA_TITULO, CLASE_TITULO
            FROM codigo_unspsc
            WHERE CODIGO_UNSPSC LIKE ? AND NOMBRE_PRODUCTO IS NOT NULL
            ORDER BY CODIGO_UNSPSC
            LIMIT 20";
    $param = $q . '%';
} else {
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO, SEGMENTO_TITULO, FAMILIA_TITULO, CLASE_TITULO
            FROM codigo_unspsc
            WHERE NOMBRE_PRODUCTO LIKE ?
            ORDER BY NOMBRE_PRODUCTO
            LIMIT 20";
    $param = '%' . $q . '%';
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$param]);
$rows = $stmt->fetchAll();

$out = array_map(function ($r) {
    return [
        'codigo' => $r['CODIGO_UNSPSC'],
        'nombre' => $r['NOMBRE_PRODUCTO'],
        'segmento_titulo' => $r['SEGMENTO_TITULO'],
        'familia_titulo' => $r['FAMILIA_TITULO'],
        'clase_titulo' => $r['CLASE_TITULO'],
    ];
}, $rows);

echo json_encode($out);
