<?php
require_once '../conexion.php';

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
    SELECT mi.ID_MATRIZ_ITEM, mi.DESCRIPCION_BIEN, lr.LOTE_NOMBRE
    FROM matriz_item mi
    INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
    WHERE lr.ID_SOLICITANTE = ?
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

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idMatrizItem      = intval($_POST['id_matriz_item']);
        $numItem           = intval($_POST['num_item']);
        $nombreItem        = trim($_POST['nombre_item']);
        $codigoUnspsc      = trim($_POST['codigo_unspsc']);
        $denominacion      = trim($_POST['denominacion_tecnica']);
        $unidadMedida      = trim($_POST['unidad_medida']);
        $descripcion       = trim($_POST['descripcion_general']);
        $comentarios       = trim($_POST['comentarios'] ?? '');

        // Validar / insertar código UNSPSC si no existe
        $stmtCheckUnspsc = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
        $stmtCheckUnspsc->execute([$codigoUnspsc]);
        if (!$stmtCheckUnspsc->fetch()) {
            $stmtInsertUnspsc = $pdo->prepare("INSERT INTO codigo_unspsc (SEGMENTO, FAMILIA, CLASE, CODIGO_UNSPSC) VALUES (?, ?, ?, ?)");
            $stmtInsertUnspsc->execute(['SIN', 'ASIG', 'CL', $codigoUnspsc]);
        }

        // Insertar en tabla ficha_tecnica
        $sqlInsert = "INSERT INTO ficha_tecnica
            (ID_MATRIZ_ITEM, NOMBRE_ITEM, CODIGO_UNSPSC_FK, DENOMINACION_TECNICA_BIEN, UNIDAD_MEDIDA, DESCRIPCION_GENERAL, COMENTARIOS)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $idMatrizItem, $nombreItem, $codigoUnspsc,
            $denominacion, $unidadMedida, $descripcion, $comentarios
        ]);

        $mensaje = "<div class='alert success'>✓ Ficha Técnica guardada con éxito.</div>";
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
<body>
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
        <a href="../instructor_profile.php" class="header-avatar-link" title="Editar perfil">
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
        </div>
        <div class="sidebar-group">
            <h4>Operaciones</h4>
            <a href="crear_ficha_tecnica.php" class="sidebar-link sidebar-link--primary active">Ficha Técnica</a>
            <a href="consulta_lote.php" class="sidebar-link">Consulta de Lotes</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="../historial_existencia.php" class="sidebar-link">Historial de Existencia</a>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="../instructor_profile.php" class="sidebar-link">Editar Perfil</a>
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
            <div class="ficha-container">

                <!-- ── Título ── -->
                <div class="ficha-title">Ficha Técnica de Producto</div>

                <!-- ── Fila: ítem de matriz + número ── -->
                <div class="ficha-meta-row">
                    <div class="ficha-meta-cell">
                        <label for="id_matriz_item">Ítem de Matriz (Lote)</label>
                        <select name="id_matriz_item" id="id_matriz_item" required>
                            <option value="">-- Seleccione el ítem de la matriz --</option>
                            <?php foreach ($matrizItems as $mi): ?>
                                <option value="<?= $mi['ID_MATRIZ_ITEM'] ?>">
                                    #<?= $mi['ID_MATRIZ_ITEM'] ?> — <?= htmlspecialchars($mi['LOTE_NOMBRE']) ?>: <?= htmlspecialchars(mb_strimwidth($mi['DESCRIPCION_BIEN'], 0, 50, '…')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ficha-meta-cell" style="max-width:160px">
                        <label for="num_item">Número de Ítem</label>
                        <input type="number" name="num_item" id="num_item" placeholder="Ej: 2" required min="1">
                    </div>
                </div>

                <!-- ── NUMERO DE ITEM / NOMBRE ── -->
                <div class="ficha-row">
                    <div class="ficha-label">Nombre del Ítem *</div>
                    <div class="ficha-value">
                        <input type="text" name="nombre_item" id="nombre_item"
                               placeholder="Ej: ESPUMA LIMPIADORA, 370 ML" required>
                    </div>
                </div>

                <!-- ── CÓDIGO UNSPSC ── -->
                <div class="ficha-row">
                    <div class="ficha-label">Código UNSPSC *</div>
                    <div class="ficha-value">
                        <input type="text" name="codigo_unspsc" id="codigo_unspsc"
                               placeholder="Ej: 47131805" required>
                    </div>
                </div>

                <!-- ── DENOMINACIÓN TÉCNICA DEL BIEN ── -->
                <div class="ficha-section-header">Denominación Técnica del Bien</div>
                <div class="ficha-full-row">
                    <input type="text" name="denominacion_tecnica" id="denominacion_tecnica"
                           placeholder="Denominación técnica detallada del bien" required>
                </div>

                <!-- ── UNIDAD DE MEDIDA ── -->
                <div class="ficha-section-header">Unidad de Medida</div>
                <div class="ficha-full-row" style="text-align:center">
                    <input type="text" name="unidad_medida" id="unidad_medida"
                           placeholder="Ej: Unidad, Galón, Kit" required style="text-align:center">
                </div>

                <!-- ── DESCRIPCIÓN GENERAL ── -->
                <div class="ficha-section-header">Descripción General</div>
                <div class="ficha-desc-area">
                    <div class="ficha-desc-text">
                        <textarea name="descripcion_general" id="descripcion_general"
                                  placeholder="Tipo de elemento: ...&#10;Tecnología: ...&#10;Presentación: ..."
                                  required></textarea>
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

<script src="../javascript.js"></script>
</body>
</html>
