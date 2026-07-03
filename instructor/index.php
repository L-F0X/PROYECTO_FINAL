<?php
// index.php
require_once '../conexion.php';
require_once '../csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Foto de perfil para el instructor
$photoPath = null;
if ($rolNombre === 'instructor') {
    foreach (['jpg','jpeg','png','webp'] as $ext) {
        $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
        if (file_exists($candidate)) {
            $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
            break;
        }
    }
}

// Parámetros de búsqueda y filtrado
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

if ($rolNombre === 'instructor') {
    $sql = "SELECT * FROM lote_requerimiento WHERE ID_SOLICITANTE = ?";
    $params = [$usuarioId];
    if ($busqueda !== '') {
        $sql .= " AND (LOTE_NOMBRE LIKE ? OR ID_LOTE = ?)";
        $params[] = "%$busqueda%";
        $params[] = intval($busqueda);
    }
    if ($filtroEstado !== '') {
        $sql .= " AND ESTADO_TRAMITE = ?";
        $params[] = $filtroEstado;
    }
    $sql .= " ORDER BY FECHA_CREACION DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $panelTitulo = 'Lotes';
    $panelDescripcion = 'Aquí verás los lotes que has creado o que están a tu cargo como instructor.';

    // Query stats for charts/summary (Instructor)
    $totalLotes = 0;
    $totalItems = 0;
    $totalFichas = 0;
    $lotesBorrador = 0;
    $lotesEnviado = 0;
    $lotesAprobado = 0;
    $lotesRechazado = 0;

    try {
        // Total Lotes
        $stmtCountLotes = $pdo->prepare("SELECT COUNT(*) FROM lote_requerimiento WHERE ID_SOLICITANTE = ?");
        $stmtCountLotes->execute([$usuarioId]);
        $totalLotes = intval($stmtCountLotes->fetchColumn());

        // Total Items
        $stmtCountItems = $pdo->prepare("SELECT COUNT(*) FROM matriz_item mi INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE WHERE lr.ID_SOLICITANTE = ?");
        $stmtCountItems->execute([$usuarioId]);
        $totalItems = intval($stmtCountItems->fetchColumn());

        // Total Fichas
        $stmtCountFichas = $pdo->prepare("SELECT COUNT(*) FROM ficha_tecnica ft INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE WHERE lr.ID_SOLICITANTE = ?");
        $stmtCountFichas->execute([$usuarioId]);
        $totalFichas = intval($stmtCountFichas->fetchColumn());

        // Lotes states count
        $stmtStates = $pdo->prepare("SELECT ESTADO_TRAMITE, COUNT(*) as count FROM lote_requerimiento WHERE ID_SOLICITANTE = ? GROUP BY ESTADO_TRAMITE");
        $stmtStates->execute([$usuarioId]);
        $statesData = $stmtStates->fetchAll();
        foreach ($statesData as $row) {
            $est = trim($row['ESTADO_TRAMITE']);
            if ($est === 'Borrador') $lotesBorrador = intval($row['count']);
            elseif ($est === 'Enviado') $lotesEnviado = intval($row['count']);
            elseif ($est === 'Aprobado') $lotesAprobado = intval($row['count']);
            elseif ($est === 'Rechazado') $lotesRechazado = intval($row['count']);
        }
    } catch (Exception $e) {
        error_log('Error cargando estadísticas en index: ' . $e->getMessage());
    }
} elseif ($rolNombre === 'coordinador') {
    $sql = "SELECT * FROM lote_requerimiento";
    $params = [];
    $where = [];
    if ($busqueda !== '') {
        $where[] = "(LOTE_NOMBRE LIKE ? OR ID_LOTE = ?)";
        $params[] = "%$busqueda%";
        $params[] = intval($busqueda);
    }
    if ($filtroEstado !== '') {
        $where[] = "ESTADO_TRAMITE = ?";
        $params[] = $filtroEstado;
    }
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY FECHA_CREACION DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $panelTitulo = 'Panel de Coordinador';
    $panelDescripcion = 'Revisa, coordina y da seguimiento a los lotes de requerimiento del equipo.';
} else {
    $sql = "SELECT * FROM lote_requerimiento";
    $params = [];
    $where = [];
    if ($busqueda !== '') {
        $where[] = "(LOTE_NOMBRE LIKE ? OR ID_LOTE = ?)";
        $params[] = "%$busqueda%";
        $params[] = intval($busqueda);
    }
    if ($filtroEstado !== '') {
        $where[] = "ESTADO_TRAMITE = ?";
        $params[] = $filtroEstado;
    }
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY FECHA_CREACION DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $panelTitulo = 'Lotes de Requerimiento (Pre-Compra)';
    $panelDescripcion = 'Lista completa de lotes registrados en el sistema.';
}

$lotes = $stmt->fetchAll();
$total = count($lotes);

// Procesar POST de asignación de ficha técnica a lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_ficha_lote') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $id_lote = isset($_POST['id_lote']) ? intval($_POST['id_lote']) : 0;
    $id_ficha_tecnica = isset($_POST['id_ficha_tecnica']) && $_POST['id_ficha_tecnica'] !== '' ? intval($_POST['id_ficha_tecnica']) : 0;
    
    if ($id_lote > 0 && $id_ficha_tecnica > 0) {
        try {
            // Obtener los detalles de la ficha técnica seleccionada
            $stmtFicha = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
            $stmtFicha->execute([$id_ficha_tecnica]);
            $ficha = $stmtFicha->fetch();
            
            if ($ficha) {
                // Obtener o crear código UNSPSC
                $codigoUnspsc = trim($ficha['CODIGO_UNSPSC_FK'] ?: 'SIN_ASIGNAR');
                $stmtCheckUnspsc = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
                $stmtCheckUnspsc->execute([$codigoUnspsc]);
                $id_unspsc = $stmtCheckUnspsc->fetchColumn();
                if (!$id_unspsc) {
                    $pdo->prepare("INSERT INTO codigo_unspsc (SEGMENTO, FAMILIA, CLASE, CODIGO_UNSPSC) VALUES (?, ?, ?, ?)")
                        ->execute(['SIN', 'ASIG', 'CL', $codigoUnspsc]);
                    $id_unspsc = $pdo->lastInsertId();
                }
                
                // Insertar en matriz_item
                $stmtInsert = $pdo->prepare("INSERT INTO matriz_item (ID_LOTE, ID_FICHA_TECNICA, ID_CODIGO_UNSPSC, ID_IVA, DESCRIPCION_BIEN, UNIDAD_MEDIDA, CANTIDAD_REGULAR, ESTADO_ITEM) VALUES (?, ?, ?, 1, ?, ?, 1, 'Borrador')");
                $stmtInsert->execute([
                    $id_lote,
                    $id_ficha_tecnica,
                    $id_unspsc,
                    $ficha['NOMBRE_ITEM'],
                    $ficha['UNIDAD_MEDIDA'] ?: 'Unidad'
                ]);
            }
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            error_log('Error al asignar ficha técnica en index: ' . $e->getMessage());
            die('Error al asignar la ficha técnica.');
        }
    }
}

// Cargar todas las fichas técnicas creadas en el sistema
$fichasTecnicas = [];
try {
    $stmtFichas = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK FROM ficha_tecnica ORDER BY NOMBRE_ITEM");
    $fichasTecnicas = $stmtFichas->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando fichas técnicas en index: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Pre-compra SENA</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php if ($rolNombre === 'instructor'): ?>
<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
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
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"></a>
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link" id="sidebar-ficha">Ficha Técnica</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link" id="sidebar-consulta">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link" id="sidebar-certificados">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link" id="sidebar-perfil">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Instructor</h2>
                <p class="dashboard-subtitle">Accede a tus herramientas: ficha técnica, certificados de existencia y consulta de matrices.</p>
            </div>
        </div>

        <!-- Resumen de Actividad e Indicadores -->
        <style>
            .stats-dashboard-grid {
                display: grid;
                grid-template-columns: 1.2fr 1fr;
                gap: 20px;
                margin-bottom: 24px;
            }
            @media (max-width: 768px) {
                .stats-dashboard-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="stats-dashboard-grid">
            <!-- Tarjetas de Métricas -->
            <div class="metrics-container" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid var(--verde-sena);">
                    <div>
                        <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Lotes Creados</span>
                        <h3 style="font-size: 2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $totalLotes ?></h3>
                    </div>
                    <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">Total requerimientos</p>
                </div>
                <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #0284c7;">
                    <div>
                        <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Ítems Creados</span>
                        <h3 style="font-size: 2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $totalItems ?></h3>
                    </div>
                    <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">En matriz de lotes</p>
                </div>
                <div class="metric-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid #7c3aed; grid-column: span 2;">
                    <div>
                        <span style="color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Fichas Técnicas</span>
                        <h3 style="font-size: 2rem; margin: 10px 0 0; font-weight: 800; color: var(--texto-oscuro);"><?= $totalFichas ?></h3>
                    </div>
                    <p style="margin: 8px 0 0; font-size: 0.8rem; color: #94a3b8;">Asociadas a materiales</p>
                </div>
            </div>

            <!-- Gráfico de Estados -->
            <div class="chart-card" style="background: #ffffff; border-radius: 12px; padding: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center;">
                <h4 style="margin: 0 0 15px; color: var(--texto-oscuro); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Estados de los Lotes</h4>
                <div style="position: relative; height: 160px; width: 100%; display: flex; justify-content: center; align-items: center;">
                    <canvas id="lotesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="panel-card" id="lotes-panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h3>Mis Lotes</h3>
                <div class="actions-bar" style="border: none; padding: 0; margin: 0;">
                    <a href="crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
                </div>
            </div>

            <!-- Formulario de búsqueda -->
            <form method="GET" action="index.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar lote</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="search-input"
                            placeholder="Buscar por nombre o ID..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                            autocomplete="off"
                        >
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por estado</label>
                        <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <?php
                            $estados = ['Borrador','Enviado','Aprobado','Rechazado'];
                            foreach ($estados as $e):
                                $sel = ($filtroEstado === $e) ? 'selected' : '';
                            ?>
                                <option value="<?= $e ?>" <?= $sel ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                        <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Lote</th>
                        <th>Estado Trámite</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lotes)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No hay lotes registrados o que coincidan con la búsqueda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($lotes as $lote): ?>
                            <tr>
                                <td><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                                <td><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                                <td><strong><?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></strong></td>
                                <td><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                                <td>
                                    <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #39A900;">Gestionar Ítems</a>
                                    <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Fichas Tecnicas</a>
                                    <a href="editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                                    <form action="eliminar.php" method="POST" style="display:inline; margin:0;">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                        <button type="submit" class="btn btn-danger btn-eliminar" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--alerta-rojo); color: white;">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-card" id="iframe-panel-card" style="display: none; padding: 0; overflow: hidden; border: none; background: transparent; box-shadow: none;">
            <iframe id="content-iframe" src="" style="width: 100%; border: none; min-height: 850px; overflow: auto; background: transparent;"></iframe>
        </div>
    </main>
</div>

<?php else: ?>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <h1 class="header-title">BICERGAM | <span class="accent-color">SENA</span></h1>
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-center">
        <span class="user-greeting">Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></span>
        <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
    <div class="header-right">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
    </div>
</header>

<div class="container fade-in">
    <div class="role-banner role-<?= htmlspecialchars(str_replace(' ', '-', $rolNombre)) ?>">
        <h2><?= htmlspecialchars($panelTitulo) ?></h2>
        <p><?= htmlspecialchars($panelDescripcion) ?></p>
    </div>

    <!-- Barra de acciones y búsqueda -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <form method="GET" action="index.php" id="form-busqueda" style="margin: 0; flex: 1; min-width: 300px;">
            <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="field-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar lote</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        class="search-input"
                        placeholder="Buscar por nombre o ID..."
                        value="<?= htmlspecialchars($busqueda) ?>"
                        style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                        autocomplete="off"
                    >
                </div>
                <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                    <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por estado</label>
                    <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">— Todos —</option>
                        <?php
                        $estados = ['Borrador','Enviado','Aprobado','Rechazado'];
                        foreach ($estados as $e):
                            $sel = ($filtroEstado === $e) ? 'selected' : '';
                        ?>
                            <option value="<?= $e ?>" <?= $sel ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                    <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="actions-bar justify-end" style="border: none; padding: 0; margin: 0;">
            <a href="crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Lote</th>
                <th>ID Solicitante</th>
                <th>Estado Trámite</th>
                <th>Fecha Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($lotes)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay lotes de requerimiento registrados o que coincidan con el filtro.</td>
                </tr>
            <?php else: ?>
                <?php foreach($lotes as $lote): ?>
                    <tr>
                        <td><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                        <td><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                        <td><?= htmlspecialchars($lote['ID_SOLICITANTE']) ?></td>
                        <td><strong><?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></strong></td>
                        <td><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                        <td>
                                    <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #39A900;">Gestionar Ítems</a>
                                    <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Fichas Tecnicas</a>
                                    <a href="editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                                    <form action="eliminar.php" method="POST" style="display:inline; margin:0;">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                        <button type="submit" class="btn btn-danger btn-eliminar" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--alerta-rojo); color: white;">Eliminar</button>
                                    </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script src="../javascript.js"></script>
<?php if ($rolNombre === 'instructor'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('lotesChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Borrador', 'Enviado', 'Aprobado', 'Rechazado'],
                datasets: [{
                    data: [
                        <?= $lotesBorrador ?>,
                        <?= $lotesEnviado ?>,
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
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                family: "'Plus Jakarta Sans', sans-serif",
                                size: 11,
                                weight: '600'
                            },
                            color: '#475569'
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
</script>
<?php endif; ?>
<script>
    (function () {
        const input  = document.getElementById('q');
        const select = document.getElementById('estado');
        const form   = document.getElementById('form-busqueda');
        if (input && select && form) {
            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 350);
            });
            select.addEventListener('change', () => form.submit());
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        const sidebarLinks = {
            'sidebar-ficha': 'crear_ficha_tecnica.php',
            'sidebar-consulta': 'matriz_consulta.php',
            'sidebar-certificados': 'certificado_existencia.php',
            'sidebar-perfil': 'instructor_profile.php'
        };

        const lotesPanel = document.getElementById('lotes-panel-card');
        const iframePanel = document.getElementById('iframe-panel-card');
        const iframe = document.getElementById('content-iframe');

        Object.keys(sidebarLinks).forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Update active class
                    document.querySelectorAll('.sidebar-link').forEach(link => {
                        link.classList.remove('active');
                        link.classList.remove('sidebar-link--primary');
                    });
                    btn.classList.add('active');

                    // Load src inside iframe
                    lotesPanel.style.display = 'none';
                    iframePanel.style.display = 'block';
                    iframe.src = sidebarLinks[id] + '?iframe=1';
                });
            }
        });

        // Intercept Inicio button to restore default Lotes list
        const inicioBtn = document.querySelector('.btn-inicio-nav');
        if (inicioBtn) {
            inicioBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Reset active states
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    link.classList.remove('active');
                    link.classList.remove('sidebar-link--primary');
                });
                lotesPanel.style.display = 'block';
                iframePanel.style.display = 'none';
                iframe.src = '';
            });
        }
    });
</script>
</body>
</html>