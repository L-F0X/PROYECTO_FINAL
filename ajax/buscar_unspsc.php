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
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO, SEGMENTO_TITULO, FAMILIA_TITULO, CLASE_TITULO
            FROM codigo_unspsc
            WHERE CODIGO_UNSPSC LIKE ? AND NOMBRE_PRODUCTO IS NOT NULL
            ORDER BY CODIGO_UNSPSC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q . '%']);
} elseif (mb_strlen($q) === 1) {
    // Una sola letra: solo coincidencias que empiecen con ella (más rápido y relevante
    // que buscarla en cualquier parte del texto, que con miles de filas sería ruido).
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO, SEGMENTO_TITULO, FAMILIA_TITULO, CLASE_TITULO
            FROM codigo_unspsc
            WHERE NOMBRE_PRODUCTO LIKE ?
            ORDER BY CHAR_LENGTH(NOMBRE_PRODUCTO) ASC, NOMBRE_PRODUCTO ASC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q . '%']);
} else {
    // Prioriza los nombres que EMPIEZAN con la búsqueda sobre los que solo la
    // contienen en medio del texto (antes ambos casos se mezclaban sin orden de relevancia).
    $sql = "SELECT CODIGO_UNSPSC, NOMBRE_PRODUCTO, SEGMENTO_TITULO, FAMILIA_TITULO, CLASE_TITULO,
                   (CASE WHEN NOMBRE_PRODUCTO LIKE ? THEN 0 ELSE 1 END) AS relevancia
            FROM codigo_unspsc
            WHERE NOMBRE_PRODUCTO LIKE ?
            ORDER BY relevancia ASC, CHAR_LENGTH(NOMBRE_PRODUCTO) ASC, NOMBRE_PRODUCTO ASC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q . '%', '%' . $q . '%']);
}

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
