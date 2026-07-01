<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = 'success';

// Cargar datos actuales
$stmt = $pdo->prepare('SELECT NOMBRE, APELLIDO, EMAIL FROM usuario WHERE ID_USUARIO = ?');
$stmt->execute([$usuarioId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email']    ?? '');

    if ($nombre !== '' && $apellido !== '' && $email !== '') {
        try {
            $u = $pdo->prepare('UPDATE usuario SET NOMBRE = ?, APELLIDO = ?, EMAIL = ? WHERE ID_USUARIO = ?');
            $u->execute([$nombre, $apellido, $email, $usuarioId]);
            $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;
            $message = '✓ Perfil actualizado correctamente.';
        } catch (\PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $message = '✗ Error al actualizar el perfil.';
            $messageType = 'error';
        }
    } else {
        $message = '✗ Todos los campos son obligatorios.';
        $messageType = 'error';
    }

    // Manejo de foto
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $file = $_FILES['photo'];
        if ($file['error'] === UPLOAD_ERR_OK && isset($allowed[$file['type']])) {
            $ext = $allowed[$file['type']];
            $dir = __DIR__ . '/../uploads/profiles';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $target = $dir . '/' . $usuarioId . '.' . $ext;
            foreach (['jpg','jpeg','png','webp'] as $old) {
                $oldf = $dir . '/' . $usuarioId . '.' . $old;
                if (file_exists($oldf) && $oldf !== $target) @unlink($oldf);
            }
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $message .= ($message ? ' ' : '') . 'Foto actualizada.';
            } else {
                $message .= ' Error al subir la foto.';
            }
        } else {
            $message .= ' Formato de imagen no permitido.';
        }
    }

    $stmt->execute([$usuarioId]);
    $user = $stmt->fetch();
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Editar perfil del instructor en BICERGAM.">
    <title>Editar Perfil - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        /* ── Tarjeta del perfil ── */
        .profile-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,.08);
            max-width: 520px;
            padding: 28px 32px;
            margin: 0 auto;
        }
        .profile-card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--gris-claro);
            padding-bottom: 20px;
        }
        .profile-avatar-wrapper {
            position: relative;
            width: 80px;
            height: 80px;
        }
        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--verde-sena);
        }
        .profile-avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--azul-sena);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
        }
        .profile-title h3 {
            margin: 0;
            color: var(--negro);
            font-size: 20px;
        }
        .profile-title p {
            margin: 4px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        /* ── Formularios ── */
        .profile-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }
        .form-row-double {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .profile-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: var(--negro);
        }
        .profile-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #d9d9d9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .profile-input:focus {
            border-color: var(--verde-sena);
            outline: none;
            box-shadow: 0 0 0 3px rgba(57,181,74,0.15);
        }
        .profile-input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        /* ── File Upload ── */
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
            background: rgba(57,181,74,0.02);
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

        /* ── Footer ── */
        .profile-card-footer {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
        }

        /* Responsive */
        @media(max-width: 580px) {
            .form-row-double {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
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
            <img src="../imagenes/sena-logo.png" alt="SENA" style="max-height:48px; width:auto;">
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
            <a href="instructor_profile.php" class="sidebar-link active">Editar Perfil</a>
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

            <!-- ── Cabecera del card con avatar ── -->
            <div class="profile-card-header">
                <?php if ($photoPath): ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto" class="profile-avatar-big">
                <?php else: ?>
                    <div class="profile-avatar-initials">
                        <?= strtoupper(substr($user['NOMBRE'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="profile-card-header-info">
                    <h3><?= htmlspecialchars(($user['NOMBRE'] ?? '') . ' ' . ($user['APELLIDO'] ?? '')) ?></h3>
                    <span><?= htmlspecialchars($user['EMAIL'] ?? '') ?> &nbsp;·&nbsp; Instructor</span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="profile-alert <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="instructor_profile.php" method="POST" enctype="multipart/form-data" id="form-perfil">
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
                        <label>Foto de Perfil</label>
                        <div class="photo-row">
                            <?php if ($photoPath): ?>
                                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto" class="photo-thumb">
                            <?php else: ?>
                                <div class="photo-thumb-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                                         viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="1.5">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="file-input-label">
                                <span><?= $photoPath ? 'Cambiar foto' : 'Subir foto' ?> (png, jpg, webp)</span><br>
                                <input type="file" name="photo" id="p-photo"
                                       accept="image/png, image/jpeg, image/webp">
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

<script src="../javascript.js"></script>
</body>
</html>
