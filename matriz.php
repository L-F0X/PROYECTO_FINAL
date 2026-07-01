<?php
// matriz.php
require_once 'conexion.php';
require_once 'csrf.php';

function build_need_label(array $need): string {
    $segments = [];

    if (!empty($need['ID_NECESIDAD'])) {
        $segments[] = 'Necesidad #' . intval($need['ID_NECESIDAD']);
    }

    if (!empty($need['ID_MATRIZ'])) {
        $segments[] = 'Matriz #' . intval($need['ID_MATRIZ']);
    }

    $quantityFields = [
        'CANTIDAD_REGULAR' => 'Regular',
        'CANTIDAD_NESECIDAD' => 'Necesidad',
        'CANTIDAD_CAMPESINA_COMPLEMENTARIA' => 'Campesina complementaria',
        'CANTIDAD_CAMPESINA_TITULADA' => 'Campesina titulada',
        'CANTIDAD_VULNERABLE' => 'Vulnerable',
        'CANTIDAD_MEDIA_TECNICA' => 'Media técnica',
        'CANTIDAD_FIC' => 'FIC',
        'CANTIDAD_ECONOMIA_POPULAR' => 'Economía popular',
        'CANTIDAD_ENI' => 'ENI',
        'CANTIDAD_FC_CAMPESINA' => 'FC campesina',
    ];

    foreach ($quantityFields as $field => $label) {
        $value = isset($need[$field]) ? intval($need[$field]) : 0;
        if ($value > 0) {
            $segments[] = $label . ': ' . $value;
        }
    }

    if (empty($segments)) {
        return 'Sin detalle de necesidad';
    }

    return implode(' · ', $segments);
}

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Capturar el ID del lote para ver sus ítems específicos
$id_lote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;

// Preserve coordinator context when redirecting
$indexFrom = (in_array(strtolower(trim($_SESSION['rol_nombre'] ?? '')), ['coordinador','coordinacion'])) ? '?from=coordinador' : '';
if ($id_lote === 0) {
    header("Location: index.php" . $indexFrom);
    exit;
}

// Obtener información del lote actual
$stmtLote = $pdo->prepare("SELECT LOTE_NOMBRE FROM lote_requerimiento WHERE ID_LOTE = ?");
$stmtLote->execute([$id_lote]);
$loteInfo = $stmtLote->fetch();

// Cargar lista de instructores para el select de apoyo
$instructors = [];
try {
    $stmtRole = $pdo->query("SELECT ID_ROL FROM rol WHERE LOWER(NOMBRE_ROL) = 'instructor' LIMIT 1");
    $roleId = $stmtRole->fetchColumn();
    if ($roleId) {
        $stmtIns = $pdo->prepare("SELECT ID_USUARIO, NOMBRE, APELLIDO FROM usuario WHERE ID_ROL = ? ORDER BY NOMBRE, APELLIDO");
        $stmtIns->execute([$roleId]);
        $instructors = $stmtIns->fetchAll();
    }
} catch (Exception $e) {
    error_log('Error cargando instructores: ' . $e->getMessage());
}

// Cargar necesidades / estrategias académicas para tomar la cantidad requerida desde la tabla de necesidades
$needs = [];
try {
    $stmtNeeds = $pdo->query("SELECT ID_NECESIDAD, ID_MATRIZ, CANTIDAD_REGULAR, CANTIDAD_NESECIDAD, CANTIDAD_CAMPESINA_COMPLEMENTARIA, CANTIDAD_CAMPESINA_TITULADA, CANTIDAD_VULNERABLE, CANTIDAD_MEDIA_TECNICA, CANTIDAD_FIC, CANTIDAD_ECONOMIA_POPULAR, CANTIDAD_ENI, CANTIDAD_FC_CAMPESINA FROM necesidad ORDER BY ID_NECESIDAD");
    $needRows = $stmtNeeds->fetchAll();
    foreach ($needRows as $needRow) {
        $needQty = max(1, intval($needRow['CANTIDAD_REGULAR'] ?? 0));
        if ($needQty <= 0) {
            $needQty = max(1, intval($needRow['CANTIDAD_NESECIDAD'] ?? 0));
        }
        $needRow['LABEL'] = build_need_label($needRow);
        $needRow['CANTIDAD_BASE'] = $needQty;
        $needs[] = $needRow;
    }
} catch (Exception $e) {
    error_log('Error cargando necesidades: ' . $e->getMessage());
}

// Procesar inserción de un nuevo ítem si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $id_unspsc = isset($_POST['id_codigo_unspsc']) && $_POST['id_codigo_unspsc'] !== '' ? intval($_POST['id_codigo_unspsc']) : 0;
    $id_iva = 0; // lo decide el proveedor
    $id_necesidad = isset($_POST['id_necesidad']) && $_POST['id_necesidad'] !== '' ? intval($_POST['id_necesidad']) : 0;
    $descripcion = trim($_POST['descripcion_bien']);
    $cantidad = 1;
    if ($id_necesidad > 0) {
        try {
            $stmtNeed = $pdo->prepare("SELECT CANTIDAD_REGULAR, CANTIDAD_NESECIDAD FROM necesidad WHERE ID_NECESIDAD = ? LIMIT 1");
            $stmtNeed->execute([$id_necesidad]);
            $needData = $stmtNeed->fetch();
            if ($needData) {
                $cantidad = max(1, intval($needData['CANTIDAD_REGULAR'] ?? 0));
                if ($cantidad <= 0) {
                    $cantidad = max(1, intval($needData['CANTIDAD_NESECIDAD'] ?? 0));
                }
            }
        } catch (Exception $e) {
            error_log('Error consultando la necesidad seleccionada: ' . $e->getMessage());
        }
    }
    $cantidad = max(1, intval($_POST['cantidad_regular'] ?? $cantidad));
    $ficha = trim($_POST['ficha_tecnica'] ?? '');
    $instructor_apoyo = isset($_POST['instructor_apoyo']) && $_POST['instructor_apoyo'] !== '' ? intval($_POST['instructor_apoyo']) : null;

    // Acción del botón: guardar borrador o enviar solicitud
    $submitAction = $_POST['submit_action'] ?? 'guardar';
    $estadoItem = $submitAction === 'enviar' ? 'Pendiente' : 'Borrador';

    // Si no se proporciona un ID UNSPSC, usar/crear un registro 'SIN_ASIGNAR' en codigo_unspsc para respetar la FK
    if ($id_unspsc === 0) {
        try {
            $stmtCu = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ? LIMIT 1");
            $stmtCu->execute(['SIN_ASIGNAR']);
            $found = $stmtCu->fetchColumn();
            if ($found) {
                $id_unspsc = intval($found);
            } else {
                $pdo->prepare("INSERT INTO codigo_unspsc (SEGMENTO, FAMILIA, CLASE, CODIGO_UNSPSC) VALUES (?,?,?,?)")->execute(['','', '', 'SIN_ASIGNAR']);
                $id_unspsc = $pdo->lastInsertId();
            }
        } catch (Exception $e) {
            error_log('Error al asegurar codigo_unspsc SIN_ASIGNAR: ' . $e->getMessage());
            $id_unspsc = 0; // si algo falla, dejar 0 y la inserción fallará por FK evitando datos inconsistentes
        }
    }

    $sqlInsert = "INSERT INTO matriz_item (ID_LOTE, ID_NECESIDAD, ID_CODIGO_UNSPSC, ID_IVA, DESCRIPCION_BIEN, CANTIDAD_REGULAR, FICHA_TECNICA, ESTADO_ITEM, INSTRUCTOR_APOYO) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    try {
        $pdo->prepare($sqlInsert)->execute([$id_lote, $id_necesidad > 0 ? $id_necesidad : null, $id_unspsc, $id_iva, $descripcion, $cantidad, $ficha, $estadoItem, $instructor_apoyo]);

        // Si la acción fue enviar, marcar el lote como Enviado para revisión del coordinador
        if ($submitAction === 'enviar') {
            $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Enviado' WHERE ID_LOTE = ?")->execute([$id_lote]);
        }

        header("Location: matriz.php?lote=" . $id_lote);
        exit;
    } catch (\PDOException $e) {
        error_log('Insert matriz item error: ' . $e->getMessage());
        die('Error al agregar el ítem. Contacte al administrador.');
    }
}

// Consultar los ítems asociados a este lote
$stmtItems = $pdo->prepare("SELECT m.*, c.CODIGO_UNSPSC, i.PORCENTAJE, ua.NOMBRE AS APOYO_NOMBRE, ua.APELLIDO AS APOYO_APELLIDO, n.ID_MATRIZ AS NECESIDAD_MATRIZ, n.CANTIDAD_REGULAR AS NECESIDAD_BASE, n.CANTIDAD_NESECIDAD, n.CANTIDAD_CAMPESINA_COMPLEMENTARIA, n.CANTIDAD_CAMPESINA_TITULADA, n.CANTIDAD_VULNERABLE, n.CANTIDAD_MEDIA_TECNICA, n.CANTIDAD_FIC, n.CANTIDAD_ECONOMIA_POPULAR, n.CANTIDAD_ENI, n.CANTIDAD_FC_CAMPESINA
                            FROM matriz_item m 
                            LEFT JOIN codigo_unspsc c ON m.ID_CODIGO_UNSPSC = c.ID_CODIGO
                            LEFT JOIN iva i ON m.ID_IVA = i.ID_IVA
                            LEFT JOIN usuario ua ON m.INSTRUCTOR_APOYO = ua.ID_USUARIO
                            LEFT JOIN necesidad n ON m.ID_NECESIDAD = n.ID_NECESIDAD
                            WHERE m.ID_LOTE = ?");
$stmtItems->execute([$id_lote]);
$items = $stmtItems->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BICERGAM - Matriz de Ítems</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header>
    <h1>BICERGAM | <span>SENA</span></h1>
    <div style="text-align: right; color: white;">
        Usuario: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> | 
        <a href="index.php<?= $indexFrom ?>" style="color: var(--verde-sena); text-decoration: none; font-weight: bold; margin-right: 15px;">← Volver a Lotes</a>
        <a href="logout.php" style="color: var(--alerta-rojo); text-decoration: none; font-weight: bold;">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in">
    <h2>Componentes del Lote: <span style="color: var(--verde-sena);"><?= htmlspecialchars($loteInfo['LOTE_NOMBRE'] ?? 'Desconocido') ?></span></h2>
    
    <div style="background: var(--gris-claro); padding: 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid var(--verde-sena);">
        <h3>Añadir Material / Bien al Lote</h3>
        <form action="matriz.php?lote=<?= htmlspecialchars($id_lote) ?>" method="POST" id="formItem">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="id_codigo_unspsc">ID Código UNSPSC (opcional):</label>
                    <input type="number" id="id_codigo_unspsc" name="id_codigo_unspsc" class="form-control" placeholder="Ej: 635188316" />
                </div>
                <input type="hidden" id="id_iva" name="id_iva" value="0">
            </div>

            <div class="form-group">
                <label for="id_necesidad">Necesidad / Estrategia Académica:</label>
                <select id="id_necesidad" name="id_necesidad" class="form-control" required>
                    <option value="">-- Seleccione una necesidad --</option>
                    <?php foreach ($needs as $need): ?>
                        <option value="<?= (int) $need['ID_NECESIDAD'] ?>" data-cantidad="<?= (int) $need['CANTIDAD_BASE'] ?>" data-label="<?= htmlspecialchars($need['LABEL']) ?>">
                            <?= htmlspecialchars($need['LABEL']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="need-summary" style="margin-top:8px; color:#166534; font-weight:600;">Seleccione una necesidad para ver el detalle académico.</div>
                <small style="display:block; margin-top:6px; color:#666;">La cantidad del material se tomará de la necesidad seleccionada en la tabla de necesidades.</small>
            </div>

            <div class="form-group">
                <label for="descripcion_bien">Descripción Detallada del Bien / Ficha técnica breve:</label>
                <textarea id="descripcion_bien" name="descripcion_bien" class="form-control" rows="2" required></textarea>
            </div>

            <div class="form-group" style="width: 32%;">
                <label for="cantidad_regular">Cantidad Requerida:</label>
                <input type="number" id="cantidad_regular" name="cantidad_regular" class="form-control" min="1" value="1" required readonly>
            </div>

            <div class="form-group">
                <label for="instructor_apoyo">Instructor de Apoyo (opcional):</label>
                <select id="instructor_apoyo" name="instructor_apoyo" class="form-control">
                    <option value="">-- Ninguno --</option>
                    <?php foreach($instructors as $ins): ?>
                        <option value="<?= htmlspecialchars($ins['ID_USUARIO']) ?>"><?= htmlspecialchars($ins['NOMBRE'] . ' ' . $ins['APELLIDO']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ficha_tecnica">Ficha Técnica (detalles para el coordinador):</label>
                <textarea id="ficha_tecnica" name="ficha_tecnica" class="form-control" rows="3" placeholder="Detalles adicionales, enlaces o requisitos"></textarea>
            </div>

            <div style="display:flex; gap:10px;">
                <input type="hidden" name="submit_action" id="submit_action" value="guardar">
                <button type="submit" onclick="document.getElementById('submit_action').value='guardar';" class="btn">Guardar Borrador</button>
                <button type="submit" onclick="document.getElementById('submit_action').value='enviar';" class="btn btn-sena">Enviar Solicitud al Coordinador</button>
            </div>
        </form>
    </div>

    <h3>Artículos Solicitados en este Lote</h3>
    <table>
        <thead>
            <tr>
                <th>ID Ítem</th>
                <th>Código UNSPSC</th>
                <th>Descripción del Bien</th>
                <th>Necesidad</th>
                <th>U. Medida</th>
                <th>Cantidad</th>
                <th>Instructor Apoyo</th>
                <th>Estado</th>
                <th>Ficha Técnica</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($items)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No se han agregado materiales a este requerimiento todavía.</td>
                </tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                        <td><?= htmlspecialchars($item['CODIGO_UNSPSC'] ?? 'ID: '.htmlspecialchars($item['ID_CODIGO_UNSPSC'])) ?></td>
                        <td><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></td>
                        <td><?= $item['ID_NECESIDAD'] ? htmlspecialchars(build_need_label($item)) : '<em>—</em>' ?></td>
                        <td><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
                        <td><strong><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></strong></td>
                        <td><?= htmlspecialchars(($item['APOYO_NOMBRE'] ?? '') ? ($item['APOYO_NOMBRE'].' '.$item['APOYO_APELLIDO']) : '-') ?></td>
                        <td><?= htmlspecialchars($item['ESTADO_ITEM'] ?? 'Borrador') ?></td>
                        <td><?= $item['FICHA_TECNICA'] ? htmlspecialchars(substr($item['FICHA_TECNICA'],0,80)).'...' : '<em>—</em>' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <script src="javascript.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const needSelect = document.getElementById('id_necesidad');
        const qtyInput = document.getElementById('cantidad_regular');
        const needSummary = document.getElementById('need-summary');
        if (needSelect && qtyInput) {
            const updateQuantity = function () {
                const selected = needSelect.options[needSelect.selectedIndex];
                const cantidad = selected ? selected.getAttribute('data-cantidad') : null;
                const label = selected ? selected.getAttribute('data-label') : '';
                qtyInput.value = cantidad && Number(cantidad) > 0 ? cantidad : '1';
                if (needSummary) {
                    needSummary.textContent = label ? label : 'Seleccione una necesidad para ver el detalle académico.';
                }
            };
            needSelect.addEventListener('change', updateQuantity);
            updateQuantity();
        }
    });
    </script>
</body>
</html>