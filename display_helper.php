<?php
// display_helper.php
// Los IDs reales (ID_LOTE, ID_MATRIZ_ITEM, ID_USUARIO...) tienen huecos por
// pruebas y datos borrados durante el desarrollo (ej. un lote con ID_LOTE=15
// cuando en realidad es apenas el 4to lote que existe). Mostrar esos números
// crudos en pantalla se ve poco profesional. Esta función traduce un ID real
// a su posición dentro de la tabla (1, 2, 3... sin huecos) SOLO para
// mostrarlo — no cambia ningún ID real ni ninguna relación en la base de
// datos, así que es seguro de usar sin riesgo de romper nada.
//
// Con $columnaScope/$valorScope, la posición se cuenta solo entre las filas
// que comparten ese valor (ej. "el 3er lote DE ESTE instructor" en vez de
// "el lote #47 de todo el sistema"), que es lo que de verdad espera ver el
// usuario dueño del dato.
function numero_visible(PDO $pdo, string $tabla, string $columnaId, ?int $id, ?string $columnaScope = null, $valorScope = null): int {
    static $cache = [];
    if ($id === null || $id <= 0) {
        return 0;
    }
    $key = $tabla . '.' . $columnaId . '.' . ($columnaScope ?? '') . '.' . ($valorScope ?? '');
    if (isset($cache[$key][$id])) {
        return $cache[$key][$id];
    }
    $sql = "SELECT COUNT(*) FROM `$tabla` WHERE `$columnaId` <= ?";
    $params = [$id];
    if ($columnaScope !== null) {
        $sql .= " AND `$columnaScope` = ?";
        $params[] = $valorScope;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rank = (int) $stmt->fetchColumn();
    $cache[$key][$id] = $rank;
    return $rank;
}

// Posición del lote entre los lotes del mismo instructor (su 1er, 2do...
// lote), no su posición entre TODOS los lotes del sistema.
function numero_visible_lote(PDO $pdo, int $idLote, int $idSolicitante): int {
    return numero_visible($pdo, 'lote_requerimiento', 'ID_LOTE', $idLote, 'ID_SOLICITANTE', $idSolicitante);
}

// Posición del ítem dentro de su propio lote (1er, 2do ítem de ESE lote).
function numero_visible_item(PDO $pdo, int $idMatrizItem, int $idLote): int {
    return numero_visible($pdo, 'matriz_item', 'ID_MATRIZ_ITEM', $idMatrizItem, 'ID_LOTE', $idLote);
}
