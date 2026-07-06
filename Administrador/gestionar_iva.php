<?php
// Administrador/gestionar_iva.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'administrador') {
    header("Location: ../index.php");
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador');
$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $accion = $_POST['accion'] ?? '';
    $porcentaje = isset($_POST['porcentaje']) ? trim($_POST['porcentaje']) : '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!is_numeric($porcentaje) || $porcentaje < 0 || $porcentaje > 100) {
        $error = 'El porcentaje debe ser un número entre 0 y 100.';
    } elseif ($descripcion === '') {
        $error = 'La descripción es obligatoria.';
    } else {
        try {
            if ($accion === 'crear') {
                $stmt = $pdo->prepare('INSERT INTO iva (PORCENTAJE, DESCRIPCION) VALUES (?, ?)');
                $stmt->execute([$porcentaje, $descripcion]);
                $mensaje = '✓ Tasa de IVA creada correctamente.';
            } elseif ($accion === 'editar') {
                $idIva = intval($_POST['id_iva'] ?? 0);
                $stmt = $pdo->prepare('UPDATE iva SET PORCENTAJE = ?, DESCRIPCION = ? WHERE ID_IVA = ?');
                $stmt->execute([$porcentaje, $descripcion, $idIva]);
                $mensaje = '✓ Tasa de IVA actualizada correctamente.';
            }
        } catch (Exception $e) {
            error_log('Error gestionando IVA: ' . $e->getMessage());
            $error = 'No se pudo guardar la tasa de IVA.';
        }
    }
}

$tasas = $pdo->query('SELECT * FROM iva ORDER BY PORCENTAJE')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Gestionar IVA</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Administrador</span></h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Administrador)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?></a>
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
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
            <a href="index.php" class="sidebar-link">Gestión Usuarios</a>
            <a href="importar_unspsc.php" class="sidebar-link">Importar UNSPSC</a>
            <a href="gestionar_iva.php" class="sidebar-link sidebar-link--primary active">Gestionar IVA</a>
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
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
            <h2>Gestionar Tasas de IVA</h2>
            <p>Estas tasas se pueden seleccionar al crear un ítem en la matriz de un lote.</p>

            <?php if ($error): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin: 20px 0; font-weight: 500; background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;">
                    ✗ <?= htmlspecialchars($error) ?>
                </div>
            <?php elseif ($mensaje): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin: 20px 0; font-weight: 500; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <div class="panel-card" style="margin-top: 20px;">
                <h3>Nueva Tasa de IVA</h3>
                <form method="POST" action="gestionar_iva.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="porcentaje">Porcentaje (%) *</label>
                            <input type="number" step="0.01" min="0" max="100" id="porcentaje" name="porcentaje" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción *</label>
                            <input type="text" id="descripcion" name="descripcion" class="form-control" placeholder="Ej: IVA General" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sena" style="margin-top: 15px;">Crear Tasa</button>
                </form>
            </div>

            <div class="panel-card" style="margin-top: 20px;">
                <h3>Tasas Registradas (<?= count($tasas) ?>)</h3>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Porcentaje</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasas as $t): ?>
                            <?php $formId = 'form-iva-' . $t['ID_IVA']; ?>
                            <tr>
                                <td><?= htmlspecialchars($t['ID_IVA']) ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="100" name="porcentaje" form="<?= $formId ?>" value="<?= htmlspecialchars($t['PORCENTAJE']) ?>" class="form-control" style="width: 100px;" required>
                                </td>
                                <td>
                                    <input type="text" name="descripcion" form="<?= $formId ?>" value="<?= htmlspecialchars($t['DESCRIPCION']) ?>" class="form-control" required>
                                </td>
                                <td>
                                    <button type="submit" form="<?= $formId ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Guardar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php foreach ($tasas as $t): ?>
                    <form id="form-iva-<?= htmlspecialchars($t['ID_IVA']) ?>" method="POST" action="gestionar_iva.php" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_iva" value="<?= htmlspecialchars($t['ID_IVA']) ?>">
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<script src="../js/apartados.js"></script>
</body>
</html>
