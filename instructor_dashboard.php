<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = 'uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Instructor - BICERGAM</title>
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
    <div class="role-banner role-instructor">
        <h2>Panel de Instructor</h2>
        <p>Accede a tus herramientas: ficha técnica, historial de existencia y consulta de matrices.</p>
    </div>

    <div class="dashboard-layout">
        <main>
            <div class="panel-card">
                <h3 style="margin-top:0;">Resumen</h3>
                <p style="margin-top:6px; color:#546a72;">Aquí puedes ver tus lotes recientes y acceder rápidamente a acciones relacionadas.</p>

                <section style="margin-top:18px;">
                    <h3>Tus lotes recientes</h3>
                    <?php
                        $stmt = $pdo->prepare("SELECT * FROM lote_requerimiento WHERE ID_SOLICITANTE = ? ORDER BY FECHA_CREACION DESC LIMIT 8");
                        $stmt->execute([$usuarioId]);
                        $lotes = $stmt->fetchAll();
                    ?>
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lotes)): ?>
                                <tr><td colspan="5" style="text-align:center">No hay lotes recientes.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lotes as $l): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($l['ID_LOTE']) ?></td>
                                        <td><?= htmlspecialchars($l['LOTE_NOMBRE']) ?></td>
                                        <td><?= htmlspecialchars($l['ESTADO_TRAMITE']) ?></td>
                                        <td><?= htmlspecialchars($l['FECHA_CREACION']) ?></td>
                                        <td><a href="matriz.php?lote=<?= htmlspecialchars($l['ID_LOTE']) ?>" class="btn">Ver</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>

        <aside class="sidebar">
            <div class="profile-box">
                <?php if ($photoPath): ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'],0,1)) ?></div>
                <?php endif; ?>
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
                    <div class="profile-role"><?= htmlspecialchars($_SESSION['rol_nombre']) ?></div>
                    <a href="instructor_profile.php" class="btn" style="margin-top:8px; display:inline-block;">Editar Perfil</a>
                </div>
            </div>

            <div class="action-group">
                <h4>Operaciones</h4>
                <a href="crear.php" class="dash-btn dash-btn--primary">Ficha Técnica</a>
                <a href="crear.php" class="dash-btn">+ Crear Nuevo Lote</a>
            </div>

            <div class="action-group">
                <h4>Consultas</h4>
                <a href="historial_existencia.php" class="dash-btn dash-btn--ghost">Historial de Existencia</a>
                <a href="matriz.php" class="dash-btn dash-btn--ghost">Consulta de Matrices</a>
            </div>

            <div class="action-group action-group--end">
                <h4>Sesión</h4>
                <a href="logout.php" class="logout-link" style="display:block; text-align:center;">Cerrar Sesión</a>
            </div>
        </aside>
    </div>
</div>

<script src="javascript.js"></script>
</body>
</html>
