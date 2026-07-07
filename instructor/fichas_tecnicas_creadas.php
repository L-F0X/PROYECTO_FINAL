<?php
// fichas_tecnicas_creadas.php — Catálogo de Fichas Técnicas
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$idLote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;

if ($idLote === 0) {
    header("Location: index.php");
    exit;
}

// Verificar que el lote referenciado pertenece al instructor autenticado
$stmtLoteCheck = $pdo->prepare("SELECT 1 FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
$stmtLoteCheck->execute([$idLote, $usuarioId]);
if (!$stmtLoteCheck->fetch()) {
    header("Location: index.php");
    exit;
}

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

$msg = $_GET['msg'] ?? '';
$messageText = '';
if ($msg === 'sin_permiso') {
    $messageText = '✗ No puede editar esta ficha técnica: fue creada por otro instructor.';
}

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta de fichas técnicas: solo las que pertenecen a ítems de este lote
// (una ficha "pertenece" al lote del ítem de matriz al que está asociada).
$sql = "SELECT ft.* FROM ficha_tecnica ft
        INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
        WHERE mi.ID_LOTE = ?";
$params = [$idLote];
if ($busqueda !== '') {
    $sql .= " AND (ft.NOMBRE_ITEM LIKE ? OR ft.CODIGO_UNSPSC_FK LIKE ? OR ft.DENOMINACION_TECNICA_BIEN LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
$sql .= " ORDER BY ft.ID_FICHA_TECNICA DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fichas = $stmt->fetchAll();
$total = count($fichas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichas Técnicas Creadas - BICERGAM</title>
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
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
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
                <h2>Fichas Técnicas del Lote #<?= $idLote ?></h2>
                <p class="dashboard-subtitle">Fichas técnicas creadas para los ítems de este lote.</p>
            </div>
        </div>

        <?php if (!empty($messageText)): ?>
            <div class="profile-alert error" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;">
                <?= htmlspecialchars($messageText) ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <!-- Barra de búsqueda -->
            <form method="GET" action="fichas_tecnicas_creadas.php" id="form-busqueda" style="margin-bottom: 20px;">
                <input type="hidden" name="lote" value="<?= htmlspecialchars($idLote) ?>">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="field-group" style="flex: 1; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar Ficha Técnica</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="search-input"
                            placeholder="Buscar por nombre, código o descripción..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                            style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-secondary" style="padding: 8px 16px; text-decoration: none; border: 1px solid #ccc; background: #eee; color: #333; border-radius: 4px;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <div class="results-meta" style="margin: 15px 0;">
                    <span>Resultados: </span><strong><?= $total ?></strong> ficha<?= $total !== 1 ? 's' : '' ?> encontrada<?= $total !== 1 ? 's' : '' ?>
                </div>

                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Ítem</th>
                            <th>Código UNSPSC</th>
                            <th>Denominación Técnica</th>
                            <th>Unidad de Medida</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fichas)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>Este lote todavía no tiene fichas técnicas creadas.</p>
                                        <span>Crea una ficha técnica para un ítem de este lote y aparecerá aquí.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fichas as $f): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?></td>
                                    <td><strong><?= htmlspecialchars($f['NOMBRE_ITEM']) ?></strong></td>
                                    <td><?= htmlspecialchars($f['CODIGO_UNSPSC_FK']) ?></td>
                                    <td><?= htmlspecialchars($f['DENOMINACION_TECNICA_BIEN']) ?></td>
                                    <td><?= htmlspecialchars($f['UNIDAD_MEDIDA']) ?></td>
                                    <td>
                                        <a href="ver_ficha_tecnica.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>&lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none;">Ver</a>
                                        <a href="editar_ficha_tecnica.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none; background-color: #00324D;">Editar</a>
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
</body>
</html>
