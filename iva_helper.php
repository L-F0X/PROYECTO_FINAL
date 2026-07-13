<?php
// iva_helper.php
// Resolución automática de la tasa de IVA vigente (por fecha de vigencia),
// para que el instructor ya no tenga que seleccionar la tasa manualmente.

function iva_columna_vigencia_existe(PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iva' AND COLUMN_NAME = 'FECHA_VIGENCIA'");
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function asegurar_columna_vigencia_iva(PDO $pdo): void {
    // ALTER TABLE es DDL: MySQL hace commit implícito de cualquier transacción
    // abierta, por eso se verifica primero por information_schema (solo SELECT).
    if (!iva_columna_vigencia_existe($pdo)) {
        $pdo->exec("ALTER TABLE iva ADD COLUMN FECHA_VIGENCIA DATE NOT NULL DEFAULT '2000-01-01'");
    }
}

function obtener_iva_vigente(PDO $pdo): ?array {
    asegurar_columna_vigencia_iva($pdo);
    $stmt = $pdo->query("SELECT * FROM iva WHERE FECHA_VIGENCIA <= CURDATE() ORDER BY FECHA_VIGENCIA DESC, ID_IVA DESC LIMIT 1");
    $row = $stmt->fetch();
    return $row ?: null;
}

// Cada oferta de un proveedor puede traer su propio porcentaje de IVA (no
// todos los proveedores facturan el mismo bien con la misma tarifa), así que
// esto no reutiliza la tasa "vigente" del ítem: busca una fila existente en
// el catálogo iva con ese porcentaje exacto, o crea una nueva si no existe.
function obtener_o_crear_iva_por_porcentaje(PDO $pdo, float $porcentaje): int {
    $stmt = $pdo->prepare("SELECT ID_IVA FROM iva WHERE PORCENTAJE = ? LIMIT 1");
    $stmt->execute([$porcentaje]);
    $idIva = $stmt->fetchColumn();
    if ($idIva) {
        return (int) $idIva;
    }
    $pdo->prepare("INSERT INTO iva (PORCENTAJE, DESCRIPCION, FECHA_VIGENCIA) VALUES (?, '', CURDATE())")->execute([$porcentaje]);
    return (int) $pdo->lastInsertId();
}
