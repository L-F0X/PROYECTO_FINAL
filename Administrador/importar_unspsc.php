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
$esPeticionAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        if ($esPeticionAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
            exit;
        }
        die('Token CSRF inválido.');
    }
    set_time_limit(0);
    try {
        unspsc_asegurar_columnas($pdo);
        $rutaCsv = __DIR__ . '/../USNPSC/codigos Unspcs.csv';
        $inicio = microtime(true);
        $resultado = unspsc_importar_csv($pdo, $rutaCsv);
        $resultado['segundos'] = round(microtime(true) - $inicio, 1);

        // Notificación dentro del sistema (bandeja/campana), no solo el banner
        // de esta página: así se entera aunque haya navegado a otra pantalla.
        crear_notificacion(
            $pdo,
            intval($_SESSION['usuario_id']),
            "✓ Importación de catálogo UNSPSC completada en {$resultado['segundos']}s. " .
                "Insertadas: {$resultado['insertadas']}, actualizadas: {$resultado['actualizadas']}, con error: {$resultado['errores']}.",
            'importar_unspsc.php'
        );
    } catch (Exception $e) {
        error_log('Error importando UNSPSC: ' . $e->getMessage());
        $error = $e->getMessage();
        crear_notificacion(
            $pdo,
            intval($_SESSION['usuario_id']),
            "✗ Error al importar el catálogo UNSPSC: $error",
            'importar_unspsc.php'
        );
    }

    if ($esPeticionAjax) {
        header('Content-Type: application/json');
        if ($error) {
            echo json_encode(['ok' => false, 'error' => $error]);
        } else {
            echo json_encode(['ok' => true, 'resultado' => $resultado]);
        }
        exit;
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
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?></a>
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

                <form method="POST" action="importar_unspsc.php" id="form-importar-unspsc">
                    <input type="hidden" name="accion" value="importar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <button type="submit" class="btn btn-sena" id="btn-importar-unspsc">Ejecutar Importación</button>
                </form>
                <p id="importar-unspsc-status" style="margin-top: 10px; font-size: 13px; color: #555;"></p>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
<script>
    // Importación asíncrona: en vez de un POST de página completa (que bloquea
    // el navegador y "inutiliza" el resto de la interfaz mientras el CSV de
    // ~150k filas se procesa), se envía por fetch en segundo plano. La página
    // sigue siendo usable (sidebar, otros enlaces) mientras la importación corre;
    // el resultado llega como toast y además queda en la campana de notificaciones
    // por si el administrador ya navegó a otra pantalla de esta misma sesión.
    document.getElementById('form-importar-unspsc').addEventListener('submit', async function (e) {
        e.preventDefault();
        const confirmado = await confirmAction({
            title: 'Ejecutar Importación',
            message: '¿Ejecutar la importación del catálogo UNSPSC? Puede tardar varios minutos, pero podrás seguir usando el resto del sistema mientras corre.',
            confirmLabel: 'Ejecutar',
            danger: false
        });
        if (!confirmado) return;

        const form = this;
        const boton = document.getElementById('btn-importar-unspsc');
        const estado = document.getElementById('importar-unspsc-status');
        boton.disabled = true;
        boton.textContent = 'Importando...';
        estado.textContent = 'Importación en curso, esto puede tardar varios minutos. Puedes seguir navegando por el resto del sistema mientras tanto.';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'fetch' }
            });
            const data = await response.json();
            if (data.ok) {
                const r = data.resultado;
                showToast(
                    `✓ Importación completada en ${r.segundos}s. Insertadas: ${r.insertadas}, actualizadas: ${r.actualizadas}, con error: ${r.errores}.`,
                    'success',
                    9000
                );
            } else {
                showToast('✗ Error al importar: ' + data.error, 'error', 9000);
            }
        } catch (err) {
            showToast('✗ No se pudo completar la importación. Intente de nuevo.', 'error', 9000);
        } finally {
            boton.disabled = false;
            boton.textContent = 'Ejecutar Importación';
            estado.textContent = '';
            // Refresca los contadores de "Códigos actualmente en catálogo" una
            // vez terminó (no durante la carga, por eso no bloquea nada antes).
            window.location.reload();
        }
    });
</script>
</body>
</html>
