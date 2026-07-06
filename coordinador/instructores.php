<?php
require_once '../conexion.php';

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

// Obtener todos los instructores y sus lotes
try {
    $sql = "SELECT DISTINCT u.ID_USUARIO, u.NOMBRE, u.APELLIDO, u.EMAIL, u.ESTADO,
            COUNT(DISTINCT lr.ID_LOTE) as CANTIDAD_LOTES,
            GROUP_CONCAT(DISTINCT lr.LOTE_NOMBRE SEPARATOR ', ') as LOTES
            FROM usuario u
            LEFT JOIN lote_requerimiento lr ON u.ID_USUARIO = lr.ID_SOLICITANTE
            WHERE u.ID_ROL = 1";
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (u.NOMBRE LIKE ? OR u.APELLIDO LIKE ? OR u.EMAIL LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    $sql .= " GROUP BY u.ID_USUARIO ORDER BY u.NOMBRE";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $instructores = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching instructores: ' . $e->getMessage());
    $instructores = [];
}

$total = count($instructores);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructores - BICERGAM</title>
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
            <a href="instructores.php" class="sidebar-link sidebar-link--primary active">Instructores</a>
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
                <h2>Instructores del Sistema</h2>
                <p class="dashboard-subtitle">Consulta información de instructores y sus lotes asociados.</p>
            </div>
        </div>

        <div class="panel-card">
            <form method="GET" action="instructores.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar instructor</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre o correo..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <a href="instructores.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <h3>Total de Instructores: <?= $total ?></h3>
                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre Completo</th>
                            <th>Correo</th>
                            <th>Estado</th>
                            <th>Lotes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($instructores)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No hay instructores registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instructores as $instructor): ?>
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['ID_USUARIO']) ?></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['NOMBRE'] . ' ' . $instructor['APELLIDO']) ?></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><?= htmlspecialchars($instructor['EMAIL']) ?></td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <span style="padding: 4px 8px; border-radius: 4px; <?= $instructor['ESTADO'] === 'Activo' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                                            <?= htmlspecialchars($instructor['ESTADO']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <?= htmlspecialchars($instructor['CANTIDAD_LOTES'] ?? 0) ?>
                                        <?php if (!empty($instructor['LOTES'])): ?>
                                            <br><small style="color: #666;"><?= htmlspecialchars(substr($instructor['LOTES'], 0, 50)) . (strlen($instructor['LOTES']) > 50 ? '...' : '') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <a href="instructor_detalle.php?id=<?= htmlspecialchars($instructor['ID_USUARIO']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Ver</a>
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
