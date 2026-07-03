<?php
// almacenista/dashboard_stats.php
// Componente de métricas rápidas de inventario

$stats = [
    'total' => 0,
    'optimo' => 0,
    'critico' => 0,
    'agotado' => 0,
    'certificados' => 0
];

try {
    // Total, óptimo, crítico, agotado
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN CANTIDAD > 5 THEN 1 ELSE 0 END) as optimo,
            SUM(CASE WHEN CANTIDAD > 0 AND CANTIDAD <= 5 THEN 1 ELSE 0 END) as critico,
            SUM(CASE WHEN CANTIDAD = 0 THEN 1 ELSE 0 END) as agotado
        FROM ficha_tecnica
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

<div class="stats-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #39A900;">
        <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Total Artículos</span>
        <strong style="font-size: 2rem; margin-top: 10px;"><?= $stats['total'] ?></strong>
    </div>

    <div class="stat-card" style="background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #10b981; border: 1px solid #e2e8f0; border-left-width: 5px;">
        <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Stock Óptimo</span>
        <strong style="font-size: 2rem; margin-top: 10px; color: #10b981;"><?= $stats['optimo'] ?></strong>
    </div>

    <div class="stat-card" style="background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #f59e0b; border: 1px solid #e2e8f0; border-left-width: 5px;">
        <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Stock Crítico</span>
        <strong style="font-size: 2rem; margin-top: 10px; color: #f59e0b;"><?= $stats['critico'] ?></strong>
    </div>

    <div class="stat-card" style="background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #ef4444; border: 1px solid #e2e8f0; border-left-width: 5px;">
        <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Agotados</span>
        <strong style="font-size: 2rem; margin-top: 10px; color: #ef4444;"><?= $stats['agotado'] ?></strong>
    </div>

    <div class="stat-card" style="background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #0ea5e9; border: 1px solid #e2e8f0; border-left-width: 5px;">
        <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Certificados Emitidos</span>
        <strong style="font-size: 2rem; margin-top: 10px; color: #0ea5e9;"><?= $stats['certificados'] ?></strong>
    </div>
</div>
