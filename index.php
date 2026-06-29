<?php
// index.php
require_once 'conexion.php';
require_once 'csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

if ($rolNombre === 'instructor') {
    $stmt = $pdo->prepare("SELECT * FROM lote_requerimiento WHERE ID_SOLICITANTE = ? ORDER BY FECHA_CREACION DESC");
    $stmt->execute([$usuarioId]);
    $panelTitulo = 'Panel de Instructor';
    $panelDescripcion = 'Aquí verás los lotes que has creado o que están a tu cargo como instructor.';
} elseif ($rolNombre === 'coordinador') {
    $stmt = $pdo->query("SELECT * FROM lote_requerimiento ORDER BY FECHA_CREACION DESC");
    $panelTitulo = 'Panel de Coordinador';
    $panelDescripcion = 'Revisa, coordina y da seguimiento a los lotes de requerimiento del equipo.';
} else {
    $stmt = $pdo->query("SELECT * FROM lote_requerimiento ORDER BY FECHA_CREACION DESC");
    $panelTitulo = 'Lotes de Requerimiento (Pre-Compra)';
    $panelDescripcion = 'Lista completa de lotes registrados en el sistema.';
}

$lotes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BICERGAM - Pre-compra SENA</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header>
    <h1>BICERGAM | <span>SENA</span></h1>
    <div style="text-align: right; color: white;">
        Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> (<?= htmlspecialchars($_SESSION['rol_nombre']) ?>) | 
        <a href="logout.php" style="color: var(--alerta-rojo); text-decoration: none; font-weight: bold; margin-left: 10px;">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in">
    <div class="role-banner role-<?= htmlspecialchars(str_replace(' ', '-', $rolNombre)) ?>">
        <h2><?= htmlspecialchars($panelTitulo) ?></h2>
        <p><?= htmlspecialchars($panelDescripcion) ?></p>
    </div>
    <?php if ($rolNombre === 'instructor'): ?>
        <div style="display:flex; gap:12px; margin-bottom:18px;">
            <a href="instructor_dashboard.php" class="btn btn-sena">Ir al Panel de Instructor</a>
            <a href="instructor_profile.php" class="btn">Editar Perfil</a>
            <a href="crear.php" class="btn btn-sena" style="margin-left:auto;">+ Crear Nuevo Lote</a>
        </div>
    <?php else: ?>
        <a href="crear.php" class="btn btn-sena" style="margin-bottom: 20px;">+ Crear Nuevo Lote</a>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Lote</th>
                <th>ID Solicitante</th>
                <th>Estado Trámite</th>
                <th>Fecha Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($lotes)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No hay lotes de requerimiento registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach($lotes as $lote): ?>
                    <tr>
                        <td><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                        <td><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                        <td><?= htmlspecialchars($lote['ID_SOLICITANTE']) ?></td>
                        <td><strong><?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></strong></td>
                        <td><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                        <td>
                            <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Materiales</a>
                            <a href="editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                            <form action="eliminar.php" method="POST" style="display:inline; margin:0;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <button type="submit" class="btn btn-danger btn-eliminar" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--alerta-rojo); color: white;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="javascript.js"></script>
</body>
</html>