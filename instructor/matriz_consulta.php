<?php
// matriz_consulta.php — Consulta de Ítems de la Matriz para Instructor
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

// Parámetros de búsqueda
$busqueda = trim($_GET['q'] ?? '');

// Construir consulta dinámica
$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(mi.DESCRIPCION_BIEN LIKE ? OR lr.LOTE_NOMBRE LIKE ? OR mi.ID_MATRIZ_ITEM LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Los instructores solo ven los ítems asociados a lotes creados por ellos
$where[] = "lr.ID_SOLICITANTE = ?";
$params[] = $usuarioId;

$sql = "SELECT mi.*, lr.LOTE_NOMBRE, u.NOMBRE AS APOYO_NOMBRE, u.APELLIDO AS APOYO_APELLIDO
        FROM matriz_item mi
        INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
        LEFT JOIN usuario u ON mi.INSTRUCTOR_APOYO = u.ID_USUARIO";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
if ($busqueda !== '') {
    // Coincidencias de descripción por prefijo se muestran primero, igual que en Fase 22.
    $sql .= " ORDER BY CASE WHEN mi.DESCRIPCION_BIEN LIKE ? THEN 0 ELSE 1 END, mi.ID_MATRIZ_ITEM DESC";
    $params[] = "$busqueda%";
} else {
    $sql .= " ORDER BY mi.ID_MATRIZ_ITEM DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$total = count($items);
$isIframe = isset($_GET['iframe']) ? true : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Consulta de Ítems de la Matriz en BICERGAM.">
    <title>Consulta de Ítems - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        /* ── Barra de búsqueda ── */
        .search-bar {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .search-bar .field-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .search-bar label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .search-input {
            border: 1.5px solid #ddd;
            border-radius: 7px;
            padding: 9px 14px;
            font-size: 14px;
            outline: none;
            background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 300px;
        }
        .search-input:focus {
            border-color: #39a900;
            box-shadow: 0 0 0 3px rgba(57,169,0,.12);
            background: #fff;
        }
        .btn-buscar {
            background: linear-gradient(135deg,#39a900,#2e8600);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .btn-buscar:hover  { background: linear-gradient(135deg,#2e8600,#206000); }
        .btn-buscar:active { transform: scale(0.98); }
        .btn-limpiar {
            background: #fff;
            color: #555;
            border: 1.5px solid #ddd;
            border-radius: 7px;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-limpiar:hover { border-color: #aaa; color: #333; }

        /* ── Contador de resultados ── */
        .results-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .results-meta strong { color: #333; }
        .badge-count {
            background: #39a900;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 20px;
        }

        /* ── Tabla ── */
        .lotes-table {
            width: 100%;

            font-size: 14px;
        }
        .lotes-table thead tr {
            background: #1a1a2e;
            color: #fff;
        }
        .lotes-table thead th {
            padding: 13px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .lotes-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .lotes-table tbody tr:last-child { border-bottom: none; }
        .lotes-table tbody tr:hover { background: #f6fff0; }
        .lotes-table tbody td {
            padding: 12px 16px;
            color: #333;
            vertical-align: middle;
        }
        .lotes-table .td-id {
            font-weight: 700;
            color: #39a900;
            font-size: 15px;
        }
        .lotes-table .td-nombre { font-weight: 500; }
        .lotes-table .td-solicitante { color: #555; font-size: 13px; }
    </style>
</head>
<body class="<?= $isIframe ? 'iframe-mode' : '' ?>">

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); $wsToken = generar_ws_token($pdo, intval($_SESSION['usuario_id']), $_SESSION['rol_nombre'] ?? ''); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="instructor_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link">Mis Lotes</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link active">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Consulta de Ítems de la Matriz</h2>
                <p class="dashboard-subtitle">Búsqueda y visualización de bienes registrados en tus lotes.</p>
            </div>
        </div>

        <div class="panel-card">

            <!-- ── Barra de búsqueda ── -->
            <form method="GET" action="matriz_consulta.php" id="form-busqueda">
                <div class="search-bar">
                    <div class="field-group">
                        <label for="q">Buscar por Descripción, Lote o ID de Ítem</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="search-input"
                            placeholder="Ej: ESPUMA, REDES, 7..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                            autocomplete="off"
                        >
                    </div>
                    <a href="matriz_consulta.php" class="btn-limpiar" id="btn-limpiar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        Limpiar
                    </a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <!-- ── Contador ── -->
                <div class="results-meta">
                    <span>Resultados:</span>
                    <span class="badge-count"><?= $total ?></span>
                    <strong>ítem<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></strong>
                    <?php if ($busqueda !== ''): ?>
                        <span style="color:#aaa">— filtro activo</span>
                    <?php endif; ?>
                </div>

                <!-- ── Tabla ── -->
                <table class="lotes-table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Lote</th>
                            <th>Descripción del Bien</th>
                            <th>U. Medida</th>
                            <th>Cantidad</th>
                            <th>Estado Ítem</th>
                            <th>Instructor Apoyo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52"
                                             viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No se encontraron ítems</p>
                                        <span>Intenta con otro término o limpia la búsqueda</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $numItemConsulta = 1; foreach ($items as $item): ?>
                                <?php
                                    $apoyo = trim(($item['APOYO_NOMBRE'] ?? '') . ' ' . ($item['APOYO_APELLIDO'] ?? ''));
                                    if ($apoyo === '') $apoyo = '—';
                                ?>
                                <tr>
                                    <td class="td-id">#<?= $numItemConsulta++ ?></td>
                                    <td><?= htmlspecialchars($item['LOTE_NOMBRE']) ?></td>
                                    <td class="td-nombre"><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></td>
                                    <td><?= htmlspecialchars($item['UNIDAD_MEDIDA'] ?: '—') ?></td>
                                    <td><strong><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></strong></td>
                                    <td>
                                        <span class="badge-estado badge-<?= htmlspecialchars(strtolower($item['ESTADO_ITEM'])) ?>">
                                            <?= htmlspecialchars($item['ESTADO_ITEM']) ?>
                                        </span>
                                    </td>
                                    <td class="td-solicitante"><?= htmlspecialchars($apoyo) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><!-- /.panel-card -->
    </main>
</div>

<script src="../js/apartados.js"></script>
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
