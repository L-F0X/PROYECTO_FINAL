<?php
// recuperar_password.php
require_once 'conexion.php';
require_once 'password_reset.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$mensajeTipo = 'success';
$enlaceGenerado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $mensaje = 'Ingrese su correo electrónico.';
        $mensajeTipo = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT ID_USUARIO FROM usuario WHERE EMAIL = ?');
        $stmt->execute([$email]);
        $idUsuario = $stmt->fetchColumn();

        if ($idUsuario) {
            $token = generar_token_reset($pdo, intval($idUsuario));
            $baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            $enlaceGenerado = rtrim($baseUrl, '/') . '/restablecer_password.php?token=' . $token;
        }

        // Mensaje genérico: no confirmamos ni negamos si el correo existe.
        $mensaje = 'Si el correo electrónico está registrado, se generó un enlace de restablecimiento (ver abajo).';
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
                <p style="margin: 0 0 8px; color: #664d03;"><strong>Modo desarrollo:</strong> este proyecto no tiene un servidor de correo configurado, así que el enlace se muestra aquí directamente (en producción se enviaría al correo del usuario). Válido por 1 hora, un solo uso.</p>
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
