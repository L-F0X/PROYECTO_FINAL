<?php
// instructor/editar_ficha_tecnica.php
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

// Migración aditiva: registrar quién creó cada ficha técnica, para restringir su edición
function editarficha_columna_existe(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tabla, $columna]);
    return (bool) $stmt->fetchColumn();
}
if (!editarficha_columna_existe($pdo, 'ficha_tecnica', 'ID_CREADOR')) {
    $pdo->exec("ALTER TABLE ficha_tecnica ADD COLUMN ID_CREADOR INT DEFAULT NULL");
}

$idFicha = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idFicha <= 0) {
    header('Location: fichas_tecnicas_creadas.php');
    exit;
}

$stmtFicha = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
$stmtFicha->execute([$idFicha]);
$ficha = $stmtFicha->fetch();
if (!$ficha) {
    header('Location: fichas_tecnicas_creadas.php');
    exit;
}

// Solo el instructor que creó la ficha puede editarla. Las fichas sin creador
// registrado (creadas antes de esta restricción) siguen siendo editables por cualquiera.
if ($ficha['ID_CREADOR'] !== null && intval($ficha['ID_CREADOR']) !== $usuarioId) {
    header('Location: fichas_tecnicas_creadas.php?msg=sin_permiso');
    exit;
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception("Token CSRF inválido. Recargue la página e intente de nuevo.");
        }

        $nombreItem   = trim($_POST['nombre_item'] ?? '');
        $codigoUnspsc = trim($_POST['codigo_unspsc'] ?? '');
        $denominacion = trim($_POST['denominacion_tecnica'] ?? '');
        $unidadMedida = trim($_POST['unidad_medida'] ?? '');
        $cantidad     = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;
        $descripcion  = trim($_POST['descripcion_general'] ?? '');
        $comentarios  = trim($_POST['comentarios'] ?? '');

        if ($nombreItem === '' || $denominacion === '' || $unidadMedida === '') {
            throw new Exception("Nombre del ítem, denominación técnica y unidad de medida son obligatorios.");
        }
        if (strlen($nombreItem) > 150) {
            throw new Exception("El nombre del ítem no puede tener más de 150 caracteres.");
        }
        if (strlen($unidadMedida) > 50) {
            throw new Exception("La unidad de medida no puede tener más de 50 caracteres.");
        }
        if ($cantidad <= 0) {
            throw new Exception("La cantidad debe ser un número entero mayor que cero.");
        }
        if ($codigoUnspsc === '') {
            throw new Exception("Debe seleccionar un código UNSPSC del catálogo.");
        }

        $stmtCheckUnspsc = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
        $stmtCheckUnspsc->execute([$codigoUnspsc]);
        if (!$stmtCheckUnspsc->fetchColumn()) {
            throw new Exception("El código UNSPSC ingresado no existe en el catálogo. Selecciónelo de la lista de sugerencias.");
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE ficha_tecnica
            SET NOMBRE_ITEM = ?, CODIGO_UNSPSC_FK = ?, DENOMINACION_TECNICA_BIEN = ?, UNIDAD_MEDIDA = ?, CANTIDAD = ?, DESCRIPCION_GENERAL = ?, COMENTARIOS = ?
            WHERE ID_FICHA_TECNICA = ?
        ");
        $stmtUpdate->execute([$nombreItem, $codigoUnspsc, $denominacion, $unidadMedida, $cantidad, $descripcion, $comentarios, $idFicha]);

        $mensaje = "<div class='alert success'>✓ Ficha Técnica actualizada con éxito.</div>";

        $stmtFicha->execute([$idFicha]);
        $ficha = $stmtFicha->fetch();
    } catch (PDOException $e) {
        error_log('Error actualizando ficha técnica: ' . $e->getMessage());
        $mensaje = "<div class='alert error'>✗ No se pudo actualizar la ficha técnica. Intente de nuevo más tarde.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert error'>✗ Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

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
    <meta name="description" content="Editar ficha técnica de producto en el sistema BICERGAM del SENA.">
    <title>Editar Ficha Técnica - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .ficha-container {
            background: #ffffff;
            border: 2px solid #333;
            max-width: 820px;
            margin: 20px auto 30px;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
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
        }
        .btn-submit:hover { background: linear-gradient(135deg, #2e8600, #206000); }
    </style>
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Instructor Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="../index.php" class="btn-inicio-nav">Inicio</a>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
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
                <h2>Editar Ficha Técnica #<?= htmlspecialchars($ficha['ID_FICHA_TECNICA']) ?></h2>
                <p class="dashboard-subtitle">Esta ficha es parte del catálogo compartido: los cambios se reflejan en cualquier ítem que la use.</p>
            </div>
        </div>

        <?= $mensaje ?>

        <form action="editar_ficha_tecnica.php?id=<?= htmlspecialchars($idFicha) ?>" method="POST" id="form-ficha">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div class="ficha-container">
                <div class="ficha-title">Ficha Técnica de Producto</div>

                <div class="ficha-row">
                    <div class="ficha-label">Nombre del Ítem *</div>
                    <div class="ficha-value">
                        <input type="text" name="nombre_item" id="nombre_item" value="<?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?>" required maxlength="150">
                    </div>
                </div>

                <div class="ficha-row" style="position: relative;">
                    <div class="ficha-label">Código UNSPSC *</div>
                    <div class="ficha-value" style="position: relative;">
                        <input type="text" id="codigo_unspsc_busqueda" autocomplete="off"
                               placeholder="Escriba el nombre o código del producto para buscar"
                               value="<?= htmlspecialchars($ficha['CODIGO_UNSPSC_FK']) ?>" required>
                        <input type="hidden" name="codigo_unspsc" id="codigo_unspsc" value="<?= htmlspecialchars($ficha['CODIGO_UNSPSC_FK']) ?>">
                        <div id="unspsc_resultados" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:20; max-height:220px; overflow-y:auto; box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                    </div>
                </div>
                <div id="unspsc_contexto" style="font-size:12px; color:#666; padding:0 12px 8px; text-align:right;"></div>

                <div class="ficha-section-header">Denominación Técnica del Bien</div>
                <div class="ficha-full-row">
                    <input type="text" name="denominacion_tecnica" id="denominacion_tecnica" value="<?= htmlspecialchars($ficha['DENOMINACION_TECNICA_BIEN']) ?>" required>
                </div>

                <div class="ficha-section-header">Unidad de Medida</div>
                <div class="ficha-full-row" style="text-align:center">
                    <input type="text" name="unidad_medida" id="unidad_medida" value="<?= htmlspecialchars($ficha['UNIDAD_MEDIDA']) ?>" required maxlength="50" style="text-align:center">
                </div>

                <div class="ficha-section-header">Cantidad</div>
                <div class="ficha-full-row" style="text-align:center">
                    <input type="number" name="cantidad" id="cantidad" min="1" value="<?= htmlspecialchars($ficha['CANTIDAD']) ?>" required style="text-align:center">
                </div>

                <div class="ficha-section-header">Descripción General</div>
                <div class="ficha-full-row">
                    <textarea name="descripcion_general" id="descripcion_general" style="height:100px; resize:vertical; width:100%; border:1px solid #bbb; padding:6px 8px; box-sizing:border-box; border-radius:3px; font-size:13px; background:#fafafa;" required><?= htmlspecialchars($ficha['DESCRIPCION_GENERAL']) ?></textarea>
                </div>

                <div class="ficha-section-header">Comentarios / Especificaciones Adicionales</div>
                <div class="ficha-full-row">
                    <textarea name="comentarios" id="comentarios" style="height:60px; resize:vertical; width:100%; border:1px solid #bbb; padding:6px 8px; box-sizing:border-box; border-radius:3px; font-size:13px; background:#fafafa;"><?= htmlspecialchars($ficha['COMENTARIOS'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-submit">Guardar Cambios</button>
        </form>
    </main>
</div>

<script src="../js/apartados.js"></script>
<script src="../js/unspsc-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initUnspscAutocomplete({
        inputSelector: '#codigo_unspsc_busqueda',
        hiddenCodeSelector: '#codigo_unspsc',
        resultsSelector: '#unspsc_resultados',
        searchUrl: '../ajax/buscar_unspsc.php',
        onSelect: function (item) {
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
