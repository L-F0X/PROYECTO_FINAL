<?php
// Administrador/importar_unspsc.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'administrador') {
    header('Location: ../index.php');
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador');

require_once __DIR__ . '/unspsc_import_lib.php';

$resultado = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    set_time_limit(0);
    try {
        unspsc_asegurar_columnas($pdo);
        $rutaCsv = __DIR__ . '/../USNPSC/codigos Unspcs.csv';
        $inicio = microtime(true);
        $resultado = unspsc_importar_csv($pdo, $rutaCsv);
        $resultado['segundos'] = round(microtime(true) - $inicio, 1);
    } catch (Exception $e) {
        error_log('Error importando UNSPSC: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

$totalActual = (int) $pdo->query("SELECT COUNT(*) FROM codigo_unspsc")->fetchColumn();
$conCatalogo = (int) $pdo->query("SELECT COUNT(*) FROM codigo_unspsc WHERE NOMBRE_PRODUCTO IS NOT NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Importar UNSPSC</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Administrador</span></h1>
            <div class="user-greeting">Administrador del Sistema: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Administrador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?></a>
        <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Administración</h4>
            <a href="index.php" class="sidebar-link">Gestión Usuarios</a>
            <a href="importar_unspsc.php" class="sidebar-link sidebar-link--primary active">Importar UNSPSC</a>
            <a href="gestionar_iva.php" class="sidebar-link">Gestionar IVA</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="../instructor/index.php" class="sidebar-link">Panel Instructor</a>
            <a href="../coordinador/index.php" class="sidebar-link">Panel Coordinador</a>
            <a href="../almacenista/index.php" class="sidebar-link">Panel Almacenista</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Importar Catálogo UNSPSC</h2>
                <p class="dashboard-subtitle">Carga o actualiza el catálogo oficial UNSPSC desde el archivo CSV del proyecto.</p>
            </div>
        </div>

        <div class="container fade-in" style="margin: 0; max-width: 100%;">

            <?php if ($error): ?>
                <div class="profile-alert error" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; background: #fdeeee; color: #7a1f1f; border: 1px solid #f0c6c6;">
                    ✗ Error al importar: <?= htmlspecialchars($error) ?>
                </div>
            <?php elseif ($resultado): ?>
                <div class="profile-alert success" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
                    ✓ Importación completada en <?= htmlspecialchars($resultado['segundos']) ?>s.
                    Filas leídas: <strong><?= number_format($resultado['leidas']) ?></strong> ·
                    Insertadas: <strong><?= number_format($resultado['insertadas']) ?></strong> ·
                    Actualizadas: <strong><?= number_format($resultado['actualizadas']) ?></strong> ·
                    Con error: <strong><?= number_format($resultado['errores']) ?></strong>
                </div>
            <?php endif; ?>

            <div style="background: var(--gris-claro); padding: 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid var(--verde-sena);">
                <p>Códigos actualmente en catálogo: <strong><?= number_format($totalActual) ?></strong>
                    (con datos de producto importados: <strong><?= number_format($conCatalogo) ?></strong>)</p>
                <p>Origen del archivo: <code>USNPSC/codigos Unspcs.csv</code></p>
                <p style="color:#555; font-size: 13px;">La importación es segura de repetir: los códigos ya existentes se actualizan en vez de duplicarse.</p>

                <form method="POST" action="importar_unspsc.php" onsubmit="return confirm('¿Ejecutar la importación del catálogo UNSPSC? Puede tardar varios minutos.');">
                    <input type="hidden" name="accion" value="importar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <button type="submit" class="btn btn-sena">Ejecutar Importación</button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>
