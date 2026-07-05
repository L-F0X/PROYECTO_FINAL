<?php
require_once '../conexion.php';
require_once '../csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

// Crear tabla de auditoría si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS aprobacion_rechazo_lote (
        ID_DECISION INT AUTO_INCREMENT PRIMARY KEY,
        ID_LOTE INT NOT NULL,
        ID_COORDINADOR INT NOT NULL,
        ESTADO_DECISION ENUM('Aprobado', 'Rechazado') NOT NULL,
        JUSTIFICACION TEXT DEFAULT NULL,
        FECHA_DECISION TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ID_LOTE) REFERENCES lote_requerimiento(ID_LOTE),
        FOREIGN KEY (ID_COORDINADOR) REFERENCES usuario(ID_USUARIO)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('Error creating audit table: ' . $e->getMessage());
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Obtener todos los lotes para coordinador
try {
    // Los lotes en Borrador aún no han sido enviados por el instructor,
    // así que el coordinador no debe verlos todavía.
    $sql = "SELECT lr.*, u.NOMBRE, u.APELLIDO, u.EMAIL,
            (SELECT COUNT(*) FROM matriz_item WHERE ID_LOTE = lr.ID_LOTE) as ITEMS_COUNT
            FROM lote_requerimiento lr
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            WHERE lr.ESTADO_TRAMITE != 'Borrador'";
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (lr.LOTE_NOMBRE LIKE ? OR lr.ID_LOTE = ?)";
        $params[] = "%$busqueda%";
        $params[] = intval($busqueda);
    }

    if ($filtroEstado !== '') {
        $sql .= " AND lr.ESTADO_TRAMITE = ?";
        $params[] = $filtroEstado;
    }

    $sql .= " ORDER BY lr.FECHA_CREACION DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lotes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching lotes: ' . $e->getMessage());
    $lotes = [];
}

$total = count($lotes);

$msg = $_GET['msg'] ?? '';
$messageText = '';
$msgType = 'success';

$procesados = intval($_GET['procesados'] ?? 0);
$omitidos = intval($_GET['omitidos'] ?? 0);

if ($msg === 'aprobado') {
    $messageText = '✓ Lote aprobado exitosamente.';
} elseif ($msg === 'rechazado') {
    $messageText = '✓ Lote rechazado exitosamente.';
} elseif ($msg === 'masivo_aprobado') {
    $messageText = "✓ $procesados lote(s) aprobado(s)." . ($omitidos > 0 ? " $omitidos omitido(s) por no estar en estado Enviado." : '');
} elseif ($msg === 'masivo_rechazado') {
    $messageText = "✓ $procesados lote(s) rechazado(s)." . ($omitidos > 0 ? " $omitidos omitido(s) por no estar en estado Enviado." : '');
} elseif ($msg === 'sinseleccion') {
    $messageText = '✗ Debe seleccionar al menos un lote.';
    $msgType = 'error';
} elseif ($msg === 'faltajustificacion') {
    $messageText = '✗ La justificación es requerida para rechazar lotes.';
    $msgType = 'error';
} elseif ($msg === 'error') {
    $messageText = '✗ Ocurrió un error al procesar la solicitud.';
    $msgType = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Lotes - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
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
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="revisar_lotes.php" class="sidebar-link sidebar-link--primary active">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
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
                <h2>Revisión de Lotes</h2>
                <p class="dashboard-subtitle">Revisa, aprueba o rechaza los lotes enviados por los instructores.</p>
            </div>
        </div>

        <?php if ($messageText): ?>
            <div class="profile-alert <?= $msgType ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; <?= $msgType === 'error' ? 'background: #fdeeee; color: #7a1f1f; border: 1px solid #f0c6c6;' : 'background: #eff8f1; color: #270; border: 1px solid #d4ebd5;' ?>">
                <?= htmlspecialchars($messageText) ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <!-- Formulario de búsqueda y filtrado -->
            <form method="GET" action="revisar_lotes.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar lote</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre o ID..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por estado</label>
                        <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <option value="Enviado" <?= $filtroEstado === 'Enviado' ? 'selected' : '' ?>>Enviado</option>
                            <option value="Aprobado" <?= $filtroEstado === 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="Rechazado" <?= $filtroEstado === 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <a href="revisar_lotes.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <?php $totalEnviados = count(array_filter($lotes, fn($l) => $l['ESTADO_TRAMITE'] === 'Enviado')); ?>
            <div id="resultados-busqueda">
                <h3>Lotes de Requerimiento (Total: <?= $total ?>)</h3>

                <form method="POST" action="procesar_lotes_masivo.php" id="form-masivo">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="accion" id="masivo-accion" value="">

                    <?php if ($totalEnviados > 0): ?>
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; background:#f5f5f5; padding:12px 15px; border-radius:6px; margin:10px 0;">
                            <strong id="contador-seleccion">0 lote(s) seleccionado(s)</strong>
                            <button type="button" class="btn" style="padding:6px 14px; font-size:13px; background-color:#28a745; color:white; border:none; border-radius:4px;" onclick="confirmarAccionMasiva('aprobar')">Aprobar Seleccionados</button>
                            <button type="button" class="btn" style="padding:6px 14px; font-size:13px; background-color:#dc3545; color:white; border:none; border-radius:4px;" onclick="mostrarJustificacionMasiva()">Rechazar Seleccionados</button>
                        </div>
                        <div id="caja-justificacion-masiva" style="display:none; background:#fff3f3; border:1px solid #f5c6cb; padding:15px; border-radius:6px; margin-bottom:10px;">
                            <label for="justificacion-masiva" style="display:block; font-weight:600; margin-bottom:8px;">Justificación del Rechazo Masivo *</label>
                            <textarea id="justificacion-masiva" name="justificacion" rows="3" style="width:100%; padding:10px; border:1px solid #d4dadb; border-radius:6px;" placeholder="Explique por qué se rechazan los lotes seleccionados..."></textarea>
                            <div style="margin-top:10px; display:flex; gap:10px;">
                                <button type="button" class="btn" style="padding:6px 14px; font-size:13px; background-color:#dc3545; color:white; border:none; border-radius:4px;" onclick="confirmarAccionMasiva('rechazar')">Confirmar Rechazo Masivo</button>
                                <button type="button" class="btn btn-secondary" style="padding:6px 14px; font-size:13px;" onclick="document.getElementById('caja-justificacion-masiva').style.display='none';">Cancelar</button>
                            </div>
                        </div>
                    <?php endif; ?>

                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php if ($totalEnviados > 0): ?><input type="checkbox" id="check-todos" title="Seleccionar todos los Enviados"><?php endif; ?></th>
                            <th>ID Lote</th>
                            <th>Nombre</th>
                            <th>Instructor</th>
                            <th>Items</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">No hay lotes registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $lote): ?>
                                <tr>
                                    <td>
                                        <?php if ($lote['ESTADO_TRAMITE'] === 'Enviado'): ?>
                                            <input type="checkbox" name="lotes[]" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="check-lote">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                                    <td><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                                    <td><?= htmlspecialchars($lote['NOMBRE'] . ' ' . $lote['APELLIDO']) ?></td>
                                    <td><?= htmlspecialchars($lote['ITEMS_COUNT']) ?></td>
                                    <td><strong><?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></strong></td>
                                    <td><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="revisar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Ver</a>
                                            <?php if ($lote['ESTADO_TRAMITE'] === 'Enviado'): ?>
                                                <a href="aprobar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 5px 10px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; text-decoration: none; cursor: pointer;">Aprobar</a>
                                                <a href="rechazar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 5px 10px; font-size: 12px; background-color: #dc3545; color: white; border: none; border-radius: 4px; text-decoration: none; cursor: pointer;">Rechazar</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
    </main>
</div>

<script src="../js/apartados.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkTodos = document.getElementById('check-todos');
    const checksLote = document.querySelectorAll('.check-lote');
    const contador = document.getElementById('contador-seleccion');

    function actualizarContador() {
        if (!contador) return;
        const marcados = document.querySelectorAll('.check-lote:checked').length;
        contador.textContent = marcados + ' lote(s) seleccionado(s)';
    }

    if (checkTodos) {
        checkTodos.addEventListener('change', function () {
            checksLote.forEach(cb => { cb.checked = checkTodos.checked; });
            actualizarContador();
        });
    }
    checksLote.forEach(cb => cb.addEventListener('change', actualizarContador));

    window.mostrarJustificacionMasiva = function () {
        if (document.querySelectorAll('.check-lote:checked').length === 0) {
            alert('Seleccione al menos un lote.');
            return;
        }
        document.getElementById('caja-justificacion-masiva').style.display = 'block';
    };

    window.confirmarAccionMasiva = function (accion) {
        const marcados = document.querySelectorAll('.check-lote:checked').length;
        if (marcados === 0) {
            alert('Seleccione al menos un lote.');
            return;
        }
        if (accion === 'rechazar' && document.getElementById('justificacion-masiva').value.trim() === '') {
            alert('La justificación es requerida para rechazar lotes.');
            return;
        }
        const confirmacion = accion === 'aprobar'
            ? '¿Aprobar los ' + marcados + ' lote(s) seleccionado(s)?'
            : '¿Rechazar los ' + marcados + ' lote(s) seleccionado(s)?';
        if (!confirm(confirmacion)) return;

        document.getElementById('masivo-accion').value = accion;
        document.getElementById('form-masivo').submit();
    };
});
</script>
</body>
</html>
