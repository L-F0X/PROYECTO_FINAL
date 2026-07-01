<?php
// crear.php — Consulta de Lotes (solo lectura para Instructor)
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    // Preserve coordinator context when redirecting
    $from = (in_array(strtolower(trim($_SESSION['rol_nombre'] ?? '')), ['coordinador','coordinacion'])) ? '?from=coordinador' : '';
    header('Location: index.php' . $from);
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = 'uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

// Parámetros de búsqueda
$busqueda = trim($_GET['q'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');

// Construir consulta dinámica
$where = [];
$params = [];

if ($busqueda !== '') {
    $where[] = "(lr.LOTE_NOMBRE LIKE ? OR lr.ID_LOTE LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if ($filtroEstado !== '') {
    $where[] = "lr.ESTADO_TRAMITE = ?";
    $params[] = $filtroEstado;
}

$sql = "SELECT lr.ID_LOTE, lr.LOTE_NOMBRE, lr.ESTADO_TRAMITE, lr.FECHA_CREACION,
               u.NOMBRE, u.APELLIDO
        FROM lote_requerimiento lr
        LEFT JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY lr.FECHA_CREACION DESC, lr.ID_LOTE DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lotes = $stmt->fetchAll();
$total = count($lotes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Consulta de lotes de requerimiento en BICERGAM.">
    <title>Consulta de Lotes - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
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
            min-width: 220px;
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
        .lotes-table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }
        .lotes-table {
            width: 100%;
            border-collapse: collapse;
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
        .lotes-table .td-fecha { color: #666; font-size: 13px; }
        .lotes-table .td-solicitante { color: #555; font-size: 13px; }

        /* ── Badges de estado ── */
        .badge-estado {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-borrador  { background: #fff3cd; color: #856404; }
        .badge-enviado   { background: #cce5ff; color: #004085; }
        .badge-aprobado  { background: #d4edda; color: #155724; }
        .badge-rechazado { background: #f8d7da; color: #721c24; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        .empty-state svg { margin-bottom: 16px; opacity: 0.4; }
        .empty-state p { font-size: 15px; margin: 0; }
        .empty-state span { font-size: 13px; color: #bbb; }
    </style>
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand">
        <img src="imagenes/sena-logo.png" alt="SENA">
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
        <a href="profile.php" class="header-avatar-link" title="Editar perfil">
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
            <img src="imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
            <a href="crear.php" class="sidebar-link active">Consulta de Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="historial_existencia.php" class="sidebar-link">Historial de Existencia</a>
            <a href="matriz.php" class="sidebar-link">Consulta de Matrices</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Consulta de Lotes de Requerimiento</h2>
                <p class="dashboard-subtitle">Visualiza y busca los lotes registrados en el sistema. Solo lectura.</p>
            </div>
        </div>

        <div class="panel-card">

            <!-- ── Barra de búsqueda ── -->
            <form method="GET" action="crear.php" id="form-busqueda">
                <div class="search-bar">
                    <div class="field-group">
                        <label for="q">Buscar por nombre o ID</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="search-input"
                            placeholder="Ej: REDES, 2..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                            autocomplete="off"
                        >
                    </div>
                    <div class="field-group">
                        <label for="estado">Filtrar por estado</label>
                        <select name="estado" id="estado" class="search-input" style="min-width:160px">
                            <option value="">— Todos los estados —</option>
                            <?php
                            $estados = ['Borrador','Enviado','Aprobado','Rechazado'];
                            foreach ($estados as $e):
                                $sel = ($filtroEstado === $e) ? 'selected' : '';
                            ?>
                                <option value="<?= $e ?>" <?= $sel ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-buscar" id="btn-buscar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        Buscar
                    </button>
                    <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                        <a href="crear.php" class="btn-limpiar" id="btn-limpiar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- ── Contador ── -->
            <div class="results-meta">
                <span>Resultados:</span>
                <span class="badge-count"><?= $total ?></span>
                <strong>lote<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></strong>
                <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                    <span style="color:#aaa">— filtro activo</span>
                <?php endif; ?>
            </div>

            <!-- ── Tabla ── -->
            <div class="lotes-table-wrap">
                <table class="lotes-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Lote</th>
                            <th>Estado</th>
                            <th>Solicitante</th>
                            <th>Fecha Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52"
                                             viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No se encontraron lotes</p>
                                        <span>Intenta con otro término o limpia los filtros</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $l): ?>
                                <?php
                                    $estadoClass = 'badge-' . strtolower($l['ESTADO_TRAMITE']);
                                    $solicitante = trim(($l['NOMBRE'] ?? '') . ' ' . ($l['APELLIDO'] ?? ''));
                                    if ($solicitante === '') $solicitante = '—';
                                ?>
                                <tr>
                                    <td class="td-id">#<?= htmlspecialchars($l['ID_LOTE']) ?></td>
                                    <td class="td-nombre"><?= htmlspecialchars($l['LOTE_NOMBRE']) ?></td>
                                    <td>
                                        <span class="badge-estado <?= htmlspecialchars($estadoClass) ?>">
                                            <?= htmlspecialchars($l['ESTADO_TRAMITE']) ?>
                                        </span>
                                    </td>
                                    <td class="td-solicitante"><?= htmlspecialchars($solicitante) ?></td>
                                    <td class="td-fecha"><?= htmlspecialchars($l['FECHA_CREACION']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><!-- /.panel-card -->
    </main>
</div>

<script src="javascript.js"></script>
<script>
    // Búsqueda en vivo al escribir (debounce 350ms)
    (function () {
        const input  = document.getElementById('q');
        const select = document.getElementById('estado');
        const form   = document.getElementById('form-busqueda');
        let timer;

        function submitDelayed() {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 350);
        }

        input.addEventListener('input', submitDelayed);
        select.addEventListener('change', () => form.submit());
    })();
</script>
</body>
</html>