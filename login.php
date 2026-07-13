<?php
// login.php
require_once 'conexion.php';

// Si el usuario ya está logueado, redirigir al inicio
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

$msg = $_GET['msg'] ?? '';
$messageText = '';
if ($msg === 'logout') {
    $messageText = '✓ Sesión cerrada correctamente.';
} elseif ($msg === 'inactivo') {
    $messageText = '⚠ Sesión cerrada por inactividad (5 minutos sin actividad).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        try {
            // Consultar el usuario y su rol
            $stmt = $pdo->prepare("SELECT u.*, r.NOMBRE_ROL 
                                FROM usuario u 
                                INNER JOIN rol r ON u.ID_ROL = r.ID_ROL 
                                WHERE u.EMAIL = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            // Verificar existencia, contraseña y estado activo
            if ($usuario && password_verify($password, $usuario['PASSWORD'])) {
                if ($usuario['ESTADO'] === 'Activo') {
                    // Regenerar id de sesión para mitigar session fixation
                    session_regenerate_id(true);

                    // Iniciar variables de sesión
                    $_SESSION['usuario_id']     = $usuario['ID_USUARIO'];
                    $_SESSION['usuario_nombre'] = $usuario['NOMBRE'] . " " . $usuario['APELLIDO'];
                    $_SESSION['usuario_rol']    = $usuario['ID_ROL'];
                    $_SESSION['rol_nombre']     = $usuario['NOMBRE_ROL'];

                    // Rehash check: no actualizamos DB automáticamente aquí, solo registramos recomendación
                    if (password_needs_rehash($usuario['PASSWORD'], PASSWORD_DEFAULT)) {
                        error_log('Password for user ID ' . $usuario['ID_USUARIO'] . ' needs rehash. Consider updating hash.');
                    }

                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Su usuario se encuentra en estado: " . $usuario['ESTADO'] . ". Contacte al administrador.";
                }
            } else {
                $error = "Correo electrónico o contraseña incorrectos.";
            }
        } catch (\PDOException $e) {
            error_log('Login DB error: ' . $e->getMessage());
            $error = "Error interno. Intente de nuevo más tarde.";
        }
    } else {
        $error = "Por favor, complete todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Iniciar Sesión</title>
    <link rel="stylesheet" href="estilos.css?v=<?= filemtime('estilos.css') ?>">
</head>
<body class="login-page">

<div class="login-card fade-in">
    <div class="sena-logo" aria-label="Logo SENA">
        <img src="imagenes/sena-logo.png" alt="Logo SENA" />
    </div>
    <section class="login-hero">
        <div class="login-hero-content">
            <div class="login-acronym">BICERGAM</div>
            <p class="login-meaning">Bienes e Inventarios para Consolidación, Estructuración y Requerimiento de Gestión de Adquisición de Materiales de formación</p>
            <p class="login-subtitle">Accede al sistema de pre-compra y gestión de materiales de formación con seguridad institucional y un entorno diseñado para facilitar tus procesos.</p>
        </div>
    </section>

    <section class="login-form-container">
        <div class="login-form-header">
            <h2>Iniciar Sesión</h2>
            <p>Ingresa con tu correo institucional y contraseña.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($messageText)): ?>
            <div class="profile-alert success" data-toast-duration="8000" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;"><?= htmlspecialchars($messageText) ?></div>
        <?php endif; ?>

        <form id="formLogin" action="login.php" method="POST" autocomplete="off">
            <div class="form-group">
                <label for="email">Correo Electrónico Institucional</label>
                <input type="email" id="email" name="email" class="form-control" autocomplete="off" required value="">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('password', this)">
                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>
            </div>

            <div style="text-align: right; margin-bottom: 15px;">
                <a href="recuperar_password.php" style="font-size: 13px; color: var(--verde-sena);">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-sena btn-block">Ingresar al Sistema</button>
        </form>
    </section>
</div>

<script src="js/apartados.js"></script>
<script>
    // Limpiar sesión del lado del cliente al estar en la página de login
    sessionStorage.removeItem('session_active');

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

    // Validación rápida con JS para evitar envíos vacíos no intencionados
    document.getElementById('formLogin').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        
        if(email === "" || password === "") {
            e.preventDefault();
            alert("Debe rellenar todos los campos del formulario.");
        } else {
            sessionStorage.setItem('session_active', '1');
        }
    });
</script>
</body>
</html>
