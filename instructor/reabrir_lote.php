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
        $stmt = $pdo->prepare("UPDATE lote_requerimiento SET ESTADO_TRAMITE = 'Borrador' WHERE ID_LOTE = ? AND ID_SOLICITANTE = ? AND ESTADO_TRAMITE = 'Rechazado'");
        $stmt->execute([$idLote, $usuarioId]);
    } catch (\PDOException $e) {
        error_log('Reabrir lote error: ' . $e->getMessage());
    }
}

header("Location: mis_lotes.php?msg=reabierto");
exit;
