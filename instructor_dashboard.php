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
    <div class="topbar">
        <div class="brand">
            <img src="imagenes/sena-logo.png" alt="SENA">
            <h1>BICERGAM | <span>SENA</span></h1>
        </div>
        <div class="user-actions">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> (<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)
            <a href="logout.php" class="logout-link">Cerrar Sesión</a>
        </div>
    </div>
</header>

<div class="container fade-in">
    <div class="role-banner role-instructor">
        <h2>Panel de Instructor</h2>
        <p>Accede a tus herramientas: ficha técnica, historial de existencia y consulta de matrices.</p>
    </div>

    <div class="panel-card">
        <div class="profile-row">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-avatar" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
                <div class="profile-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'],0,1)) ?></div>
            <?php endif; ?>
            <div>
                <div style="font-weight:700; font-size:1.05rem;"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
                <a href="instructor_profile.php" class="btn" style="margin-top:6px; padding:6px 10px; background:#ffffff; border:1px solid rgba(0,0,0,0.06);">Editar Perfil</a>
            </div>
        </div>

        <div class="dash-actions">
            <a href="crear.php" class="dash-btn dash-btn--primary">Ficha Técnica</a>
            <a href="historial_existencia.php" class="dash-btn dash-btn--ghost">Historial de Existencia</a>
            <a href="matriz.php" class="dash-btn dash-btn--ghost">Consulta de Matrices</a>
        </div>
    </div>

    <section>
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

<script src="javascript.js"></script>
</body>
</html>
