<?php
// Administrador/crear_usuario.php
require_once '../conexion.php';
require_once '../csrf.php';

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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $idRol      = intval($_POST['id_rol'] ?? 0);
    $documento  = trim($_POST['documento'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $apellido   = trim($_POST['apellido'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $estado     = trim($_POST['estado'] ?? 'Activo');

    if ($idRol > 0 && $documento !== '' && $nombre !== '' && $apellido !== '' && $email !== '' && $password !== '') {
        try {
            // Verificar si el documento o correo ya están registrados
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE DOCUMENTO = ? OR EMAIL = ?");
            $stmtCheck->execute([$documento, $email]);
            if ($stmtCheck->fetchColumn() > 0) {
                $error = '✗ El documento o correo electrónico ya están registrados.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO usuario (ID_ROL, DOCUMENTO, NOMBRE, APELLIDO, EMAIL, PASSWORD, ESTADO) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$idRol, $documento, $nombre, $apellido, $email, $passwordHash, $estado]);
                
                $nuevoId = $pdo->lastInsertId();

                // Registrar en la auditoría
                $stmtLog = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, ?, ?)");
                $stmtLog->execute([
                    $_SESSION['usuario_id'],
                    'Creación Usuario',
                    "Creado nuevo usuario: $nombre $apellido (ID: $nuevoId, Rol: $idRol, Documento: $documento)"
                ]);

                header("Location: index.php?msg=user_created");
                exit;
            }
        } catch (Exception $e) {
            error_log('Error creando usuario: ' . $e->getMessage());
            $error = '✗ Error interno al procesar el registro.';
        }
    } else {
        $error = '✗ Todos los campos son obligatorios.';
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
    <title>Crear Usuario - BICERGAM</title>
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
            <div class="user-greeting">Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador') ?></strong> <span class="role-badge">(Administrador)</span></div>
        </div>
    </div>
    <div class="header-right">
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Administración</h4>
            <a href="index.php" class="sidebar-link sidebar-link--primary">Gestión Usuarios</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="../instructor/index.php" class="sidebar-link">Panel Instructor</a>
            <a href="../coordinador/index.php" class="sidebar-link">Panel Coordinador</a>
            <a href="../almacenista/index.php" class="sidebar-link">Panel Almacenista</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container" style="padding: 20px; margin: 0; max-width: 100%;">
            <div class="profile-card">
                <h2>Crear Nuevo Usuario</h2>
                <p>Introduce la información personal y el rol del nuevo usuario en el sistema.</p>

                <?php if ($error): ?>
                    <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="crear_usuario.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <div class="profile-grid">
                        <div class="profile-field">
                            <label for="documento">Documento de Identidad</label>
                            <input type="text" id="documento" name="documento" required autocomplete="off">
                        </div>

                        <div class="profile-field">
                            <label for="id_rol">Rol asignado</label>
                            <select id="id_rol" name="id_rol" required>
                                <option value="">Seleccione un rol...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['ID_ROL'] ?>"><?= htmlspecialchars($r['NOMBRE_ROL']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="profile-field">
                            <label for="nombre">Nombres</label>
                            <input type="text" id="nombre" name="nombre" required autocomplete="off">
                        </div>

                        <div class="profile-field">
                            <label for="apellido">Apellidos</label>
                            <input type="text" id="apellido" name="apellido" required autocomplete="off">
                        </div>

                        <div class="profile-field full-col">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" required autocomplete="off">
                        </div>

                        <div class="profile-field">
                            <label for="password">Contraseña inicial</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password">
                        </div>

                        <div class="profile-field">
                            <label for="estado">Estado inicial</label>
                            <select id="estado" name="estado" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="btn-save">Crear Usuario</button>
                        <a href="index.php" class="btn-back">← Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>
