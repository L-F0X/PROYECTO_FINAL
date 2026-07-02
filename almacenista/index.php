<?php
// almacenista/index.php
require_once '../conexion.php';
require_once '../csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
// Permitimos al almacenista acceder. Si es otro rol, redirigir al index general para que lo enrute correctamente.
if ($rolNombre !== 'almacenista') {
    header("Location: ../index.php");
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Búsqueda y filtrado mock/boceto
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Consulta de ejemplo para mostrar elementos en inventario (usando fichas técnicas como boceto de ítems)
try {
    $sql = "SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD, DESCRIPCION_GENERAL FROM ficha_tecnica WHERE 1=1";
    $params = [];
    if ($busqueda !== '') {
        $sql .= " AND (NOMBRE_ITEM LIKE ? OR CODIGO_UNSPSC_FK LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    $sql .= " ORDER BY ID_FICHA_TECNICA DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $itemsInventario = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error al consultar ítems para almacenista: ' . $e->getMessage());
    $itemsInventario = [];
}

$totalItems = count($itemsInventario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BICERGAM - Almacenista</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén</span></h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Almacenista)</span></div>
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
            <h4>Gestión de Inventario</h4>
            <a href="index.php" class="sidebar-link sidebar-link--primary">Vista de Stock</a>
            <a href="#" class="sidebar-link" onclick="alert('Funcionalidad de Registrar Entrada (Boceto)')">Registrar Entrada</a>
            <a href="#" class="sidebar-link" onclick="alert('Funcionalidad de Registrar Salida (Boceto)')">Registrar Salida</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <!-- Respetando las rutas relativas a las carpetas de instructor y coordinador -->
            <a href="../instructor/index.php" class="sidebar-link">Panel Instructor</a>
            <a href="../coordinador/index.php" class="sidebar-link">Panel Coordinador</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Panel de Control - Almacenista</h2>
                <p class="dashboard-subtitle">Visualiza y controla las existencias, entradas y salidas de elementos de formación.</p>
            </div>
        </div>

        <div class="panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h3>Inventario de Artículos (Total: <?= $totalItems ?>)</h3>
                <div class="actions-bar" style="border: none; padding: 0; margin: 0; display: flex; gap: 10px;">
                    <button class="btn btn-sena" onclick="alert('Nueva Entrada de Mercancía (Boceto)')">+ Nueva Entrada</button>
                    <button class="btn btn-secondary" onclick="alert('Nueva Salida / Despacho (Boceto)')">- Registrar Salida</button>
                </div>
            </div>

            <!-- Formulario de búsqueda y filtrado (Boceto) -->
            <form method="GET" action="index.php" id="form-busqueda" style="margin-bottom: 20px;">
                <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                        <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar artículo</label>
                        <input type="text" id="q" name="q" class="search-input" placeholder="Buscar por nombre o código UNSPSC..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
                    </div>
                    <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                        <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar Estado Stock</label>
                        <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">— Todos —</option>
                            <option value="disponible">Con Stock</option>
                            <option value="agotado">Agotados</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
                    <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                        <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID Item</th>
                        <th>Nombre del Artículo</th>
                        <th>Código UNSPSC</th>
                        <th>Unidad de Medida</th>
                        <th>Stock Actual</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemsInventario)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No hay artículos registrados en el inventario.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itemsInventario as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['ID_FICHA_TECNICA']) ?></td>
                                <td><?= htmlspecialchars($item['NOMBRE_ITEM']) ?></td>
                                <td><?= htmlspecialchars($item['CODIGO_UNSPSC_FK'] ?: 'Sin Asignar') ?></td>
                                <td><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
                                <td><strong><?= htmlspecialchars($item['CANTIDAD']) ?></strong></td>
                                <td>
                                    <button class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;" onclick="alert('Detalles del ítem: <?= htmlspecialchars($item['NOMBRE_ITEM']) ?>')">Ver Kárdex</button>
                                </td>
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
        const select = document.getElementById('estado');
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
