<?php
require_once 'conexion.php';
require_once 'csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if (!in_array($rol, ['coordinacion', 'coordinador'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = 'success';

// Foto de perfil (mostrar si existe)
$usuarioId = intval($_SESSION['usuario_id']);
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = 'uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['item_id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $itemId = intval($_POST['item_id']);
    $action = $_POST['action'] === 'reject' ? 'Rechazado' : 'Aprobado';

    try {
        $stmtUpdate = $pdo->prepare("UPDATE matriz_item SET ESTADO_ITEM = ? WHERE ID_MATRIZ_ITEM = ?");
        $stmtUpdate->execute([$action, $itemId]);

        $message = "<div class=\"alert success\">✓ Ítem técnico actualizado a <strong>{$action}</strong>.</div>";
    } catch (Exception $e) {
        error_log('Coordinador aprobación error: ' . $e->getMessage());
        $message = '<div class="alert error">✗ No fue posible actualizar el estado. Intente de nuevo.</div>';
    }
}

// Items de ficha técnica pendientes o con estado
$pendingStmt = $pdo->query(
    "SELECT mi.ID_MATRIZ_ITEM, mi.DESCRIPCION_BIEN, mi.FICHA_TECNICA, COALESCE(mi.ESTADO_ITEM, 'Pendiente') AS ESTADO_ITEM,
            mi.UNIDAD_MEDIDA, mi.CANTIDAD_REGULAR,
            lr.ID_LOTE, lr.LOTE_NOMBRE,
            CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS SOLICITANTE,
            CONCAT(ua.NOMBRE, ' ', ua.APELLIDO) AS APOYO
     FROM matriz_item mi
     INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
     LEFT JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
     LEFT JOIN usuario ua ON mi.INSTRUCTOR_APOYO = ua.ID_USUARIO
     WHERE mi.FICHA_TECNICA IS NOT NULL AND mi.FICHA_TECNICA <> ''
     ORDER BY mi.ID_MATRIZ_ITEM DESC"
);
$pendingItems = $pendingStmt->fetchAll();

$historyStmt = $pdo->query(
    "SELECT ft.ID_FICHA_TECNICA, ft.FECHA_EMISION, ft.NOMBRE_ITEM, ft.CODIGO_UNSPSC_FK,
            ft.DENOMINACION_TECNICA_BIEN, ft.UNIDAD_MEDIDA, ft.DESCRIPCION_GENERAL, ft.COMENTARIOS,
            mi.ID_LOTE, lr.LOTE_NOMBRE,
            CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS SOLICITANTE
     FROM ficha_tecnica ft
     INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
     INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
     LEFT JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
     ORDER BY ft.FECHA_EMISION DESC
     LIMIT 150"
);
$historyFichas = $historyStmt->fetchAll();

$existenciasStmt = $pdo->query(
    "SELECT ce.ID_CERTIFICADO, ce.NUMERO_CERTIFICADO, lr.ID_LOTE, lr.LOTE_NOMBRE,
            CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS SOLICITANTE
     FROM certificado_existencia ce
     LEFT JOIN lote_requerimiento lr ON ce.ID_LOTE = lr.ID_LOTE
     LEFT JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
     ORDER BY ce.ID_CERTIFICADO DESC"
);
$existencias = $existenciasStmt->fetchAll();

$instructorStmt = $pdo->query(
    "SELECT s.ID_INSTRUCTOR_LIDER, s.ID_INSTRUCTOR_APOYO,
            CONCAT(l.NOMBRE, ' ', l.APELLIDO) AS LIDER,
            CONCAT(a.NOMBRE, ' ', a.APELLIDO) AS APOYO
     FROM solicitante s
     LEFT JOIN usuario l ON s.ID_INSTRUCTOR_LIDER = l.ID_USUARIO
     LEFT JOIN usuario a ON s.ID_INSTRUCTOR_APOYO = a.ID_USUARIO
     ORDER BY l.NOMBRE, l.APELLIDO, a.NOMBRE, a.APELLIDO"
);
$instructores = $instructorStmt->fetchAll();

function badgeForEstado($estado) {
    $normalized = strtolower(trim($estado));
    if ($normalized === 'borrador') return 'badge-borrador';
    if ($normalized === 'enviado') return 'badge-enviado';
    if ($normalized === 'aprobado') return 'badge-aprobado';
    if ($normalized === 'rechazado') return 'badge-rechazado';
    if ($normalized === 'pendiente') return 'badge-pendiente';
    return 'badge-default';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Coordinador - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header class="dashboard-header">
    <div class="header-brand">
        <img src="imagenes/sena-logo.png" alt="SENA">
        <div class="header-brand-text">
            <span class="header-brand-title">BICERGAM | SENA</span>
            <span class="header-brand-subtitle">Panel Coordinador</span>
        </div>
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
            <span>Coordinador</span>
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="#aprobacion" class="sidebar-link sidebar-link--primary">Aprobación de Fichas</a>
            <a href="index.php?from=coordinador" class="sidebar-link">Ver Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="#historial-fichas" class="sidebar-link">Historial de Fichas</a>
            <a href="#historial-existencias" class="sidebar-link">Historial de Existencias</a>
            <a href="#instructores" class="sidebar-link">Instructores Asociados</a>
        </div>
        <div class="sidebar-group">
            <h4>Perfil</h4>
            <a href="profile.php" class="sidebar-link">Editar Perfil</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Coordinador</h2>
                <p class="dashboard-subtitle">Administre la aprobación de fichas técnicas, revise historiales y consulte instructores asociados.</p>
            </div>
        </div>

        <?= $message ?>

        <div class="profile-coord-card">
            <?php if (!empty($photoPath)): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-coord-avatar" style="object-fit:cover;" />
            <?php else: ?>
                <div class="profile-coord-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
            <?php endif; ?>
            <div class="profile-coord-info">
                <h3 class="profile-coord-name"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></h3>
                <p class="profile-coord-role">Coordinador de Pre-compra</p>
                <div class="profile-coord-meta">
                    <span>Rol: <?= htmlspecialchars($_SESSION['rol_nombre']) ?></span>
                    <span>Usuario ID: <?= htmlspecialchars($_SESSION['usuario_id']) ?></span>
                </div>
                <a class="profile-coord-link" href="profile.php">Editar perfil</a>
            </div>
        </div>

        <div class="panel-grid">
            <div class="panel-main">
                <div class="stat-grid">
                    <div class="stat-card">
                        <h3>Fichas Técnicas</h3>
                        <p><?= count($historyFichas) ?></p>
                        <span class="small-text">Registradas</span>
                    </div>
                    <div class="stat-card">
                        <h3>Ítems con ficha</h3>
                        <p><?= count($pendingItems) ?></p>
                        <span class="small-text">Para revisión</span>
                    </div>
                    <div class="stat-card">
                        <h3>Historial de existencias</h3>
                        <p><?= count($existencias) ?></p>
                        <span class="small-text">Registros</span>
                    </div>
                    <div class="stat-card">
                        <h3>Asociaciones</h3>
                        <p><?= count($instructores) ?></p>
                        <span class="small-text">Líderes / Apoyo</span>
                    </div>
                </div>

                <section id="aprobacion" class="section-card panel-card">
                    <h3 class="section-title">Aprobación de Fichas Técnicas</h3>
                    <p class="small-text">Revise las fichas técnicas cargadas por instructores y actualice el estado del ítem.</p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th># Ítem</th>
                                    <th>Lote</th>
                                    <th>Solicitante</th>
                                    <th>Instructor Apoyo</th>
                                    <th>Estado</th>
                                    <th>Ficha Técnica</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingItems)): ?>
                                    <tr><td colspan="7" style="text-align:center;">No hay fichas técnicas cargadas para revisión.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingItems as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                                            <td><?= htmlspecialchars($item['LOTE_NOMBRE']) ?></td>
                                            <td><?= htmlspecialchars($item['SOLICITANTE']) ?></td>
                                            <td><?= htmlspecialchars($item['APOYO'] ?: '—') ?></td>
                                            <td><span class="badge <?= badgeForEstado($item['ESTADO_ITEM']) ?>"><?= htmlspecialchars($item['ESTADO_ITEM']) ?></span></td>
                                            <td style="max-width:260px;"><div style="white-space:normal; overflow:hidden; text-overflow:ellipsis; max-height:84px;"><?= htmlspecialchars($item['FICHA_TECNICA']) ?></div></td>
                                            <td>
                                                <form action="coordinador_dashboard.php#aprobacion" method="POST" style="display:inline-flex; gap:6px; flex-wrap:wrap;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                                    <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-sena" style="padding: 8px 10px; font-size: 12px;">Aprobar</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger" style="padding: 8px 10px; font-size: 12px;">Rechazar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="historial-fichas" class="section-card panel-card">
                    <h3 class="section-title">Historial de Fichas Técnicas</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Lote</th>
                                    <th>Solicitante</th>
                                    <th>Nombre item</th>
                                    <th>Código UNSPSC</th>
                                    <th>Unidad</th>
                                    <th>Comentarios</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historyFichas)): ?>
                                    <tr><td colspan="8" style="text-align:center;">Aún no hay fichas técnicas registradas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historyFichas as $ficha): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ficha['ID_FICHA_TECNICA']) ?></td>
                                            <td><?= htmlspecialchars($ficha['FECHA_EMISION']) ?></td>
                                            <td><?= htmlspecialchars($ficha['LOTE_NOMBRE']) ?></td>
                                            <td><?= htmlspecialchars($ficha['SOLICITANTE']) ?></td>
                                            <td><?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?></td>
                                            <td><?= htmlspecialchars($ficha['CODIGO_UNSPSC_FK'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($ficha['UNIDAD_MEDIDA']) ?></td>
                                            <td><?= htmlspecialchars(mb_strimwidth($ficha['COMENTARIOS'] ?: $ficha['DESCRIPCION_GENERAL'] ?? '—', 0, 80, '...')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="historial-existencias" class="section-card panel-card">
                    <h3 class="section-title">Historial de Existencias</h3>
                    <p class="small-text">Registro de certificados de existencia asociados a lotes e ítems.</p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th># Certificado</th>
                                    <th>Número</th>
                                    <th>Lote</th>
                                    <th>Item</th>
                                    <th>Solicitante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($existencias)): ?>
                                    <tr><td colspan="5" style="text-align:center;">No hay registros de existencias disponibles.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($existencias as $existencia): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($existencia['ID_CERTIFICADO']) ?></td>
                                            <td><?= htmlspecialchars($existencia['NUMERO_CERTIFICADO']) ?></td>
                                            <td><?= htmlspecialchars($existencia['LOTE_NOMBRE'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($existencia['ITEM_DESCRIPCION'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($existencia['SOLICITANTE'] ?: '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="instructores" class="section-card panel-card">
                    <h3 class="section-title">Instructores Asociados</h3>
                    <p class="small-text">Lista de instructores líderes y apoyo registrados en la tabla de solicitantes.</p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Líder</th>
                                    <th>Apoyo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($instructores)): ?>
                                    <tr><td colspan="2" style="text-align:center;">No hay instructores asociados registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($instructores as $assoc): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($assoc['LIDER'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($assoc['APOYO'] ?: '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <aside class="panel-aside">
                <div class="section-card panel-card">
                    <h3 class="section-title">Acciones rápidas</h3>
                    <div class="action-links">
                        <a href="#aprobacion" class="btn btn-sena">Revisar fichas</a>
                        <a href="#historial-fichas" class="btn btn-secondary">Ver historial</a>
                        <a href="profile.php" class="btn btn-secondary">Editar perfil</a>
                    </div>
                </div>
                <div class="section-card panel-card">
                    <h3 class="section-title">Perfil resumido</h3>
                    <p class="small-text">Este módulo contiene el resumen del coordinador y acceso directo a su configuración.</p>
                    <div class="profile-coord-meta" style="flex-direction:column; align-items:flex-start; gap:12px; margin-top:10px;">
                        <span><strong>Nombre:</strong> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                        <span><strong>Rol:</strong> <?= htmlspecialchars($_SESSION['rol_nombre']) ?></span>
                        <span><strong>ID de Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario_id']) ?></span>
                        <span><strong>Sección actual:</strong> Coordinador</span>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>

</body>
</html>
