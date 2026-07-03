<?php
// almacenista/registrar_salida.php
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

// Crear tabla de movimientos si no existe
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

// Cargar artículos con stock
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
    $destino = trim($_POST['destino'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($idFicha <= 0) {
        $mensaje = '✗ Debe seleccionar un artículo del inventario.';
        $tipoMensaje = 'error';
    } elseif ($cantidad <= 0) {
        $mensaje = '✗ La cantidad debe ser mayor a 0.';
        $tipoMensaje = 'error';
    } elseif ($destino === '') {
        $mensaje = '✗ Debe indicar el destino o motivo de la salida.';
        $tipoMensaje = 'error';
    } else {
        try {
            // Verificar stock disponible
            $stmtStock = $pdo->prepare("SELECT NOMBRE_ITEM, CANTIDAD FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ?");
            $stmtStock->execute([$idFicha]);
            $itemData = $stmtStock->fetch();

            if (!$itemData) {
                $mensaje = '✗ El artículo seleccionado no existe.';
                $tipoMensaje = 'error';
            } elseif ($itemData['CANTIDAD'] < $cantidad) {
                $mensaje = '✗ Stock insuficiente. Solo hay ' . intval($itemData['CANTIDAD']) . ' unidades disponibles de "' . htmlspecialchars($itemData['NOMBRE_ITEM']) . '".';
                $tipoMensaje = 'error';
            } else {
                $pdo->beginTransaction();

                // Insertar movimiento de salida
                $stmtInsert = $pdo->prepare("INSERT INTO movimiento_inventario (ID_FICHA_TECNICA, TIPO_MOVIMIENTO, CANTIDAD, RESPONSABLE, DESTINO, OBSERVACIONES) VALUES (?, 'Salida', ?, ?, ?, ?)");
                $stmtInsert->execute([$idFicha, $cantidad, $usuarioId, $destino, $observaciones]);

                // Actualizar stock restando
                $stmtUpdate = $pdo->prepare("UPDATE ficha_tecnica SET CANTIDAD = CANTIDAD - ? WHERE ID_FICHA_TECNICA = ?");
                $stmtUpdate->execute([$cantidad, $idFicha]);

                $pdo->commit();

                $mensaje = '✓ Salida registrada: -' . $cantidad . ' unidades de "' . htmlspecialchars($itemData['NOMBRE_ITEM']) . '" despachadas a "' . htmlspecialchars($destino) . '".';
                $tipoMensaje = 'success';

                // Recargar artículos con stock actualizado
                $stmtItems = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD FROM ficha_tecnica ORDER BY NOMBRE_ITEM");
                $items = $stmtItems->fetchAll();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error al registrar salida: ' . $e->getMessage());
            $mensaje = '✗ Error al registrar la salida. Intente nuevamente.';
            $tipoMensaje = 'error';
        }
    }
}

// Cargar últimas salidas registradas
$ultimasSalidas = [];
try {
    $stmtRecientes = $pdo->prepare("SELECT m.*, f.NOMBRE_ITEM, u.NOMBRE, u.APELLIDO 
                                     FROM movimiento_inventario m 
                                     INNER JOIN ficha_tecnica f ON m.ID_FICHA_TECNICA = f.ID_FICHA_TECNICA 
                                     INNER JOIN usuario u ON m.RESPONSABLE = u.ID_USUARIO 
                                     WHERE m.TIPO_MOVIMIENTO = 'Salida' 
                                     ORDER BY m.FECHA_MOVIMIENTO DESC LIMIT 10");
    $stmtRecientes->execute();
    $ultimasSalidas = $stmtRecientes->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando últimas salidas: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BICERGAM - Registrar Salida</title>
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
            <a href="registrar_entrada.php" class="sidebar-link">Registrar Entrada</a>
            <a href="registrar_salida.php" class="sidebar-link active">Registrar Salida</a>
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
                <h2>Registrar Salida / Despacho</h2>
                <p class="dashboard-subtitle">Registra el despacho o salida de artículos del inventario hacia un destino.</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alm-alert alm-alert--<?= $tipoMensaje ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <h3>Nueva Salida</h3>
            <p class="panel-description">Seleccione el artículo, indique la cantidad a despachar, el destino y las observaciones.</p>

            <form method="POST" action="registrar_salida.php" class="alm-form" id="formSalida">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                <div class="alm-form-grid">
                    <div class="alm-form-group alm-form-group--full">
                        <label for="id_ficha_tecnica" class="alm-form-label">Artículo <span class="required">*</span></label>
                        <select name="id_ficha_tecnica" id="id_ficha_tecnica" class="alm-form-select" required>
                            <option value="">— Seleccione un artículo —</option>
                            <?php foreach ($items as $item): ?>
                                <?php $stockDisponible = intval($item['CANTIDAD']); ?>
                                <option value="<?= $item['ID_FICHA_TECNICA'] ?>"
                                    data-unidad="<?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?>"
                                    data-stock="<?= $stockDisponible ?>"
                                    <?= $stockDisponible <= 0 ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($item['NOMBRE_ITEM']) ?> — Stock: <?= $stockDisponible ?> <?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?><?= $stockDisponible <= 0 ? ' (AGOTADO)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alm-form-group">
                        <label for="cantidad" class="alm-form-label">Cantidad a despachar <span class="required">*</span></label>
                        <input type="number" name="cantidad" id="cantidad" class="alm-form-input" min="1" required placeholder="Ej: 10">
                        <span class="alm-form-hint" id="hint-disponible">Disponible: —</span>
                    </div>

                    <div class="alm-form-group">
                        <label for="destino" class="alm-form-label">Destino / Motivo <span class="required">*</span></label>
                        <input type="text" name="destino" id="destino" class="alm-form-input" required placeholder="Ej: Taller de electrónica, Ambiente 302">
                    </div>

                    <div class="alm-form-group alm-form-group--full">
                        <label for="observaciones" class="alm-form-label">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" class="alm-form-textarea" placeholder="Detalles adicionales: solicitante, número de orden, condición, etc."></textarea>
                    </div>
                </div>

                <div class="alm-form-actions">
                    <button type="submit" class="btn btn-sena">Registrar Salida</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Últimas salidas registradas -->
        <?php if (!empty($ultimasSalidas)): ?>
        <div class="panel-card" style="margin-top: 22px;">
            <div class="alm-section-header">
                <h3>Últimas Salidas Registradas</h3>
            </div>
            <div class="alm-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Artículo</th>
                            <th>Cantidad</th>
                            <th>Destino</th>
                            <th>Responsable</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasSalidas as $mov): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= htmlspecialchars($mov['FECHA_MOVIMIENTO']) ?></td>
                                <td><?= htmlspecialchars($mov['NOMBRE_ITEM']) ?></td>
                                <td><strong style="color: var(--alerta-rojo);">-<?= htmlspecialchars($mov['CANTIDAD']) ?></strong></td>
                                <td><?= htmlspecialchars($mov['DESTINO'] ?: '—') ?></td>
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
    const hintDisponible = document.getElementById('hint-disponible');
    const cantidadInput = document.getElementById('cantidad');

    if (select) {
        select.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (this.value) {
                const stock = parseInt(opt.dataset.stock, 10) || 0;
                hintDisponible.textContent = 'Disponible: ' + stock + ' ' + (opt.dataset.unidad || 'unidades');
                cantidadInput.max = stock;
            } else {
                hintDisponible.textContent = 'Disponible: —';
                cantidadInput.removeAttribute('max');
            }
        });
    }

    // Validación antes de enviar
    const form = document.getElementById('formSalida');
    if (form) {
        form.addEventListener('submit', function (e) {
            const cantidad = parseInt(cantidadInput.value, 10);
            const opt = select.options[select.selectedIndex];
            const stock = parseInt(opt.dataset.stock, 10) || 0;

            if (isNaN(cantidad) || cantidad <= 0) {
                e.preventDefault();
                alert('La cantidad debe ser un número mayor a 0.');
                return;
            }

            if (cantidad > stock) {
                e.preventDefault();
                alert('Stock insuficiente. Solo hay ' + stock + ' unidades disponibles.');
                return;
            }

            const destino = document.getElementById('destino').value.trim();
            if (destino === '') {
                e.preventDefault();
                alert('Debe indicar el destino o motivo de la salida.');
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
