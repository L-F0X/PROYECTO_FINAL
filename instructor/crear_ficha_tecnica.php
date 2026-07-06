<?php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$mensaje = "";

// Cargar items de la matriz disponibles (del instructor) para asociar la ficha
$stmtItems = $pdo->prepare("
    SELECT mi.ID_MATRIZ_ITEM, mi.DESCRIPCION_BIEN, lr.LOTE_NOMBRE, mi.ID_LOTE, mi.UNIDAD_MEDIDA, mi.CANTIDAD_REGULAR, c.CODIGO_UNSPSC
    FROM matriz_item mi
    INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
    LEFT JOIN codigo_unspsc c ON mi.ID_CODIGO_UNSPSC = c.ID_CODIGO
    WHERE lr.ID_SOLICITANTE = ? AND mi.ID_FICHA_TECNICA IS NULL
    ORDER BY mi.ID_MATRIZ_ITEM DESC
");
$stmtItems->execute([$usuarioId]);
$matrizItems = $stmtItems->fetchAll();

// Cargar lotes disponibles
$stmtLotes = $pdo->prepare("SELECT ID_LOTE, LOTE_NOMBRE FROM lote_requerimiento WHERE ID_SOLICITANTE = ?");
$stmtLotes->execute([$usuarioId]);
$lotes = $stmtLotes->fetchAll();

// Cargar IVAs disponibles
$stmtIva = $pdo->query("SELECT ID_IVA, PORCENTAJE, DESCRIPCION FROM iva");
$ivas = $stmtIva->fetchAll();

// Cargar datos pre-rellenados si se especifica un item en la URL
$prefilledItem = null;
$prefilledItemId = isset($_GET['item']) ? intval($_GET['item']) : 0;
if ($prefilledItemId > 0) {
    try {
        $stmtPrefill = $pdo->prepare("
            SELECT mi.*, lr.LOTE_NOMBRE, c.CODIGO_UNSPSC, c.NOMBRE_PRODUCTO AS UNSPSC_NOMBRE
            FROM matriz_item mi
            INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
            LEFT JOIN codigo_unspsc c ON mi.ID_CODIGO_UNSPSC = c.ID_CODIGO
            WHERE mi.ID_MATRIZ_ITEM = ? AND lr.ID_SOLICITANTE = ? AND mi.ID_FICHA_TECNICA IS NULL
            LIMIT 1
        ");
        $stmtPrefill->execute([$prefilledItemId, $usuarioId]);
        $prefilledItem = $stmtPrefill->fetch();
    } catch (Exception $e) {
        error_log('Error al cargar datos pre-rellenados: ' . $e->getMessage());
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception("Token CSRF inválido. Recargue la página e intente de nuevo.");
        }

        $nombreItem        = trim($_POST['nombre_item']);
        $codigoUnspsc      = trim($_POST['codigo_unspsc']);
        $denominacion      = trim($_POST['denominacion_tecnica']);
        $unidadMedida      = trim($_POST['unidad_medida']);
        $descripcion       = trim($_POST['descripcion_general']);
        $comentarios       = trim($_POST['comentarios'] ?? '');
        $idLote            = isset($_POST['id_lote']) && trim($_POST['id_lote']) !== '' ? intval($_POST['id_lote']) : 0;
        $cantidad          = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;
        $idMatrizItem      = isset($_POST['id_matriz_item']) && trim($_POST['id_matriz_item']) !== '' ? intval($_POST['id_matriz_item']) : 0;
        $idIva             = isset($_POST['id_iva']) ? intval($_POST['id_iva']) : 0;

        if ($idLote <= 0) {
            throw new Exception("Debe seleccionar un lote de destino para asociar la ficha técnica.");
        }
        $stmtCheckLote = $pdo->prepare("SELECT ID_LOTE FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
        $stmtCheckLote->execute([$idLote, $usuarioId]);
        if (!$stmtCheckLote->fetchColumn()) {
            throw new Exception("El lote seleccionado no existe o no le pertenece.");
        }
        if ($idMatrizItem > 0) {
            $stmtCheckItem = $pdo->prepare("
                SELECT mi.ID_MATRIZ_ITEM FROM matriz_item mi
                INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
                WHERE mi.ID_MATRIZ_ITEM = ? AND lr.ID_SOLICITANTE = ?
            ");
            $stmtCheckItem->execute([$idMatrizItem, $usuarioId]);
            if (!$stmtCheckItem->fetchColumn()) {
                throw new Exception("El ítem seleccionado no existe o no le pertenece.");
            }
        }
        if ($cantidad <= 0) {
            throw new Exception("La cantidad debe ser un número entero mayor que cero.");
        }
        if ($idMatrizItem <= 0) {
            $stmtCheckIva = $pdo->prepare("SELECT ID_IVA FROM iva WHERE ID_IVA = ?");
            $stmtCheckIva->execute([$idIva]);
            if (!$stmtCheckIva->fetchColumn()) {
                throw new Exception("Debe seleccionar una tasa de IVA válida.");
            }
        }
        if ($codigoUnspsc === '') {
            throw new Exception("Debe seleccionar un código UNSPSC del catálogo.");
        }

        // El código UNSPSC debe existir en el catálogo importado; ya no se crean códigos "al vuelo"
        $stmtCheckUnspsc = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
        $stmtCheckUnspsc->execute([$codigoUnspsc]);
        $found = $stmtCheckUnspsc->fetchColumn();
        if (!$found) {
            throw new Exception("El código UNSPSC ingresado no existe en el catálogo. Selecciónelo de la lista de sugerencias.");
        }
        $id_unspsc = intval($found);

        if ($idMatrizItem > 0) {
            // Caso A: Asociar a un ítem existente de la matriz
            // 1. Insertar en tabla ficha_tecnica
            $sqlInsert = "INSERT INTO ficha_tecnica
                (ID_MATRIZ_ITEM, NOMBRE_ITEM, CODIGO_UNSPSC_FK, DENOMINACION_TECNICA_BIEN, UNIDAD_MEDIDA, DESCRIPCION_GENERAL, COMENTARIOS, CANTIDAD)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $idMatrizItem, $nombreItem, $codigoUnspsc,
                $denominacion, $unidadMedida, $descripcion, $comentarios, $cantidad
            ]);
            $lastFichaId = $pdo->lastInsertId();

            // 2. Actualizar la tabla matriz_item con el ID_FICHA_TECNICA y sincronizar campos
            $stmtUpdateMatrizItem = $pdo->prepare("
                UPDATE matriz_item 
                SET ID_FICHA_TECNICA = ?, ID_CODIGO_UNSPSC = ?, UNIDAD_MEDIDA = ?, CANTIDAD_REGULAR = ? 
                WHERE ID_MATRIZ_ITEM = ?
            ");
            $stmtUpdateMatrizItem->execute([$lastFichaId, $id_unspsc, $unidadMedida, $cantidad, $idMatrizItem]);

        } else {
            // Caso B: Crear un nuevo ítem en matriz y luego asociar la ficha técnica
            // 1. Insertar nuevo registro en matriz_item (con ID_FICHA_TECNICA temporalmente null)
            $sqlMatrizItem = "INSERT INTO matriz_item
                (ID_LOTE, ID_CODIGO_UNSPSC, ID_IVA, DESCRIPCION_BIEN, UNIDAD_MEDIDA, CANTIDAD_REGULAR, FICHA_TECNICA, ESTADO_ITEM, ID_FICHA_TECNICA)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Borrador', NULL)";

            $stmtNewItem = $pdo->prepare($sqlMatrizItem);
            $stmtNewItem->execute([
                $idLote, $id_unspsc, $idIva, $nombreItem . " - " . $denominacion, $unidadMedida, $cantidad, $descripcion
            ]);
            $idMatrizItem = $pdo->lastInsertId();

            // 2. Insertar en tabla ficha_tecnica
            $sqlInsert = "INSERT INTO ficha_tecnica
                (ID_MATRIZ_ITEM, NOMBRE_ITEM, CODIGO_UNSPSC_FK, DENOMINACION_TECNICA_BIEN, UNIDAD_MEDIDA, DESCRIPCION_GENERAL, COMENTARIOS, CANTIDAD)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $idMatrizItem, $nombreItem, $codigoUnspsc,
                $denominacion, $unidadMedida, $descripcion, $comentarios, $cantidad
            ]);
            $lastFichaId = $pdo->lastInsertId();

            // 3. Vincular el ID_FICHA_TECNICA recién creado de vuelta a la tabla matriz_item
            $stmtUpdateMatrizItem = $pdo->prepare("UPDATE matriz_item SET ID_FICHA_TECNICA = ? WHERE ID_MATRIZ_ITEM = ?");
            $stmtUpdateMatrizItem->execute([$lastFichaId, $idMatrizItem]);
        }

        $mensaje = "<div class='alert success'>✓ Ficha Técnica guardada y vinculada con éxito.</div>";
        // Recargar la lista de items
        $stmtItems->execute([$usuarioId]);
        $matrizItems = $stmtItems->fetchAll();
    } catch (Exception $e) {
        $mensaje = "<div class='alert error'>✗ Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Lógica de foto de perfil
$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
$isIframe = isset($_GET['iframe']) ? true : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crear ficha técnica de producto en el sistema BICERGAM del SENA.">
    <title>Crear Ficha Técnica - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        /* ── Ficha: contenedor principal ── */
        .ficha-container {
            background: #ffffff;
            border: 2px solid #333;
            max-width: 820px;
            margin: 20px auto 30px;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        /* ── Título principal centrado ── */
        .ficha-title {
            text-align: center;
            border-bottom: 2px solid #333;
            padding: 12px 15px;
            background-color: #e8e8e8;
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Fila estándar: label | valor ── */
        .ficha-row {
            display: flex;
            border-bottom: 1px solid #aaa;
        }
        .ficha-row:last-child { border-bottom: none; }

        .ficha-label {
            width: 35%;
            background: #f0f0f0;
            padding: 10px 12px;
            font-weight: bold;
            border-right: 1px solid #aaa;
            display: flex;
            align-items: center;
            text-transform: uppercase;
            font-size: 12px;
        }
        .ficha-value {
            width: 65%;
            padding: 8px 12px;
            display: flex;
            align-items: center;
        }
        .ficha-value input,
        .ficha-value select {
            width: 100%;
            border: 1px solid #bbb;
            padding: 6px 8px;
            box-sizing: border-box;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
        }
        .ficha-value input:focus,
        .ficha-value select:focus { outline: 2px solid #39a900; background: #fff; }

        /* ── Fila de sección: cabecera gris ancho completo ── */
        .ficha-section-header {
            background: #d8d8d8;
            border-bottom: 1px solid #aaa;
            padding: 9px 12px;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 0.5px;
        }

        /* ── Fila de contenido expandido (denominación, unidad) ── */
        .ficha-full-row {
            border-bottom: 1px solid #aaa;
            padding: 10px 12px;
            min-height: 38px;
        }
        .ficha-full-row input,
        .ficha-full-row textarea {
            width: 100%;
            border: 1px solid #bbb;
            padding: 6px 8px;
            box-sizing: border-box;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
        }
        .ficha-full-row input:focus,
        .ficha-full-row textarea:focus { outline: 2px solid #39a900; background: #fff; }

        /* ── Área de descripción con espacio para imagen ── */
        .ficha-desc-area {
            border-bottom: 1px solid #aaa;
            padding: 12px;
            display: flex;
            gap: 15px;
            min-height: 160px;
        }
        .ficha-desc-text {
            flex: 1;
        }
        .ficha-desc-text textarea {
            width: 100%;
            height: 130px;
            border: 1px solid #bbb;
            padding: 8px;
            box-sizing: border-box;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
            resize: vertical;
        }
        .ficha-desc-text textarea:focus { outline: 2px solid #39a900; background: #fff; }
        .ficha-desc-img {
            width: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px dashed #999;
            border-radius: 4px;
            background: #f7f7f7;
            color: #666;
            font-size: 11px;
            text-align: center;
            padding: 8px;
            gap: 8px;
        }
        .ficha-desc-img svg { opacity: 0.4; }
        .ficha-img-caption {
            font-weight: bold;
            font-size: 11px;
            color: #444;
            text-align: center;
            padding: 4px 12px 8px;
            font-style: italic;
        }

        /* ── Fila de firma/marca: label en negrita ancho completo ── */
        .ficha-row-firma {
            display: flex;
            border-bottom: 1px solid #aaa;
            min-height: 40px;
        }
        .ficha-row-firma:last-child { border-bottom: none; }
        .ficha-label-firma {
            width: 40%;
            background: #f0f0f0;
            padding: 10px 12px;
            font-weight: bold;
            border-right: 1px solid #aaa;
            display: flex;
            align-items: center;
            font-size: 12px;
            text-transform: uppercase;
        }
        .ficha-value-firma {
            width: 60%;
            padding: 8px 12px;
        }
        .ficha-value-firma input {
            width: 100%;
            border: 1px solid #bbb;
            padding: 6px 8px;
            box-sizing: border-box;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
        }

        /* ── Sección superior: lote + ítem ── */
        .ficha-meta-row {
            display: flex;
            border-bottom: 1px solid #aaa;
        }
        .ficha-meta-cell {
            flex: 1;
            padding: 8px 12px;
            border-right: 1px solid #aaa;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .ficha-meta-cell:last-child { border-right: none; }
        .ficha-meta-cell label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
        }
        .ficha-meta-cell select,
        .ficha-meta-cell input {
            width: 100%;
            border: 1px solid #bbb;
            padding: 5px 8px;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
            box-sizing: border-box;
        }

        /* ── Alertas ── */
        .alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 5px;
            font-weight: 500;
            max-width: 820px;
            margin-left: auto;
            margin-right: auto;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── Botón guardar ── */
        .btn-submit {
            background: linear-gradient(135deg, #39a900, #2e8600);
            color: white;
            padding: 13px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            margin: 18px auto 0;
            display: block;
            max-width: 820px;
            width: 100%;
            letter-spacing: 0.5px;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover  { background: linear-gradient(135deg, #2e8600, #206000); }
        .btn-submit:active { transform: scale(0.99); }
    </style>
</head>
<body class="<?= $isIframe ? 'iframe-mode' : '' ?>">
<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
            <span class="header-user-role">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span>
        </div>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones">🔔<?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="instructor_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
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
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link">Mis Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary active">Ficha Técnica</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Estructurar Ficha Técnica de Producto</h2>
                <p class="dashboard-subtitle">Complete el formulario replicando el formato oficial de adquisición.</p>
            </div>
        </div>

        <?= $mensaje ?>

        <form action="crear_ficha_tecnica.php" method="POST" id="form-ficha">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div class="ficha-container">

                <!-- ── Título ── -->
                <div class="ficha-title">Ficha Técnica de Producto</div>

                <!-- ── SELECCIONAR LOTE DESTINO ── -->
                <?php
                $prefilledNombre = '';
                $prefilledDenominacion = '';
                if ($prefilledItem) {
                    $parts = explode(' - ', $prefilledItem['DESCRIPCION_BIEN'], 2);
                    $prefilledNombre = trim($parts[0] ?? '');
                    $prefilledDenominacion = trim($parts[1] ?? '');
                }
                ?>
                <?php if ($prefilledItem): ?>
                    <input type="hidden" name="id_lote" value="<?= htmlspecialchars($prefilledItem['ID_LOTE']) ?>">
                    <input type="hidden" name="id_matriz_item" value="<?= htmlspecialchars($prefilledItem['ID_MATRIZ_ITEM']) ?>">
                    <div class="ficha-row">
                        <div class="ficha-label">Lote Destino</div>
                        <div class="ficha-value">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($prefilledItem['LOTE_NOMBRE']) ?>" disabled>
                        </div>
                    </div>
                    <div class="ficha-row">
                        <div class="ficha-label">Ítem Asociado</div>
                        <div class="ficha-value">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($prefilledItem['DESCRIPCION_BIEN']) ?> (ID: <?= htmlspecialchars($prefilledItem['ID_MATRIZ_ITEM']) ?>)" disabled>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ficha-row">
                        <div class="ficha-label">Lote Destino *</div>
                        <div class="ficha-value">
                            <select name="id_lote" id="id_lote" required>
                                <option value="">-- Seleccionar Lote --</option>
                                <?php foreach ($lotes as $l): ?>
                                    <option value="<?= htmlspecialchars($l['ID_LOTE']) ?>"><?= htmlspecialchars($l['LOTE_NOMBRE']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="ficha-row" id="row_item">
                        <div class="ficha-label">Asociar a un Ítem Existente *</div>
                        <div class="ficha-value">
                            <select name="id_matriz_item" id="id_matriz_item" required>
                                <option value="">-- Seleccione un Ítem --</option>
                                <?php foreach ($matrizItems as $item): ?>
                                    <option value="<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>" 
                                            data-lote="<?= htmlspecialchars($item['ID_LOTE']) ?>"
                                            data-nombre="<?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?>"
                                            data-unspsc="<?= htmlspecialchars($item['CODIGO_UNSPSC'] ?? 'SIN_ASIGNAR') ?>"
                                            data-unidad="<?= htmlspecialchars($item['UNIDAD_MEDIDA'] ?? 'Unidad') ?>"
                                            data-cantidad="<?= htmlspecialchars($item['CANTIDAD_REGULAR'] ?? '1') ?>">
                                        <?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?> (ID: <?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ── NUMERO DE ITEM / NOMBRE ── -->
                <div class="ficha-row">
                    <div class="ficha-label">Nombre del Ítem *</div>
                    <div class="ficha-value">
                        <input type="text" name="nombre_item" id="nombre_item"
                               placeholder="Ej: ESPUMA LIMPIADORA, 370 ML" value="<?= htmlspecialchars($prefilledNombre) ?>" required>
                    </div>
                </div>

                <!-- ── CÓDIGO UNSPSC ── -->
                <?php
                $prefilledUnspscCodigo = $prefilledItem['CODIGO_UNSPSC'] ?? '';
                $prefilledUnspscNombre = $prefilledItem['UNSPSC_NOMBRE'] ?? '';
                $prefilledUnspscDisplay = $prefilledUnspscCodigo !== ''
                    ? $prefilledUnspscCodigo . ($prefilledUnspscNombre !== '' ? ' - ' . $prefilledUnspscNombre : '')
                    : '';
                ?>
                <div class="ficha-row" style="position: relative;">
                    <div class="ficha-label">Código UNSPSC *</div>
                    <div class="ficha-value" style="position: relative;">
                        <input type="text" id="codigo_unspsc_busqueda" autocomplete="off"
                               placeholder="Escriba el nombre o código del producto para buscar"
                               value="<?= htmlspecialchars($prefilledUnspscDisplay) ?>" required>
                        <input type="hidden" name="codigo_unspsc" id="codigo_unspsc" value="<?= htmlspecialchars($prefilledUnspscCodigo) ?>">
                        <div id="unspsc_resultados" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:20; max-height:220px; overflow-y:auto; box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                    </div>
                </div>
                <div id="unspsc_contexto" style="font-size:12px; color:#666; padding:0 12px 8px; text-align:right;"></div>

                <!-- ── DENOMINACIÓN TÉCNICA DEL BIEN ── -->
                <div class="ficha-section-header">Denominación Técnica del Bien</div>
                <div class="ficha-full-row">
                    <input type="text" name="denominacion_tecnica" id="denominacion_tecnica"
                           placeholder="Denominación técnica detallada del bien" value="<?= htmlspecialchars($prefilledDenominacion) ?>" required>
                </div>

                <!-- ── UNIDAD DE MEDIDA ── -->
                <div class="ficha-section-header">Unidad de Medida</div>
                <div class="ficha-full-row" style="text-align:center">
                    <input type="text" name="unidad_medida" id="unidad_medida"
                           placeholder="Ej: Unidad, Galón, Kit" value="<?= htmlspecialchars($prefilledItem['UNIDAD_MEDIDA'] ?? '') ?>" required style="text-align:center">
                </div>

                <!-- ── CANTIDAD ── -->
                <div class="ficha-section-header">Cantidad</div>
                <div class="ficha-full-row" style="text-align:center">
                    <input type="number" name="cantidad" id="cantidad" min="1" value="<?= htmlspecialchars($prefilledItem['CANTIDAD_REGULAR'] ?? '1') ?>" required style="text-align:center">
                </div>

                <?php if (!$prefilledItem): ?>
                <!-- ── TASA DE IVA (solo al crear un ítem nuevo) ── -->
                <div class="ficha-section-header">Tasa de IVA</div>
                <div class="ficha-full-row" style="text-align:center">
                    <select name="id_iva" id="id_iva" required>
                        <?php foreach ($ivas as $iva): ?>
                            <option value="<?= htmlspecialchars($iva['ID_IVA']) ?>"><?= htmlspecialchars($iva['DESCRIPCION']) ?> (<?= htmlspecialchars(rtrim(rtrim(number_format($iva['PORCENTAJE'], 2), '0'), '.')) ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- ── DESCRIPCIÓN GENERAL ── -->
                <div class="ficha-section-header">Descripción General</div>
                <div class="ficha-desc-area">
                    <div class="ficha-desc-text">
                        <textarea name="descripcion_general" id="descripcion_general"
                                  placeholder="Tipo de elemento: ...&#10;Tecnología: ...&#10;Presentación: ..."
                                  required><?= htmlspecialchars($prefilledItem['FICHA_TECNICA'] ?? '') ?></textarea>
                    </div>
                    <div class="ficha-desc-img">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Imagen de referencia
                    </div>
                </div>
                <div class="ficha-img-caption">La imagen es de referencia</div>

                <!-- ── COMENTARIOS ── -->
                <div class="ficha-section-header">Comentarios / Especificaciones Adicionales</div>
                <div class="ficha-full-row">
                    <textarea name="comentarios" id="comentarios"
                              placeholder="Especificaciones adicionales, normas técnicas, etc. (opcional)"
                              style="height:60px; resize:vertical"></textarea>
                </div>

                <!-- ── MARCA OFRECIDA ── -->
                <div class="ficha-row-firma">
                    <div class="ficha-label-firma">Marca Ofrecida</div>
                    <div class="ficha-value-firma">
                        <input type="text" name="marca_ofrecida" id="marca_ofrecida"
                               placeholder="Nombre de la marca">
                    </div>
                </div>

                <!-- ── FIRMA DEL PROPONENTE ── -->
                <div class="ficha-row-firma">
                    <div class="ficha-label-firma">Firma del Proponente</div>
                    <div class="ficha-value-firma">
                        <input type="text" name="firma_proponente" id="firma_proponente"
                               placeholder="Nombre / firma del proponente">
                    </div>
                </div>

            </div><!-- /.ficha-container -->

            <button type="submit" class="btn-submit" id="btn-guardar-ficha">
                Guardar Ficha Técnica
            </button>
        </form>
    </main>
</div>

<script src="../js/apartados.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loteSelect = document.getElementById('id_lote');
    const itemSelect = document.getElementById('id_matriz_item');

    if (loteSelect && itemSelect) {
        // Al cambiar de lote, filtramos los items elegibles
        loteSelect.addEventListener('change', function() {
            const selectedLote = this.value;
            const options = itemSelect.querySelectorAll('option');
            
            itemSelect.value = '';
            
            options.forEach(opt => {
                if (opt.value === '') {
                    opt.style.display = 'block';
                } else {
                    const optLote = opt.getAttribute('data-lote');
                    if (optLote === selectedLote) {
                        opt.style.display = 'block';
                    } else {
                        opt.style.display = 'none';
                    }
                }
            });
            
            // Disparar evento change de itemSelect para limpiar campos
            itemSelect.dispatchEvent(new Event('change'));
        });

        // Al cambiar de item, rellenamos los campos automáticamente
        itemSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            if (!selectedOpt || selectedOpt.value === '') {
                // Si no hay item seleccionado, limpiar campos
                document.getElementById('nombre_item').value = '';
                document.getElementById('codigo_unspsc').value = '';
                document.getElementById('codigo_unspsc_busqueda').value = '';
                document.getElementById('denominacion_tecnica').value = '';
                document.getElementById('unidad_medida').value = '';
                document.getElementById('cantidad').value = '1';
                return;
            }

            const fullName = selectedOpt.getAttribute('data-nombre') || '';
            const parts = fullName.split(' - ');
            const nombre = parts[0] ? parts[0].trim() : '';
            const denominacion = parts[1] ? parts[1].trim() : '';
            const unspscCodigo = selectedOpt.getAttribute('data-unspsc') || '';

            document.getElementById('nombre_item').value = nombre;
            document.getElementById('denominacion_tecnica').value = denominacion || nombre;
            document.getElementById('codigo_unspsc').value = unspscCodigo;
            document.getElementById('codigo_unspsc_busqueda').value = unspscCodigo;
            document.getElementById('unidad_medida').value = selectedOpt.getAttribute('data-unidad') || 'Unidad';
            document.getElementById('cantidad').value = selectedOpt.getAttribute('data-cantidad') || '1';
        });

        // Disparar inicialmente en caso de que esté pre-seleccionado un lote
        if (loteSelect.value !== '') {
            loteSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>
<script src="../js/unspsc-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initUnspscAutocomplete({
        inputSelector: '#codigo_unspsc_busqueda',
        hiddenCodeSelector: '#codigo_unspsc',
        resultsSelector: '#unspsc_resultados',
        searchUrl: '../ajax/buscar_unspsc.php',
        onSelect: function (item) {
            const nombreItem = document.getElementById('nombre_item');
            const denominacion = document.getElementById('denominacion_tecnica');
            if (nombreItem && nombreItem.value.trim() === '') {
                nombreItem.value = item.nombre;
            }
            if (denominacion && denominacion.value.trim() === '') {
                denominacion.value = item.nombre;
            }
            const contexto = document.getElementById('unspsc_contexto');
            if (contexto) {
                const partes = [item.segmento_titulo, item.familia_titulo, item.clase_titulo].filter(Boolean);
                contexto.textContent = partes.length ? partes.join(' > ') : '';
            }
        }
    });
});
</script>
</body>
</html>
