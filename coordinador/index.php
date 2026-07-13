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

    // Últimos avisos de aprobado/rechazado, para verlos de un vistazo sin
    // tener que ir al Historial de Decisiones completo.
    $stmtUltimasDecisiones = $pdo->query("
        SELECT arl.*, lr.LOTE_NOMBRE
        FROM aprobacion_rechazo_lote arl
        INNER JOIN lote_requerimiento lr ON arl.ID_LOTE = lr.ID_LOTE
        ORDER BY arl.FECHA_DECISION DESC
        LIMIT 6
    ");
    $ultimasDecisiones = $stmtUltimasDecisiones->fetchAll();

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
    $ultimasDecisiones = [];
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

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Coordinador</span></h1>
            <div class="user-greeting">Solicitante: <strong><?= $usuarioNombre ?></strong></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); $wsToken = generar_ws_token($pdo, intval($_SESSION['usuario_id']), $_SESSION['rol_nombre'] ?? ''); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
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
        <div class="stats-container">
            <div class="stat-card" style="border-left-color: #7c3aed;">
                <span class="stat-label">Instructores</span>
                <strong class="stat-value"><?= $totalInstructores ?></strong>
                <p class="stat-hint">Instructores registrados</p>
            </div>

            <div class="stat-card" style="border-left-color: #0284c7;">
                <span class="stat-label">Lotes Enviados</span>
                <strong class="stat-value"><?= $lotesEnviados ?></strong>
                <p class="stat-hint">En espera de revisión</p>
            </div>

            <div class="stat-card" style="border-left-color: #39A900;">
                <span class="stat-label">Decisiones Tomadas</span>
                <strong class="stat-value"><?= $decisionesTomadas ?></strong>
                <p class="stat-hint">Aprobados y rechazados</p>
            </div>

            <div class="stat-card" style="border-left-color: #ef4444;">
                <span class="stat-label">Decisiones por Tomar</span>
                <strong class="stat-value"><?= $decisionesPorTomar ?></strong>
                <p class="stat-hint">Pendientes de resolver</p>
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

        <!-- Avisos de Aprobado/Rechazado -->
        <div class="panel-card" style="margin-bottom: 30px;">
            <h3>Avisos Recientes de Decisiones</h3>
            <p class="dashboard-subtitle">Últimos lotes aprobados o rechazados en el sistema.</p>
            <?php if (empty($ultimasDecisiones)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        <line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                    <p>No hay decisiones registradas todavía.</p>
                    <span>Los avisos de lotes aprobados o rechazados aparecerán aquí.</span>
                </div>
            <?php else: ?>
                <ul style="list-style:none; margin:15px 0 0; padding:0;">
                    <?php foreach ($ultimasDecisiones as $dec): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #eee;">
                            <span>
                                <span style="padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; <?= $dec['ESTADO_DECISION'] === 'Aprobado' ? 'background:#d4edda; color:#155724;' : 'background:#f8d7da; color:#721c24;' ?>">
                                    <?= htmlspecialchars($dec['ESTADO_DECISION']) ?>
                                </span>
                                <strong style="margin-left:8px;"><?= htmlspecialchars($dec['LOTE_NOMBRE']) ?></strong>
                            </span>
                            <span style="font-size:12px; color:#888; white-space:nowrap;"><?= htmlspecialchars($dec['FECHA_DECISION']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="historial_decisiones.php" style="display:inline-block; margin-top:12px; font-size:13px; color: var(--verde-sena);">Ver historial completo →</a>
            <?php endif; ?>
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
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
