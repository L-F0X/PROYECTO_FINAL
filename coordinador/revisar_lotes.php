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
    $sql = "SELECT lr.*, u.NOMBRE, u.APELLIDO, u.EMAIL,
            (SELECT COUNT(*) FROM matriz_item WHERE ID_LOTE = lr.ID_LOTE) as ITEMS_COUNT
            FROM lote_requerimiento lr
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            WHERE 1=1";
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

if ($msg === 'aprobado') {
    $messageText = '✓ Lote aprobado exitosamente.';
} elseif ($msg === 'rechazado') {
    $messageText = '✓ Lote rechazado exitosamente.';
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
            <a href="index.php" class="sidebar-link">Inicio (HUD)</a>
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
        <div class="dashboard-topbar">
            <div>
                <h2>Revisión de Lotes</h2>
                <p class="dashboard-subtitle">Revisa, aprueba o rechaza los lotes enviados por los instructores.</p>
            </div>
        </div>

        <?php if ($messageText): ?>
            <div class="profile-alert <?= $msgType ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
                <?= htmlspecialchars($messageText) ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h3>Lotes de Requerimiento (Total: <?= $total ?>)</h3>
            </div>

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
                            <option value="Borrador" <?= $filtroEstado === 'Borrador' ? 'selected' : '' ?>>Borrador</option>
                            <option value="Enviado" <?= $filtroEstado === 'Enviado' ? 'selected' : '' ?>>Enviado</option>
                            <option value="Aprobado" <?= $filtroEstado === 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="Rechazado" <?= $filtroEstado === 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                        <a href="revisar_lotes.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
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
                            <td colspan="7" style="text-align: center; padding: 20px;">No hay lotes registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lotes as $lote): ?>
                            <tr>
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
        </div>
    </main>
</div>

<script>
    (function () {
        const input = document.getElementById('q');
        const select = document.getElementById('estado');
        const form = document.getElementById('form-busqueda');
        if (input && select && form) {
            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 350);
            });
            select.addEventListener('change', () => form.submit());
        }
    })();
</script>

</body>
</html>
