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

// Mensajes de estado
$message = '';
$messageType = 'success';

// Cargar datos actuales
$stmt = $pdo->prepare('SELECT NOMBRE, APELLIDO, EMAIL FROM usuario WHERE ID_USUARIO = ?');
$stmt->execute([intval($_SESSION['usuario_id'])]);
$user = $stmt->fetch();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $message = 'Token CSRF inválido.';
        $messageType = 'error';
    } else {
        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $emailIn  = trim($_POST['email']    ?? '');

        if ($nombre !== '' && $apellido !== '' && $emailIn !== '') {
            try {
                $u = $pdo->prepare('UPDATE usuario SET NOMBRE = ?, APELLIDO = ?, EMAIL = ? WHERE ID_USUARIO = ?');
                $u->execute([$nombre, $apellido, $emailIn, intval($_SESSION['usuario_id'])]);
                $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;
                $message = '✓ Perfil actualizado correctamente.';

                // Manejo de foto
                if (!empty($_FILES['photo']['name'])) {
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $file = $_FILES['photo'];
                    if ($file['error'] === UPLOAD_ERR_OK && isset($allowed[$file['type']])) {
                        $ext = $allowed[$file['type']];
                        $dir = __DIR__ . '/../uploads/profiles';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $target = $dir . '/' . intval($_SESSION['usuario_id']) . '.' . $ext;
                        foreach (['jpg','jpeg','png','webp'] as $old) {
                            $oldf = $dir . '/' . intval($_SESSION['usuario_id']) . '.' . $old;
                            if (file_exists($oldf) && $oldf !== $target) @unlink($oldf);
                        }
                        if (move_uploaded_file($file['tmp_name'], $target)) {
                            $message .= ' Foto actualizada.';
                        } else {
                            $messageType = 'error';
                            $message .= ' Error al subir la foto.';
                        }
                    } else {
                        $messageType = 'error';
                        $message .= ' Formato de imagen no permitido.';
                    }
                }
            } catch (\PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $message = '✗ Error al actualizar el perfil.';
                $messageType = 'error';
            }
        } else {
            $message = '✗ Todos los campos son obligatorios.';
            $messageType = 'error';
        }
    }

    // Recargar datos
    $stmt->execute([intval($_SESSION['usuario_id'])]);
    $user = $stmt->fetch();
}

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

$usuarioNombre = htmlspecialchars($user['NOMBRE'] . ' ' . $user['APELLIDO']);
$email = htmlspecialchars($user['EMAIL'] ?? 'correo@institucional.com');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil Coordinador - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 16px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">Perfil Coordinador</h1>
            <p style="margin: 4px 0 0; color: rgba(255,255,255,0.8); font-size: 0.95rem;">Actualiza tu información y foto de perfil.</p>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 10px;">
        <?php if ($photoPath): ?>
            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar" style="width: 52px; height: 52px; object-fit: cover;">
        <?php else: ?>
            <div class="header-avatar" style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; font-size: 18px;"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
        <?php endif; ?>
    </div>
</header>

<div class="container fade-in" style="margin-top: 20px;">
    <div class="role-banner role-coordinador">
        <h2>Edición de Perfil</h2>
        <p>Mantén tu información de coordinador actualizada. Esta vista conserva la misma estética del instructor.</p>
    </div>

    <div class="profile-card">
        <form method="POST" action="coordinador_profile.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <div class="profile-card-header">
                    <div class="profile-avatar-wrapper">
                        <?php if ($photoPath): ?>
                            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-title">
                        <h3><?= $usuarioNombre ?></h3>
                        <p>Coordinador del sistema</p>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div style="margin-bottom: 12px; padding: 10px; border-radius: 6px; <?= $messageType === 'error' ? 'background:#fbeaea;color:#8a1f1f;border:1px solid #f5c6cb;' : 'background:#e7f9ee;color:#114b23;border:1px solid #cfe9d6;' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-form-grid">
                    <div>
                        <label class="profile-label">Nombre</label>
                        <input class="profile-input" name="nombre" type="text" value="<?= htmlspecialchars($user['NOMBRE'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="profile-label">Apellido</label>
                        <input class="profile-input" name="apellido" type="text" value="<?= htmlspecialchars($user['APELLIDO'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="profile-label">Correo</label>
                        <input class="profile-input" name="email" type="email" value="<?= $email ?>" required>
                    </div>
                    <div>
                        <label class="profile-label">Rol</label>
                        <input class="profile-input" type="text" value="Coordinador" readonly>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <label class="profile-label">Foto de Perfil (jpg, png, webp)</label>
                    <div class="file-upload-wrapper">
                        <label class="file-upload-btn">Subir / Cambiar Foto
                            <input type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                        </label>
                    </div>
                </div>
            </form>
    </div>
</div>
</body>
</html>
