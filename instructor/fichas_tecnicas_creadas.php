<?php
// fichas_tecnicas_creadas.php — Catálogo de Fichas Técnicas
require_once '../conexion.php';
require_once '../csrf.php';

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

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta de fichas técnicas
$sql = "SELECT * FROM ficha_tecnica";
$params = [];
if ($busqueda !== '') {
    $sql .= " WHERE NOMBRE_ITEM LIKE ? OR CODIGO_UNSPSC_FK LIKE ? OR DENOMINACION_TECNICA_BIEN LIKE ?";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
$sql .= " ORDER BY ID_FICHA_TECNICA DESC";
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
<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
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
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link">Mis Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
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
                <h2>Catálogo de Fichas Técnicas Creadas</h2>
                <p class="dashboard-subtitle">Selecciona una ficha técnica para asignarla a tu lote actual (#<?= $idLote ?>).</p>
            </div>
        </div>

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
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-secondary" style="padding: 8px 16px; text-decoration: none; border: 1px solid #ccc; background: #eee; color: #333; border-radius: 4px;">Limpiar</a>
                </div>
            </form>

            <div id="resultados-busqueda">
                <div class="results-meta" style="margin: 15px 0;">
                    <span>Resultados: </span><strong><?= $total ?></strong> ficha<?= $total !== 1 ? 's' : '' ?> encontrada<?= $total !== 1 ? 's' : '' ?>
                </div>

                <table style="width: 100%; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Ítem</th>
                            <th>Código UNSPSC</th>
                            <th>Denominación Técnica</th>
                            <th>Unidad de Medida</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fichas)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No hay fichas técnicas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fichas as $f): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?></td>
                                    <td><strong><?= htmlspecialchars($f['NOMBRE_ITEM']) ?></strong></td>
                                    <td><?= htmlspecialchars($f['CODIGO_UNSPSC_FK']) ?></td>
                                    <td><?= htmlspecialchars($f['DENOMINACION_TECNICA_BIEN']) ?></td>
                                    <td><?= htmlspecialchars($f['UNIDAD_MEDIDA']) ?></td>
                                    <td>
                                        <a href="ver_ficha_tecnica.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>&lote=<?= htmlspecialchars($idLote) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none;">Ver</a>
                                        <a href="editar_ficha_tecnica.php?id=<?= htmlspecialchars($f['ID_FICHA_TECNICA']) ?>" class="btn btn-sena" style="padding: 5px 12px; font-size: 13px; text-decoration: none; background-color: #00324D;">Editar</a>
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
