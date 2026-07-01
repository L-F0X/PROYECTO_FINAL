<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador') {
    header('Location: ../login.php');
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$email = 'correo@institucional.com';
try {
    $stmt = $pdo->prepare('SELECT EMAIL FROM usuario WHERE ID_USUARIO = ? LIMIT 1');
    $stmt->execute([intval($_SESSION['usuario_id'])]);
    $userData = $stmt->fetch();
    if (!empty($userData['EMAIL'])) {
        $email = htmlspecialchars($userData['EMAIL']);
    }
} catch (\PDOException $e) {
    error_log('Coordinador profile email load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil Coordinador - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">Perfil Coordinador</h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Coordinador)</span></div>
        </div>
    </div>
    <div class="header-right">
        <a href="index.php" class="btn btn-secondary" style="margin-right: 10px;">Volver</a>
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in" style="margin-top: 20px;">
    <div class="role-banner role-coordinador">
        <h2>Edición de Perfil</h2>
        <p>Mantén tu información de coordinador actualizada. Esta vista conserva la misma estética del instructor.</p>
    </div>

    <div class="profile-card">
        <div class="profile-card-header">
            <div class="profile-avatar-wrapper">
                <div class="profile-avatar-placeholder"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            </div>
            <div class="profile-title">
                <h3><?= $usuarioNombre ?></h3>
                <p>Coordinador del sistema</p>
            </div>
        </div>

        <div class="profile-form-grid">
            <div>
                <label class="profile-label">Nombre completo</label>
                <input class="profile-input" type="text" value="<?= $usuarioNombre ?>" readonly>
            </div>
            <div>
                <label class="profile-label">Correo</label>
                <input class="profile-input" type="email" value="<?= $email ?>" readonly>
            </div>
            <div>
                <label class="profile-label">Rol</label>
                <input class="profile-input" type="text" value="Coordinador" readonly>
            </div>
            <div>
                <label class="profile-label">Estado</label>
                <input class="profile-input" type="text" value="Activo" readonly>
            </div>
        </div>

        <div class="profile-card-footer">
            <a href="index.php" class="btn btn-sena">Volver al Panel</a>
        </div>
    </div>
</div>
</body>
</html>
