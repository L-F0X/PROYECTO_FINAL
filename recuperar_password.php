<?php
// recuperar_password.php
require_once 'conexion.php';
require_once 'password_reset.php';
require_once 'mailer.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$mensajeTipo = 'success';
$enlaceGenerado = '';
$correoEnviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $mensaje = 'Ingrese su correo electrónico.';
        $mensajeTipo = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT ID_USUARIO, NOMBRE FROM usuario WHERE EMAIL = ?');
        $stmt->execute([$email]);
        $usuarioFila = $stmt->fetch();

        if ($usuarioFila) {
            $token = generar_token_reset($pdo, intval($usuarioFila['ID_USUARIO']));
            $baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            $enlace = rtrim($baseUrl, '/') . '/restablecer_password.php?token=' . $token;

            $cuerpoHtml = '<div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto;">'
                . '<h2 style="color: #39A900;">BICERGAM</h2>'
                . '<p>Hola ' . htmlspecialchars($usuarioFila['NOMBRE']) . ',</p>'
                . '<p>Recibimos una solicitud para restablecer tu contraseña. Haz clic en el siguiente enlace para continuar (válido por 1 hora, un solo uso):</p>'
                . '<p><a href="' . htmlspecialchars($enlace) . '" style="background: #39A900; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;">Restablecer contraseña</a></p>'
                . '<p style="font-size: 13px; color: #666;">Si no solicitaste esto, puedes ignorar este correo.</p>'
                . '</div>';

            $correoEnviado = enviar_correo($email, 'Recuperación de contraseña - BICERGAM', $cuerpoHtml);

            // Respaldo: si el envío falla (SMTP no configurado o con error), se
            // muestra el enlace directamente para que la recuperación no quede rota.
            if (!$correoEnviado) {
                $enlaceGenerado = $enlace;
            }
        }

        // Mensaje genérico: no confirmamos ni negamos si el correo existe.
        $mensaje = 'Si el correo electrónico está registrado, se envió un enlace de restablecimiento a esa dirección.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Recuperar Contraseña</title>
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
            <p class="login-subtitle">Recupera el acceso a tu cuenta institucional.</p>
        </div>
    </section>

    <section class="login-form-container">
        <div class="login-form-header">
            <h2>Recuperar Contraseña</h2>
            <p>Ingresa tu correo institucional para generar un enlace de restablecimiento.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="profile-alert <?= $mensajeTipo ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; <?= $mensajeTipo === 'error' ? 'background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;' : 'background: #eff8f1; color: #270; border: 1px solid #d4ebd5;' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($enlaceGenerado): ?>
            <div style="background: #fff3cd; border: 1px solid #ffe69c; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px;">
                <p style="margin: 0 0 8px; color: #664d03;"><strong>No se pudo enviar el correo</strong> (revisa la configuración SMTP en <code>mail_config.php</code>). Mientras tanto, aquí tienes el enlace directamente. Válido por 1 hora, un solo uso.</p>
                <a href="<?= htmlspecialchars($enlaceGenerado) ?>" style="word-break: break-all; color: #00324D; font-weight: 600;"><?= htmlspecialchars($enlaceGenerado) ?></a>
            </div>
        <?php endif; ?>

        <form method="POST" action="recuperar_password.php">
            <div class="form-group">
                <label for="email">Correo Electrónico Institucional</label>
                <input type="email" id="email" name="email" class="form-control" autocomplete="username" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-sena btn-block">Generar Enlace de Restablecimiento</button>
        </form>

        <div style="text-align: center; margin-top: 15px;">
            <a href="login.php" style="font-size: 13px; color: var(--verde-sena);">← Volver a Iniciar Sesión</a>
        </div>
    </section>
</div>

<script src="js/apartados.js"></script>
</body>
</html>
