<?php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

// Métricas de la HUD
try {
    // 1. Cantidad de Instructores
    $stmtInst = $pdo->query("SELECT COUNT(*) FROM usuario WHERE ID_ROL = 1");
    $totalInstructores = intval($stmtInst->fetchColumn());

    // 2. Decisiones Tomadas (Historial)
    $stmtDecTomadas = $pdo->query("SELECT COUNT(*) FROM aprobacion_rechazo_lote");
    $decisionesTomadas = intval($stmtDecTomadas->fetchColumn());

    // 3. Lotes Enviados (Pendientes / Decisiones por Tomar)
    $stmtEnviados = $pdo->query("SELECT COUNT(*) FROM lote_requerimiento WHERE ESTADO_TRAMITE = 'Enviado'");
    $lotesEnviados = intval($stmtEnviados->fetchColumn());
    $decisionesPorTomar = $lotesEnviados; // Los lotes 'Enviados' son las decisiones por tomar

    // 4. Total de Lotes en el sistema
    $stmtTotalLotes = $pdo->query("SELECT COUNT(*) FROM lote_requerimiento");
    $totalLotes = intval($stmtTotalLotes->fetchColumn());

    // --- Datos para Gráfica 1: Distribución de Lotes ---
    $stmtBorrador = $pdo->query("SELECT COUNT(*) FROM lote_requerimiento WHERE ESTADO_TRAMITE = 'Borrador'");
    $lotesBorrador = intval($stmtBorrador->fetchColumn());

    $stmtAprobado = $pdo->query("SELECT COUNT(*) FROM lote_requerimiento WHERE ESTADO_TRAMITE = 'Aprobado'");
    $lotesAprobado = intval($stmtAprobado->fetchColumn());

    $stmtRechazado = $pdo->query("SELECT COUNT(*) FROM lote_requerimiento WHERE ESTADO_TRAMITE = 'Rechazado'");
    $lotesRechazado = intval($stmtRechazado->fetchColumn());

    // --- Datos para Gráfica 2: Historial de Aprobaciones vs Rechazos ---
    $stmtDecAprobado = $pdo->query("SELECT COUNT(*) FROM aprobacion_rechazo_lote WHERE ESTADO_DECISION = 'Aprobado'");
    $decAprobados = intval($stmtDecAprobado->fetchColumn());

    $stmtDecRechazado = $pdo->query("SELECT COUNT(*) FROM aprobacion_rechazo_lote WHERE ESTADO_DECISION = 'Rechazado'");
    $decRechazados = intval($stmtDecRechazado->fetchColumn());

} catch (Exception $e) {
    error_log('Error loading coordinator dashboard stats: ' . $e->getMessage());
    $totalInstructores = 0;
    $decisionesTomadas = 0;
    $lotesEnviados = 0;
    $decisionesPorTomar = 0;
    $totalLotes = 0;
    $lotesBorrador = 0;
    $lotesAprobado = 0;
    $lotesRechazado = 0;
    $decAprobados = 0;
    $decRechazados = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Coordinador</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Coordinador de Compras: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="coordinador_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"><span>BICERGAM</span></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <span class="hud-brand">BICERGAM</span>
                <h2>Panel Principal del Coordinador</h2>
                <p class="dashboard-subtitle">Visualiza el resumen institucional de los lotes, instructores y decisiones.</p>
            </div>
            <div class="hud-status">
                <span class="hud-dot"></span>
                <span><?= fecha_larga_es() ?></span>
            </div>
        </div>

        <!-- Fila de Tarjetas de Métricas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
            
            <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #7c3aed;">
                <div>
                    <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Instructores</span>
                    <h3 style="font-size: 2.2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $totalInstructores ?></h3>
                </div>
                <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">Instructores registrados</p>
            </div>

            <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #0284c7;">
                <div>
                    <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Lotes Enviados</span>
                    <h3 style="font-size: 2.2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $lotesEnviados ?></h3>
                </div>
                <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">En espera de revisión</p>
            </div>

            <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid var(--verde-sena);">
                <div>
                    <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Decisiones Tomadas</span>
                    <h3 style="font-size: 2.2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $decisionesTomadas ?></h3>
                </div>
                <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">Aprobados y rechazados</p>
            </div>

            <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid var(--alerta-rojo);">
                <div>
                    <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Decisiones por Tomar</span>
                    <h3 style="font-size: 2.2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $decisionesPorTomar ?></h3>
                </div>
                <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">Pendientes de resolver</p>
            </div>
        </div>

        <!-- Sección de Gráficos -->
        <div class="charts-grid-2">
            
            <!-- Gráfica 1: Estados de los Lotes -->
            <div class="panel-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 20px;">
                <h4 style="margin: 0 0 15px; color: var(--texto-oscuro); font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Distribución de Lotes por Estado</h4>
                <div style="position: relative; height: 260px; width: 100%; display: flex; justify-content: center; align-items: center;">
                    <canvas id="lotesDistribucionChart"></canvas>
                </div>
            </div>

            <!-- Gráfica 2: Historial de Aprobaciones vs Rechazos -->
            <div class="panel-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 20px;">
                <h4 style="margin: 0 0 15px; color: var(--texto-oscuro); font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Historial de Decisiones</h4>
                <div style="position: relative; height: 260px; width: 100%; display: flex; justify-content: center; align-items: center;">
                    <canvas id="lotesDecisionesChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Accesos rápidos -->
        <div class="panel-card" style="margin-bottom: 30px;">
            <h3>Enlaces y Acciones Rápidas</h3>
            <p class="dashboard-subtitle">Navega rápidamente a las principales secciones de revisión y consulta.</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
                <a href="revisar_lotes.php" class="btn btn-sena">Ir a Revisar Lotes</a>
                <a href="historial_decisiones.php" class="btn btn-secondary">Ver Historial de Decisiones</a>
                <a href="instructores.php" class="btn btn-secondary">Consultar Instructores</a>
            </div>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- GRÁFICA 1: DISTRIBUCIÓN DE LOTES (DOUGHNUT) ---
        const ctx1 = document.getElementById('lotesDistribucionChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Borrador', 'Enviado', 'Aprobado', 'Rechazado'],
                datasets: [{
                    data: [
                        <?= $lotesBorrador ?>,
                        <?= $lotesEnviados ?>,
                        <?= $lotesAprobado ?>,
                        <?= $lotesRechazado ?>
                    ],
                    backgroundColor: [
                        '#64748b', // Borrador - Gris
                        '#3b82f6', // Enviado - Azul
                        '#10b981', // Aprobado - Verde
                        '#ef4444'  // Rechazado - Rojo
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: {
                                family: "'Plus Jakarta Sans', sans-serif",
                                size: 12,
                                weight: '600'
                            },
                            color: '#475569'
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // --- GRÁFICA 2: HISTORIAL DE DECISIONES (BAR) ---
        const ctx2 = document.getElementById('lotesDecisionesChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Aprobados', 'Rechazados'],
                datasets: [{
                    label: 'Decisiones',
                    data: [
                        <?= $decAprobados ?>,
                        <?= $decRechazados ?>
                    ],
                    backgroundColor: [
                        '#10b981', // Aprobado - Verde
                        '#ef4444'  // Rechazado - Rojo
                    ],
                    borderRadius: 6,
                    borderWidth: 0,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: '#64748b'
                        },
                        grid: {
                            color: '#e2e8f0'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: {
                                weight: '600'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>

<script src="../js/apartados.js"></script>
</body>
</html>
