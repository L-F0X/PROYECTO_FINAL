<?php
// index.php
require_once 'conexion.php';
require_once 'csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Foto de perfil para el instructor
$photoPath = null;
if ($rolNombre === 'instructor') {
    foreach (['jpg','jpeg','png','webp'] as $ext) {
        $candidate = __DIR__ . '/uploads/profiles/' . $usuarioId . '.' . $ext;
        if (file_exists($candidate)) {
            $photoPath = 'uploads/profiles/' . $usuarioId . '.' . $ext;
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BICERGAM - Pre-compra SENA</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php if ($rolNombre === 'instructor'): ?>
<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="imagenes/sena-logo.png" alt="SENA">
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
            <img src="imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crud_instructor/crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="crud_instructor/matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="crud_instructor/certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Instructor</h2>
                <p class="dashboard-subtitle">Accede a tus herramientas: ficha técnica, certificados de existencia y consulta de matrices.</p>
            </div>
        </div>

        <div class="panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h3>Mis Lotes</h3>
                <div class="actions-bar" style="border: none; padding: 0; margin: 0;">
                    <a href="crud_instructor/crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
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
                                    <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Materiales</a>
                                    <a href="crud_instructor/editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                                    <form action="crud_instructor/eliminar.php" method="POST" style="display:inline; margin:0;">
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
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
    <div class="header-right">
        <img src="imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
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
            <a href="crud_instructor/crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
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
                            <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Materiales</a>
                            <a href="crud_instructor/editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                            <form action="crud_instructor/eliminar.php" method="POST" style="display:inline; margin:0;">
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

<script src="javascript.js"></script>
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
</script>
</body>
</html>