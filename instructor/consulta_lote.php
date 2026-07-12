<?php
// consulta_lote.php — Consulta de Lotes para Instructor
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
$filtroEstado = trim($_GET['estado'] ?? '');

// Construir consulta dinámica
$where = ["lr.ID_SOLICITANTE = ?"];
$params = [$usuarioId];

if ($busqueda !== '') {
    $where[] = "(lr.LOTE_NOMBRE LIKE ? OR lr.ID_LOTE = ?)";
    $params[] = "%$busqueda%";
    $params[] = intval($busqueda);
}

if ($filtroEstado !== '') {
    $where[] = "lr.ESTADO_TRAMITE = ?";
    $params[] = $filtroEstado;
}

$whereClause = implode(' AND ', $where);
// Coincidencias de nombre de lote por prefijo se muestran primero, igual que en Fase 22.
$orderClause = "ORDER BY lr.FECHA_CREACION DESC";
if ($busqueda !== '') {
    $orderClause = "ORDER BY CASE WHEN lr.LOTE_NOMBRE LIKE ? THEN 0 ELSE 1 END, lr.FECHA_CREACION DESC";
    $params[] = "$busqueda%";
}
$sql = "SELECT lr.*, u.NOMBRE, u.APELLIDO
        FROM lote_requerimiento lr
        LEFT JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
        WHERE $whereClause
        $orderClause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lotes = $stmt->fetchAll();
$total = count($lotes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Lotes - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Instructor Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></div>
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
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
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
                <h2>Consulta de Lotes de Requerimiento</h2>
                <p class="dashboard-subtitle">Visualiza, busca y filtra tus lotes registrados.</p>
            </div>
        </div>

        <div class="panel-card">
            <!-- ── Barra de búsqueda ── -->
            <form method="GET" action="consulta_lote.php" id="form-busqueda">
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
                    <a href="consulta_lote.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <!-- ── Contador ── -->
                <div class="results-meta" style="margin: 20px 0;">
                    <span>Resultados: </span><strong><?= $total ?></strong> lote<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                </div>

                <!-- ── Tabla ── -->
                <table class="lotes-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Lote</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lotes)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                                <circle cx="11" cy="11" r="8"/>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                                <line x1="8" y1="11" x2="14" y2="11"/>
                                            </svg>
                                            <p>No se encontraron lotes para esta búsqueda.</p>
                                            <span>Intenta con otro término de búsqueda.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lotes as $l): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($l['ID_LOTE']) ?></td>
                                        <td><?= htmlspecialchars($l['LOTE_NOMBRE']) ?></td>
                                        <td><strong><?= htmlspecialchars($l['ESTADO_TRAMITE']) ?></strong></td>
                                        <td><?= htmlspecialchars($l['FECHA_CREACION']) ?></td>
                                        <td>
                                            <a href="matriz.php?lote=<?= htmlspecialchars($l['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Materiales</a>
                                            <?php if ($l['ESTADO_TRAMITE'] === 'Borrador'): ?>
                                                <a href="editar.php?id=<?= htmlspecialchars($l['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </main>
</div>

<script src="../js/apartados.js"></script>
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
