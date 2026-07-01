<?php
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if (!in_array($rol, ['instructor', 'coordinacion'])) {
    header('Location: index.php');
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
            $dir = __DIR__ . '/uploads/profiles';
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
    <meta name="description" content="Editar perfil del instructor en BICERGAM.">
    <title>Editar Perfil - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
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
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f0f0f0;
        }
        .profile-avatar-big {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #39a900;
            flex-shrink: 0;
        }
        .profile-avatar-initials {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #39a900, #2e8600);
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .profile-card-header-info h3 {
            margin: 0 0 3px;
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .profile-card-header-info span {
            font-size: 12px;
            color: #888;
        }

        /* ── Grid de campos ── */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 16px;
        }
        .profile-grid .full-col { grid-column: 1 / -1; }

        .profile-field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            margin-bottom: 5px;
        }
        .profile-field input {
            width: 100%;
            border: 1.5px solid #e0e0e0;
            border-radius: 7px;
            padding: 8px 11px;
            font-size: 13.5px;
            box-sizing: border-box;
            background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
            color: #1a1a2e;
        }
        .profile-field input:focus {
            outline: none;
            border-color: #39a900;
            box-shadow: 0 0 0 3px rgba(57,169,0,.1);
            background: #fff;
        }

        /* ── Foto ── */
        .photo-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 4px;
        }
        .photo-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            flex-shrink: 0;
        }
        .photo-thumb-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .file-input-label {
            font-size: 12px;
            color: #555;
        }
        .file-input-label input[type="file"] {
            margin-top: 4px;
            font-size: 12px;
        }

        /* ── Acciones ── */
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #f0f0f0;
        }
        .btn-save {
            background: linear-gradient(135deg,#39a900,#2e8600);
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-save:hover  { background: linear-gradient(135deg,#2e8600,#1d5800); }
        .btn-save:active { transform: scale(0.98); }
        .btn-back {
            background: #fff;
            color: #555;
            border: 1.5px solid #ddd;
            border-radius: 7px;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-back:hover { border-color: #aaa; color: #333; }

        /* ── Alertas ── */
        .profile-alert {
            padding: 10px 14px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 18px;
        }
        .profile-alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .profile-alert.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand">
        <img src="imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
        <a href="profile.php" class="header-avatar-link" title="Editar perfil">
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
            <img src="imagenes/sena-logo.png" alt="SENA" style="max-height:48px; width:auto;">
        </div>
        <!-- Sidebar: show role-specific links below -->
        <?php if ($rol === 'instructor'): ?>
            <div class="sidebar-group">
                <h4>Operaciones</h4>
                <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary">Ficha Técnica</a>
                <a href="crear.php" class="sidebar-link">Consulta de Lotes</a>
            </div>

            <div class="sidebar-group">
                <h4>Consultas</h4>
                <a href="historial_existencia.php" class="sidebar-link">Historial de Existencia</a>
                <a href="matriz.php" class="sidebar-link">Consulta de Matrices</a>
            </div>

            <div class="sidebar-group sidebar-group--session">
                <h4>Sesión</h4>
                <a href="instructor_profile.php" class="sidebar-link active">Editar Perfil</a>
                <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
            </div>
        <?php else: ?>
            <div class="sidebar-group">
                <h4>Operaciones</h4>
                <a href="coordinador_dashboard.php" class="sidebar-link sidebar-link--primary">Aprobación de Fichas</a>
                <a href="index.php?from=coordinador" class="sidebar-link">Ver Lotes</a>
            </div>

            <div class="sidebar-group">
                <h4>Consultas</h4>
                <a href="historial_existencia.php" class="sidebar-link">Historial de Existencia</a>
                <a href="matriz.php" class="sidebar-link">Consulta de Matrices</a>
            </div>

            <div class="sidebar-group sidebar-group--session">
                <h4>Sesión</h4>
                <a href="profile.php" class="sidebar-link active">Editar Perfil</a>
                <a href="logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
            </div>
        <?php endif; ?>
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

            <form action="profile.php" method="POST" enctype="multipart/form-data" id="form-perfil">
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
                    <?php if ($rol === 'instructor'): ?>
                        <a href="instructor_dashboard.php" class="btn-back" id="btn-volver-dashboard">← Volver</a>
                    <?php else: ?>
                        <a href="coordinador_dashboard.php" class="btn-back" id="btn-volver-dashboard">← Volver</a>
                    <?php endif; ?>
                </div>
            </form>

        </div><!-- /.profile-card -->
    </main>
</div>

<script src="javascript.js"></script>
</body>
</html>
