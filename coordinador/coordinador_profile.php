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
    <title>Perfil Coordinador</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .profile-card {
            max-width: 700px;
            margin: 0 auto;
            padding: 24px;
        }
        .profile-card-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9f0ef;
            padding-bottom: 18px;
        }
        .profile-avatar-wrapper {
            width: 72px;
            height: 72px;
            position: relative;
            flex-shrink: 0;
        }
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #d4dadb;
            background: #f8fafb;
        }
        .profile-avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--verde-sena);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
        }
        .profile-title h3 {
            margin: 0;
            font-size: 1.35rem;
        }
        .profile-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: flex-start;
        }
        .profile-input {
            min-width: 0;
        }
        .file-upload-wrapper {
            position: relative;
            margin-top: 5px;
        }
        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f5f5f5;
            border: 1.5px dashed #ccc;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
            justify-content: center;
        }
        .file-upload-btn:hover {
            border-color: var(--verde-sena);
            background: rgba(57,181,74,0.05);
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .profile-card-footer {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </div>
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
                            <img id="current-avatar" src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="profile-avatar">
                        <?php else: ?>
                            <div id="current-avatar" class="profile-avatar-placeholder"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
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
                            <input id="photo-input" type="file" name="photo" accept="image/png, image/jpeg, image/webp">
                        </label>
                    </div>
                    <div class="photo-preview-note">Vista previa de la imagen seleccionada:</div>
                    <div id="photo-preview" style="margin-top: 10px; display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; border: 1px solid #d4dadb; overflow: hidden; background: #fff;">
                        <?php if ($photoPath): ?>
                            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Previsualización" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #55686e; font-weight: 700; font-size: 1.1rem;">+</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-card-footer">
                    <a href="index.php" class="btn btn-secondary" style="margin-right: 12px;">Volver al Panel</a>
                    <button type="submit" class="btn btn-sena">Guardar Cambios</button>
                </div>
            </form>
    </div>
<script>
    const photoInput = document.getElementById('photo-input');
    const photoPreview = document.getElementById('photo-preview');
    const currentAvatar = document.getElementById('current-avatar');

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                photoPreview.innerHTML = '';
                const img = document.createElement('img');
                img.src = event.target.result;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                photoPreview.appendChild(img);

                if (currentAvatar) {
                    if (currentAvatar.tagName === 'IMG') {
                        currentAvatar.src = event.target.result;
                    } else {
                        const avatarImg = document.createElement('img');
                        avatarImg.src = event.target.result;
                        avatarImg.style.width = '100%';
                        avatarImg.style.height = '100%';
                        avatarImg.style.objectFit = 'cover';
                        avatarImg.className = 'profile-avatar';
                        currentAvatar.replaceWith(avatarImg);
                    }
                }
            };
            reader.readAsDataURL(file);
        });
    }
</script>
</body>
</html>
