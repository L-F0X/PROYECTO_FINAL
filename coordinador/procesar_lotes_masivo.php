<?php
// coordinador/procesar_lotes_masivo.php
require_once '../conexion.php';
require_once '../csrf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: revisar_lotes.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    die('Token CSRF inválido.');
}

$accion = $_POST['accion'] ?? '';
$lotesIds = isset($_POST['lotes']) && is_array($_POST['lotes']) ? array_unique(array_map('intval', $_POST['lotes'])) : [];
$justificacion = trim($_POST['justificacion'] ?? '');
$idCoordinador = intval($_SESSION['usuario_id']);

if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
    header('Location: revisar_lotes.php?msg=error');
    exit;
}
if (empty($lotesIds)) {
    header('Location: revisar_lotes.php?msg=sinseleccion');
    exit;
}
if ($accion === 'rechazar' && $justificacion === '') {
    header('Location: revisar_lotes.php?msg=faltajustificacion');
    exit;
}

$procesados = 0;
$omitidos = 0;
$estadoNuevo = $accion === 'aprobar' ? 'Aprobado' : 'Rechazado';
$justificacionFinal = $accion === 'aprobar' ? 'Lote aprobado por coordinador (acción masiva)' : $justificacion;

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT ESTADO_TRAMITE FROM lote_requerimiento WHERE ID_LOTE = ? FOR UPDATE");
    $stmtUpdate = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = ? WHERE ID_LOTE = ?");
    $stmtAudit = $pdo->prepare("INSERT INTO aprobacion_rechazo_lote (ID_LOTE, ID_COORDINADOR, ESTADO_DECISION, JUSTIFICACION) VALUES (?, ?, ?, ?)");

    foreach ($lotesIds as $idLote) {
        $stmtCheck->execute([$idLote]);
        $estadoActual = $stmtCheck->fetchColumn();

        if ($estadoActual !== 'Enviado') {
            $omitidos++;
            continue;
        }

        $stmtUpdate->execute([$estadoNuevo, $idLote]);
        $stmtAudit->execute([$idLote, $idCoordinador, $estadoNuevo, $justificacionFinal]);
        $procesados++;
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en procesamiento masivo de lotes: ' . $e->getMessage());
    header('Location: revisar_lotes.php?msg=error');
    exit;
}

$msg = $accion === 'aprobar' ? 'masivo_aprobado' : 'masivo_rechazado';
header("Location: revisar_lotes.php?msg=$msg&procesados=$procesados&omitidos=$omitidos");
exit;
