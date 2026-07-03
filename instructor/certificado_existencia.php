<?php
// certificado_existencia.php — Consulta de Certificados de Existencia
require_once '../conexion.php';
require_once '../csrf.php';

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
    $where[] = "(ce.NUMERO_CERTIFICADO LIKE ? OR lr.LOTE_NOMBRE LIKE ? OR ce.ID_LOTE LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Los instructores solo ven los certificados asociados a lotes creados por ellos
$where[] = "lr.ID_SOLICITANTE = ?";
$params[] = $usuarioId;

$sql = "SELECT ce.ID_CERTIFICADO, ce.NUMERO_CERTIFICADO, ce.ID_LOTE, lr.LOTE_NOMBRE, lr.ESTADO_TRAMITE
        FROM certificado_existencia ce
        LEFT JOIN lote_requerimiento lr ON ce.ID_LOTE = lr.ID_LOTE";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY ce.ID_CERTIFICADO DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$certificados = $stmt->fetchAll();
$total = count($certificados);
$isIframe = isset($_GET['iframe']) ? true : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Consulta de Certificados de Existencia en BICERGAM.">
    <title>Certificados de Existencia - BICERGAM</title>
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
        .lotes-table .td-solicitante { color: #555; font-size: 13px; }

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
<body class="<?= $isIframe ? 'iframe-mode' : '' ?>">

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
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
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link active">Certificados Existencia</a>
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
                <h2>Certificados de Existencia</h2>
                <p class="dashboard-subtitle">Búsqueda y visualización de certificados asociados a tus lotes.</p>
            </div>
        </div>

        <div class="panel-card">

            <!-- ── Barra de búsqueda ── -->
            <form method="GET" action="certificado_existencia.php" id="form-busqueda">
                <div class="search-bar">
                    <div class="field-group">
                        <label for="q">Buscar por Nº Certificado, Nombre o ID de Lote</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="search-input"
                            placeholder="Ej: CERT-102, REDES, 2..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="btn-buscar" id="btn-buscar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        Buscar
                    </button>
                    <?php if ($busqueda !== ''): ?>
                        <a href="certificado_existencia.php" class="btn-limpiar" id="btn-limpiar">
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
                <strong>certificado<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></strong>
                <?php if ($busqueda !== ''): ?>
                    <span style="color:#aaa">— filtro activo</span>
                <?php endif; ?>
            </div>

            <!-- ── Tabla ── -->
            <div class="lotes-table-wrap">
                <table class="lotes-table">
                    <thead>
                        <tr>
                            <th>ID Certificado</th>
                            <th>Número de Certificado</th>
                            <th>ID Lote</th>
                            <th>Nombre del Lote</th>
                            <th>Estado del Lote</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificados)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52"
                                             viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No se encontraron certificados</p>
                                        <span>Intenta con otro término o limpia la búsqueda</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificados as $c): ?>
                                <tr>
                                    <td class="td-id">#<?= htmlspecialchars($c['ID_CERTIFICADO']) ?></td>
                                    <td class="td-nombre"><strong><?= htmlspecialchars($c['NUMERO_CERTIFICADO']) ?></strong></td>
                                    <td>#<?= htmlspecialchars($c['ID_LOTE']) ?></td>
                                    <td class="td-solicitante"><?= htmlspecialchars($c['LOTE_NOMBRE']) ?></td>
                                    <td>
                                        <span class="badge-estado badge-<?= htmlspecialchars(strtolower($c['ESTADO_TRAMITE'])) ?>">
                                            <?= htmlspecialchars($c['ESTADO_TRAMITE']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><!-- /.panel-card -->
    </main>
</div>

<script src="../javascript.js"></script>
<script>
    // Búsqueda en vivo al escribir (debounce 350ms)
    (function () {
        const input  = document.getElementById('q');
        const form   = document.getElementById('form-busqueda');
        let timer;

        function submitDelayed() {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 350);
        }

        input.addEventListener('input', submitDelayed);
    })();
</script>
</body>
</html>
