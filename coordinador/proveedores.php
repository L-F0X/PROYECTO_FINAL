<?php
// coordinador/proveedores.php
require_once '../conexion.php';
require_once '../csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

$mensaje = '';
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $mensaje = 'Token CSRF inválido.';
        $tipoMensaje = 'error';
    } else {
        $nit = trim($_POST['nit'] ?? '');
        $razonSocial = trim($_POST['razon_social'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nit === '' || $razonSocial === '') {
            $mensaje = 'NIT y Razón Social son obligatorios.';
            $tipoMensaje = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'El correo electrónico no tiene un formato válido.';
            $tipoMensaje = 'error';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO proveedor (NIT, RAZON_SOCIAL, EMAIL) VALUES (?, ?, ?)');
                $stmt->execute([$nit, $razonSocial, $email]);
                $mensaje = '✓ Proveedor registrado correctamente.';
            } catch (PDOException $e) {
                error_log('Error creando proveedor: ' . $e->getMessage());
                $mensaje = $e->getCode() === '23000'
                    ? 'Ya existe un proveedor con ese NIT.'
                    : 'No se pudo registrar el proveedor. Contacte al administrador.';
                $tipoMensaje = 'error';
            }
        }
    }
}

$busqueda = trim($_GET['q'] ?? '');
try {
    if ($busqueda !== '') {
        $stmt = $pdo->prepare('SELECT * FROM proveedor WHERE NIT LIKE ? OR RAZON_SOCIAL LIKE ? ORDER BY RAZON_SOCIAL');
        $like = "%$busqueda%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query('SELECT * FROM proveedor ORDER BY RAZON_SOCIAL');
    }
    $proveedores = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error listando proveedores: ' . $e->getMessage());
    $proveedores = [];
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <a href="coordinador_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="proveedores.php" class="sidebar-link sidebar-link--primary active">Proveedores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <div class="role-banner role-coordinador">
                <h2>Proveedores</h2>
                <p>Registra los proveedores que participan en las cotizaciones de los ítems de la matriz.</p>
            </div>

            <?php if ($mensaje): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin: 20px 0; font-weight: 500; <?= $tipoMensaje === 'error' ? 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' ?>">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <div class="panel-card" style="margin-top: 20px;">
                <h3>Nuevo Proveedor</h3>
                <form method="POST" action="proveedores.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="nit">NIT *</label>
                            <input type="text" id="nit" name="nit" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="razon_social">Razón Social *</label>
                            <input type="text" id="razon_social" name="razon_social" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sena" style="margin-top: 15px;">Registrar Proveedor</button>
                </form>
            </div>

            <div class="panel-card" style="margin-top: 20px;">
                <h3>Proveedores Registrados (<?= count($proveedores) ?>)</h3>
                <form method="GET" action="proveedores.php" style="margin-bottom: 15px; display: flex; gap: 10px;">
                    <input type="text" name="q" class="form-control" placeholder="Buscar por NIT o Razón Social..." value="<?= htmlspecialchars($busqueda) ?>" style="max-width: 300px;">
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                </form>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NIT</th>
                            <th>Razón Social</th>
                            <th>Correo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 20px; color:#999;">No hay proveedores registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($proveedores as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['ID_PROVEEDOR']) ?></td>
                                    <td><?= htmlspecialchars($p['NIT']) ?></td>
                                    <td><?= htmlspecialchars($p['RAZON_SOCIAL']) ?></td>
                                    <td><?= htmlspecialchars($p['EMAIL']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
