<?php
// instructor/configurar_matriz.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';
require_once '../texto_helper.php';
require_once '../cotizacion_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rol = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rol !== 'instructor') {
    header('Location: ../index.php');
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);
$idFicha = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idFicha <= 0) {
    header('Location: fichas_tecnicas_creadas.php');
    exit;
}

$stmtFicha = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
$stmtFicha->execute([$idFicha]);
$ficha = $stmtFicha->fetch();
if (!$ficha) {
    header('Location: fichas_tecnicas_creadas.php');
    exit;
}

// Cargar matriz_item y necesidad asociados
$id_matriz_item = intval($ficha['ID_MATRIZ_ITEM'] ?? 0);
$matriz_item = null;
$necesidad = null;
if ($id_matriz_item > 0) {
    // Verificar que el ítem pertenezca a un lote del instructor autenticado
    // (evita que un instructor vea/edite la matriz de otro instructor
    // cambiando el parámetro ?id= — mismo patrón que matriz.php).
    $stmtOwner = $pdo->prepare("SELECT 1 FROM matriz_item mi INNER JOIN lote_requerimiento lr ON mi.ID_LOTE = lr.ID_LOTE WHERE mi.ID_MATRIZ_ITEM = ? AND lr.ID_SOLICITANTE = ?");
    $stmtOwner->execute([$id_matriz_item, $usuarioId]);
    if (!$stmtOwner->fetchColumn()) {
        header('Location: fichas_tecnicas_creadas.php');
        exit;
    }

    $stmtMatriz = $pdo->prepare("SELECT * FROM matriz_item WHERE ID_MATRIZ_ITEM = ? LIMIT 1");
    $stmtMatriz->execute([$id_matriz_item]);
    $matriz_item = $stmtMatriz->fetch();
    
    if ($matriz_item && $matriz_item['ID_NECESIDAD']) {
        $stmtNec = $pdo->prepare("SELECT * FROM necesidad WHERE ID_NECESIDAD = ? LIMIT 1");
        $stmtNec->execute([$matriz_item['ID_NECESIDAD']]);
        $necesidad = $stmtNec->fetch();
    }
}

// Cargar instructores de apoyo
$instructors = [];
try {
    $stmtRole = $pdo->query("SELECT ID_ROL FROM rol WHERE LOWER(NOMBRE_ROL) = 'instructor' LIMIT 1");
    $roleId = $stmtRole->fetchColumn();
    if ($roleId) {
        $stmtIns = $pdo->prepare("SELECT ID_USUARIO, NOMBRE, APELLIDO FROM usuario WHERE ID_ROL = ? AND ID_USUARIO != ? ORDER BY NOMBRE, APELLIDO");
        $stmtIns->execute([$roleId, $usuarioId]);
        $instructors = $stmtIns->fetchAll();
    }
} catch (Exception $e) {
    error_log('Error cargando instructores: ' . $e->getMessage());
}

// Catálogo de proveedores (para elegir uno al registrar una oferta) y el
// detalle real de las ofertas ya vinculadas a este ítem (proveedor + valor,
// no un número suelto — matriz_item.OFERTA_1/2/3 son en realidad claves hacia
// cotizacion.ID_COTIZACION, así el coordinador sí puede ver estas ofertas
// al revisar el lote).
$proveedoresCatalogo = $pdo->query("SELECT ID_PROVEEDOR, RAZON_SOCIAL FROM proveedor ORDER BY RAZON_SOCIAL")->fetchAll();

function cargar_ofertas_detalle(PDO $pdo, ?array $matrizItem): array {
    $slots = [];
    foreach (['OFERTA_1', 'OFERTA_2', 'OFERTA_3'] as $col) {
        $idCotizacion = $matrizItem[$col] ?? null;
        if (empty($idCotizacion)) {
            $slots[] = null;
            continue;
        }
        $stmt = $pdo->prepare("SELECT c.ID_COTIZACION, c.VALOR_UNITARIO, p.RAZON_SOCIAL FROM cotizacion c INNER JOIN proveedor p ON c.ID_PROVEEDOR = p.ID_PROVEEDOR WHERE c.ID_COTIZACION = ?");
        $stmt->execute([$idCotizacion]);
        $slots[] = $stmt->fetch() ?: null;
    }
    return $slots;
}
$ofertasDetalle = cargar_ofertas_detalle($pdo, $matriz_item);

// Mapeo de estrategias académicas
$estrategiasMap = [
    'cantidad_regular' => ['label' => 'Regular', 'col' => 'CANTIDAD_REGULAR'],
    'cantidad_campesina_complementaria' => ['label' => 'Campesina Complementaria', 'col' => 'CANTIDAD_CAMPESINA_COMPLEMENTARIA'],
    'cantidad_campesina_titulada' => ['label' => 'Campesina Titulada', 'col' => 'CANTIDAD_CAMPESINA_TITULADA'],
    'cantidad_vulnerable' => ['label' => 'Vulnerable', 'col' => 'CANTIDAD_VULNERABLE'],
    'cantidad_media_tecnica' => ['label' => 'Media Técnica', 'col' => 'CANTIDAD_MEDIA_TECNICA'],
    'cantidad_fic' => ['label' => 'FIC', 'col' => 'CANTIDAD_FIC'],
    'cantidad_economia_popular' => ['label' => 'Economía Popular', 'col' => 'CANTIDAD_ECONOMIA_POPULAR'],
    'cantidad_eni' => ['label' => 'ENI', 'col' => 'CANTIDAD_ENI'],
    'cantidad_fc_campesina' => ['label' => 'FC Campesina', 'col' => 'CANTIDAD_FC_CAMPESINA'],
];

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'guardar') === 'agregar_oferta') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception("Token CSRF inválido. Recargue la página e intente de nuevo.");
        }
        if ($id_matriz_item <= 0) {
            throw new Exception("Este ítem no tiene una fila de matriz asociada.");
        }

        $idProveedor   = isset($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : 0;
        $valorUnitario = isset($_POST['valor_unitario']) ? intval($_POST['valor_unitario']) : 0;

        if ($valorUnitario <= 0 || $valorUnitario > 999999999) {
            throw new Exception("El valor unitario debe ser un número entero entre 1 y 999,999,999.");
        }

        $pdo->beginTransaction();

        if ($idProveedor === 0) {
            // Proveedor nuevo, capturado en el mismo formulario de la oferta.
            $nit         = trim($_POST['nuevo_nit'] ?? '');
            $razonSocial = trim($_POST['nuevo_razon_social'] ?? '');
            $email       = trim($_POST['nuevo_email'] ?? '');

            if ($nit === '' || $razonSocial === '' || $email === '') {
                throw new Exception("Para un proveedor nuevo, NIT, Razón Social y Correo son obligatorios.");
            }
            if (!preg_match('/^[0-9]+$/', $nit) || strlen($nit) > 20) {
                throw new Exception("El NIT del proveedor nuevo debe ser numérico (máx. 20 caracteres).");
            }
            if (strlen($razonSocial) > 150) {
                throw new Exception("La razón social del proveedor nuevo no puede tener más de 150 caracteres.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                throw new Exception("El correo del proveedor nuevo no tiene un formato válido.");
            }

            try {
                $pdo->prepare("INSERT INTO proveedor (NIT, RAZON_SOCIAL, EMAIL) VALUES (?, ?, ?)")->execute([$nit, $razonSocial, $email]);
                $idProveedor = (int) $pdo->lastInsertId();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new Exception("Ya existe un proveedor con ese NIT. Selecciónelo de la lista en vez de crear uno nuevo.");
                }
                throw $e;
            }
        } else {
            $stmtProv = $pdo->prepare("SELECT 1 FROM proveedor WHERE ID_PROVEEDOR = ?");
            $stmtProv->execute([$idProveedor]);
            if (!$stmtProv->fetchColumn()) {
                throw new Exception("El proveedor seleccionado no existe.");
            }
        }

        $stmtItemLock = $pdo->prepare("SELECT ID_IVA, OFERTA_1, OFERTA_2, OFERTA_3 FROM matriz_item WHERE ID_MATRIZ_ITEM = ? FOR UPDATE");
        $stmtItemLock->execute([$id_matriz_item]);
        $itemActual = $stmtItemLock->fetch();
        if (!$itemActual || empty($itemActual['ID_IVA'])) {
            throw new Exception("Este ítem no tiene una tasa de IVA asignada; no se puede registrar la oferta.");
        }

        // Las 3 ofertas deben ser de proveedores distintos (así lo indica la
        // propia sección en pantalla); sin este chequeo se podían registrar
        // 2-3 cotizaciones del mismo proveedor para el mismo ítem.
        $stmtDup = $pdo->prepare("SELECT 1 FROM cotizacion WHERE ID_MATRIZ_ITEM = ? AND ID_PROVEEDOR = ?");
        $stmtDup->execute([$id_matriz_item, $idProveedor]);
        if ($stmtDup->fetchColumn()) {
            throw new Exception("Ya hay una oferta de este proveedor para este ítem. Las 3 ofertas deben ser de proveedores distintos.");
        }

        $slotLibre = null;
        foreach (['OFERTA_1', 'OFERTA_2', 'OFERTA_3'] as $slot) {
            if (empty($itemActual[$slot])) {
                $slotLibre = $slot;
                break;
            }
        }
        if ($slotLibre === null) {
            throw new Exception("Este ítem ya tiene las 3 ofertas máximas registradas.");
        }

        $stmtInsertCot = $pdo->prepare("INSERT INTO cotizacion (ID_MATRIZ_ITEM, ID_PROVEEDOR, ID_IVA, VALOR_UNITARIO, VALOR_TOTAL) VALUES (?, ?, ?, ?, 0)");
        $stmtInsertCot->execute([$id_matriz_item, $idProveedor, $itemActual['ID_IVA'], $valorUnitario]);
        $idCotizacion = $pdo->lastInsertId();

        $pdo->prepare("UPDATE matriz_item SET $slotLibre = ? WHERE ID_MATRIZ_ITEM = ?")->execute([$idCotizacion, $id_matriz_item]);
        recalcular_promedios_item($pdo, $id_matriz_item);

        $pdo->commit();
        $mensaje = "<div class='alert success'>✓ Oferta registrada correctamente.</div>";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error agregando oferta: ' . $e->getMessage());
        $mensaje = "<div class='alert error'>✗ No se pudo registrar la oferta. Intente de nuevo más tarde.</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = "<div class='alert error'>✗ " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    if ($id_matriz_item > 0) {
        $stmtMatriz->execute([$id_matriz_item]);
        $matriz_item = $stmtMatriz->fetch();
    }
    $ofertasDetalle = cargar_ofertas_detalle($pdo, $matriz_item);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar_oferta') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception("Token CSRF inválido. Recargue la página e intente de nuevo.");
        }
        $idCotizacion = isset($_POST['id_cotizacion']) ? intval($_POST['id_cotizacion']) : 0;

        $slotAEliminar = null;
        foreach (['OFERTA_1', 'OFERTA_2', 'OFERTA_3'] as $slot) {
            if (intval($matriz_item[$slot] ?? 0) === $idCotizacion) {
                $slotAEliminar = $slot;
                break;
            }
        }
        if ($slotAEliminar === null) {
            throw new Exception("La oferta indicada no pertenece a este ítem.");
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE matriz_item SET $slotAEliminar = NULL WHERE ID_MATRIZ_ITEM = ?")->execute([$id_matriz_item]);
        $pdo->prepare("DELETE FROM cotizacion WHERE ID_COTIZACION = ?")->execute([$idCotizacion]);
        recalcular_promedios_item($pdo, $id_matriz_item);
        $pdo->commit();
        $mensaje = "<div class='alert success'>✓ Oferta eliminada correctamente.</div>";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error quitando oferta: ' . $e->getMessage());
        $mensaje = "<div class='alert error'>✗ No se pudo quitar la oferta.</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = "<div class='alert error'>✗ " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    if ($id_matriz_item > 0) {
        $stmtMatriz->execute([$id_matriz_item]);
        $matriz_item = $stmtMatriz->fetch();
    }
    $ofertasDetalle = cargar_ofertas_detalle($pdo, $matriz_item);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false;
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception("Token CSRF inválido. Recargue la página e intente de nuevo.");
        }

        $cantidad         = isset($_POST['cantidad_total_calculada']) ? intval($_POST['cantidad_total_calculada']) : 1;
        $instructorApoyo  = isset($_POST['instructor_apoyo']) && $_POST['instructor_apoyo'] !== '' ? intval($_POST['instructor_apoyo']) : null;
        $cantidades       = isset($_POST['cantidades_estrategias']) ? $_POST['cantidades_estrategias'] : [];

        if ($cantidad <= 0) {
            throw new Exception("La cantidad debe ser un número entero mayor que cero.");
        }
        if ($cantidad > 100000) {
            throw new Exception("La cantidad no puede ser mayor a 100,000 unidades.");
        }

        // Iniciar transacción atómica
        $pdo->beginTransaction();
        $transactionStarted = true;

        if ($id_matriz_item > 0) {
            $stmtUpdateMatriz = $pdo->prepare("
                UPDATE matriz_item
                SET INSTRUCTOR_APOYO = ?, CANTIDAD_REGULAR = ?
                WHERE ID_MATRIZ_ITEM = ?
            ");
            $stmtUpdateMatriz->execute([$instructorApoyo, $cantidad, $id_matriz_item]);

            // El trigger TR_CALCULAR_TOTAL_COTIZACION solo recalcula
            // cotizacion.VALOR_TOTAL en el INSERT de la oferta, no cuando la
            // cantidad del ítem cambia después — si ya hay ofertas
            // registradas, hay que recalcular sus VALOR_TOTAL a mano con la
            // misma fórmula del trigger (cantidad × valor unitario × (1+IVA%))
            // para que no queden desactualizados.
            $stmtCotItem = $pdo->prepare("SELECT ID_COTIZACION, VALOR_UNITARIO, ID_IVA FROM cotizacion WHERE ID_MATRIZ_ITEM = ?");
            $stmtCotItem->execute([$id_matriz_item]);
            $cotizacionesExistentes = $stmtCotItem->fetchAll();
            if (!empty($cotizacionesExistentes)) {
                $stmtIva = $pdo->prepare("SELECT PORCENTAJE FROM iva WHERE ID_IVA = ?");
                $stmtUpdateCot = $pdo->prepare("UPDATE cotizacion SET VALOR_TOTAL = ? WHERE ID_COTIZACION = ?");
                foreach ($cotizacionesExistentes as $cot) {
                    $stmtIva->execute([$cot['ID_IVA']]);
                    $porcentajeIva = (float) $stmtIva->fetchColumn();
                    $nuevoTotal = (int) round($cantidad * $cot['VALOR_UNITARIO'] * (1 + $porcentajeIva / 100));
                    $stmtUpdateCot->execute([$nuevoTotal, $cot['ID_COTIZACION']]);
                }
                recalcular_promedios_item($pdo, $id_matriz_item);
            }

            // Sincronizar Ficha Técnica (si es necesario) con la nueva cantidad
            $stmtUpdateFicha = $pdo->prepare("UPDATE ficha_tecnica SET CANTIDAD = ? WHERE ID_FICHA_TECNICA = ?");
            $stmtUpdateFicha->execute([$cantidad, $idFicha]);

            // Procesar estrategias académicas
            $hasStrategies = false;
            foreach ($cantidades as $cKey => $cVal) {
                if (intval($cVal) > 0) {
                    $hasStrategies = true;
                    break;
                }
            }

            if ($hasStrategies) {
                $id_necesidad = isset($matriz_item['ID_NECESIDAD']) ? intval($matriz_item['ID_NECESIDAD']) : 0;
                $strategyValues = [];
                foreach ($estrategiasMap as $key => $info) {
                    $strategyValues[$info['col']] = isset($cantidades[$key]) ? max(0, intval($cantidades[$key])) : 0;
                }

                if ($id_necesidad > 0) {
                    $sqlUpdateNeed = "UPDATE necesidad SET 
                        CANTIDAD_REGULAR = ?, 
                        CANTIDAD_CAMPESINA_COMPLEMENTARIA = ?, 
                        CANTIDAD_CAMPESINA_TITULADA = ?, 
                        CANTIDAD_VULNERABLE = ?, 
                        CANTIDAD_MEDIA_TECNICA = ?, 
                        CANTIDAD_FIC = ?, 
                        CANTIDAD_ECONOMIA_POPULAR = ?, 
                        CANTIDAD_ENI = ?, 
                        CANTIDAD_FC_CAMPESINA = ?,
                        CANTIDAD_NESECIDAD = ?
                        WHERE ID_NECESIDAD = ?";
                    $pdo->prepare($sqlUpdateNeed)->execute([
                        $strategyValues['CANTIDAD_REGULAR'],
                        $strategyValues['CANTIDAD_CAMPESINA_COMPLEMENTARIA'],
                        $strategyValues['CANTIDAD_CAMPESINA_TITULADA'],
                        $strategyValues['CANTIDAD_VULNERABLE'],
                        $strategyValues['CANTIDAD_MEDIA_TECNICA'],
                        $strategyValues['CANTIDAD_FIC'],
                        $strategyValues['CANTIDAD_ECONOMIA_POPULAR'],
                        $strategyValues['CANTIDAD_ENI'],
                        $strategyValues['CANTIDAD_FC_CAMPESINA'],
                        $cantidad,
                        $id_necesidad
                    ]);
                } else {
                    $stmtMaxNeed = $pdo->query("SELECT COALESCE(MAX(ID_NECESIDAD), 0) + 1 FROM necesidad");
                    $new_id_necesidad = intval($stmtMaxNeed->fetchColumn());

                    $sqlInsertNeed = "INSERT INTO necesidad (
                        ID_NECESIDAD, ID_MATRIZ, CANTIDAD_REGULAR, CANTIDAD_NESECIDAD,
                        CANTIDAD_CAMPESINA_COMPLEMENTARIA, CANTIDAD_CAMPESINA_TITULADA,
                        CANTIDAD_VULNERABLE, CANTIDAD_MEDIA_TECNICA, CANTIDAD_FIC,
                        CANTIDAD_ECONOMIA_POPULAR, CANTIDAD_ENI, CANTIDAD_FC_CAMPESINA
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sqlInsertNeed)->execute([
                        $new_id_necesidad,
                        $id_matriz_item,
                        $strategyValues['CANTIDAD_REGULAR'], 
                        $cantidad,
                        $strategyValues['CANTIDAD_CAMPESINA_COMPLEMENTARIA'],
                        $strategyValues['CANTIDAD_CAMPESINA_TITULADA'],
                        $strategyValues['CANTIDAD_VULNERABLE'],
                        $strategyValues['CANTIDAD_MEDIA_TECNICA'],
                        $strategyValues['CANTIDAD_FIC'],
                        $strategyValues['CANTIDAD_ECONOMIA_POPULAR'],
                        $strategyValues['CANTIDAD_ENI'],
                        $strategyValues['CANTIDAD_FC_CAMPESINA']
                    ]);
                    
                    $pdo->prepare("UPDATE matriz_item SET ID_NECESIDAD = ? WHERE ID_MATRIZ_ITEM = ?")->execute([$new_id_necesidad, $id_matriz_item]);
                }
            } else {
                $id_necesidad = isset($matriz_item['ID_NECESIDAD']) ? intval($matriz_item['ID_NECESIDAD']) : 0;
                if ($id_necesidad > 0) {
                    $pdo->prepare("UPDATE necesidad SET 
                        CANTIDAD_REGULAR = ?, CANTIDAD_CAMPESINA_COMPLEMENTARIA = 0, CANTIDAD_CAMPESINA_TITULADA = 0,
                        CANTIDAD_VULNERABLE = 0, CANTIDAD_MEDIA_TECNICA = 0, CANTIDAD_FIC = 0,
                        CANTIDAD_ECONOMIA_POPULAR = 0, CANTIDAD_ENI = 0, CANTIDAD_FC_CAMPESINA = 0, CANTIDAD_NESECIDAD = ?
                        WHERE ID_NECESIDAD = ?")->execute([$cantidad, $cantidad, $id_necesidad]);
                }
            }
        }

        $pdo->commit();
        $mensaje = "<div class='alert success'>✓ Matriz configurada con éxito.</div>";

        // Recargar matriz y necesidad
        if ($id_matriz_item > 0) {
            $stmtMatriz->execute([$id_matriz_item]);
            $matriz_item = $stmtMatriz->fetch();
            if ($matriz_item && $matriz_item['ID_NECESIDAD']) {
                $stmtNec = $pdo->prepare("SELECT * FROM necesidad WHERE ID_NECESIDAD = ? LIMIT 1");
                $stmtNec->execute([$matriz_item['ID_NECESIDAD']]);
                $necesidad = $stmtNec->fetch();
            }
        }
    } catch (PDOException $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error actualizando matriz: ' . $e->getMessage());
        $mensaje = "<div class='alert error'>✗ No se pudo configurar la matriz. Intente de nuevo más tarde.</div>";
    } catch (Exception $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = "<div class='alert error'>✗ Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . $usuarioId . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . $usuarioId . '.' . $ext;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Matriz - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .ficha-container {
            background: #ffffff;
            border: 2px solid #333;
            max-width: 820px;
            margin: 20px auto 30px;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .ficha-title {
            text-align: center;
            border-bottom: 2px solid #333;
            padding: 12px 15px;
            background-color: #e8e8e8;
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .ficha-row {
            display: flex;
            border-bottom: 1px solid #aaa;
        }
        .ficha-row:last-child { border-bottom: none; }
        .ficha-label {
            width: 35%;
            background: #f0f0f0;
            padding: 10px 12px;
            font-weight: bold;
            border-right: 1px solid #aaa;
            display: flex;
            align-items: center;
            text-transform: uppercase;
            font-size: 12px;
        }
        .ficha-value {
            width: 65%;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        .ficha-value input[type="text"],
        .ficha-value input[type="number"],
        .ficha-value select {
            width: 100%;
            border: 1px solid #bbb;
            padding: 6px 8px;
            box-sizing: border-box;
            border-radius: 3px;
            font-size: 13px;
            background: #fafafa;
        }
        .ficha-value input:focus,
        .ficha-value select:focus { outline: 2px solid #39a900; background: #fff; }
        .ficha-section-header {
            background: #d8d8d8;
            border-bottom: 1px solid #aaa;
            padding: 9px 12px;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 5px;
            font-weight: 500;
            max-width: 820px;
            margin-left: auto;
            margin-right: auto;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-submit {
            background: linear-gradient(135deg, #39a900, #2e8600);
            color: white;
            padding: 13px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-block;
        }
        .btn-submit:hover { background: linear-gradient(135deg, #2e8600, #246a00); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .form-actions {
            text-align: center;
            margin: 25px 0 15px;
            padding: 15px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }

        /* Strategies Accordion */
        .estrategias-accordion {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
            background: #fff;
        }
        .estrategias-header {
            background: #f5f5f5;
            padding: 10px 15px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        .estrategias-header:hover { background: #eeeeee; }
        .estrategias-header .toggle-icon {
            transition: transform 0.3s;
            font-size: 12px;
        }
        .estrategias-header.active .toggle-icon { transform: rotate(180deg); }
        .estrategias-content {
            display: none;
            padding: 15px;
            background: #ffffff;
        }
        .estrategias-content.active { display: block; }
        .estrategia-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .estrategia-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .estrategia-item label {
            font-size: 12px;
            font-weight: bold;
            color: #444;
        }
        .estrategia-item input {
            padding: 6px 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .ofertas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            width: 100%;
        }
        .oferta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .oferta-item label {
            font-size: 12px;
            font-weight: bold;
            color: #444;
        }
    </style>
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Instructor</span></h1>
            <div class="user-greeting">Instructor Solicitante: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong> <span class="role-badge">(<?= htmlspecialchars($_SESSION['rol_nombre']) ?>)</span></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
        <?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, $usuarioId); $wsToken = generar_ws_token($pdo, $usuarioId, $_SESSION['rol_nombre'] ?? ''); ?>
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?></a>
        <a href="instructor_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="mis_lotes.php" class="sidebar-link">Mis Lotes</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="actions-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2>Configurar Matriz del Ítem</h2>
                <p class="dashboard-subtitle">Configuración de instructores de apoyo, estrategias y ofertas.</p>
            </div>
            <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($matriz_item['ID_LOTE'] ?? '') ?>" class="btn btn-secondary" style="text-decoration: none; padding: 10px 18px; border: 1px solid #ccc; background-color: #eee; color: #333;">Volver al Catálogo</a>
        </div>

        <?= $mensaje ?>

        <!-- El "Guardar Configuración" es un form aparte (no envuelve todo el
        contenedor) porque cada oferta de mercado, más abajo, es su propio
        <form> independiente — un <form> no puede anidar otro <form> en HTML.
        Los campos de instructor/estrategias se conectan a este vía el
        atributo form="...", mismo patrón que ya usa proveedores.php. -->
        <form method="POST" action="configurar_matriz.php?id=<?= $idFicha ?>" id="form-guardar-matriz">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
        </form>
        <input type="hidden" name="cantidad_total_calculada" id="cantidad_total_calculada" form="form-guardar-matriz" value="<?= htmlspecialchars($matriz_item['CANTIDAD_REGULAR'] ?? 1) ?>">

            <div class="ficha-container">
                <div class="ficha-title">Configuración de Matriz (ID: <?= htmlspecialchars($id_matriz_item) ?>)</div>
                
                <div class="ficha-row">
                    <div class="ficha-label">Nombre del Ítem</div>
                    <div class="ficha-value">
                        <input type="text" value="<?= htmlspecialchars($ficha['NOMBRE_ITEM']) ?>" readonly style="background: #e9ecef; cursor: not-allowed; border-color: #ccc;">
                    </div>
                </div>

                <div class="ficha-section-header">Asignaciones</div>

                <div class="ficha-row">
                    <div class="ficha-label">Instructor de Apoyo (Opcional)</div>
                    <div class="ficha-value">
                        <select name="instructor_apoyo" form="form-guardar-matriz">
                            <option value="">-- Ninguno --</option>
                            <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['ID_USUARIO'] ?>" <?= ($matriz_item && $matriz_item['INSTRUCTOR_APOYO'] == $inst['ID_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['NOMBRE'] . ' ' . $inst['APELLIDO']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ficha-section-header">Ofertas de Mercado (hasta 3, de proveedores distintos)</div>

                <div class="ficha-row" style="padding: 15px;">
                    <div class="ofertas-grid">
                        <?php foreach ([1, 2, 3] as $n): ?>
                            <?php $of = $ofertasDetalle[$n - 1] ?? null; ?>
                            <div class="oferta-item">
                                <label>Oferta <?= $n ?></label>
                                <?php if ($of): ?>
                                    <div style="border: 1px solid #cfe8c0; background: #f4faf1; border-radius: 4px; padding: 8px 10px; font-size: 12px;">
                                        <div style="font-weight: bold;"><?= htmlspecialchars($of['RAZON_SOCIAL']) ?></div>
                                        <div>$<?= number_format($of['VALOR_UNITARIO']) ?> unitario</div>
                                    </div>
                                    <form method="POST" action="configurar_matriz.php?id=<?= $idFicha ?>" style="margin-top: 4px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="quitar_oferta">
                                        <input type="hidden" name="id_cotizacion" value="<?= (int) $of['ID_COTIZACION'] ?>">
                                        <button type="submit" class="js-confirm-submit" data-confirm-title="Quitar oferta" data-confirm-message="¿Quitar esta oferta?" data-confirm-label="Quitar" style="width: 100%; padding: 5px; font-size: 11px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 3px; cursor: pointer;">Quitar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="configurar_matriz.php?id=<?= $idFicha ?>" class="form-agregar-oferta">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="agregar_oferta">
                                        <select name="id_proveedor" class="select-proveedor-oferta" style="margin-bottom: 4px;">
                                            <option value="0">+ Nuevo proveedor...</option>
                                            <?php foreach ($proveedoresCatalogo as $prov): ?>
                                                <option value="<?= $prov['ID_PROVEEDOR'] ?>"><?= htmlspecialchars($prov['RAZON_SOCIAL']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="nuevo-proveedor-campos" style="display:none; flex-direction: column; gap: 4px; margin-bottom: 4px;">
                                            <input type="text" name="nuevo_nit" placeholder="NIT" inputmode="numeric" pattern="[0-9]*" maxlength="20" style="font-size: 12px;">
                                            <input type="text" name="nuevo_razon_social" placeholder="Razón Social" maxlength="150" style="font-size: 12px;">
                                            <input type="email" name="nuevo_email" placeholder="Correo" maxlength="100" style="font-size: 12px;">
                                        </div>
                                        <input type="text" inputmode="numeric" class="input-valor-formateado" placeholder="$ Valor unitario" required style="margin-bottom: 4px;">
                                        <input type="hidden" name="valor_unitario" class="input-valor-real">
                                        <button type="submit" style="width: 100%; padding: 5px; font-size: 11px; background: #39a900; color: white; border: none; border-radius: 3px; cursor: pointer;">+ Agregar Oferta</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ficha-section-header">Estrategias Académicas y Cantidad</div>

                <div class="ficha-row">
                    <div class="ficha-label">Cantidad Total</div>
                    <div class="ficha-value" style="font-weight: bold; font-size: 16px; color: #39a900;" id="display-cantidad-total">
                        <?= htmlspecialchars($matriz_item['CANTIDAD_REGULAR'] ?? 1) ?>
                    </div>
                </div>

                <div class="ficha-row" style="padding: 15px; background: #fafafa; border-top: 1px solid #aaa;">
                    <div class="estrategias-accordion">
                        <div class="estrategias-header" id="estrategias-toggle">
                            <span>► Seleccionar Estrategias Académicas (Distribución)</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="estrategias-content" id="estrategias-content">
                            <div class="estrategia-grid">
                                <?php foreach ($estrategiasMap as $key => $info): ?>
                                    <div class="estrategia-item">
                                        <label><?= htmlspecialchars($info['label']) ?></label>
                                        <input type="number" name="cantidades_estrategias[<?= htmlspecialchars($key) ?>]" class="input-estrategia" form="form-guardar-matriz" min="0" value="<?= $necesidad ? htmlspecialchars($necesidad[$info['col']]) : ($key === 'cantidad_regular' ? htmlspecialchars($matriz_item['CANTIDAD_REGULAR'] ?? 1) : '0') ?>" placeholder="0">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: 15px; text-align: right;">
                                <button type="button" id="btn-limpiar-estrategias" style="padding: 6px 12px; background: #e0e0e0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 12px;">Limpiar Todas</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" form="form-guardar-matriz" class="btn-submit">💾 Guardar Configuración</button>
                </div>
            </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Accordion Logic
    const toggle = document.getElementById('estrategias-toggle');
    const content = document.getElementById('estrategias-content');
    if (toggle && content) {
        toggle.addEventListener('click', function () {
            toggle.classList.toggle('active');
            content.classList.toggle('active');
            const icon = toggle.querySelector('.toggle-icon');
            if (icon) {
                icon.textContent = toggle.classList.contains('active') ? '▲' : '▼';
            }
        });
    }

    // Calculation Logic
    const inputs = document.querySelectorAll('.input-estrategia');
    const displayTotal = document.getElementById('display-cantidad-total');
    const inputTotalHidden = document.getElementById('cantidad_total_calculada');
    const btnLimpiar = document.getElementById('btn-limpiar-estrategias');

    function recomputeTotal() {
        let total = 0;
        inputs.forEach(inp => {
            const val = parseInt(inp.value, 10);
            if (!isNaN(val) && val > 0) {
                total += val;
            }
        });
        if (total === 0) {
            // Fallback si borran todo
            total = 1;
            const regularInp = document.querySelector('input[name="cantidades_estrategias[cantidad_regular]"]');
            if (regularInp) regularInp.value = 1;
        }
        displayTotal.textContent = total;
        inputTotalHidden.value = total;
    }

    inputs.forEach(inp => {
        inp.addEventListener('input', recomputeTotal);
        inp.addEventListener('change', recomputeTotal);
    });

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            inputs.forEach(inp => { inp.value = 0; });
            recomputeTotal();
        });
    }

    // Inicializar total
    recomputeTotal();

    // Ofertas de mercado: mostrar los campos de "nuevo proveedor" solo
    // cuando se elige esa opción en vez de un proveedor ya registrado.
    document.querySelectorAll('.select-proveedor-oferta').forEach(function (select) {
        const campos = select.closest('form').querySelector('.nuevo-proveedor-campos');
        function actualizar() {
            campos.style.display = select.value === '0' ? 'flex' : 'none';
            campos.querySelectorAll('input').forEach(function (inp) {
                inp.required = select.value === '0';
            });
        }
        select.addEventListener('change', actualizar);
        actualizar();
    });

    // Formatea el valor unitario con separador de miles mientras se escribe
    // (igual que se ve en el Excel exportado), guardando el número limpio en
    // un input oculto que es el que en verdad se envía al servidor.
    document.querySelectorAll('.input-valor-formateado').forEach(function (visible) {
        const real = visible.closest('form').querySelector('.input-valor-real');
        visible.addEventListener('input', function () {
            const digitos = visible.value.replace(/\D/g, '');
            real.value = digitos;
            visible.value = digitos ? '$ ' + Number(digitos).toLocaleString('es-CO') : '';
        });
    });
});
</script>
<script src="../js/apartados.js"></script>
<script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
</body>
</html>
