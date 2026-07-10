<?php
// restablecer_password.php
require_once 'conexion.php';
require_once 'password_reset.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$exito = false;
$email = trim($_POST['email'] ?? '');
$codigo = trim($_POST['codigo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $codigo === '' || $newPassword === '' || $confirmPassword === '') {
        $mensaje = 'Debe completar todos los campos.';
    } elseif (!preg_match('/^[0-9]{6}$/', $codigo)) {
        $mensaje = 'El código debe tener 6 dígitos.';
    } elseif (strlen($newPassword) < 6) {
        $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($newPassword !== $confirmPassword) {
        $mensaje = 'La contraseña y su confirmación no coinciden.';
    } else {
        $stmtUser = $pdo->prepare('SELECT ID_USUARIO FROM usuario WHERE EMAIL = ?');
        $stmtUser->execute([$email]);
        $usuarioFila = $stmtUser->fetch();

        // Mensaje genérico si el correo no existe o el código no es válido para
        // ese correo: no se revela cuál de los dos falló.
        $fila = $usuarioFila ? validar_token_reset($pdo, intval($usuarioFila['ID_USUARIO']), $codigo) : false;
        if (!$fila) {
            $mensaje = 'El correo o el código no son válidos, ya fue usado, o venció. Solicita uno nuevo.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE usuario SET PASSWORD = ? WHERE ID_USUARIO = ?')->execute([$hash, intval($fila['ID_USUARIO'])]);
            marcar_token_usado($pdo, intval($fila['ID_TOKEN']));
            $exito = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Restablecer Contraseña</title>
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
            <p class="login-subtitle">Restablece tu contraseña de acceso.</p>
        </div>
    </section>

    <section class="login-form-container">
        <div class="login-form-header">
            <h2>Restablecer Contraseña</h2>
            <?php if (!$exito): ?>
                <p>Ingresa el código de 6 dígitos que enviamos a tu correo junto con tu nueva contraseña.</p>
            <?php endif; ?>
        </div>

        <?php if ($exito): ?>
            <div class="profile-alert success" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
                ✓ Contraseña actualizada correctamente. Ya puedes iniciar sesión.
            </div>
            <a href="login.php" class="btn btn-sena btn-block" style="text-align:center; display:block; text-decoration:none;">Ir a Iniciar Sesión</a>
        <?php else: ?>
            <?php if ($mensaje): ?>
                <div class="error-msg"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <form method="POST" action="restablecer_password.php">
                <div class="form-group">
                    <label for="email">Correo Electrónico Institucional</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="codigo">Código de Verificación</label>
                    <input type="text" id="codigo" name="codigo" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 dígitos" value="<?= htmlspecialchars($codigo) ?>" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Nueva Contraseña</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-sena btn-block">Restablecer Contraseña</button>
            </form>
            <div style="text-align: center; margin-top: 15px;">
                <a href="recuperar_password.php" style="font-size: 13px; color: var(--verde-sena);">Solicitar un nuevo código</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="js/apartados.js"></script>
<script>
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
</script>
</body>
</html>
