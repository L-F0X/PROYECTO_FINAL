<?php
require_once '../conexion.php';
require_once '../csrf.php';
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

$idLote = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idLote <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener información del lote y del instructor
try {
    $sql = "SELECT lr.*, u.NOMBRE, u.APELLIDO, u.EMAIL
            FROM lote_requerimiento lr
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            WHERE lr.ID_LOTE = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idLote]);
    $lote = $stmt->fetch();

    if (!$lote) {
        header('Location: index.php');
        exit;
    }

    // Los lotes en Borrador aún no han sido enviados por el instructor,
    // así que el coordinador no debe poder verlos ni siquiera por URL directa.
    if ($lote['ESTADO_TRAMITE'] === 'Borrador') {
        header('Location: revisar_lotes.php');
        exit;
    }

    // Obtener items del lote, junto con las hasta 3 ofertas/cotizaciones registradas por ítem
    $sqlItems = "SELECT mi.*, ft.NOMBRE_ITEM, ft.DENOMINACION_TECNICA_BIEN,
                 cu.CODIGO_UNSPSC, iva.PORCENTAJE,
                 c1.VALOR_UNITARIO AS OF1_VALOR, c1.VALOR_TOTAL AS OF1_TOTAL, c1.MARCA_OFRECIDA AS OF1_MARCA, p1.RAZON_SOCIAL AS OF1_PROVEEDOR,
                 c2.VALOR_UNITARIO AS OF2_VALOR, c2.VALOR_TOTAL AS OF2_TOTAL, c2.MARCA_OFRECIDA AS OF2_MARCA, p2.RAZON_SOCIAL AS OF2_PROVEEDOR,
                 c3.VALOR_UNITARIO AS OF3_VALOR, c3.VALOR_TOTAL AS OF3_TOTAL, c3.MARCA_OFRECIDA AS OF3_MARCA, p3.RAZON_SOCIAL AS OF3_PROVEEDOR
                 FROM matriz_item mi
                 LEFT JOIN ficha_tecnica ft ON mi.ID_FICHA_TECNICA = ft.ID_FICHA_TECNICA
                 LEFT JOIN codigo_unspsc cu ON mi.ID_CODIGO_UNSPSC = cu.ID_CODIGO
                 LEFT JOIN iva ON mi.ID_IVA = iva.ID_IVA
                 LEFT JOIN cotizacion c1 ON mi.OFERTA_1 = c1.ID_COTIZACION
                 LEFT JOIN proveedor p1 ON c1.ID_PROVEEDOR = p1.ID_PROVEEDOR
                 LEFT JOIN cotizacion c2 ON mi.OFERTA_2 = c2.ID_COTIZACION
                 LEFT JOIN proveedor p2 ON c2.ID_PROVEEDOR = p2.ID_PROVEEDOR
                 LEFT JOIN cotizacion c3 ON mi.OFERTA_3 = c3.ID_COTIZACION
                 LEFT JOIN proveedor p3 ON c3.ID_PROVEEDOR = p3.ID_PROVEEDOR
                 WHERE mi.ID_LOTE = ?
                 ORDER BY mi.ID_MATRIZ_ITEM";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([$idLote]);
    $items = $stmtItems->fetchAll();

    // Obtener historial de decisiones
    $sqlHistorial = "SELECT * FROM aprobacion_rechazo_lote WHERE ID_LOTE = ? ORDER BY FECHA_DECISION DESC";
    $stmtHistorial = $pdo->prepare($sqlHistorial);
    $stmtHistorial->execute([$idLote]);
    $historial = $stmtHistorial->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching lote details: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

$msgOferta = $_GET['msg'] ?? '';
$mensajeOferta = '';
$tipoMensajeOferta = 'success';
if ($msgOferta === 'oferta_agregada') {
    $mensajeOferta = '✓ Oferta registrada correctamente.';
} elseif ($msgOferta === 'oferta_quitada') {
    $mensajeOferta = '✓ Oferta eliminada correctamente.';
} elseif ($msgOferta === 'oferta_error') {
    $mensajeOferta = '✗ ' . ($_GET['detalle'] ?? 'No se pudo procesar la oferta.');
    $tipoMensajeOferta = 'error';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Lote - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Coordinador</span></h1>
            <div class="user-greeting">Coordinador de Compras: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Coordinador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
            <a href="revisar_lotes.php" class="sidebar-link sidebar-link--primary active">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
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
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <div class="role-banner role-coordinador">
                <h2>Lote: <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></h2>
                <p>ID: <?= htmlspecialchars($lote['ID_LOTE']) ?> | Estado: <?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></p>
            </div>

            <?php if ($mensajeOferta): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-top: 20px; font-weight: 500; <?= $tipoMensajeOferta === 'error' ? 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' ?>">
                    <?= htmlspecialchars($mensajeOferta) ?>
                </div>
            <?php endif; ?>

            <!-- Información del Instructor -->
            <div class="panel-card" style="margin-top: 20px;">
                <h3>Información del Instructor Solicitante</h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">Nombre:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['NOMBRE'] . ' ' . $lote['APELLIDO']) ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 25%;">Correo:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['EMAIL']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Rol:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">Instructor</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Fecha Creación:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                    </tr>
                </table>
                <div style="margin-top: 15px;">
                    <a href="instructores.php?id=<?= htmlspecialchars($lote['ID_SOLICITANTE']) ?>" class="btn btn-sena" style="padding: 8px 16px;">Ver más instructores</a>
                </div>
            </div>

            <!-- Items del Lote -->
            <div class="panel-card" style="margin-top: 20px;">
                <h3>Items del Lote (<?= count($items) ?>)</h3>
                <table style="width: 100%; margin-top: 10px;">
                    <thead>
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">ID Item</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Descrición</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Cantidad</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Unidad</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Estado</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Ofertas / Proveedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No hay ítems registrados en este lote.</p>
                                        <span>Los ítems que agregue el instructor aparecerán aquí.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item):
                                $ofertas = [];
                                foreach ([1, 2, 3] as $n) {
                                    $valor = $item["OF{$n}_VALOR"] ?? null;
                                    if ($valor !== null) {
                                        $ofertas[] = [
                                            'n' => $n,
                                            'id_cotizacion' => $item["OFERTA_$n"],
                                            'proveedor' => $item["OF{$n}_PROVEEDOR"],
                                            'valor' => $valor,
                                            'total' => $item["OF{$n}_TOTAL"],
                                        ];
                                    }
                                }
                            ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></td>
                                    <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($item['ESTADO_ITEM']) ?></td>
                                    <td style="padding: 12px; min-width: 260px;">
                                        <?php if (empty($ofertas)): ?>
                                            <span style="color:#999; font-style:italic; font-size:13px;">Sin ofertas registradas</span>
                                        <?php else: ?>
                                            <ul style="margin:0 0 8px; padding-left:0; list-style:none; font-size:12px;">
                                                <?php foreach ($ofertas as $of): ?>
                                                    <li style="margin-bottom:4px; display:flex; justify-content:space-between; gap:8px;">
                                                        <span><strong><?= htmlspecialchars($of['proveedor']) ?></strong>: $<?= number_format($of['valor']) ?> u. ($<?= number_format($of['total']) ?> total)</span>
                                                        <form method="POST" action="gestionar_oferta.php" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                                            <input type="hidden" name="accion" value="quitar">
                                                            <input type="hidden" name="id_matriz_item" value="<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>">
                                                            <input type="hidden" name="id_cotizacion" value="<?= htmlspecialchars($of['id_cotizacion']) ?>">
                                                            <button type="submit" class="js-confirm-submit" style="color:#dc3545; background:none; border:none; cursor:pointer; font-size:12px; text-decoration:underline; padding:0;" data-confirm-title="Quitar oferta" data-confirm-message="¿Quitar esta oferta?" data-confirm-label="Quitar">Quitar</button>
                                                        </form>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php if ($item['VALOR_UNITARIO_PROMEDIO'] !== null): ?>
                                                <div style="font-size:12px; color:#264047;"><strong>Promedio:</strong> $<?= number_format($item['VALOR_UNITARIO_PROMEDIO']) ?> u. / $<?= number_format($item['VALOR_TOTAL_PROMEDIO']) ?> total</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Historial de Decisiones -->
            <?php if (!empty($historial)): ?>
                <div class="panel-card" style="margin-top: 20px;">
                    <h3>Historial de Decisiones</h3>
                    <table style="width: 100%; margin-top: 10px;">
                        <thead>
                            <tr style="background-color: #f5f5f5;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Fecha</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Decisión</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ccc;">Justificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $decision): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?= htmlspecialchars($decision['FECHA_DECISION']) ?></td>
                                    <td style="padding: 12px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; <?= $decision['ESTADO_DECISION'] === 'Aprobado' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                            <?= htmlspecialchars($decision['ESTADO_DECISION']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($decision['JUSTIFICACION'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Acciones -->
            <?php if ($lote['ESTADO_TRAMITE'] === 'Enviado'): ?>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="aprobar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;">Aprobar Lote</a>
                    <a href="rechazar_lote.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn" style="padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px;">Rechazar Lote</a>
                </div>
            <?php endif; ?>

            <a href="revisar_lotes.php" class="btn btn-secondary" style="padding: 10px 20px; margin-top: 20px; display: inline-block;">Volver a Lotes</a>
        </div>
    </main>
</div>

<script src="../js/apartados.js"></script>
</body>
</html>
