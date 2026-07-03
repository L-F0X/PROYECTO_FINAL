<?php
// almacenista/registrar_entrada.php
require_once '../conexion.php';
require_once '../csrf.php';

// Control de acceso
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'almacenista') {
    header("Location: ../index.php");
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Crear tabla de movimientos si no existe (patrón idempotente)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS movimiento_inventario (
        ID_MOVIMIENTO INT AUTO_INCREMENT PRIMARY KEY,
        ID_FICHA_TECNICA INT NOT NULL,
        TIPO_MOVIMIENTO ENUM('Entrada', 'Salida') NOT NULL,
        CANTIDAD INT NOT NULL,
        RESPONSABLE INT NOT NULL,
        DESTINO VARCHAR(255) DEFAULT NULL,
        OBSERVACIONES TEXT DEFAULT NULL,
        FECHA_MOVIMIENTO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ID_FICHA_TECNICA) REFERENCES ficha_tecnica(ID_FICHA_TECNICA),
        FOREIGN KEY (RESPONSABLE) REFERENCES usuario(ID_USUARIO)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log('Error creando tabla movimiento_inventario: ' . $e->getMessage());
}

// Cargar artículos para el dropdown
try {
    $stmtItems = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD FROM ficha_tecnica ORDER BY NOMBRE_ITEM");
    $items = $stmtItems->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando artículos: ' . $e->getMessage());
    $items = [];
}

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $idFicha = intval($_POST['id_ficha_tecnica'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($idFicha <= 0) {
        $mensaje = '✗ Debe seleccionar un artículo del inventario.';
        $tipoMensaje = 'error';
    } elseif ($cantidad <= 0) {
        $mensaje = '✗ La cantidad debe ser mayor a 0.';
        $tipoMensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Insertar movimiento
            $stmtInsert = $pdo->prepare("INSERT INTO movimiento_inventario (ID_FICHA_TECNICA, TIPO_MOVIMIENTO, CANTIDAD, RESPONSABLE, OBSERVACIONES) VALUES (?, 'Entrada', ?, ?, ?)");
            $stmtInsert->execute([$idFicha, $cantidad, $usuarioId, $observaciones]);

            // Actualizar stock sumando
            $stmtUpdate = $pdo->prepare("UPDATE ficha_tecnica SET CANTIDAD = CANTIDAD + ? WHERE ID_FICHA_TECNICA = ?");
            $stmtUpdate->execute([$cantidad, $idFicha]);

            $pdo->commit();

            // Obtener nombre del artículo para el mensaje
            $stmtNombre = $pdo->prepare("SELECT NOMBRE_ITEM FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ?");
            $stmtNombre->execute([$idFicha]);
            $nombreItem = $stmtNombre->fetchColumn();

            $mensaje = '✓ Entrada registrada: +' . $cantidad . ' unidades de "' . htmlspecialchars($nombreItem) . '" añadidas al inventario.';
            $tipoMensaje = 'success';

            // Recargar artículos con stock actualizado
            $stmtItems = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD FROM ficha_tecnica ORDER BY NOMBRE_ITEM");
            $items = $stmtItems->fetchAll();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error al registrar entrada: ' . $e->getMessage());
            $mensaje = '✗ Error al registrar la entrada. Intente nuevamente.';
            $tipoMensaje = 'error';
        }
    }
}

// Cargar últimas entradas registradas
$ultimasEntradas = [];
try {
    $stmtRecientes = $pdo->prepare("SELECT m.*, f.NOMBRE_ITEM, u.NOMBRE, u.APELLIDO 
                                     FROM movimiento_inventario m 
                                     INNER JOIN ficha_tecnica f ON m.ID_FICHA_TECNICA = f.ID_FICHA_TECNICA 
                                     INNER JOIN usuario u ON m.RESPONSABLE = u.ID_USUARIO 
                                     WHERE m.TIPO_MOVIMIENTO = 'Entrada' 
                                     ORDER BY m.FECHA_MOVIMIENTO DESC LIMIT 10");
    $stmtRecientes->execute();
    $ultimasEntradas = $stmtRecientes->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando últimas entradas: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Registrar Entrada</title>
    <link rel="stylesheet" href="../estilos.css">
    <link rel="stylesheet" href="almacenista.css">
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
            <a href="index.php" class="sidebar-link">Vista de Stock</a>
            <a href="registrar_entrada.php" class="sidebar-link active">Registrar Entrada</a>
            <a href="registrar_salida.php" class="sidebar-link">Registrar Salida</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
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
                <a href="index.php" class="alm-back-link">← Volver al Panel</a>
                <h2>Registrar Entrada de Mercancía</h2>
                <p class="dashboard-subtitle">Registra el ingreso de artículos al inventario del almacén.</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alm-alert alm-alert--<?= $tipoMensaje ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <h3>Nueva Entrada</h3>
            <p class="panel-description">Seleccione el artículo, indique la cantidad recibida y registre las observaciones pertinentes.</p>

            <form method="POST" action="registrar_entrada.php" class="alm-form" id="formEntrada">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                <div class="alm-form-grid">
                    <div class="alm-form-group alm-form-group--full">
                        <label for="id_ficha_tecnica" class="alm-form-label">Artículo <span class="required">*</span></label>
                        <select name="id_ficha_tecnica" id="id_ficha_tecnica" class="alm-form-select" required>
                            <option value="">— Seleccione un artículo —</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['ID_FICHA_TECNICA'] ?>"
                                    data-unidad="<?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?>"
                                    data-stock="<?= intval($item['CANTIDAD']) ?>">
                                    <?= htmlspecialchars($item['NOMBRE_ITEM']) ?> — <?= htmlspecialchars($item['CODIGO_UNSPSC_FK'] ?: 'Sin código') ?> (Stock: <?= intval($item['CANTIDAD']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alm-form-group">
                        <label for="cantidad" class="alm-form-label">Cantidad a ingresar <span class="required">*</span></label>
                        <input type="number" name="cantidad" id="cantidad" class="alm-form-input" min="1" required placeholder="Ej: 50">
                        <span class="alm-form-hint" id="hint-unidad">Unidad de medida: —</span>
                    </div>

                    <div class="alm-form-group">
                        <label class="alm-form-label">Stock actual</label>
                        <div class="alm-form-input" style="background: #eef3ee; cursor: default;" id="stock-actual">—</div>
                    </div>

                    <div class="alm-form-group alm-form-group--full">
                        <label for="observaciones" class="alm-form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="alm-form-textarea" placeholder="Detalles adicionales: proveedor, número de factura, condición del material, etc."></textarea>
                    </div>
                </div>

                <div class="alm-form-actions">
                    <button type="submit" class="btn btn-sena">Registrar Entrada</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Últimas entradas registradas -->
        <?php if (!empty($ultimasEntradas)): ?>
        <div class="panel-card" style="margin-top: 22px;">
            <div class="alm-section-header">
                <h3>Últimas Entradas Registradas</h3>
            </div>
            <div class="alm-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Artículo</th>
                            <th>Cantidad</th>
                            <th>Responsable</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasEntradas as $mov): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= htmlspecialchars($mov['FECHA_MOVIMIENTO']) ?></td>
                                <td><?= htmlspecialchars($mov['NOMBRE_ITEM']) ?></td>
                                <td><strong style="color: var(--verde-sena);">+<?= htmlspecialchars($mov['CANTIDAD']) ?></strong></td>
                                <td><?= htmlspecialchars($mov['NOMBRE'] . ' ' . $mov['APELLIDO']) ?></td>
                                <td><?= htmlspecialchars($mov['OBSERVACIONES'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
(function () {
    const select = document.getElementById('id_ficha_tecnica');
    const hintUnidad = document.getElementById('hint-unidad');
    const stockActual = document.getElementById('stock-actual');

    if (select) {
        select.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (this.value) {
                hintUnidad.textContent = 'Unidad de medida: ' + (opt.dataset.unidad || '—');
                stockActual.textContent = opt.dataset.stock + ' unidades';
            } else {
                hintUnidad.textContent = 'Unidad de medida: —';
                stockActual.textContent = '—';
            }
        });
    }

    // Validación antes de enviar
    const form = document.getElementById('formEntrada');
    if (form) {
        form.addEventListener('submit', function (e) {
            const cantidad = parseInt(document.getElementById('cantidad').value, 10);
            if (isNaN(cantidad) || cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser un número mayor a 0.');
                return;
            }
            const btn = form.querySelector('button[type="submit"]');
            btn.textContent = 'Procesando...';
            btn.style.opacity = '0.7';
            btn.disabled = true;
        });
    }
})();
</script>

</body>
</html>
