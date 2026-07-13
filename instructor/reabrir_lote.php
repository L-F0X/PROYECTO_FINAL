<?php
// instructor/reabrir_lote.php
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

        $stmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Borrador' WHERE ID_LOTE = ? AND ID_SOLICITANTE = ? AND ESTADO_TRAMITE = 'Rechazado'");
        $stmt->execute([$idLote, $usuarioId]);

        // Los ítems que ya se habían enviado (ESTADO_ITEM='Rechazado', o el
        // 'Pendiente' que rechazar_lote.php ya no deja desde este parche)
        // deben volver a 'Borrador' junto con el lote; si no, quedan en un
        // limbo: no seleccionables para reenviar (fichas_tecnicas_creadas.php
        // solo deja marcar ítems en Borrador) y a la vez invisibles para el
        // coordinador (revisar_lote.php no muestra lotes en Borrador).
        if ($stmt->rowCount() > 0) {
            $stmtItems = $pdo->prepare("UPDATE matriz_item SET ESTADO_ITEM = 'Borrador' WHERE ID_LOTE = ? AND ESTADO_ITEM IN ('Pendiente', 'Rechazado')");
            $stmtItems->execute([$idLote]);
        }

        $pdo->commit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reabrir lote error: ' . $e->getMessage());
    }
}

header("Location: mis_lotes.php?msg=reabierto");
exit;
