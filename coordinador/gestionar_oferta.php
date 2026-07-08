<?php
// coordinador/gestionar_oferta.php
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

function recalcular_promedios_item(PDO $pdo, int $idMatrizItem): void {
    $stmt = $pdo->prepare("SELECT OFERTA_1, OFERTA_2, OFERTA_3 FROM matriz_item WHERE ID_MATRIZ_ITEM = ?");
    $stmt->execute([$idMatrizItem]);
    $item = $stmt->fetch();
    $idsCotizacion = array_filter([$item['OFERTA_1'] ?? null, $item['OFERTA_2'] ?? null, $item['OFERTA_3'] ?? null]);

    $valorUnitarioProm = null;
    $valorTotalProm = null;

    if (!empty($idsCotizacion)) {
        $placeholders = implode(',', array_fill(0, count($idsCotizacion), '?'));
        $stmtCot = $pdo->prepare("SELECT VALOR_UNITARIO, VALOR_TOTAL FROM cotizacion WHERE ID_COTIZACION IN ($placeholders)");
        $stmtCot->execute(array_values($idsCotizacion));
        $cotizaciones = $stmtCot->fetchAll();

        if (!empty($cotizaciones)) {
            $sumaUnitario = array_sum(array_column($cotizaciones, 'VALOR_UNITARIO'));
            $sumaTotal = array_sum(array_column($cotizaciones, 'VALOR_TOTAL'));
            $count = count($cotizaciones);
            $valorUnitarioProm = (int) round($sumaUnitario / $count);
            $valorTotalProm = (int) round($sumaTotal / $count);
        }
    }

    $pdo->prepare("UPDATE matriz_item SET VALOR_UNITARIO_PROMEDIO = ?, VALOR_TOTAL_PROMEDIO = ? WHERE ID_MATRIZ_ITEM = ?")
        ->execute([$valorUnitarioProm, $valorTotalProm, $idMatrizItem]);
}

$accion = $_POST['accion'] ?? '';
$idMatrizItem = isset($_POST['id_matriz_item']) ? intval($_POST['id_matriz_item']) : 0;

// Cargar el ítem y validar que su lote sea visible para el coordinador (no Borrador)
$stmtItem = $pdo->prepare("
    SELECT mi.ID_MATRIZ_ITEM, mi.ID_LOTE, mi.ID_IVA, mi.OFERTA_1, mi.OFERTA_2, mi.OFERTA_3, lr.ESTADO_TRAMITE
    FROM matriz_item mi
    INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE
    WHERE mi.ID_MATRIZ_ITEM = ?
");
$stmtItem->execute([$idMatrizItem]);
$item = $stmtItem->fetch();

if (!$item || $item['ESTADO_TRAMITE'] !== 'Enviado') {
    header('Location: revisar_lotes.php?msg=error');
    exit;
}
$idLote = intval($item['ID_LOTE']);

if ($accion === 'agregar') {
    // El coordinador solo aprueba/revisa lotes; registrar ofertas y proveedores
    // es responsabilidad del almacenista (ver Fase 39/43).
    header("Location: revisar_lote.php?id=$idLote&msg=oferta_error&detalle=" . urlencode('El coordinador ya no registra ofertas. Esa gestión corresponde al almacenista.'));
    exit;
} elseif ($accion === 'quitar') {
    $idCotizacion = isset($_POST['id_cotizacion']) ? intval($_POST['id_cotizacion']) : 0;

    $slotAEliminar = null;
    foreach (['OFERTA_1', 'OFERTA_2', 'OFERTA_3'] as $slot) {
        if (intval($item[$slot] ?? 0) === $idCotizacion) {
            $slotAEliminar = $slot;
            break;
        }
    }
    if ($slotAEliminar === null) {
        header("Location: revisar_lote.php?id=$idLote&msg=oferta_error&detalle=" . urlencode('La oferta indicada no pertenece a este ítem.'));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE matriz_item SET $slotAEliminar = NULL WHERE ID_MATRIZ_ITEM = ?")->execute([$idMatrizItem]);
        $pdo->prepare("DELETE FROM cotizacion WHERE ID_COTIZACION = ?")->execute([$idCotizacion]);
        recalcular_promedios_item($pdo, $idMatrizItem);
        $pdo->commit();
        header("Location: revisar_lote.php?id=$idLote&msg=oferta_quitada");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error quitando oferta: ' . $e->getMessage());
        header("Location: revisar_lote.php?id=$idLote&msg=oferta_error&detalle=" . urlencode('No se pudo quitar la oferta.'));
        exit;
    }
} else {
    header("Location: revisar_lote.php?id=$idLote");
    exit;
}
