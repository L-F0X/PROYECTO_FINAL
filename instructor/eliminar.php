<?php
// eliminar.php - ahora acepta solo POST con token CSRF
require_once '../conexion.php';
require_once '../csrf.php';

// Control de acceso: si no hay sesión activa, denegar el proceso inmediatamente
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $token = $_POST['csrf_token'] ?? '';

    if ($id > 0 && verify_csrf_token($token)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM lote_requerimiento WHERE ID_LOTE = ?");
            $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log('Eliminar lote error: ' . $e->getMessage());
            // No mostrar detalles al usuario
        }
    }
}

header("Location: ../index.php?msg=eliminado");
exit;
?>
