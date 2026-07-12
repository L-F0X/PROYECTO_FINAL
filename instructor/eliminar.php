<?php
// eliminar.php - ahora acepta solo POST con token CSRF
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../auditoria_helper.php';

// Control de acceso: si no hay sesión activa, denegar el proceso inmediatamente
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header("Location: ../index.php");
    exit;
}

$resultado = 'noop';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $token = $_POST['csrf_token'] ?? '';
    $usuarioId = intval($_SESSION['usuario_id']);

    if ($id > 0 && verify_csrf_token($token)) {
        try {
            $stmtLote = $pdo->prepare("SELECT LOTE_NOMBRE, ESTADO_TRAMITE FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
            $stmtLote->execute([$id, $usuarioId]);
            $loteData = $stmtLote->fetch();

            $stmt = $pdo->prepare("DELETE FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
            $stmt->execute([$id, $usuarioId]);
            $resultado = $stmt->rowCount() > 0 ? 'eliminado' : 'noop';

            if ($resultado === 'eliminado' && $loteData) {
                asegurar_tabla_auditoria($pdo);
                $stmtLog = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, ?, ?)");
                $stmtLog->execute([
                    $usuarioId,
                    'Eliminación Lote',
                    "Eliminado lote de requerimiento: {$loteData['LOTE_NOMBRE']} (ID: $id, Estado previo: {$loteData['ESTADO_TRAMITE']})"
                ]);
            }
        } catch (\PDOException $e) {
            error_log('Eliminar lote error: ' . $e->getMessage());
            // Violación de FK: el lote todavía tiene ítems, certificados u otros
            // registros asociados. No se muestra el detalle técnico al usuario.
            $resultado = 'con_dependencias';
        }
    }
}

header("Location: mis_lotes.php?msg=" . $resultado);
exit;
?>
