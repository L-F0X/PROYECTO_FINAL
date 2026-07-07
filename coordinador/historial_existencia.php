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
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

// Obtener certificados de existencia
try {
    $sql = "SELECT ce.*, lr.LOTE_NOMBRE, lr.ID_SOLICITANTE, u.NOMBRE, u.APELLIDO
            FROM certificado_existencia ce
            INNER JOIN lote_requerimiento lr ON ce.ID_LOTE = lr.ID_LOTE
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            WHERE 1=1";
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (ce.NUMERO_CERTIFICADO LIKE ? OR lr.LOTE_NOMBRE LIKE ? OR u.NOMBRE LIKE ? OR u.APELLIDO LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    $sql .= " ORDER BY ce.ID_CERTIFICADO DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $certificados = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching certificados: ' . $e->getMessage());
    $certificados = [];
}

$total = count($certificados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificados de Existencia - BICERGAM</title>
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
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link sidebar-link--primary active">Certificados Existencia</a>
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
                <h2>Certificados de Existencia</h2>
                <p class="dashboard-subtitle">Historial de certificados de existencia emitidos para lotes.</p>
            </div>
        </div>

        <div class="panel-card">
            <form method="GET" action="historial_existencia.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar certificado</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Nº certificado, lote o instructor..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <a href="historial_existencia.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <h3>Total de Certificados: <?= $total ?></h3>
                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID Certificado</th>
                            <th>ID Lote</th>
                            <th>Nombre Lote</th>
                            <th>Instructor</th>
                            <th>Número Certificado</th>
                            <th>Fecha Emisión</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificados)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No hay certificados de existencia registrados.</p>
                                        <span>Los certificados emitidos por el almacén aparecerán aquí.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificados as $cert): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?= htmlspecialchars($cert['ID_CERTIFICADO']) ?></td>
                                    <td style="padding: 12px;">
                                        <a href="revisar_lote.php?id=<?= htmlspecialchars($cert['ID_LOTE']) ?>" style="color: #39A900; text-decoration: none;">
                                            <?= htmlspecialchars($cert['ID_LOTE']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($cert['LOTE_NOMBRE']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($cert['NOMBRE'] . ' ' . $cert['APELLIDO']) ?></td>
                                    <td style="padding: 12px;"><?= htmlspecialchars($cert['NUMERO_CERTIFICADO']) ?></td>
                                    <td style="padding: 12px;"><?= !empty($cert['FECHA_EMISION']) ? htmlspecialchars(date('d/m/Y', strtotime($cert['FECHA_EMISION']))) : 'N/D' ?></td>
                                    <td style="padding: 12px;">
                                        <a href="../instructor/certificado_pdf.php?id=<?= (int)$cert['ID_CERTIFICADO'] ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Ver / PDF</a>
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
