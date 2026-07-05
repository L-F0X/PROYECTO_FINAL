<?php
// Administrador/unspsc_import_lib.php
// Lógica de migración/importación del catálogo UNSPSC, reutilizable desde la UI de administración o desde CLI.

function unspsc_columna_existe(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tabla, $columna]);
    return (bool) $stmt->fetchColumn();
}

function unspsc_asegurar_columnas(PDO $pdo): void {
    $columnas = [
        'SEGMENTO_TITULO'      => "ALTER TABLE codigo_unspsc ADD COLUMN SEGMENTO_TITULO VARCHAR(150) DEFAULT NULL",
        'FAMILIA_TITULO'       => "ALTER TABLE codigo_unspsc ADD COLUMN FAMILIA_TITULO VARCHAR(150) DEFAULT NULL",
        'CLASE_TITULO'         => "ALTER TABLE codigo_unspsc ADD COLUMN CLASE_TITULO VARCHAR(150) DEFAULT NULL",
        'NOMBRE_PRODUCTO'      => "ALTER TABLE codigo_unspsc ADD COLUMN NOMBRE_PRODUCTO VARCHAR(255) DEFAULT NULL",
        'DESCRIPCION_PRODUCTO' => "ALTER TABLE codigo_unspsc ADD COLUMN DESCRIPCION_PRODUCTO TEXT DEFAULT NULL",
    ];
    foreach ($columnas as $nombre => $ddl) {
        if (!unspsc_columna_existe($pdo, 'codigo_unspsc', $nombre)) {
            $pdo->exec($ddl);
        }
    }
}

function unspsc_importar_csv(PDO $pdo, string $ruta): array {
    if (!file_exists($ruta)) {
        throw new Exception("No se encontró el archivo CSV en: $ruta");
    }
    $handle = fopen($ruta, 'r');
    if (!$handle) {
        throw new Exception('No se pudo abrir el archivo CSV.');
    }

    // Saltar la fila de cabecera
    fgetcsv($handle, 0, ';');

    $sql = "INSERT INTO codigo_unspsc
                (SEGMENTO, SEGMENTO_TITULO, FAMILIA, FAMILIA_TITULO, CLASE, CLASE_TITULO, CODIGO_UNSPSC, NOMBRE_PRODUCTO, DESCRIPCION_PRODUCTO)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                SEGMENTO = VALUES(SEGMENTO),
                SEGMENTO_TITULO = VALUES(SEGMENTO_TITULO),
                FAMILIA = VALUES(FAMILIA),
                FAMILIA_TITULO = VALUES(FAMILIA_TITULO),
                CLASE = VALUES(CLASE),
                CLASE_TITULO = VALUES(CLASE_TITULO),
                NOMBRE_PRODUCTO = VALUES(NOMBRE_PRODUCTO),
                DESCRIPCION_PRODUCTO = VALUES(DESCRIPCION_PRODUCTO)";
    $stmt = $pdo->prepare($sql);

    $leidas = 0;
    $insertadas = 0;
    $actualizadas = 0;
    $errores = 0;
    $loteSize = 500;
    $enLote = 0;

    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $leidas++;
            if (count($row) < 16) {
                $errores++;
                continue;
            }

            $conv = array_map(function ($v) {
                $v = $v === null ? '' : trim($v);
                return mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
            }, $row);

            $segmento            = $conv[4];
            $segmentoTitulo      = $conv[5];
            $familia             = $conv[7];
            $familiaTitulo       = $conv[8];
            $clase               = $conv[10];
            $claseTitulo         = $conv[11];
            $producto            = $conv[13];
            $tituloProducto      = $conv[14];
            $descripcionProducto = $conv[15];

            if ($producto === '' || $tituloProducto === '') {
                $errores++;
                continue;
            }

            $stmt->execute([
                $segmento, $segmentoTitulo, $familia, $familiaTitulo, $clase, $claseTitulo,
                $producto, $tituloProducto, $descripcionProducto !== '' ? $descripcionProducto : null
            ]);

            $rc = $stmt->rowCount();
            if ($rc === 1) {
                $insertadas++;
            } elseif ($rc === 2) {
                $actualizadas++;
            }

            $enLote++;
            if ($enLote >= $loteSize) {
                $pdo->commit();
                $pdo->beginTransaction();
                $enLote = 0;
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fclose($handle);
        throw $e;
    }

    fclose($handle);

    return ['leidas' => $leidas, 'insertadas' => $insertadas, 'actualizadas' => $actualizadas, 'errores' => $errores];
}
