<?php
// certificado_helper.php - existencia real por ítem en el certificado de
// existencia. El certificado ya no es solo una autorización de compra a
// nivel de lote: por cada ítem solicitado se registra si YA hay esa
// cantidad (o más) en el stock físico del almacén (ficha_tecnica con
// ID_MATRIZ_ITEM NULL), emparejando por código UNSPSC (el único campo que
// comparten de forma confiable el lado "solicitud" y el lado "stock" —
// comparar por nombre de artículo sería frágil, texto libre no normalizado).
// Es una FOTO al momento de emitir el certificado (no un valor en vivo),
// igual que el resto de datos de un certificado ya emitido.

function asegurar_tabla_certificado_item(PDO $pdo): void {
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificado_existencia_item'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS certificado_existencia_item (
            ID_CERT_ITEM INT AUTO_INCREMENT PRIMARY KEY,
            ID_CERTIFICADO INT NOT NULL,
            ID_MATRIZ_ITEM INT NOT NULL,
            EN_EXISTENCIA TINYINT(1) NOT NULL DEFAULT 0,
            CANTIDAD_DISPONIBLE INT NOT NULL DEFAULT 0,
            FOREIGN KEY (ID_CERTIFICADO) REFERENCES certificado_existencia(ID_CERTIFICADO) ON DELETE CASCADE,
            FOREIGN KEY (ID_MATRIZ_ITEM) REFERENCES matriz_item(ID_MATRIZ_ITEM) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $verificada = true;
}

// Calcula y guarda, para cada ítem del lote, si ya hay existencia real en
// stock (sumando el stock físico -ID_MATRIZ_ITEM IS NULL- cuyo código UNSPSC
// coincida con el del ítem solicitado). Se llama una sola vez, al emitir el
// certificado; los datos quedan fijos desde ese momento.
function registrar_existencia_certificado(PDO $pdo, int $idCertificado, int $idLote): void {
    asegurar_tabla_certificado_item($pdo);

    // Se toma el código UNSPSC desde la ficha técnica propia del ítem
    // (ID_FICHA_TECNICA -> CODIGO_UNSPSC_FK, texto) en vez de
    // ID_CODIGO_UNSPSC -> codigo_unspsc.ID_CODIGO: ese segundo camino puede
    // quedar apuntando a un ID que ya no existe si el catálogo UNSPSC se
    // reimportó después de crear el ítem, mientras que CODIGO_UNSPSC_FK es
    // el mismo código de texto que también usa el lado de stock.
    $stmtItems = $pdo->prepare("
        SELECT mi.ID_MATRIZ_ITEM, ft.CODIGO_UNSPSC_FK AS CODIGO_UNSPSC
        FROM matriz_item mi
        LEFT JOIN ficha_tecnica ft ON mi.ID_FICHA_TECNICA = ft.ID_FICHA_TECNICA
        WHERE mi.ID_LOTE = ?
    ");
    $stmtItems->execute([$idLote]);
    $items = $stmtItems->fetchAll();

    $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(CANTIDAD), 0) FROM ficha_tecnica WHERE ID_MATRIZ_ITEM IS NULL AND CODIGO_UNSPSC_FK = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO certificado_existencia_item (ID_CERTIFICADO, ID_MATRIZ_ITEM, EN_EXISTENCIA, CANTIDAD_DISPONIBLE) VALUES (?, ?, ?, ?)");

    foreach ($items as $item) {
        $cantidadDisponible = 0;
        if (!empty($item['CODIGO_UNSPSC'])) {
            $stmtStock->execute([$item['CODIGO_UNSPSC']]);
            $cantidadDisponible = (int) $stmtStock->fetchColumn();
        }
        $stmtInsert->execute([$idCertificado, $item['ID_MATRIZ_ITEM'], $cantidadDisponible > 0 ? 1 : 0, $cantidadDisponible]);
    }
}

// Devuelve, para un lote ya certificado, un mapa ID_MATRIZ_ITEM => fila de
// existencia (['EN_EXISTENCIA' => 0|1, 'CANTIDAD_DISPONIBLE' => int]). Vacío
// si el lote aún no tiene certificado emitido.
function obtener_existencia_lote(PDO $pdo, int $idLote): array {
    asegurar_tabla_certificado_item($pdo);
    $stmt = $pdo->prepare("
        SELECT cei.ID_MATRIZ_ITEM, cei.EN_EXISTENCIA, cei.CANTIDAD_DISPONIBLE
        FROM certificado_existencia_item cei
        INNER JOIN certificado_existencia ce ON cei.ID_CERTIFICADO = ce.ID_CERTIFICADO
        WHERE ce.ID_LOTE = ?
    ");
    $stmt->execute([$idLote]);
    $mapa = [];
    foreach ($stmt->fetchAll() as $fila) {
        $mapa[(int) $fila['ID_MATRIZ_ITEM']] = $fila;
    }
    return $mapa;
}

// --- Certificado de Inventario General ---
// A diferencia del certificado por lote (que compara lo solicitado contra el
// stock), este certifica el inventario físico completo tal cual está en el
// momento de emitirlo — no depende de ninguna solicitud de instructor. Se
// guarda una copia (nombre/código/unidad/cantidad) de cada artículo en vez
// de solo su ID, para que el certificado quede fijo aunque el artículo se
// edite o elimine del stock después.

function asegurar_tablas_certificado_inventario(PDO $pdo): void {
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificado_inventario'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS certificado_inventario (
            ID_CERTIFICADO_INV INT AUTO_INCREMENT PRIMARY KEY,
            NUMERO_CERTIFICADO VARCHAR(50) NOT NULL UNIQUE,
            FECHA_EMISION TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ID_ALMACENISTA INT DEFAULT NULL,
            FOREIGN KEY (ID_ALMACENISTA) REFERENCES usuario(ID_USUARIO) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificado_inventario_item'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS certificado_inventario_item (
            ID_ITEM INT AUTO_INCREMENT PRIMARY KEY,
            ID_CERTIFICADO_INV INT NOT NULL,
            ID_FICHA_TECNICA INT DEFAULT NULL,
            NOMBRE_ITEM VARCHAR(150) NOT NULL,
            CODIGO_UNSPSC VARCHAR(20) DEFAULT NULL,
            UNIDAD_MEDIDA VARCHAR(50) DEFAULT NULL,
            CANTIDAD INT NOT NULL DEFAULT 0,
            FOREIGN KEY (ID_CERTIFICADO_INV) REFERENCES certificado_inventario(ID_CERTIFICADO_INV) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $verificada = true;
}

// Emite un certificado nuevo con una copia de todo el inventario físico
// actual (ficha_tecnica con ID_MATRIZ_ITEM NULL). Devuelve el ID del
// certificado creado.
function emitir_certificado_inventario(PDO $pdo, int $idAlmacenista): int {
    asegurar_tablas_certificado_inventario($pdo);

    $numero = "CERT-INV-" . date('Ymd') . "-" . time();
    $pdo->prepare("INSERT INTO certificado_inventario (NUMERO_CERTIFICADO, ID_ALMACENISTA) VALUES (?, ?)")
        ->execute([$numero, $idAlmacenista]);
    $idCertificado = (int) $pdo->lastInsertId();

    $stmtStock = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD FROM ficha_tecnica WHERE ID_MATRIZ_ITEM IS NULL ORDER BY NOMBRE_ITEM");
    $stmtInsert = $pdo->prepare("INSERT INTO certificado_inventario_item (ID_CERTIFICADO_INV, ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC, UNIDAD_MEDIDA, CANTIDAD) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($stmtStock->fetchAll() as $item) {
        $stmtInsert->execute([
            $idCertificado,
            $item['ID_FICHA_TECNICA'],
            $item['NOMBRE_ITEM'],
            $item['CODIGO_UNSPSC_FK'],
            $item['UNIDAD_MEDIDA'],
            (int) $item['CANTIDAD'],
        ]);
    }

    // Solo se conservan los últimos 5 certificados de inventario: al emitir
    // uno nuevo estando ya en el máximo, se elimina el más antiguo (FIFO).
    // Sus ítems se borran solos por el ON DELETE CASCADE de la tabla.
    $maxCertificadosInventario = 5;
    $totalCertificados = (int) $pdo->query("SELECT COUNT(*) FROM certificado_inventario")->fetchColumn();
    if ($totalCertificados > $maxCertificadosInventario) {
        $sobrantes = $totalCertificados - $maxCertificadosInventario;
        $stmtViejos = $pdo->prepare("SELECT ID_CERTIFICADO_INV FROM certificado_inventario ORDER BY FECHA_EMISION ASC, ID_CERTIFICADO_INV ASC LIMIT ?");
        $stmtViejos->bindValue(1, $sobrantes, PDO::PARAM_INT);
        $stmtViejos->execute();
        $idsViejos = $stmtViejos->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($idsViejos)) {
            $placeholders = implode(',', array_fill(0, count($idsViejos), '?'));
            $pdo->prepare("DELETE FROM certificado_inventario WHERE ID_CERTIFICADO_INV IN ($placeholders)")->execute($idsViejos);
        }
    }

    return $idCertificado;
}
