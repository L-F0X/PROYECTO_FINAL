<?php
// restablecer_password.php
require_once 'conexion.php';
require_once 'password_reset.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mensaje = '';
$mensajeTipo = 'error';
$tokenValido = false;
$exito = false;

if ($token === '') {
    $mensaje = 'Enlace de restablecimiento inválido.';
} else {
    $fila = validar_token_reset($pdo, $token);
    if (!$fila) {
        $mensaje = 'Este enlace de restablecimiento no es válido, ya fue usado, o venció. Solicita uno nuevo.';
    } else {
        $tokenValido = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword === '' || $confirmPassword === '') {
                $mensaje = 'Debe completar ambos campos de contraseña.';
            } elseif (strlen($newPassword) < 6) {
                $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } elseif ($newPassword !== $confirmPassword) {
                $mensaje = 'La contraseña y su confirmación no coinciden.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE usuario SET PASSWORD = ? WHERE ID_USUARIO = ?')->execute([$hash, intval($fila['ID_USUARIO'])]);
                marcar_token_usado($pdo, intval($fila['ID_TOKEN']));
                $exito = true;
            }
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
        </div>

        <?php if ($exito): ?>
            <div class="profile-alert success" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
                ✓ Contraseña actualizada correctamente. Ya puedes iniciar sesión.
            </div>
            <a href="login.php" class="btn btn-sena btn-block" style="text-align:center; display:block; text-decoration:none;">Ir a Iniciar Sesión</a>
        <?php elseif (!$tokenValido): ?>
            <div class="error-msg"><?= htmlspecialchars($mensaje) ?></div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="recuperar_password.php" style="font-size: 13px; color: var(--verde-sena);">Solicitar un nuevo enlace</a>
            </div>
        <?php else: ?>
            <?php if ($mensaje): ?>
                <div class="error-msg"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <form method="POST" action="restablecer_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="new_password">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-sena btn-block">Restablecer Contraseña</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<script src="js/apartados.js"></script>
</body>
</html>
