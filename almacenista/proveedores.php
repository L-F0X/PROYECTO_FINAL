<?php
// almacenista/proveedores.php - consulta de solo lectura. La gestión
// (crear/editar) vive ahora en instructor/proveedores.php: es el instructor
// quien registra ofertas/proveedores al configurar la matriz de sus ítems.
require_once '../conexion.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'almacenista') {
    header('Location: ../login.php');
    exit;
}

$busqueda = trim($_GET['q'] ?? '');
try {
    if ($busqueda !== '') {
        // Coincidencias por prefijo en cualquier campo se muestran primero
        // (mismo patrón de ranking usado en el resto del proyecto).
        $stmt = $pdo->prepare('SELECT * FROM proveedor WHERE NIT LIKE ? OR RAZON_SOCIAL LIKE ? OR CONTACTO LIKE ?
            ORDER BY CASE WHEN NIT LIKE ? OR RAZON_SOCIAL LIKE ? OR CONTACTO LIKE ? THEN 0 ELSE 1 END, RAZON_SOCIAL');
        $like = "%$busqueda%";
        $prefijo = "$busqueda%";
        $stmt->execute([$like, $like, $like, $prefijo, $prefijo, $prefijo]);
    } else {
        $stmt = $pdo->query('SELECT * FROM proveedor ORDER BY RAZON_SOCIAL');
    }
    $proveedores = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error listando proveedores: ' . $e->getMessage());
    $proveedores = [];
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén Central</span></h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong></div>
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
            <a href="historial_movimientos.php" class="sidebar-link">Historial de Movimientos</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="index.php?tab=instructor" class="sidebar-link">Panel Instructor</a>
            <a href="proveedores.php" class="sidebar-link sidebar-link--primary active">Proveedores</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="almacenista_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <div class="dashboard-topbar">
                <div>
                    <h2>Proveedores</h2>
                    <p class="dashboard-subtitle">Catálogo de proveedores externos que cotizan los ítems de la matriz (solo consulta — el instructor los gestiona al configurar cada ítem).</p>
                </div>
            </div>

            <div class="panel-card" style="margin-top: 20px;">
                <form method="GET" action="proveedores.php" id="form-busqueda" style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <input type="text" id="q" name="q" class="form-control" placeholder="Buscar por NIT, Razón Social o Contacto..." value="<?= htmlspecialchars($busqueda) ?>" style="max-width: 300px;">
                </form>
                <div id="resultados-busqueda">
                <h3>Proveedores Registrados (<?= count($proveedores) ?>)</h3>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NIT</th>
                            <th>Razón Social</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Contacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No hay proveedores registrados.</p>
                                        <span>Los proveedores que registre un instructor aparecerán aquí.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($proveedores as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['ID_PROVEEDOR']) ?></td>
                                    <td><?= htmlspecialchars($p['NIT']) ?></td>
                                    <td><?= htmlspecialchars($p['RAZON_SOCIAL']) ?></td>
                                    <td><?= htmlspecialchars($p['EMAIL']) ?></td>
                                    <td><?php if (($p['TELEFONO'] ?? '') !== ''): ?><?= htmlspecialchars($p['TELEFONO']) ?><?php else: ?><span style="color:#999; font-style:italic;">Sin registrar</span><?php endif; ?></td>
                                    <td><?php if (($p['CONTACTO'] ?? '') !== ''): ?><?= htmlspecialchars($p['CONTACTO']) ?><?php else: ?><span style="color:#999; font-style:italic;">Sin registrar</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
