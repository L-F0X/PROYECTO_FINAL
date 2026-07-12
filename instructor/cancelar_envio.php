<?php
// instructor/cancelar_envio.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis_lotes.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    die('Token CSRF inválido.');
}

$usuarioId = intval($_SESSION['usuario_id']);
$idLote = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($idLote > 0) {
    $loteNombre = null;
    try {
        $pdo->beginTransaction();

        // Solo se puede cancelar mientras el coordinador aún no ha tomado una
        // decisión (ESTADO_TRAMITE sigue en 'Enviado'); si ya fue Aprobado o
        // Rechazado, esta condición simplemente no aplica y no hace nada.
        $stmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Borrador' WHERE ID_LOTE = ? AND ID_SOLICITANTE = ? AND ESTADO_TRAMITE = 'Enviado'");
        $stmt->execute([$idLote, $usuarioId]);

        if ($stmt->rowCount() > 0) {
            $stmtItems = $pdo->prepare("UPDATE matriz_item SET ESTADO_ITEM = 'Borrador' WHERE ID_LOTE = ? AND ESTADO_ITEM = 'Pendiente'");
            $stmtItems->execute([$idLote]);

            $loteNombre = $pdo->prepare("SELECT LOTE_NOMBRE FROM lote_requerimiento WHERE ID_LOTE = ?");
            $loteNombre->execute([$idLote]);
            $loteNombre = $loteNombre->fetchColumn();
        }

        $pdo->commit();

        // Si un coordinador tiene abierta la revisión de este lote (o de una
        // de sus fichas técnicas) en este momento, avisarle en vivo y que esa
        // vista se cierre sola, en vez de dejarlo viendo un "Enviado" que ya
        // no existe. Se dirige al canal "lote_X"; ver revisar_lote.php y
        // ver_ficha_tecnica.php en coordinador/ para quién se une a él.
        if ($loteNombre !== null) {
            push_ws_evento([
                'destino_tipo' => 'canal',
                'destino' => 'lote_' . $idLote,
                'evento' => 'lote_cancelado',
                'data' => [
                    'id_lote' => $idLote,
                    'mensaje' => "El instructor canceló el envío del lote '" . $loteNombre . "'. Ya no está pendiente de revisión.",
                ],
            ]);
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Cancelar envío error: ' . $e->getMessage());
    }
}

header("Location: mis_lotes.php?msg=envio_cancelado");
exit;
