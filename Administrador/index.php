<?php
// Administrador/index.php
require_once '../conexion.php';
require_once '../csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
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

// Crear tabla de auditoría si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria_actividad (
        ID_AUDITORIA INT AUTO_INCREMENT PRIMARY KEY,
        ID_USUARIO INT NOT NULL,
        ACCION VARCHAR(255) NOT NULL,
        DETALLE TEXT DEFAULT NULL,
        FECHA TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('Error creando la tabla de auditoría: ' . $e->getMessage());
}

// Cambiar estado de usuario (Activar/Desactivar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_estado') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    
    $idUsuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $nuevoEstado = isset($_POST['nuevo_estado']) ? trim($_POST['nuevo_estado']) : '';
    
    if ($idUsuario > 0 && in_array($nuevoEstado, ['Activo', 'Inactivo'])) {
        try {
            // Prevenir desactivarse a sí mismo
            if ($idUsuario === intval($_SESSION['usuario_id'])) {
                header("Location: index.php?msg=self_deactivate_error&type=error");
                exit;
            }
            
            // Obtener datos del usuario para el log
            $stmtUser = $pdo->prepare("SELECT NOMBRE, APELLIDO FROM usuario WHERE ID_USUARIO = ?");
            $stmtUser->execute([$idUsuario]);
            $usrData = $stmtUser->fetch();
            
            $stmtUpdate = $pdo->prepare("UPDATE usuario SET ESTADO = ? WHERE ID_USUARIO = ?");
            $stmtUpdate->execute([$nuevoEstado, $idUsuario]);
            
            // Registrar actividad
            $stmtLog = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, ?, ?)");
            $stmtLog->execute([
                $_SESSION['usuario_id'],
                'Cambio de Estado',
                "Cambiado estado de usuario: " . $usrData['NOMBRE'] . " " . $usrData['APELLIDO'] . " (ID: $idUsuario) a " . $nuevoEstado
            ]);
            
            header("Location: index.php?msg=status_updated");
            exit;
        } catch (Exception $e) {
            error_log('Error al cambiar estado de usuario: ' . $e->getMessage());
            header("Location: index.php?msg=error&type=error");
            exit;
        }
    }
}

// Búsqueda y filtrado de usuarios
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroRol = isset($_GET['rol']) ? trim($_GET['rol']) : '';

try {
    $sql = "SELECT u.*, r.NOMBRE_ROL 
            FROM usuario u 
            INNER JOIN rol r ON u.ID_ROL = r.ID_ROL 
            WHERE 1=1";
    $params = [];
    
    if ($busqueda !== '') {
        $sql .= " AND (u.NOMBRE LIKE ? OR u.APELLIDO LIKE ? OR u.EMAIL LIKE ? OR u.DOCUMENTO LIKE ?)";
        $term = "%$busqueda%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    
    if ($filtroRol !== '') {
        $sql .= " AND u.ID_ROL = ?";
        $params[] = intval($filtroRol);
    }
    
    $sql .= " ORDER BY u.ID_USUARIO DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando usuarios: ' . $e->getMessage());
    $usuarios = [];
}

// Cargar logs de actividad
try {
    $stmtLogs = $pdo->query("SELECT a.*, u.NOMBRE, u.APELLIDO 
                             FROM auditoria_actividad a 
                             INNER JOIN usuario u ON a.ID_USUARIO = u.ID_USUARIO 
                             ORDER BY a.FECHA DESC LIMIT 50");
    $logsActividad = $stmtLogs->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando log de actividad: ' . $e->getMessage());
    $logsActividad = [];
}

// Cargar roles para filtros/formularios
$roles = [];
try {
    $roles = $pdo->query("SELECT * FROM rol ORDER BY ID_ROL")->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando roles: ' . $e->getMessage());
}

// Métricas resumen para el panel principal
$totalUsuarios = 0;
$totalActivos = 0;
$totalInactivos = 0;
$totalAccionesHoy = 0;
try {
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM usuario");
    $totalUsuarios = intval($stmtTotal->fetchColumn());

    $stmtActivos = $pdo->query("SELECT COUNT(*) FROM usuario WHERE ESTADO = 'Activo'");
    $totalActivos = intval($stmtActivos->fetchColumn());

    $totalInactivos = $totalUsuarios - $totalActivos;

    $stmtHoy = $pdo->query("SELECT COUNT(*) FROM auditoria_actividad WHERE DATE(FECHA) = CURDATE()");
    $totalAccionesHoy = intval($stmtHoy->fetchColumn());
} catch (Exception $e) {
    error_log('Error cargando métricas de administrador: ' . $e->getMessage());
}

$msg = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? 'success';
$messageText = '';
if ($msg === 'status_updated') {
    $messageText = '✓ Estado del usuario actualizado correctamente.';
} elseif ($msg === 'self_deactivate_error') {
    $messageText = '✗ No puedes desactivar tu propia cuenta de administrador.';
} elseif ($msg === 'user_created') {
    $messageText = '✓ Usuario creado correctamente.';
} elseif ($msg === 'user_updated') {
    $messageText = '✓ Usuario editado correctamente.';
} elseif ($msg === 'error') {
    $messageText = '✗ Ocurrió un error al procesar la solicitud.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Administrador</title>
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
            <!-- Respetando rutas del index.php hacia los otros roles -->
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
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Administración General</h2>
                <p class="dashboard-subtitle">Administra cuentas de usuario, asigna roles, activa/desactiva cuentas y supervisa la actividad del sistema.</p>
            </div>
        </div>

        <?php if ($messageText): ?>
            <div class="profile-alert <?= $msgType ?>" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; <?= $msgType === 'success' ? 'background: #eff8f1; color: #270; border: 1px solid #d4ebd5;' : 'background: #fdf2f2; color: #de3a3a; border: 1px solid #fde2e2;' ?>">
                <?= htmlspecialchars($messageText) ?>
            </div>
        <?php endif; ?>

        <!-- Resumen del sistema -->
        <div class="stats-container">
            <div class="stat-card" style="border-left-color: #7c3aed;">
                <span class="stat-label">Usuarios Totales</span>
                <strong class="stat-value"><?= $totalUsuarios ?></strong>
                <p class="stat-hint">Registrados en el sistema</p>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <span class="stat-label">Cuentas Activas</span>
                <strong class="stat-value" style="color: #10b981;"><?= $totalActivos ?></strong>
                <p class="stat-hint">Pueden iniciar sesión</p>
            </div>
            <div class="stat-card" style="border-left-color: #ef4444;">
                <span class="stat-label">Cuentas Inactivas</span>
                <strong class="stat-value" style="color: #ef4444;"><?= $totalInactivos ?></strong>
                <p class="stat-hint">Acceso deshabilitado</p>
            </div>
            <div class="stat-card" style="border-left-color: #0284c7;">
                <span class="stat-label">Actividad de Hoy</span>
                <strong class="stat-value" style="color: #0284c7;"><?= $totalAccionesHoy ?></strong>
                <p class="stat-hint">Acciones registradas hoy</p>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="panel-card" style="margin-bottom: 30px;">
            <h3>Enlaces y Acciones Rápidas</h3>
            <p class="dashboard-subtitle">Navega rápidamente a las principales secciones de administración.</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
                <a href="crear_usuario.php" class="btn btn-sena">+ Crear Nuevo Usuario</a>
                <a href="../coordinador/index.php" class="btn btn-secondary">Panel Coordinador</a>
                <a href="../almacenista/index.php" class="btn btn-secondary">Panel Almacenista</a>
                <a href="../instructor/index.php" class="btn btn-secondary">Panel Instructor</a>
            </div>
        </div>

        <!-- Sección de Gestión de Usuarios -->
        <div class="panel-card" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h3>Gestión de Usuarios (Total: <?= count($usuarios) ?>)</h3>
                <a href="crear_usuario.php" class="btn btn-sena">+ Crear Nuevo Usuario</a>
            </div>

            <!-- Formulario de búsqueda -->
            <form method="GET" action="index.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar usuario</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre, documento o correo..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="rol" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por Rol</label>
                        <select name="rol" id="rol" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['ID_ROL'] ?>" <?= $filtroRol == $r['ID_ROL'] ? 'selected' : '' ?>><?= htmlspecialchars($r['NOMBRE_ROL']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <?php if ($busqueda !== '' || $filtroRol !== ''): ?>
                        <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Nombre Completo</th>
                        <th>Correo Electrónico</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No se encontraron usuarios.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usr): ?>
                            <tr>
                                <td><?= htmlspecialchars($usr['DOCUMENTO']) ?></td>
                                <td><?= htmlspecialchars($usr['NOMBRE'] . ' ' . $usr['APELLIDO']) ?></td>
                                <td><?= htmlspecialchars($usr['EMAIL']) ?></td>
                                <td><span class="role-badge"><?= htmlspecialchars($usr['NOMBRE_ROL']) ?></span></td>
                                <td>
                                    <strong style="color: <?= $usr['ESTADO'] === 'Activo' ? 'var(--verde-sena)' : 'var(--alerta-rojo)' ?>;">
                                        <?= htmlspecialchars($usr['ESTADO']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="editar_usuario.php?id=<?= $usr['ID_USUARIO'] ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Editar</a>
                                        
                                        <!-- Formulario para activar/desactivar -->
                                        <form action="index.php" method="POST" style="margin: 0; display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                            <input type="hidden" name="accion" value="toggle_estado">
                                            <input type="hidden" name="id_usuario" value="<?= $usr['ID_USUARIO'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $usr['ESTADO'] === 'Activo' ? 'Inactivo' : 'Activo' ?>">
                                            
                                            <?php if ($usr['ID_USUARIO'] === intval($_SESSION['usuario_id'])): ?>
                                                <button type="button" class="btn" style="padding: 5px 10px; font-size: 12px; border: none; background: #ccc; color: #666; cursor: not-allowed;" title="No puedes desactivarte a ti mismo" disabled>Desactivar</button>
                                            <?php elseif ($usr['ESTADO'] === 'Activo'): ?>
                                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--alerta-rojo); color: white;">Desactivar</button>
                                            <?php else: ?>
                                                <button type="submit" class="btn" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--verde-sena); color: white;">Activar</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sección de Actividad de Usuarios -->
        <div class="panel-card">
            <h3>Actividad Reciente en el Sistema</h3>
            <p class="dashboard-subtitle">Historial de las últimas operaciones registradas por los administradores y coordinadores.</p>
            
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logsActividad)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No hay registros de actividad recientes.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logsActividad as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= htmlspecialchars($log['FECHA']) ?></td>
                                <td><strong><?= htmlspecialchars($log['NOMBRE'] . ' ' . $log['APELLIDO']) ?></strong></td>
                                <td><span style="background: #e1e8ed; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;"><?= htmlspecialchars($log['ACCION']) ?></span></td>
                                <td><?= htmlspecialchars($log['DETALLE']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="../javascript.js"></script>
<script>
    (function () {
        const input = document.getElementById('q');
        const select = document.getElementById('rol');
        const form = document.getElementById('form-busqueda');
        if (input && select && form) {
            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 350);
            });
            select.addEventListener('change', () => form.submit());
        }
    })();
</script>

</body>
</html>
