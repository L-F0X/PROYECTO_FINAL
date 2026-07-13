<?php
// coordinador/procesar_lotes_masivo.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';

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
if (count($lotesIds) > 200) {
    // Límite defensivo: evita una transacción excesivamente larga con muchos
    // bloqueos FOR UPDATE simultáneos si llega una solicitud manipulada.
    header('Location: revisar_lotes.php?msg=error');
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

// Las notificaciones se acumulan aquí y se envían recién después del commit:
// crear_notificacion/notificar_por_rol pueden ejecutar un CREATE TABLE (para
// asegurar que la tabla de notificaciones exista), y ese DDL haría un commit
// implícito de esta transacción si se llamara mientras sigue abierta.
$notificacionesPendientes = [];

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT ESTADO_TRAMITE, LOTE_NOMBRE, ID_SOLICITANTE FROM lote_requerimiento WHERE ID_LOTE = ? FOR UPDATE");
    $stmtUpdate = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = ? WHERE ID_LOTE = ?");
    $stmtAudit = $pdo->prepare("INSERT INTO aprobacion_rechazo_lote (ID_LOTE, ID_COORDINADOR, ESTADO_DECISION, JUSTIFICACION) VALUES (?, ?, ?, ?)");
    // Sincroniza el estado de los ítems enviados con la decisión — igual que
    // aprobar_lote.php/rechazar_lote.php, para que no se queden mostrando
    // "Pendiente" para siempre aunque el lote ya esté decidido.
    $stmtItems = $pdo->prepare("UPDATE matriz_item SET ESTADO_ITEM = ? WHERE ID_LOTE = ? AND ESTADO_ITEM = 'Pendiente'");

    foreach ($lotesIds as $idLote) {
        $stmtCheck->execute([$idLote]);
        $loteFila = $stmtCheck->fetch();

        if (!$loteFila || $loteFila['ESTADO_TRAMITE'] !== 'Enviado') {
            $omitidos++;
            continue;
        }

        $stmtUpdate->execute([$estadoNuevo, $idLote]);
        $stmtItems->execute([$estadoNuevo, $idLote]);
        $stmtAudit->execute([$idLote, $idCoordinador, $estadoNuevo, $justificacionFinal]);

        $verbo = $accion === 'aprobar' ? 'fue aprobado' : 'fue rechazado';
        $notificacionesPendientes[] = [
            'tipo' => 'usuario',
            'id_usuario' => intval($loteFila['ID_SOLICITANTE']),
            'mensaje' => "Tu lote '" . $loteFila['LOTE_NOMBRE'] . "' $verbo.",
            'enlace' => "../instructor/mis_lotes.php",
        ];
        if ($accion === 'aprobar') {
            $notificacionesPendientes[] = [
                'tipo' => 'rol',
                'rol' => 'Almacenista',
                'mensaje' => "El lote '" . $loteFila['LOTE_NOMBRE'] . "' fue aprobado y ya puede certificarse.",
                'enlace' => "../almacenista/index.php?tab=instructor",
            ];
        }

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

foreach ($notificacionesPendientes as $n) {
    if ($n['tipo'] === 'usuario') {
        crear_notificacion($pdo, $n['id_usuario'], $n['mensaje'], $n['enlace']);
    } else {
        notificar_por_rol($pdo, $n['rol'], $n['mensaje'], $n['enlace']);
    }
}

$msg = $accion === 'aprobar' ? 'masivo_aprobado' : 'masivo_rechazado';
header("Location: revisar_lotes.php?msg=$msg&procesados=$procesados&omitidos=$omitidos");
exit;
