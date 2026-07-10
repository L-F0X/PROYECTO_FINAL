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
if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

if (ctype_digit($q)) {
    // If digits, search by code (primarily) or name, prioritizing exact prefix code matches
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO
            FROM codigo_unspsc
            WHERE CODIGO_UNSPSC LIKE ? OR NOMBRE_PRODUCTO LIKE ?
            ORDER BY (CASE WHEN CODIGO_UNSPSC LIKE ? THEN 0 WHEN NOMBRE_PRODUCTO LIKE ? THEN 1 ELSE 2 END) ASC, CODIGO_UNSPSC ASC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q . '%', '%' . $q . '%', $q . '%', $q . '%']);
} else {
    // If text, search by name or code, prioritizing prefix matches in name
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO
            FROM codigo_unspsc
            WHERE NOMBRE_PRODUCTO LIKE ? OR CODIGO_UNSPSC LIKE ?
            ORDER BY (CASE WHEN NOMBRE_PRODUCTO LIKE ? THEN 0 ELSE 1 END) ASC, CHAR_LENGTH(NOMBRE_PRODUCTO) ASC, NOMBRE_PRODUCTO ASC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $q . '%', '%' . $q . '%', $q . '%']);
}

$rows = $stmt->fetchAll();

$out = array_map(function ($r) {
    return [
        'codigo' => $r['CODIGO_UNSPSC'],
        'nombre' => $r['NOMBRE_PRODUCTO'],
        'segmento_titulo' => '',
        'familia_titulo' => '',
        'clase_titulo' => '',
    ];
}, $rows);

echo json_encode($out);
exit;

