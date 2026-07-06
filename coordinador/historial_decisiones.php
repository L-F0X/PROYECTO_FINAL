<?php
require_once '../conexion.php';
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
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

// Obtener historial de decisiones
try {
    $sql = "SELECT arl.*, lr.LOTE_NOMBRE, u.NOMBRE, u.APELLIDO
            FROM aprobacion_rechazo_lote arl
            INNER JOIN lote_requerimiento lr ON arl.ID_LOTE = lr.ID_LOTE
            INNER JOIN usuario u ON arl.ID_COORDINADOR = u.ID_USUARIO
            WHERE 1=1";
    
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND lr.LOTE_NOMBRE LIKE ?";
        $params[] = "%$busqueda%";
    }

    if ($filtroEstado !== '') {
        $sql .= " AND arl.ESTADO_DECISION = ?";
        $params[] = $filtroEstado;
    }

    $sql .= " ORDER BY arl.FECHA_DECISION DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $decisiones = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching decisiones: ' . $e->getMessage());
    $decisiones = [];
}

$total = count($decisiones);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Decisiones - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Coordinador de Compras: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
            <a href="historial_decisiones.php" class="sidebar-link sidebar-link--primary active">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
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
                <h2>Historial de Decisiones</h2>
                <p class="dashboard-subtitle">Registro de todas las aprobaciones y rechazos de lotes.</p>
            </div>
        </div>

        <div class="panel-card">
            <!-- Filtro -->
            <form method="GET" action="historial_decisiones.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar por lote</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre del lote..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por estado</label>
                        <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <option value="Aprobado" <?= $filtroEstado === 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="Rechazado" <?= $filtroEstado === 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Filtrar</button>
                    <a href="historial_decisiones.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <h3>Total de Decisiones: <?= $total ?></h3>
                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID Lote</th>
                            <th>Nombre Lote</th>
                            <th>Coordinador</th>
                            <th>Decisión</th>
                            <th>Justificación</th>
                            <th>Fecha Decisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($decisiones)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No hay decisiones registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($decisiones as $decision): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;">
                                        <a href="revisar_lote.php?id=<?= htmlspecialchars($decision['ID_LOTE']) ?>" style="color: #39A900; text-decoration: none;">
                                            <?= htmlspecialchars($decision['ID_LOTE']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($decision['LOTE_NOMBRE']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($decision['NOMBRE'] . ' ' . $decision['APELLIDO']) ?></td>
                                    <td style="padding: 12px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; <?= $decision['ESTADO_DECISION'] === 'Aprobado' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                            <?= htmlspecialchars($decision['ESTADO_DECISION']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if (!empty($decision['JUSTIFICACION'])): ?>
                                            <small><?= htmlspecialchars(substr($decision['JUSTIFICACION'], 0, 60)) . (strlen($decision['JUSTIFICACION']) > 60 ? '...' : '') ?></small>
                                        <?php else: ?>
                                            <small style="color: #999;">N/A</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;"><?= htmlspecialchars(substr($decision['FECHA_DECISION'], 0, 19)) ?></td>
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
