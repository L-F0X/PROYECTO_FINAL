<?php
// cotizacion_helper.php - recalcula los promedios de un ítem de matriz a
// partir de sus hasta 3 ofertas/cotizaciones vinculadas (matriz_item.OFERTA_1/
// OFERTA_2/OFERTA_3, que apuntan a cotizacion.ID_COTIZACION). Compartido entre
// instructor/configurar_matriz.php (agrega/quita ofertas) y
// coordinador/gestionar_oferta.php (quita ofertas durante la revisión).
function recalcular_promedios_item(PDO $pdo, int $idMatrizItem): void {
    $stmt = $pdo->prepare("SELECT OFERTA_1, OFERTA_2, OFERTA_3 FROM matriz_item WHERE ID_MATRIZ_ITEM = ?");
    $stmt->execute([$idMatrizItem]);
    $item = $stmt->fetch();
    $idsCotizacion = array_filter([$item['OFERTA_1'] ?? null, $item['OFERTA_2'] ?? null, $item['OFERTA_3'] ?? null]);

    $valorUnitarioProm = null;
    $valorTotalProm = null;

    if (!empty($idsCotizacion)) {
        $placeholders = implode(',', array_fill(0, count($idsCotizacion), '?'));
        $stmtCot = $pdo->prepare("SELECT VALOR_UNITARIO, VALOR_TOTAL FROM cotizacion WHERE ID_COTIZACION IN ($placeholders)");
        $stmtCot->execute(array_values($idsCotizacion));
        $cotizaciones = $stmtCot->fetchAll();

        if (!empty($cotizaciones)) {
            $sumaUnitario = array_sum(array_column($cotizaciones, 'VALOR_UNITARIO'));
            $sumaTotal = array_sum(array_column($cotizaciones, 'VALOR_TOTAL'));
            $count = count($cotizaciones);
            $valorUnitarioProm = (int) round($sumaUnitario / $count);
            $valorTotalProm = (int) round($sumaTotal / $count);
        }
    }

    $pdo->prepare("UPDATE matriz_item SET VALOR_UNITARIO_PROMEDIO = ?, VALOR_TOTAL_PROMEDIO = ? WHERE ID_MATRIZ_ITEM = ?")
        ->execute([$valorUnitarioProm, $valorTotalProm, $idMatrizItem]);
}
