<?php
// almacenista/dashboard_stats.php
// Componente de métricas rápidas de inventario
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}

$stats = [
    'total' => 0,
    'optimo' => 0,
    'critico' => 0,
    'agotado' => 0,
    'certificados' => 0
];

try {
    // Total, óptimo, crítico, agotado — solo stock físico propio del almacén
    // (ID_MATRIZ_ITEM NULL); las fichas creadas por un instructor son solicitudes, no inventario real.
    $stmtStats = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN CANTIDAD > 5 THEN 1 ELSE 0 END) as optimo,
            SUM(CASE WHEN CANTIDAD > 0 AND CANTIDAD <= 5 THEN 1 ELSE 0 END) as critico,
            SUM(CASE WHEN CANTIDAD = 0 THEN 1 ELSE 0 END) as agotado
        FROM ficha_tecnica
        WHERE ID_MATRIZ_ITEM IS NULL
    ");
    $dbStats = $stmtStats->fetch();
    if ($dbStats) {
        $stats['total'] = intval($dbStats['total']);
        $stats['optimo'] = intval($dbStats['optimo']);
        $stats['critico'] = intval($dbStats['critico']);
        $stats['agotado'] = intval($dbStats['agotado']);
    }

    // Certificados emitidos
    $stmtCert = $pdo->query("SELECT COUNT(*) as total FROM certificado_existencia");
    $dbCert = $stmtCert->fetch();
    if ($dbCert) {
        $stats['certificados'] = intval($dbCert['total']);
    }
} catch (Exception $e) {
    error_log("Error al cargar estadísticas de almacén: " . $e->getMessage());
}
?>

<div class="stats-container">
    <div class="stat-card stat-card--dark">
        <span class="stat-label">Total Artículos</span>
        <strong class="stat-value"><?= $stats['total'] ?></strong>
    </div>

    <div class="stat-card" style="border-left-color: #10b981;">
        <span class="stat-label">Stock Óptimo</span>
        <strong class="stat-value" style="color: #10b981;"><?= $stats['optimo'] ?></strong>
    </div>

    <div class="stat-card" style="border-left-color: #f59e0b;">
        <span class="stat-label">Stock Crítico</span>
        <strong class="stat-value" style="color: #f59e0b;"><?= $stats['critico'] ?></strong>
    </div>

    <div class="stat-card" style="border-left-color: #ef4444;">
        <span class="stat-label">Agotados</span>
        <strong class="stat-value" style="color: #ef4444;"><?= $stats['agotado'] ?></strong>
    </div>

    <div class="stat-card" style="border-left-color: #0ea5e9;">
        <span class="stat-label">Certificados Emitidos</span>
        <strong class="stat-value" style="color: #0ea5e9;"><?= $stats['certificados'] ?></strong>
    </div>
</div>
