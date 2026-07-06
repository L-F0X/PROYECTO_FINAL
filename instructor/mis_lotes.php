<?php
// instructor/mis_lotes.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

// Parámetros de búsqueda y filtrado
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

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

$msg = $_GET['msg'] ?? '';
$messageText = '';
if ($msg === 'editado') {
    $messageText = '✓ Lote actualizado correctamente.';
} elseif ($msg === 'no_editable') {
    $messageText = '✗ Este lote ya no se puede editar: solo los lotes en Borrador son editables.';
} elseif ($msg === 'reabierto') {
    $messageText = '✓ Lote regresado a Borrador. Ya puedes corregirlo y reenviarlo.';
}

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
    <title>Mis Lotes - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
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
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"><span>BICERGAM</span></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link sidebar-link--primary active">Mis Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link">Ficha Técnica</a>
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
                <h2>Mis Lotes</h2>
                <p class="dashboard-subtitle">Consulta, edita y da seguimiento a los lotes de requerimiento que has creado.</p>
            </div>
        </div>

        <?php if (!empty($messageText)): ?>
            <div class="profile-alert <?= $msg === 'no_editable' ? 'error' : 'success' ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; <?= $msg === 'no_editable' ? 'background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;' : 'background: #eff8f1; color: #270; border: 1px solid #d4ebd5;' ?>">
                <?= htmlspecialchars($messageText) ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <div class="actions-bar no-print" style="border: none; padding: 0; margin: 0 0 20px; justify-content: flex-end;">
                <a href="crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
            </div>

            <!-- Formulario de búsqueda -->
            <form method="GET" action="mis_lotes.php" id="form-busqueda" style="margin-bottom: 20px;">
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
                    <a href="mis_lotes.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <h3 style="margin-top: 0;">Lotes Registrados (Total: <?= $total ?>)</h3>
                <table style="width: 100%; margin-top: 15px;">
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
                                        <?php if ($lote['ESTADO_TRAMITE'] === 'Borrador'): ?>
                                            <a href="editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                                        <?php elseif ($lote['ESTADO_TRAMITE'] === 'Rechazado'): ?>
                                            <form action="reabrir_lote.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('El lote volverá a estado Borrador para que lo corrijas y lo reenvíes. ¿Continuar?');">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                                <button type="submit" class="btn" style="padding: 5px 10px; font-size: 12px; border: none; background: #ffc107; color: #664d03; border-radius: 4px;">Corregir y Reenviar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px; opacity: 0.5; cursor: not-allowed;" title="Solo los lotes en Borrador se pueden editar.">Editar Lote</span>
                                        <?php endif; ?>
                                        <form action="eliminar.php" method="POST" style="display:inline; margin:0;">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Eliminar</button>
                                        </form>
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
