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

// Obtener fichas técnicas
try {
    $sql = "SELECT ft.*, cu.CODIGO_UNSPSC,
            (SELECT COUNT(*) FROM matriz_item WHERE ID_FICHA_TECNICA = ft.ID_FICHA_TECNICA) as ITEMS_COUNT
            FROM ficha_tecnica ft
            LEFT JOIN codigo_unspsc cu ON ft.CODIGO_UNSPSC_FK = cu.CODIGO_UNSPSC
            WHERE 1=1";
    
    $params = [];

    if ($busqueda !== '') {
        $sql .= " AND (ft.NOMBRE_ITEM LIKE ? OR ft.ID_FICHA_TECNICA = ?)";
        $params[] = "%$busqueda%";
        $params[] = intval($busqueda);
    }

    $sql .= " ORDER BY ft.FECHA_EMISION DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fichas = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching fichas técnicas: ' . $e->getMessage());
    $fichas = [];
}

$total = count($fichas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fichas Técnicas - BICERGAM</title>
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
            <img src="../imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="index.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link sidebar-link--primary">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Fichas Técnicas</h2>
                <p class="dashboard-subtitle">Consulta todas las fichas técnicas disponibles en el sistema.</p>
            </div>
        </div>

        <div class="panel-card">
            <div style="margin-bottom: 20px;">
                <h3>Total de Fichas Técnicas: <?= $total ?></h3>
            </div>

            <!-- Búsqueda -->
            <form method="GET" action="fichas_tecnicas_coordinador.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar ficha</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <?php if ($busqueda !== ''): ?>
                        <a href="fichas_tecnicas_coordinador.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Item</th>
                        <th>Código UNSPSC</th>
                        <th>Unidad Medida</th>
                        <th>Cantidad</th>
                        <th>Usado En</th>
                        <th>Fecha Emisión</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fichas)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">No hay fichas técnicas registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fichas as $ficha): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?= htmlspecialchars($ficha['ID_FICHA_TECNICA']) ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars($ficha['CODIGO_UNSPSC'] ?? 'N/A') ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars($ficha['UNIDAD_MEDIDA']) ?></td>
                                <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($ficha['CANTIDAD']) ?></td>
                                <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($ficha['ITEMS_COUNT']) ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars(substr($ficha['FECHA_EMISION'], 0, 19)) ?></td>
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
        const form = document.getElementById('form-busqueda');
        if (input && form) {
            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 350);
            });
        }
    })();
</script>

</body>
</html>
