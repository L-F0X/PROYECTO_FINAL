<?php
// instructor/cancelar_envio.php
require_once '../conexion.php';
require_once '../csrf.php';

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
        }
        
        $pdo->commit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Cancelar envío error: ' . $e->getMessage());
    }
}

header("Location: mis_lotes.php?msg=envio_cancelado");
exit;
