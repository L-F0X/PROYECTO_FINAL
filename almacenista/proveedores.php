<?php
// almacenista/proveedores.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'almacenista') {
    header('Location: ../login.php');
    exit;
}

function proveedor_columna_existe(PDO $pdo, string $columna): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proveedor' AND COLUMN_NAME = ?");
    $stmt->execute([$columna]);
    return (bool) $stmt->fetchColumn();
}
// ALTER TABLE es DDL: MySQL hace commit implícito de cualquier transacción
// abierta, por eso se verifica primero por information_schema (solo SELECT).
if (!proveedor_columna_existe($pdo, 'TELEFONO')) {
    $pdo->exec("ALTER TABLE proveedor ADD COLUMN TELEFONO VARCHAR(20) DEFAULT NULL");
}
if (!proveedor_columna_existe($pdo, 'CONTACTO')) {
    $pdo->exec("ALTER TABLE proveedor ADD COLUMN CONTACTO VARCHAR(100) DEFAULT NULL");
}

$mensaje = '';
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $mensaje = 'Token CSRF inválido.';
        $tipoMensaje = 'error';
    } else {
        $accion = $_POST['accion'] ?? 'crear';
        $nit = trim($_POST['nit'] ?? '');
        $razonSocial = trim($_POST['razon_social'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $contacto = trim($_POST['contacto'] ?? '');

        if ($nit === '' || $razonSocial === '') {
            $mensaje = 'NIT y Razón Social son obligatorios.';
            $tipoMensaje = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'El correo electrónico no tiene un formato válido.';
            $tipoMensaje = 'error';
        } elseif (strlen($nit) > 20) {
            $mensaje = 'El NIT no puede tener más de 20 caracteres.';
            $tipoMensaje = 'error';
        } elseif (strlen($razonSocial) > 150) {
            $mensaje = 'La razón social no puede tener más de 150 caracteres.';
            $tipoMensaje = 'error';
        } elseif (strlen($email) > 100) {
            $mensaje = 'El correo electrónico no puede tener más de 100 caracteres.';
            $tipoMensaje = 'error';
        } elseif (strlen($telefono) > 20) {
            $mensaje = 'El teléfono no puede tener más de 20 caracteres.';
            $tipoMensaje = 'error';
        } elseif (strlen($contacto) > 100) {
            $mensaje = 'La persona de contacto no puede tener más de 100 caracteres.';
            $tipoMensaje = 'error';
        } elseif ($accion === 'editar') {
            $idProveedor = intval($_POST['id_proveedor'] ?? 0);
            try {
                $existe = $pdo->prepare('SELECT 1 FROM proveedor WHERE ID_PROVEEDOR = ?');
                $existe->execute([$idProveedor]);
                if ($idProveedor <= 0 || !$existe->fetch()) {
                    $mensaje = 'No se encontró el proveedor indicado.';
                    $tipoMensaje = 'error';
                } else {
                    $stmt = $pdo->prepare('UPDATE proveedor SET NIT = ?, RAZON_SOCIAL = ?, EMAIL = ?, TELEFONO = ?, CONTACTO = ? WHERE ID_PROVEEDOR = ?');
                    $stmt->execute([$nit, $razonSocial, $email, $telefono !== '' ? $telefono : null, $contacto !== '' ? $contacto : null, $idProveedor]);
                    $mensaje = '✓ Proveedor actualizado correctamente.';
                }
            } catch (PDOException $e) {
                error_log('Error editando proveedor: ' . $e->getMessage());
                $mensaje = $e->getCode() === '23000'
                    ? 'Ya existe otro proveedor con ese NIT.'
                    : 'No se pudo actualizar el proveedor. Contacte al administrador.';
                $tipoMensaje = 'error';
            }
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO proveedor (NIT, RAZON_SOCIAL, EMAIL, TELEFONO, CONTACTO) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nit, $razonSocial, $email, $telefono !== '' ? $telefono : null, $contacto !== '' ? $contacto : null]);
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
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
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

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén Central</span></h1>
            <div class="user-greeting">Gestor de Turno: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Almacenista)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, $usuarioId); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="almacenista_profile.php" class="header-avatar-link" title="Editar perfil">
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
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Inventario</h4>
            <a href="index.php?tab=stock" class="sidebar-link">Vista de Stock</a>
            <a href="index.php?tab=entrada" class="sidebar-link">Registrar Entrada</a>
            <a href="index.php?tab=salida" class="sidebar-link">Registrar Salida</a>
            <a href="historial_movimientos.php" class="sidebar-link">Historial de Movimientos</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="index.php?tab=instructor" class="sidebar-link">Panel Instructor</a>
            <a href="proveedores.php" class="sidebar-link sidebar-link--primary active">Proveedores</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="almacenista_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <div class="dashboard-topbar">
                <div>
                    <h2>Proveedores</h2>
                    <p class="dashboard-subtitle">Catálogo de proveedores externos que cotizan los ítems de la matriz.</p>
                </div>
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
                    <input type="hidden" name="accion" value="crear">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="nit">NIT *</label>
                            <input type="text" id="nit" name="nit" class="form-control" required maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="razon_social">Razón Social *</label>
                            <input type="text" id="razon_social" name="razon_social" class="form-control" required maxlength="150">
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico *</label>
                            <input type="email" id="email" name="email" class="form-control" required maxlength="100">
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="contacto">Persona de Contacto</label>
                            <input type="text" id="contacto" name="contacto" class="form-control" maxlength="100">
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
                            <th>Teléfono</th>
                            <th>Contacto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                            <line x1="8" y1="11" x2="14" y2="11"/>
                                        </svg>
                                        <p>No hay proveedores registrados.</p>
                                        <span>Registra el primer proveedor con el formulario de arriba.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($proveedores as $p): ?>
                                <?php $formId = 'form-prov-' . $p['ID_PROVEEDOR']; ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['ID_PROVEEDOR']) ?></td>
                                    <td>
                                        <input type="text" name="nit" form="<?= $formId ?>" value="<?= htmlspecialchars($p['NIT']) ?>" class="form-control" required maxlength="20">
                                    </td>
                                    <td>
                                        <input type="text" name="razon_social" form="<?= $formId ?>" value="<?= htmlspecialchars($p['RAZON_SOCIAL']) ?>" class="form-control" required maxlength="150">
                                    </td>
                                    <td>
                                        <input type="email" name="email" form="<?= $formId ?>" value="<?= htmlspecialchars($p['EMAIL']) ?>" class="form-control" required maxlength="100">
                                    </td>
                                    <td>
                                        <input type="tel" name="telefono" form="<?= $formId ?>" value="<?= htmlspecialchars($p['TELEFONO'] ?? '') ?>" class="form-control" maxlength="20">
                                    </td>
                                    <td>
                                        <input type="text" name="contacto" form="<?= $formId ?>" value="<?= htmlspecialchars($p['CONTACTO'] ?? '') ?>" class="form-control" maxlength="100">
                                    </td>
                                    <td>
                                        <button type="submit" form="<?= $formId ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Guardar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php foreach ($proveedores as $p): ?>
                    <form id="form-prov-<?= htmlspecialchars($p['ID_PROVEEDOR']) ?>" method="POST" action="proveedores.php<?= $busqueda !== '' ? '?q=' . urlencode($busqueda) : '' ?>" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_proveedor" value="<?= htmlspecialchars($p['ID_PROVEEDOR']) ?>">
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
