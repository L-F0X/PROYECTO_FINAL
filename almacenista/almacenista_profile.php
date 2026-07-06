<?php
// almacenista/almacenista_profile.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'almacenista') {
    header('Location: ../login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// Mensajes de estado
$message = '';
$messageType = 'success';

// Cargar datos actuales
$stmt = $pdo->prepare('SELECT NOMBRE, APELLIDO, EMAIL FROM usuario WHERE ID_USUARIO = ?');
$stmt->execute([$usuarioId]);
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
        $email    = trim($_POST['email']    ?? '');

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($nombre === '' || $apellido === '' || $email === '') {
            $message = '✗ Todos los campos de perfil son obligatorios.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '✗ El correo electrónico no tiene un formato válido.';
            $messageType = 'error';
        } else {
            try {
                $changePassword = false;
                if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
                    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                        throw new Exception('Para cambiar la contraseña, debe completar todos los campos correspondientes.');
                    }
                    if (strlen($newPassword) < 6) {
                        throw new Exception('La nueva contraseña debe tener al menos 6 caracteres.');
                    }
                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('La nueva contraseña y su confirmación no coinciden.');
                    }

                    // Validar contraseña actual
                    $stmtCheck = $pdo->prepare('SELECT PASSWORD FROM usuario WHERE ID_USUARIO = ?');
                    $stmtCheck->execute([$usuarioId]);
                    $dbPass = $stmtCheck->fetchColumn();
                    if (!$dbPass || !password_verify($currentPassword, $dbPass)) {
                        throw new Exception('La contraseña actual es incorrecta.');
                    }
                    $changePassword = true;
                }

                if ($changePassword) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $u = $pdo->prepare('UPDATE usuario SET NOMBRE = ?, APELLIDO = ?, EMAIL = ?, PASSWORD = ? WHERE ID_USUARIO = ?');
                    $u->execute([$nombre, $apellido, $email, $newHash, $usuarioId]);
                    $message = '✓ Perfil y contraseña actualizados correctamente.';
                } else {
                    $u = $pdo->prepare('UPDATE usuario SET NOMBRE = ?, APELLIDO = ?, EMAIL = ? WHERE ID_USUARIO = ?');
                    $u->execute([$nombre, $apellido, $email, $usuarioId]);
                    $message = '✓ Perfil actualizado correctamente.';
                }
                $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;

                // Manejo de foto
                if (!empty($_FILES['photo']['name'])) {
                    $file = $_FILES['photo'];
                    $allowedImageTypes = [
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_PNG  => 'png',
                        IMAGETYPE_WEBP => 'webp',
                    ];
                    $imageInfo = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;

                    if ($imageInfo !== false && isset($allowedImageTypes[$imageInfo[2]])) {
                        $ext = $allowedImageTypes[$imageInfo[2]];

                        $dir = __DIR__ . '/../uploads/profiles';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $target = $dir . '/' . $usuarioId . '.' . $ext;

                        foreach (['jpg','jpeg','png','webp'] as $old) {
                            $oldf = $dir . '/' . $usuarioId . '.' . $old;
                            if (file_exists($oldf)) {
                                @unlink($oldf);
                            }
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
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $message = $e->getCode() === '23000'
                    ? '✗ Ese correo electrónico ya está en uso por otro usuario.'
                    : '✗ No se pudo actualizar el perfil. Contacte al administrador.';
                $messageType = 'error';
            } catch (Exception $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $message = '✗ ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Recargar datos
    $stmt->execute([$usuarioId]);
    $user = $stmt->fetch();
}

// Foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext . '?t=' . filemtime($candidate);
        break;
    }
}

$usuarioNombre = htmlspecialchars($user['NOMBRE'] . ' ' . $user['APELLIDO']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Editar perfil del almacenista en BICERGAM.">
    <title>Editar Perfil Almacenista - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
    <style>
        .profile-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            max-width: 600px;
            padding: 30px;
            margin: 30px auto;
            border: 1px solid #e1e8ed;
        }
        .profile-card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .profile-avatar-big {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--verde-sena);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .profile-avatar-initials {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--verde-sena);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .profile-card-header-info h3 {
            margin: 0;
            color: #101820;
            font-size: 22px;
            font-weight: 700;
        }
        .profile-card-header-info span {
            display: inline-block;
            margin-top: 4px;
            color: #666;
            font-size: 14px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 20px;
        }
        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .profile-field.full-col {
            grid-column: span 2;
        }
        .profile-field label {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        .profile-field input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .profile-field input:focus {
            border-color: var(--verde-sena);
            outline: none;
            box-shadow: 0 0 0 3px rgba(57,181,74,0.15);
        }
        .profile-actions {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .btn-save {
            background-color: var(--verde-sena);
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(57, 169, 0, 0.2);
        }
        .btn-back {
            color: #555;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }
        .btn-back:hover {
            color: var(--verde-sena);
        }
        .profile-alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .profile-alert.success {
            background-color: #eff8f1;
            color: #270;
            border: 1px solid #d4ebd5;
        }
        .profile-alert.error {
            background-color: #fdf2f2;
            color: #de3a3a;
            border: 1px solid #fde2e2;
        }
        .btn-toggle-pwd {
            background: none;
            border: none;
            color: var(--verde-sena);
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            padding: 5px 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            transition: color 0.2s;
            margin-top: 10px;
        }
        .btn-toggle-pwd:hover {
            color: #2e8640;
            text-decoration: underline;
        }
        .password-section-container {
            grid-column: span 2;
            display: none;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 20px;
            border-top: 1px dashed #e1e8ed;
            padding-top: 20px;
            margin-top: 10px;
        }
        .avatar-edit-container {
            position: relative;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            cursor: pointer;
            overflow: hidden;
            border: 3px solid var(--verde-sena);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .avatar-edit-container:hover .avatar-overlay {
            opacity: 1;
        }
        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 11px;
            font-weight: bold;
            gap: 4px;
        }
        .avatar-overlay svg {
            margin-bottom: 2px;
        }
        .profile-avatar-big, .profile-avatar-initials {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .password-input-wrapper input {
            padding-right: 42px !important;
        }
        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: color 0.2s;
        }
        .toggle-password-btn:hover {
            color: var(--verde-sena);
        }
        @media(max-width: 580px) {
            .profile-grid, .password-section-container {
                grid-template-columns: 1fr;
            }
            .profile-field.full-col {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén Central</span></h1>
            <div class="user-greeting">Gestor de Turno: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Almacenista)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">
            🔔
            <?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, $usuarioId); ?>
            <?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="almacenista_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Inventario</h4>
            <a href="index.php?tab=stock" class="sidebar-link">Vista de Stock</a>
            <a href="index.php?tab=entrada" class="sidebar-link">Registrar Entrada</a>
            <a href="index.php?tab=salida" class="sidebar-link">Registrar Salida</a>
            <a href="historial_movimientos.php" class="sidebar-link">Historial de Movimientos</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="index.php?tab=instructor" class="sidebar-link">Panel Instructor</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="almacenista_profile.php" class="sidebar-link sidebar-link--primary active">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Editar Perfil</h2>
                <p class="dashboard-subtitle">Actualiza tu información personal y foto de perfil.</p>
            </div>
        </div>

        <div class="profile-card">

            <div class="profile-card-header">
                <div class="avatar-edit-container" title="Cambiar foto de perfil" onclick="document.getElementById('p-photo').click();">
                    <?php if ($photoPath): ?>
                        <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto" class="profile-avatar-big" id="avatar-preview">
                    <?php else: ?>
                        <div class="profile-avatar-initials" id="avatar-initials-preview">
                            <?= strtoupper(substr($user['NOMBRE'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="avatar-overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <span>Cambiar</span>
                    </div>
                </div>
                <div class="profile-card-header-info">
                    <h3><?= htmlspecialchars(($user['NOMBRE'] ?? '') . ' ' . ($user['APELLIDO'] ?? '')) ?></h3>
                    <span><?= htmlspecialchars($user['EMAIL'] ?? '') ?> &nbsp;·&nbsp; Almacenista</span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="profile-alert <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="almacenista_profile.php" method="POST" enctype="multipart/form-data" id="form-perfil">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                <input type="file" name="photo" id="p-photo" accept="image/png, image/jpeg, image/webp" style="display: none;">
                <div class="profile-grid">

                    <div class="profile-field">
                        <label for="p-nombre">Nombre</label>
                        <input type="text" id="p-nombre" name="nombre"
                               value="<?= htmlspecialchars($user['NOMBRE'] ?? '') ?>" required>
                    </div>

                    <div class="profile-field">
                        <label for="p-apellido">Apellido</label>
                        <input type="text" id="p-apellido" name="apellido"
                               value="<?= htmlspecialchars($user['APELLIDO'] ?? '') ?>" required>
                    </div>

                    <div class="profile-field full-col">
                        <label for="p-email">Correo electrónico</label>
                        <input type="email" id="p-email" name="email"
                               value="<?= htmlspecialchars($user['EMAIL'] ?? '') ?>" required>
                    </div>

                    <div class="profile-field full-col">
                        <button type="button" class="btn-toggle-pwd" id="btn-toggle-password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Cambiar Contraseña
                        </button>
                    </div>

                    <div id="password-section" class="password-section-container">
                        <div class="profile-field full-col">
                            <label for="p-current-password">Contraseña Actual</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="p-current-password" name="current_password" placeholder="Ingresa tu contraseña actual">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('p-current-password', this)">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <div class="profile-field">
                            <label for="p-new-password">Nueva Contraseña</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="p-new-password" name="new_password" placeholder="Mínimo 6 caracteres">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('p-new-password', this)">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <div class="profile-field">
                            <label for="p-confirm-password">Confirmar Nueva Contraseña</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="p-confirm-password" name="confirm_password" placeholder="Repite la nueva contraseña">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('p-confirm-password', this)">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                </div><!-- /.profile-grid -->

                <div class="profile-actions">
                    <button type="submit" class="btn-save" id="btn-guardar-perfil">Guardar cambios</button>
                    <a href="index.php" class="btn-back" id="btn-volver-dashboard">
                        ← Volver
                    </a>
                </div>
            </form>

        </div><!-- /.profile-card -->
    </main>
</div>

<script src="../js/apartados.js"></script>
<script>
document.getElementById('btn-toggle-password').addEventListener('click', function() {
    var sec = document.getElementById('password-section');
    var isHidden = window.getComputedStyle(sec).display === 'none';
    sec.style.display = isHidden ? 'grid' : 'none';

    if (!isHidden) {
        document.getElementById('p-current-password').value = '';
        document.getElementById('p-new-password').value = '';
        document.getElementById('p-confirm-password').value = '';
    }
});

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        input.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
}

document.getElementById('p-photo').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('avatar-preview');
            if (previewImg) {
                previewImg.src = e.target.result;
            } else {
                const initialsDiv = document.getElementById('avatar-initials-preview');
                if (initialsDiv) {
                    const img = document.createElement('img');
                    img.id = 'avatar-preview';
                    img.src = e.target.result;
                    img.className = 'profile-avatar-big';
                    initialsDiv.parentNode.replaceChild(img, initialsDiv);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});

document.getElementById('form-perfil').addEventListener('submit', function(event) {
    const currentPass = document.getElementById('p-current-password').value.trim();
    const newPass = document.getElementById('p-new-password').value.trim();
    const confirmPass = document.getElementById('p-confirm-password').value.trim();

    if (currentPass !== '' || newPass !== '' || confirmPass !== '') {
        const confirmacion = confirm("¿Realmente deseas cambiar tu contraseña?");
        if (!confirmacion) {
            event.preventDefault();
        }
    }
});
</script>
</body>
</html>
