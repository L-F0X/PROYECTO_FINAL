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

// Procesar envío selectivo de fichas técnicas al coordinador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'enviar_fichas') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $idsMatrizItem = isset($_POST['matriz_item']) && is_array($_POST['matriz_item']) ? array_map('intval', $_POST['matriz_item']) : [];
    $idsMatrizItem = array_values(array_filter($idsMatrizItem, fn($v) => $v > 0));

    if (empty($idsMatrizItem)) {
        header("Location: fichas_tecnicas_creadas.php?lote=$idLote&msg=sin_seleccion");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($idsMatrizItem), '?'));
        $stmtCheck = $pdo->prepare("SELECT ID_MATRIZ_ITEM FROM matriz_item WHERE ID_LOTE = ? AND ESTADO_ITEM = 'Borrador' AND ID_MATRIZ_ITEM IN ($placeholders)");
        $stmtCheck->execute(array_merge([$idLote], $idsMatrizItem));
        $idsValidos = array_map('intval', $stmtCheck->fetchAll(PDO::FETCH_COLUMN));

        if (empty($idsValidos)) {
            $pdo->rollBack();
            header("Location: fichas_tecnicas_creadas.php?lote=$idLote&msg=sin_seleccion");
            exit;
        }

        $placeholdersValidos = implode(',', array_fill(0, count($idsValidos), '?'));
        $pdo->prepare("UPDATE matriz_item SET ESTADO_ITEM = 'Pendiente' WHERE ID_MATRIZ_ITEM IN ($placeholdersValidos)")->execute($idsValidos);

        $stmtLoteEstado = $pdo->prepare("SELECT ESTADO_TRAMITE, LOTE_NOMBRE FROM lote_requerimiento WHERE ID_LOTE = ?");
        $stmtLoteEstado->execute([$idLote]);
        $loteActual = $stmtLoteEstado->fetch();
        if ($loteActual && $loteActual['ESTADO_TRAMITE'] === 'Borrador') {
            $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Enviado' WHERE ID_LOTE = ?")->execute([$idLote]);
        }

        $pdo->commit();

        $cantidadEnviada = count($idsValidos);
        notificar_por_rol(
            $pdo,
            'Coordinacion',
            htmlspecialchars($_SESSION['usuario_nombre']) . " envió $cantidadEnviada ficha(s) del lote '" . $loteActual['LOTE_NOMBRE'] . "' para revisión.",
            "../coordinador/revisar_lotes.php"
        );

        header("Location: fichas_tecnicas_creadas.php?lote=$idLote&msg=enviado&cantidad=$cantidadEnviada");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error enviando fichas técnicas: ' . $e->getMessage());
        header("Location: fichas_tecnicas_creadas.php?lote=$idLote&msg=error");
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
$messageText = '';
$messageType = 'error';
if ($msg === 'sin_permiso') {
    $messageText = '✗ No puede editar esta ficha técnica: fue creada por otro instructor.';
} elseif ($msg === 'enviado') {
    $cantidadMsg = intval($_GET['cantidad'] ?? 0);
    $messageText = $cantidadMsg === 1
        ? '✓ 1 ficha técnica enviada al coordinador para revisión.'
        : "✓ $cantidadMsg fichas técnicas enviadas al coordinador para revisión.";
    $messageType = 'success';
} elseif ($msg === 'sin_seleccion') {
    $messageText = '✗ Debe seleccionar al menos una ficha técnica para enviar.';
} elseif ($msg === 'error') {
    $messageText = '✗ Ocurrió un error al enviar las fichas técnicas.';
}

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta de fichas técnicas: solo las que pertenecen a ítems de este lote
// (una ficha "pertenece" al lote del ítem de matriz al que está asociada).
$sql = "SELECT ft.*, mi.ESTADO_ITEM FROM ficha_tecnica ft
        INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
        WHERE mi.ID_LOTE = ?";
$params = [$idLote];
if ($busqueda !== '') {
    $sql .= " AND (ft.NOMBRE_ITEM LIKE ? OR ft.CODIGO_UNSPSC_FK LIKE ? OR ft.DENOMINACION_TECNICA_BIEN LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if ($busqueda !== '') {
    // Coincidencias de nombre por prefijo se muestran primero, igual que en Fase 22.
    $sql .= " ORDER BY CASE WHEN ft.NOMBRE_ITEM LIKE ? THEN 0 ELSE 1 END, ft.ID_FICHA_TECNICA ASC";
    $params[] = "$busqueda%";
} else {
    $sql .= " ORDER BY ft.ID_FICHA_TECNICA ASC";
}
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
            <div class="profile-alert <?= $messageType ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; <?= $messageType === 'success' ? 'background: #eff8f1; color: #270; border: 1px solid #d4ebd5;' : 'background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;' ?>">
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
                    <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-secondary" style="padding: 8px 16px; text-decoration: none; border: 1px solid #ccc; background: #eee; color: #333; border-radius: 4px;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <div class="results-meta" style="margin: 15px 0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span>Resultados: </span><strong><?= $total ?></strong> ficha<?= $total !== 1 ? 's' : '' ?> encontrada<?= $total !== 1 ? 's' : '' ?>
                    </div>
                    <?php if ($total > 0): ?>
                    <div style="display: flex; gap: 10px;">
                        <a href="exportar_fichas_pdf.php?lote=<?= htmlspecialchars($idLote) ?>" class="btn" style="padding: 6px 12px; font-size: 13px; text-decoration: none; background-color: #d32f2f; color: white; border-radius: 4px; border: none; cursor: pointer;">📄 Exportar Lote PDF</a>
                        <a href="exportar_fichas_docx.php?lote=<?= htmlspecialchars($idLote) ?>" class="btn" style="padding: 6px 12px; font-size: 13px; text-decoration: none; background-color: #1976d2; color: white; border-radius: 4px; border: none; cursor: pointer;">📝 Exportar Lote DOCX</a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php $hayEnviables = count(array_filter($fichas, fn($f) => $f['ESTADO_ITEM'] === 'Borrador')) > 0; ?>
                <form method="POST" action="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($idLote) ?>" id="form-envio-fichas">
                    <input type="hidden" name="accion" value="enviar_fichas">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <?php if ($hayEnviables): ?>
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; background:#f5f5f5; padding:12px 15px; border-radius:6px; margin-bottom:15px;">
                            <strong id="contador-seleccion-fichas">0 ficha(s) seleccionada(s)</strong>
                            <button type="button" id="btn-enviar-fichas" class="btn" style="padding:6px 14px; font-size:13px; background-color:#39A900; color:white; border:none; border-radius:4px;" disabled>Enviar Solicitud</button>
                        </div>
                    <?php endif; ?>

                    <table style="width: 100%; margin-top: 15px;">
                        <thead>
                            <tr>
                                <?php if ($hayEnviables): ?>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="check-all-fichas" title="Seleccionar Todas">
                                    </th>
                                <?php endif; ?>
                                <th>N°</th>
                                <th>Nombre del Ítem</th>
                                <th>Código UNSPSC</th>
                                <th>Denominación Técnica</th>
                                <th>Unidad de Medida</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fichas)): ?>
                                <tr>
                                    <td colspan="7">
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
                                <?php $contador = 1; ?>
                                <?php foreach ($fichas as $f): ?>
                                    <tr>
                                        <?php if ($hayEnviables): ?>
                                        <td style="text-align: center;">
                                            <?php if ($f['ESTADO_ITEM'] === 'Borrador'): ?>
                                                <input type="checkbox" name="matriz_item[]" value="<?= htmlspecialchars($f['ID_MATRIZ_ITEM']) ?>" class="check-ficha">
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td><?= $contador++ ?></td>
                                        <td><strong><?= htmlspecialchars($f['NOMBRE_ITEM']) ?></strong></td>
                                        <td><?= htmlspecialchars($f['CODIGO_UNSPSC_FK']) ?></td>
                                        <td><?= htmlspecialchars($f['DENOMINACION_TECNICA_BIEN']) ?></td>
                                        <td><?= htmlspecialchars($f['UNIDAD_MEDIDA']) ?></td>
                                        <td><span class="badge-estado badge-<?= strtolower(htmlspecialchars($f['ESTADO_ITEM'] ?? 'Borrador')) ?>"><?= htmlspecialchars($f['ESTADO_ITEM'] ?? 'Borrador') ?></span></td>
                                        <td>
                                            <a href="ver_ficha_tecnica.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>&lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none;">Ver</a>
                                            <a href="configurar_matriz.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none; background-color: #00324D;">Configurar Matriz</a>
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
    const checks = document.querySelectorAll('.check-ficha');
    const contador = document.getElementById('contador-seleccion-fichas');
    const btnEnviar = document.getElementById('btn-enviar-fichas');
    const checkAll = document.getElementById('check-all-fichas');
    if (!btnEnviar) return;

    function actualizar() {
        const marcadas = document.querySelectorAll('.check-ficha:checked').length;
        contador.textContent = marcadas + ' ficha(s) seleccionada(s)';
        btnEnviar.textContent = marcadas === 1 ? 'Enviar Solicitud' : 'Enviar Solicitudes';
        btnEnviar.disabled = marcadas === 0;

        if (checkAll && checks.length > 0) {
            checkAll.checked = (marcadas === checks.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checks.forEach(cb => {
                cb.checked = checkAll.checked;
            });
            actualizar();
        });
    }

    checks.forEach(cb => cb.addEventListener('change', actualizar));

    btnEnviar.addEventListener('click', function () {
        const marcadas = document.querySelectorAll('.check-ficha:checked').length;
        if (marcadas === 0) return;
        
        btnEnviar.disabled = true;
        const originalText = btnEnviar.textContent;
        btnEnviar.textContent = 'Procesando...';

        const mensaje = marcadas === 1
            ? '¿Enviar esta ficha técnica al coordinador para revisión?'
            : '¿Enviar estas ' + marcadas + ' fichas técnicas al coordinador para revisión?';
        confirmAction({
            title: marcadas === 1 ? 'Enviar Solicitud' : 'Enviar Solicitudes',
            message: mensaje,
            confirmLabel: marcadas === 1 ? 'Enviar Solicitud' : 'Enviar Solicitudes',
            danger: false
        }).then(confirmado => {
            if (confirmado) {
                document.getElementById('form-envio-fichas').submit();
            } else {
                btnEnviar.disabled = false;
                btnEnviar.textContent = originalText;
            }
        });
    });

    actualizar();
});
</script>
</body>
</html>
