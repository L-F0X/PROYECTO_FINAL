<?php
// Administrador/editar_usuario.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';
require_once '../texto_helper.php';

// Control de acceso
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$idUsuario = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idUsuario <= 0) {
    header("Location: index.php");
    exit;
}

// Cargar datos actuales del usuario
try {
    $stmtUser = $pdo->prepare("SELECT * FROM usuario WHERE ID_USUARIO = ?");
    $stmtUser->execute([$idUsuario]);
    $user = $stmtUser->fetch();
    if (!$user) {
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    error_log('Error cargando usuario para edición: ' . $e->getMessage());
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $idRol      = intval($_POST['id_rol'] ?? 0);
    $documento  = trim($_POST['documento'] ?? '');
    $nombre     = capitalizar_nombre(trim($_POST['nombre'] ?? ''));
    $apellido   = capitalizar_nombre(trim($_POST['apellido'] ?? ''));
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $estado     = trim($_POST['estado'] ?? 'Activo');

    if ($idRol <= 0 || $documento === '' || $nombre === '' || $apellido === '' || $email === '') {
        $error = '✗ Todos los campos son obligatorios.';
    } elseif (!preg_match('/^[0-9]+$/', $documento)) {
        $error = '✗ El documento de identidad solo debe contener números.';
    } elseif (strlen($documento) < 6) {
        $error = '✗ El documento de identidad debe tener al menos 6 dígitos.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+( [a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+)?$/u', $nombre)) {
        $error = '✗ El nombre solo debe contener letras y espacios.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+( [a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+)?$/u', $apellido)) {
        $error = '✗ El apellido solo debe contener letras y espacios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '✗ El correo electrónico no tiene un formato válido.';
    } elseif (strlen($documento) > 20) {
        $error = '✗ El documento no puede tener más de 20 caracteres.';
    } elseif (strlen($nombre) > 100 || strlen($apellido) > 100) {
        $error = '✗ El nombre y el apellido no pueden tener más de 100 caracteres.';
    } elseif (strlen($email) > 100) {
        $error = '✗ El correo electrónico no puede tener más de 100 caracteres.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = '✗ La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            // Verificar que el rol seleccionado exista
            $stmtRolCheck = $pdo->prepare("SELECT 1 FROM rol WHERE ID_ROL = ?");
            $stmtRolCheck->execute([$idRol]);

            // Verificar si el documento o correo ya están registrados por otro usuario
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE (DOCUMENTO = ? OR EMAIL = ?) AND ID_USUARIO <> ?");
            $stmtCheck->execute([$documento, $email, $idUsuario]);

            if (!$stmtRolCheck->fetchColumn()) {
                $error = '✗ El rol seleccionado no es válido.';
            } elseif ($stmtCheck->fetchColumn() > 0) {
                $error = '✗ El documento o correo electrónico ya están registrados por otro usuario.';
            } else {
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE usuario SET ID_ROL = ?, DOCUMENTO = ?, NOMBRE = ?, APELLIDO = ?, EMAIL = ?, PASSWORD = ?, ESTADO = ? WHERE ID_USUARIO = ?");
                    $stmtUpdate->execute([$idRol, $documento, $nombre, $apellido, $email, $passwordHash, $estado, $idUsuario]);
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE usuario SET ID_ROL = ?, DOCUMENTO = ?, NOMBRE = ?, APELLIDO = ?, EMAIL = ?, ESTADO = ? WHERE ID_USUARIO = ?");
                    $stmtUpdate->execute([$idRol, $documento, $nombre, $apellido, $email, $estado, $idUsuario]);
                }

                // Si se edita a sí mismo, actualizar variables de sesión
                if ($idUsuario === intval($_SESSION['usuario_id'])) {
                    $_SESSION['usuario_nombre'] = $nombre . ' ' . $apellido;
                    $_SESSION['usuario_rol'] = $idRol;
                    // Recargar rol_nombre
                    $stmtRol = $pdo->prepare("SELECT NOMBRE_ROL FROM rol WHERE ID_ROL = ?");
                    $stmtRol->execute([$idRol]);
                    $_SESSION['rol_nombre'] = $stmtRol->fetchColumn();
                }

                // Registrar en la auditoría
                $stmtLog = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, ?, ?)");
                $stmtLog->execute([
                    $_SESSION['usuario_id'],
                    'Modificación Usuario',
                    "Modificado usuario: $nombre $apellido (ID: $idUsuario, Rol: $idRol, Documento: $documento, Estado: $estado)"
                ]);

                header("Location: index.php?msg=user_updated");
                exit;
            }
        } catch (Exception $e) {
            error_log('Error editando usuario: ' . $e->getMessage());
            $error = '✗ Error interno al procesar la actualización.';
        }
    }
}

// Cargar roles para el combo
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM rol ORDER BY ID_ROL")->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando roles: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
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
        .profile-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 20px;
            margin-top: 20px;
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
        .profile-field input, .profile-field select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .profile-field input:focus, .profile-field select:focus {
            border-color: var(--verde-sena);
            outline: none;
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
        }
        .btn-back {
            color: #555;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        @media (max-width: 600px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .profile-field.full-col {
                grid-column: span 1;
            }
            .profile-card {
                padding: 20px;
                margin: 16px auto;
            }
        }
    </style>
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Administrador</span></h1>
            <div class="user-greeting">Administrador del Sistema: <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador') ?></strong> <span class="role-badge">(Administrador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?></a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Administración</h4>
            <a href="index.php" class="sidebar-link sidebar-link--primary">Gestión Usuarios</a>
            <a href="importar_unspsc.php" class="sidebar-link">Importar UNSPSC</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container" style="padding: 20px; margin: 0; max-width: 100%;">
            <div class="profile-card">
                <h2>Editar Usuario - ID: <?= htmlspecialchars($user['ID_USUARIO']) ?></h2>
                <p>Actualiza la información del usuario. Deja la contraseña en blanco si no deseas cambiarla.</p>

                <?php if ($error): ?>
                    <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="editar_usuario.php?id=<?= $idUsuario ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <div class="profile-grid">
                        <div class="profile-field">
                            <label for="documento">Documento de Identidad</label>
                            <input type="text" id="documento" name="documento" value="<?= htmlspecialchars($user['DOCUMENTO']) ?>" required minlength="6" maxlength="20" autocomplete="off"
                                   inputmode="numeric" pattern="[0-9]+" title="Solo se permiten números">
                        </div>

                        <div class="profile-field">
                            <label for="id_rol">Rol asignado</label>
                            <select id="id_rol" name="id_rol" required>
                                <option value="">Seleccione un rol...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['ID_ROL'] ?>" <?= $user['ID_ROL'] == $r['ID_ROL'] ? 'selected' : '' ?>><?= htmlspecialchars($r['NOMBRE_ROL']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="profile-field">
                            <label for="nombre">Nombres</label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($user['NOMBRE']) ?>" required maxlength="100" autocomplete="off"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+( [a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+)?" title="Solo se permiten letras y espacios">
                        </div>

                        <div class="profile-field">
                            <label for="apellido">Apellidos</label>
                            <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($user['APELLIDO']) ?>" required maxlength="100" autocomplete="off"
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+( [a-zA-ZáéíóúÁÉÍÓÚüÜñÑ]+)?" title="Solo se permiten letras y espacios">
                        </div>

                        <div class="profile-field full-col">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['EMAIL']) ?>" required maxlength="100" autocomplete="off">
                        </div>

                        <div class="profile-field">
                            <label for="password">Contraseña (opcional)</label>
                            <input type="password" id="password" name="password" placeholder="Nueva contraseña..." minlength="8" autocomplete="new-password">
                        </div>

                        <div class="profile-field">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado" required>
                                <option value="Activo" <?= $user['ESTADO'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="Inactivo" <?= $user['ESTADO'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="btn-save">Guardar Cambios</button>
                        <a href="index.php" class="btn-back">← Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
