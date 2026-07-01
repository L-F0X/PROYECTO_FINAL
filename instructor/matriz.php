<?php
// matriz.php
require_once '../conexion.php';
require_once '../csrf.php';

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
    header("Location: ../login.php");
    exit;
}

// Capturar el ID del lote para ver sus ítems específicos
$id_lote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;

if ($id_lote === 0) {
    header("Location: index.php");
    exit;
}

$id_ficha_tecnica_get = isset($_GET['ficha_tecnica']) ? intval($_GET['ficha_tecnica']) : 0;
$ficha_tecnica_prefill = null;
if ($id_ficha_tecnica_get > 0) {
    try {
        $stmtF = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
        $stmtF->execute([$id_ficha_tecnica_get]);
        $ficha_tecnica_prefill = $stmtF->fetch();
    } catch (Exception $e) {
        error_log('Error cargando ficha técnica pre-llenada: ' . $e->getMessage());
    }
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

// Cargar todas las fichas técnicas disponibles
$fichasTecnicas = [];
try {
    $stmtFichas = $pdo->query("SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK FROM ficha_tecnica ORDER BY NOMBRE_ITEM");
    $fichasTecnicas = $stmtFichas->fetchAll();
} catch (Exception $e) {
    error_log('Error cargando fichas técnicas: ' . $e->getMessage());
}

// Procesar asignación de ficha técnica a un ítem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_ficha') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $id_matriz_item = isset($_POST['id_matriz_item']) ? intval($_POST['id_matriz_item']) : 0;
    $id_ficha_tecnica = isset($_POST['id_ficha_tecnica']) && $_POST['id_ficha_tecnica'] !== '' ? intval($_POST['id_ficha_tecnica']) : null;
    
    try {
        $stmtAsignar = $pdo->prepare("UPDATE matriz_item SET ID_FICHA_TECNICA = ? WHERE ID_MATRIZ_ITEM = ?");
        $stmtAsignar->execute([$id_ficha_tecnica, $id_matriz_item]);
        header("Location: matriz.php?lote=" . $id_lote);
        exit;
    } catch (Exception $e) {
        error_log('Error al asignar ficha técnica: ' . $e->getMessage());
        die('Error al asignar ficha técnica.');
    }
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
    $id_ficha_tecnica = isset($_POST['id_ficha_tecnica']) && $_POST['id_ficha_tecnica'] !== '' ? intval($_POST['id_ficha_tecnica']) : null;
    $descripcion = trim($_POST['descripcion_bien']);
    $cantidad = 1;
    if ($id_necesidad > 0) {
        try {
            $stmtNeed = $pdo->prepare("SELECT * FROM necesidad WHERE ID_NECESIDAD = ? LIMIT 1");
            $stmtNeed->execute([$id_necesidad]);
            $needData = $stmtNeed->fetch();
            if ($needData) {
                $columna = isset($_POST['columna_necesidad']) ? trim($_POST['columna_necesidad']) : 'CANTIDAD_REGULAR';
                // Convertir columna a mayúscula para que coincida con base de datos
                $columnaUpper = strtoupper($columna);
                // Validar que la columna exista en las claves del registro
                if (array_key_exists($columnaUpper, $needData)) {
                    $cantidad = max(1, intval($needData[$columnaUpper] ?? 0));
                } else {
                    $cantidad = max(1, intval($needData['CANTIDAD_REGULAR'] ?? 0));
                    if ($cantidad <= 0) {
                        $cantidad = max(1, intval($needData['CANTIDAD_NESECIDAD'] ?? 0));
                    }
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

    // Si no se proporciona un ID UNSPSC, resolver usando la Ficha Técnica o crear/usar 'SIN_ASIGNAR'
    if ($id_unspsc === 0 && $id_ficha_tecnica > 0) {
        try {
            $stmtF = $pdo->prepare("SELECT CODIGO_UNSPSC_FK FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
            $stmtF->execute([$id_ficha_tecnica]);
            $cod = trim($stmtF->fetchColumn() ?: '');
            if ($cod !== '') {
                $stmtCu = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ? LIMIT 1");
                $stmtCu->execute([$cod]);
                $found = $stmtCu->fetchColumn();
                if ($found) {
                    $id_unspsc = intval($found);
                } else {
                    $pdo->prepare("INSERT INTO codigo_unspsc (SEGMENTO, FAMILIA, CLASE, CODIGO_UNSPSC) VALUES (?,?,?,?)")->execute(['SIN','ASIG', 'CL', $cod]);
                    $id_unspsc = $pdo->lastInsertId();
                }
            }
        } catch (Exception $e) {
            error_log('Error resolviendo UNSPSC de la ficha técnica: ' . $e->getMessage());
        }
    }
    
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

    $sqlInsert = "INSERT INTO matriz_item (ID_LOTE, ID_NECESIDAD, ID_CODIGO_UNSPSC, ID_IVA, DESCRIPCION_BIEN, CANTIDAD_REGULAR, FICHA_TECNICA, ESTADO_ITEM, INSTRUCTOR_APOYO, ID_FICHA_TECNICA) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    try {
        $pdo->prepare($sqlInsert)->execute([$id_lote, $id_necesidad > 0 ? $id_necesidad : null, $id_unspsc, $id_iva, $descripcion, $cantidad, $ficha, $estadoItem, $instructor_apoyo, $id_ficha_tecnica]);

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
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header>
    <h1>BICERGAM | <span>SENA</span></h1>
    <div style="text-align: right; color: white;">
        Usuario: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> | 
        <a href="index.php" style="color: var(--verde-sena); text-decoration: none; font-weight: bold; margin-right: 15px;">← Volver a Lotes</a>
        <a href="../logout.php" style="color: var(--alerta-rojo); text-decoration: none; font-weight: bold;">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in">
    <h2>Componentes del Lote: <span style="color: var(--verde-sena);"><?= htmlspecialchars($loteInfo['LOTE_NOMBRE'] ?? 'Desconocido') ?></span></h2>
    
    <div style="background: var(--gris-claro); padding: 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid var(--verde-sena);">
        <h3>Añadir Material / Bien al Lote</h3>
        <form action="matriz.php?lote=<?= htmlspecialchars($id_lote) ?>" method="POST" id="formItem">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="id_ficha_tecnica" value="<?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['ID_FICHA_TECNICA']) : '' ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="id_codigo_unspsc">ID Código UNSPSC (opcional):</label>
                    <input type="text" id="id_codigo_unspsc" name="id_codigo_unspsc" class="form-control" placeholder="Ej: 635188316" value="<?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['CODIGO_UNSPSC_FK']) : '' ?>" />
                </div>
                <input type="hidden" id="id_iva" name="id_iva" value="0">
            </div>

            <?php if ($ficha_tecnica_prefill): ?>
                <input type="hidden" id="id_necesidad" name="id_necesidad" value="<?= !empty($needs) ? (int)$needs[0]['ID_NECESIDAD'] : '' ?>">
            <?php else: ?>
                <div class="form-group">
                    <label for="id_necesidad">Necesidad / Estrategia Académica:</label>
                    <select id="id_necesidad" name="id_necesidad" class="form-control" required>
                        <option value="">-- Seleccione una necesidad --</option>
                        <?php foreach ($needs as $need): ?>
                            <option value="<?= (int) $need['ID_NECESIDAD'] ?>" 
                                    data-label="<?= htmlspecialchars($need['LABEL']) ?>"
                                    data-cantidad_regular="<?= intval($need['CANTIDAD_REGULAR']) ?>"
                                    data-cantidad_campesina_complementaria="<?= intval($need['CANTIDAD_CAMPESINA_COMPLEMENTARIA']) ?>"
                                    data-cantidad_campesina_titulada="<?= intval($need['CANTIDAD_CAMPESINA_TITULADA']) ?>"
                                    data-cantidad_vulnerable="<?= intval($need['CANTIDAD_VULNERABLE']) ?>"
                                    data-cantidad_media_tecnica="<?= intval($need['CANTIDAD_MEDIA_TECNICA']) ?>"
                                    data-cantidad_fic="<?= intval($need['CANTIDAD_FIC']) ?>"
                                    data-cantidad_economia_popular="<?= intval($need['CANTIDAD_ECONOMIA_POPULAR']) ?>"
                                    data-cantidad_eni="<?= intval($need['CANTIDAD_ENI']) ?>"
                                    data-cantidad_fc_campesina="<?= intval($need['CANTIDAD_FC_CAMPESINA']) ?>"
                                    data-cantidad_nesecidad="<?= intval($need['CANTIDAD_NESECIDAD']) ?>">
                                <?= htmlspecialchars($need['LABEL']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="need-summary" style="margin-top:8px; color:#166534; font-weight:600;">Seleccione una necesidad para ver el detalle académico.</div>
                    <small style="display:block; margin-top:6px; color:#666;">La cantidad del material se tomará de la necesidad seleccionada en la tabla de necesidades.</small>
                </div>
            <?php endif; ?>

            <?php if ($ficha_tecnica_prefill): ?>
                <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                    <label for="columna_necesidad" style="font-weight:bold; color:#00324D;">Necesidad (Columna de la tabla Necesidad):</label>
                    <select id="columna_necesidad" name="columna_necesidad" class="form-control" style="border: 2px solid #00324D;"
                        <?php if (!empty($needs)): ?>
                            <?php $dn = $needs[0]; ?>
                            data-cantidad_regular="<?= intval($dn['CANTIDAD_REGULAR']) ?>"
                            data-cantidad_campesina_complementaria="<?= intval($dn['CANTIDAD_CAMPESINA_COMPLEMENTARIA']) ?>"
                            data-cantidad_campesina_titulada="<?= intval($dn['CANTIDAD_CAMPESINA_TITULADA']) ?>"
                            data-cantidad_vulnerable="<?= intval($dn['CANTIDAD_VULNERABLE']) ?>"
                            data-cantidad_media_tecnica="<?= intval($dn['CANTIDAD_MEDIA_TECNICA']) ?>"
                            data-cantidad_fic="<?= intval($dn['CANTIDAD_FIC']) ?>"
                            data-cantidad_economia_popular="<?= intval($dn['CANTIDAD_ECONOMIA_POPULAR']) ?>"
                            data-cantidad_eni="<?= intval($dn['CANTIDAD_ENI']) ?>"
                            data-cantidad_fc_campesina="<?= intval($dn['CANTIDAD_FC_CAMPESINA']) ?>"
                            data-cantidad_nesecidad="<?= intval($dn['CANTIDAD_NESECIDAD']) ?>"
                        <?php endif; ?>
                    >
                        <option value="cantidad_regular">Regular (CANTIDAD_REGULAR)</option>
                        <option value="cantidad_campesina_complementaria">Campesina Complementaria (CANTIDAD_CAMPESINA_COMPLEMENTARIA)</option>
                        <option value="cantidad_campesina_titulada">Campesina Titulada (CANTIDAD_CAMPESINA_TITULADA)</option>
                        <option value="cantidad_vulnerable">Vulnerable (CANTIDAD_VULNERABLE)</option>
                        <option value="cantidad_media_tecnica">Media Técnica (CANTIDAD_MEDIA_TECNICA)</option>
                        <option value="cantidad_fic">FIC (CANTIDAD_FIC)</option>
                        <option value="cantidad_economia_popular">Economía Popular (CANTIDAD_ECONOMIA_POPULAR)</option>
                        <option value="cantidad_eni">ENI (CANTIDAD_ENI)</option>
                        <option value="cantidad_fc_campesina">FC Campesina (CANTIDAD_FC_CAMPESINA)</option>
                        <option value="cantidad_nesecidad">Necesidad (CANTIDAD_NESECIDAD)</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="descripcion_bien">Descripción Detallada del Bien / Ficha técnica breve:</label>
                <textarea id="descripcion_bien" name="descripcion_bien" class="form-control" rows="2" required <?= $ficha_tecnica_prefill ? 'readonly' : '' ?>><?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['NOMBRE_ITEM'] . ' - ' . $ficha_tecnica_prefill['DENOMINACION_TECNICA_BIEN']) : '' ?></textarea>
            </div>

            <div class="form-group" style="width: 32%;">
                <label for="cantidad_regular">Cantidad Requerida:</label>
                <input type="number" id="cantidad_regular" name="cantidad_regular" class="form-control" min="1" value="1" required>
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
                <textarea id="ficha_tecnica" name="ficha_tecnica" class="form-control" rows="3" placeholder="Detalles adicionales, enlaces o requisitos" <?= $ficha_tecnica_prefill ? 'readonly' : '' ?>><?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['DESCRIPCION_GENERAL']) : '' ?></textarea>
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
                    <td colspan="9" style="text-align: center;">No se han agregado materiales a este requerimiento todavía.</td>
                </tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                        <td><?= htmlspecialchars($item['CODIGO_UNSPSC'] ?? 'SIN_ASIGNAR') ?></td>
                        <td><strong><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></strong></td>
                        <td><?= htmlspecialchars($item['ID_NECESIDAD'] ? 'Necesidad #' . $item['ID_NECESIDAD'] : 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['UNIDAD_MEDIDA'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></td>
                        <td>
                            <?php 
                            if ($item['INSTRUCTOR_APOYO']) {
                                $stmtUser = $pdo->prepare("SELECT NOMBRE, APELLIDO FROM usuario WHERE ID_USUARIO = ?");
                                $stmtUser->execute([$item['INSTRUCTOR_APOYO']]);
                                $u = $stmtUser->fetch();
                                echo htmlspecialchars($u['NOMBRE'] . ' ' . $u['APELLIDO']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><span class="badge badge-warning"><?= htmlspecialchars($item['ESTADO_ITEM'] ?? 'Borrador') ?></span></td>
                        <td>
                            <?php if ($item['ID_FICHA_TECNICA']): ?>
                                <span style="color: green; font-weight: bold;">✓ Asignada (FT #<?= $item['ID_FICHA_TECNICA'] ?>)</span>
                            <?php else: ?>
                                <span style="color: #666; font-style: italic;">Sin Ficha</span>
                            <?php endif; ?>
                            <form method="POST" action="matriz.php?lote=<?= htmlspecialchars($id_lote) ?>" style="margin-top: 5px;">
                                <input type="hidden" name="accion" value="asignar_ficha">
                                <input type="hidden" name="id_matriz_item" value="<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <select name="id_ficha_tecnica" onchange="this.form.submit()" style="padding: 2px; font-size: 11px;">
                                    <option value="">-- Cambiar Ficha --</option>
                                    <?php foreach ($fichasTecnicas as $ft): ?>
                                        <option value="<?= htmlspecialchars($ft['ID_FICHA_TECNICA']) ?>" <?= $item['ID_FICHA_TECNICA'] == $ft['ID_FICHA_TECNICA'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ft['NOMBRE_ITEM']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const needSelect = document.getElementById('id_necesidad');
        const qtyInput = document.getElementById('cantidad_regular');
        const needSummary = document.getElementById('need-summary');
        const colSelect = document.getElementById('columna_necesidad');

        // Permitir que el usuario edite la cantidad requerida
        if (qtyInput) {
            qtyInput.removeAttribute('readonly');
        }

        const updateQuantity = function () {
            if (colSelect) {
                // Modo catálogo de Ficha Técnica pre-llenada
                const columnName = colSelect.value;
                const attributeName = 'data-' + columnName;
                const cantidadVal = colSelect.getAttribute(attributeName);
                if (cantidadVal && Number(cantidadVal) > 0) {
                    qtyInput.value = cantidadVal;
                }
            } else if (needSelect) {
                // Modo manual normal
                const selected = needSelect.options[needSelect.selectedIndex];
                if (!selected || selected.value === "") {
                    qtyInput.value = '1';
                    if (needSummary) needSummary.textContent = 'Seleccione una necesidad para ver el detalle académico.';
                    return;
                }
                const label = selected.getAttribute('data-label') || '';
                const cantidadVal = selected.getAttribute('data-cantidad_regular');
                qtyInput.value = (cantidadVal && Number(cantidadVal) > 0) ? cantidadVal : '1';
                if (needSummary) {
                    needSummary.textContent = label;
                }
            }
        };

        if (needSelect) {
            needSelect.addEventListener('change', updateQuantity);
        }
        if (colSelect) {
            colSelect.addEventListener('change', updateQuantity);
        }
        
        // Ejecutar inicialmente para pre-llenar la cantidad según la columna por defecto
        updateQuantity();
    });
</script>
</body>
</html>