<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: index.php');
    exit;
}

$message = '';

// Cargar datos actuales
$stmt = $pdo->prepare('SELECT NOMBRE, APELLIDO, EMAIL FROM usuario WHERE ID_USUARIO = ?');
$stmt->execute([$usuarioId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($nombre !== '' && $apellido !== '' && $email !== '') {
        try {
            $u = $pdo->prepare('UPDATE usuario SET NOMBRE = ?, APELLIDO = ?, EMAIL = ? WHERE ID_USUARIO = ?');
            $u->execute([$nombre, $apellido, $email, $usuarioId]);
            $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;
            $message = 'Perfil actualizado correctamente.';
        } catch (\PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $message = 'Error al actualizar el perfil.';
        }
    }

    // Manejo de subida de foto (opcional)
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $file = $_FILES['photo'];
        if ($file['error'] === UPLOAD_ERR_OK && isset($allowed[$file['type']])) {
            $ext = $allowed[$file['type']];
            $dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $target = $dir . '/' . $usuarioId . '.' . $ext;
            // Eliminar archivos anteriores con otras extensiones
            foreach (['jpg','jpeg','png','webp'] as $old) {
                $oldf = $dir . '/' . $usuarioId . '.' . $old;
                if (file_exists($oldf) && $oldf !== $target) @unlink($oldf);
            }
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $message .= ($message ? ' ' : '') . 'Foto subida correctamente.';
            } else {
                $message .= ($message ? ' ' : '') . 'Error al subir la foto.';
            }
        } else {
            $message .= ($message ? ' ' : '') . 'Formato de imagen no permitido.';
        }
    }

    // Recargar datos
    $stmt->execute([$usuarioId]);
    $user = $stmt->fetch();
}

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
    <title>Editar Perfil - Instructor</title>
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
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <a href="logout.php" class="logout-link">Cerrar Sesión</a>
        </div>
    </div>
</header>

<div class="container fade-in">
    <h2>Editar Perfil</h2>
    <?php if ($message): ?>
        <div class="error-msg" style="background: rgba(57,169,0,0.08); border-left-color: var(--verde-sena); color:#0f3b4a;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="instructor_profile.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($user['NOMBRE'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Apellido</label>
            <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($user['APELLIDO'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Correo</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['EMAIL'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Foto de Perfil (png, jpg, webp)</label>
            <?php if ($photoPath): ?>
                <div style="margin-bottom:8px;"><img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto" style="width:80px; height:80px; border-radius:8px; object-fit:cover;"></div>
            <?php endif; ?>
            <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">
        </div>

        <button class="btn btn-sena" type="submit">Guardar cambios</button>
        <a href="instructor_dashboard.php" class="btn" style="margin-left:8px;">Volver</a>
    </form>
</div>

</body>
</html>
