<?php
// almacenista/historial_movimientos.php
require_once '../conexion.php';
require_once '../notificaciones.php';
require_once '../auditoria_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'almacenista') {
    header('Location: ../login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$filtroTipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

// Obtener historial de movimientos de inventario
asegurar_tabla_auditoria($pdo);
try {
    $sql = "SELECT aa.*, u.NOMBRE, u.APELLIDO
            FROM auditoria_actividad aa
            INNER JOIN usuario u ON aa.ID_USUARIO = u.ID_USUARIO
            WHERE aa.ACCION IN ('Entrada Inventario', 'Salida Inventario')";

    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND aa.DETALLE LIKE ?";
        $params[] = "%$busqueda%";
    }

    if ($filtroTipo !== '') {
        $sql .= " AND aa.ACCION = ?";
        $params[] = $filtroTipo;
    }

    $sql .= " ORDER BY aa.FECHA DESC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching movimientos: ' . $e->getMessage());
    $movimientos = [];
}

$total = count($movimientos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Movimientos - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén Central</span></h1>
            <div class="user-greeting">Gestor de Turno: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Almacenista)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, $usuarioId); $wsToken = generar_ws_token($pdo, $usuarioId, $_SESSION['rol_nombre'] ?? ''); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="almacenista_profile.php" class="header-avatar-link" title="Editar perfil">
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
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Inventario</h4>
            <a href="index.php?tab=stock" class="sidebar-link">Vista de Stock</a>
            <a href="index.php?tab=entrada" class="sidebar-link">Registrar Entrada</a>
            <a href="index.php?tab=salida" class="sidebar-link">Registrar Salida</a>
            <a href="historial_movimientos.php" class="sidebar-link sidebar-link--primary active">Historial de Movimientos</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="index.php?tab=instructor" class="sidebar-link">Panel Instructor</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="almacenista_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Historial de Movimientos</h2>
                <p class="dashboard-subtitle">Registro de todas las entradas y salidas de inventario.</p>
            </div>
        </div>

        <div class="panel-card">
            <form method="GET" action="historial_movimientos.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar en el detalle</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por ítem, comentario..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="tipo" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por tipo</label>
                        <select name="tipo" id="tipo" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <option value="Entrada Inventario" <?= $filtroTipo === 'Entrada Inventario' ? 'selected' : '' ?>>Entrada</option>
                            <option value="Salida Inventario" <?= $filtroTipo === 'Salida Inventario' ? 'selected' : '' ?>>Salida</option>
                        </select>
                    </div>
                    <a href="historial_movimientos.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <h3>Total de Movimientos: <?= $total ?></h3>
                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No hay movimientos registrados.</p>
                                        <span>Las entradas y salidas de inventario aparecerán aquí.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?= htmlspecialchars(substr($mov['FECHA'], 0, 19)) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($mov['NOMBRE'] . ' ' . $mov['APELLIDO']) ?></td>
                                    <td style="padding: 12px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; <?= $mov['ACCION'] === 'Entrada Inventario' ? 'background: #d4edda; color: #155724;' : 'background: #cce5ff; color: #004085;' ?>">
                                            <?= $mov['ACCION'] === 'Entrada Inventario' ? 'Entrada' : 'Salida' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($mov['DETALLE']) ?></td>
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
