<?php
// crear.php
require_once 'conexion.php';
require_once 'csrf.php';

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Obtener lista de instructores para el select
$instructors = [];
try {
    $stmtRoles = $pdo->prepare("SELECT ID_ROL FROM rol WHERE LOWER(NOMBRE_ROL) = 'instructor' LIMIT 1");
    $stmtRoles->execute();
    $rolInst = $stmtRoles->fetchColumn();
    if ($rolInst) {
        $stmtIns = $pdo->prepare("SELECT ID_USUARIO, NOMBRE, APELLIDO FROM usuario WHERE ID_ROL = ? ORDER BY NOMBRE, APELLIDO");
        $stmtIns->execute([$rolInst]);
        $instructors = $stmtIns->fetchAll();
    }
} catch (Exception $e) {
    error_log('No se pudo cargar la lista de instructores: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }

    $nombre = trim($_POST['lote_nombre']);
    // permitir seleccionar un instructor por listado o indicar manualmente un ID (incluso negativo para nuevos temporales)
    $solicitante = 0;
    if (isset($_POST['id_solicitante_select']) && $_POST['id_solicitante_select'] !== 'other') {
        $solicitante = intval($_POST['id_solicitante_select']);
    } elseif (!empty($_POST['id_solicitante_manual'])) {
        $solicitante = intval($_POST['id_solicitante_manual']);
    }
    $estado = trim($_POST['estado_tramite']);
    $fecha = $_POST['fecha_creacion'];

    $sql = "INSERT INTO lote_requerimiento (ID_SOLICITANTE, LOTE_NOMBRE, ESTADO_TRAMITE, FECHA_CREACION) 
            VALUES (?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$solicitante, $nombre, $estado, $fecha]);
        header("Location: index.php");
        exit;
    } catch (\PDOException $e) {
        error_log('Crear lote error: ' . $e->getMessage());
        die('Error al crear el lote. Contacte al administrador.');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Lote - BICERGAM</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header>
    <h1>BICERGAM | <span>SENA</span></h1>
    <div style="text-align: right; color: white;">
        Usuario: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> | 
        <a href="index.php" style="color: var(--verde-sena); text-decoration: none; font-weight: bold; margin-right: 15px;">← Volver</a>
        <a href="logout.php" style="color: var(--alerta-rojo); text-decoration: none; font-weight: bold;">Cerrar Sesión</a>
    </div>
</header>

<div class="container fade-in" style="max-width: 600px;">
    <h2>Crear Nuevo Lote de Requerimiento</h2>
    
        <form id="formLote" action="crear.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <div class="form-group">
            <label for="lote_nombre">Nombre del Lote / Requerimiento:</label>
            <input type="text" id="lote_nombre" name="lote_nombre" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="id_solicitante_select">Instructor Solicitante (Líder):</label>
            <select id="id_solicitante_select" name="id_solicitante_select" class="form-control" required>
                <option value="">-- Seleccione un instructor --</option>
                <?php foreach ($instructors as $ins): ?>
                    <option value="<?= htmlspecialchars($ins['ID_USUARIO']) ?>"><?= htmlspecialchars($ins['NOMBRE'] . ' ' . $ins['APELLIDO']) ?></option>
                <?php endforeach; ?>
                <option value="other">Otro (ingresar ID manualmente)</option>
            </select>
            <div style="margin-top:8px; display:none;" id="manualInstructorWrapper">
                <label for="id_solicitante_manual">ID Instructor (manual, puede ser negativo para temporales):</label>
                <input type="number" id="id_solicitante_manual" name="id_solicitante_manual" class="form-control" placeholder="Ej: -1" />
            </div>
        </div>

        <div class="form-group">
            <label for="estado_tramite">Estado del Trámite:</label>
            <select id="estado_tramite" name="estado_tramite" class="form-control">
                <option value="Borrador">Borrador</option>
                <option value="Enviado">Enviado</option>
                <option value="Aprobado">Aprobado</option>
                <option value="Rechazado">Rechazado</option>
            </select>
        </div>

        <div class="form-group">
            <label for="fecha_creacion">Fecha de Creación:</label>
            <input type="date" id="fecha_creacion" name="fecha_creacion" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <button type="submit" class="btn btn-sena" style="width: 100%;">Registrar Pre-Compra</button>
    </form>
</div>

<script src="javascript.js"></script>
<script>
document.getElementById('id_solicitante_select').addEventListener('change', function(e){
    var wrap = document.getElementById('manualInstructorWrapper');
    if(this.value === 'other') {
        wrap.style.display = 'block';
        document.getElementById('id_solicitante_manual').required = true;
    } else {
        wrap.style.display = 'none';
        document.getElementById('id_solicitante_manual').required = false;
    }
});
</script>
</body>
</html>