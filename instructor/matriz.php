<?php
// matriz.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';
require_once '../iva_helper.php';

// Lista cerrada de unidades de medida: no todos los materiales/bienes se
// cuentan como "Unidad" (ej. combustibles por Galón, telas por Metro).
$unidadesMedidaEstandar = ['Unidad', 'Caja', 'Paquete', 'Kit', 'Juego', 'Par', 'Docena', 'Rollo', 'Bolsa', 'Galón', 'Litro', 'Metro', 'Metro Cuadrado', 'Kilogramo', 'Gramo'];

function build_need_label(array $need): string {
    $segments = [];

    if (!empty($need['ID_NECESIDAD'])) {
        $segments[] = 'Necesidad #' . intval($need['ID_NECESIDAD']);
    }

    if (!empty($need['ID_MATRIZ'])) {
        $segments[] = 'Matriz #' . intval($need['ID_MATRIZ']);
    }

    $quantityFields = [
        'CANTIDAD_REGULAR' => 'Regular',
        'CANTIDAD_NESECIDAD' => 'Necesidad',
        'CANTIDAD_CAMPESINA_COMPLEMENTARIA' => 'Campesina complementaria',
        'CANTIDAD_CAMPESINA_TITULADA' => 'Campesina titulada',
        'CANTIDAD_VULNERABLE' => 'Vulnerable',
        'CANTIDAD_MEDIA_TECNICA' => 'Media técnica',
        'CANTIDAD_FIC' => 'FIC',
        'CANTIDAD_ECONOMIA_POPULAR' => 'Economía popular',
        'CANTIDAD_ENI' => 'ENI',
        'CANTIDAD_FC_CAMPESINA' => 'FC campesina',
    ];

    foreach ($quantityFields as $field => $label) {
        $value = isset($need[$field]) ? intval($need[$field]) : 0;
        if ($value > 0) {
            $segments[] = $label . ': ' . $value;
        }
    }

    if (empty($segments)) {
        return 'Sin detalle de necesidad';
    }

    return implode(' · ', $segments);
}

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$usuarioId = intval($_SESSION['usuario_id']);

// Migración: renombrar la columna de texto libre matriz_item.FICHA_TECNICA a NOTAS_TECNICAS
// para no confundirla con la tabla ficha_tecnica (el catálogo real de fichas técnicas).
function matriz_columna_existe(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tabla, $columna]);
    return (bool) $stmt->fetchColumn();
}
if (matriz_columna_existe($pdo, 'matriz_item', 'FICHA_TECNICA') && !matriz_columna_existe($pdo, 'matriz_item', 'NOTAS_TECNICAS')) {
    $pdo->exec("ALTER TABLE matriz_item CHANGE COLUMN FICHA_TECNICA NOTAS_TECNICAS TEXT DEFAULT NULL");
}
// Migración aditiva: registrar quién creó cada ficha técnica, para restringir su edición
if (!matriz_columna_existe($pdo, 'ficha_tecnica', 'ID_CREADOR')) {
    $pdo->exec("ALTER TABLE ficha_tecnica ADD COLUMN ID_CREADOR INT DEFAULT NULL");
}

// Capturar el ID del lote para ver sus ítems específicos
$id_lote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;

if ($id_lote === 0) {
    header("Location: index.php");
    exit;
}

// Verificar que el lote pertenezca al instructor autenticado (evita que un
// instructor edite/consulte el lote de otro cambiando el parámetro ?lote=)
$stmtLote = $pdo->prepare("SELECT LOTE_NOMBRE, ESTADO_TRAMITE FROM lote_requerimiento WHERE ID_LOTE = ? AND ID_SOLICITANTE = ?");
$stmtLote->execute([$id_lote, $usuarioId]);
$loteInfo = $stmtLote->fetch();
if (!$loteInfo) {
    header("Location: mis_lotes.php");
    exit;
}

$id_ficha_tecnica_get = isset($_GET['ficha_tecnica']) ? intval($_GET['ficha_tecnica']) : 0;
$ficha_tecnica_prefill = null;
if ($id_ficha_tecnica_get > 0) {
    try {
        $stmtF = $pdo->prepare("SELECT * FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
        $stmtF->execute([$id_ficha_tecnica_get]);
        $ficha_tecnica_prefill = $stmtF->fetch();
    } catch (Exception $e) {
        error_log('Error cargando ficha técnica pre-llenada: ' . $e->getMessage());
    }
}
// Si la ficha pre-llenada trae una unidad "heredada" que no está en la lista
// estándar (datos antiguos, ej. "Gato"/"Galion" mal escritos), se agrega como
// opción extra para no perderla silenciosamente al re-guardar sin tocarla.
if ($ficha_tecnica_prefill && !in_array($ficha_tecnica_prefill['UNIDAD_MEDIDA'], $unidadesMedidaEstandar, true)) {
    $unidadesMedidaEstandar[] = $ficha_tecnica_prefill['UNIDAD_MEDIDA'];
}

$msg = $_GET['msg'] ?? '';
$messageText = '';
if ($msg === 'guardado') {
    $messageText = '✓ Ficha guardada. Puedes seguir agregando más o ir a Ver Fichas Técnicas para enviarlas.';
} elseif ($msg === 'item_eliminado') {
    $messageText = '✓ Ítem quitado del lote correctamente.';
}

// Cargar lista de instructores para el select de apoyo
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

// Tasa de IVA vigente (aplicada automáticamente, ya no se selecciona manualmente)
$ivaVigente = null;
try {
    $ivaVigente = obtener_iva_vigente($pdo);
} catch (Exception $e) {
    error_log('Error cargando tasa de IVA vigente: ' . $e->getMessage());
}

// Cargar necesidades / estrategias académicas para tomar la cantidad requerida desde la tabla de necesidades
$needs = [];
try {
    $stmtNeeds = $pdo->query("SELECT ID_NECESIDAD, ID_MATRIZ, CANTIDAD_REGULAR, CANTIDAD_NESECIDAD, CANTIDAD_CAMPESINA_COMPLEMENTARIA, CANTIDAD_CAMPESINA_TITULADA, CANTIDAD_VULNERABLE, CANTIDAD_MEDIA_TECNICA, CANTIDAD_FIC, CANTIDAD_ECONOMIA_POPULAR, CANTIDAD_ENI, CANTIDAD_FC_CAMPESINA FROM necesidad ORDER BY ID_NECESIDAD");
    $needRows = $stmtNeeds->fetchAll();
    foreach ($needRows as $needRow) {
        $needQty = max(1, intval($needRow['CANTIDAD_REGULAR'] ?? 0));
        if ($needQty <= 0) {
            $needQty = max(1, intval($needRow['CANTIDAD_NESECIDAD'] ?? 0));
        }
        $needRow['LABEL'] = build_need_label($needRow);
        $needRow['CANTIDAD_BASE'] = $needQty;
        $needs[] = $needRow;
    }
} catch (Exception $e) {
    error_log('Error cargando necesidades: ' . $e->getMessage());
}

// Procesar asignación de ficha técnica a un ítem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_ficha') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    $id_matriz_item = isset($_POST['id_matriz_item']) ? intval($_POST['id_matriz_item']) : 0;
    $id_ficha_tecnica = isset($_POST['id_ficha_tecnica']) && $_POST['id_ficha_tecnica'] !== '' ? intval($_POST['id_ficha_tecnica']) : null;
    
    try {
        $stmtAsignar = $pdo->prepare("UPDATE matriz_item SET ID_FICHA_TECNICA = ? WHERE ID_MATRIZ_ITEM = ? AND ID_LOTE = ?");
        $stmtAsignar->execute([$id_ficha_tecnica, $id_matriz_item, $id_lote]);
        header("Location: matriz.php?lote=" . $id_lote);
        exit;
    } catch (Exception $e) {
        error_log('Error al asignar ficha técnica: ' . $e->getMessage());
        die('Error al asignar ficha técnica.');
    }
}

// Procesar eliminación de un ítem de la matriz (solo permitido mientras el lote esté en Borrador)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_item') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    if ($loteInfo['ESTADO_TRAMITE'] !== 'Borrador') {
        die('Solo se pueden quitar ítems de un lote en Borrador.');
    }
    $id_matriz_item = isset($_POST['id_matriz_item']) ? intval($_POST['id_matriz_item']) : 0;

    $transactionStarted = false;
    try {
        $stmtCheckItem = $pdo->prepare("SELECT ID_MATRIZ_ITEM FROM matriz_item WHERE ID_MATRIZ_ITEM = ? AND ID_LOTE = ?");
        $stmtCheckItem->execute([$id_matriz_item, $id_lote]);
        if (!$stmtCheckItem->fetchColumn()) {
            throw new Exception("El ítem indicado no pertenece a este lote.");
        }

        $pdo->beginTransaction();
        $transactionStarted = true;

        // Desvincular (no borrar) la ficha técnica: sigue disponible en el catálogo compartido
        $pdo->prepare("UPDATE ficha_tecnica SET ID_MATRIZ_ITEM = NULL WHERE ID_MATRIZ_ITEM = ?")->execute([$id_matriz_item]);
        $pdo->prepare("DELETE FROM cotizacion WHERE ID_MATRIZ_ITEM = ?")->execute([$id_matriz_item]);
        $pdo->prepare("DELETE FROM necesidad WHERE ID_MATRIZ = ?")->execute([$id_matriz_item]);
        $pdo->prepare("DELETE FROM matriz_item WHERE ID_MATRIZ_ITEM = ?")->execute([$id_matriz_item]);

        $pdo->commit();
        header("Location: matriz.php?lote=" . $id_lote . "&msg=item_eliminado");
        exit;
    } catch (PDOException $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error eliminando ítem de matriz: ' . $e->getMessage());
        die('No se pudo quitar el ítem. Intente de nuevo más tarde.');
    } catch (Exception $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error eliminando ítem de matriz: ' . $e->getMessage());
        die('Error al quitar el ítem: ' . htmlspecialchars($e->getMessage()));
    }
}

// Procesar inserción de un nuevo ítem si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Token CSRF inválido.');
    }
    if ($loteInfo['ESTADO_TRAMITE'] !== 'Borrador') {
        die('Solo se pueden agregar ítems o enviar un lote que esté en Borrador.');
    }
    $rawUnspsc = isset($_POST['id_codigo_unspsc']) ? trim($_POST['id_codigo_unspsc']) : '';
    $id_unspsc = 0;
    $id_necesidad = isset($_POST['id_necesidad']) && $_POST['id_necesidad'] !== '' ? intval($_POST['id_necesidad']) : 0;
    $id_ficha_tecnica = isset($_POST['id_ficha_tecnica']) && $_POST['id_ficha_tecnica'] !== '' ? intval($_POST['id_ficha_tecnica']) : null;
    $descripcion = trim($_POST['descripcion_bien']);
    $unidad_medida = isset($_POST['unidad_medida']) ? trim($_POST['unidad_medida']) : 'Unidad';
    
    // Calcular cantidad sumando las estrategias si se enviaron por POST
    $cantidades = isset($_POST['cantidades_estrategias']) ? $_POST['cantidades_estrategias'] : [];
    $cantidad = 0;
    if (count($cantidades) > 0) {
        foreach ($cantidades as $val) {
            $cantidad += max(0, intval($val));
        }
    }
    $cantidad = $cantidad > 0 ? $cantidad : max(1, intval($_POST['cantidad_regular'] ?? 1));
    
    $ficha = trim($_POST['ficha_tecnica'] ?? '');
    $instructor_apoyo = isset($_POST['instructor_apoyo']) && $_POST['instructor_apoyo'] !== '' ? intval($_POST['instructor_apoyo']) : null;
    if ($instructor_apoyo === $usuarioId) {
        die('No puedes seleccionarte a ti mismo como instructor de apoyo.');
    }

    // El envío a revisión ya no ocurre aquí: cada ítem se crea como Borrador y
    // se envía selectivamente después desde fichas_tecnicas_creadas.php.
    $estadoItem = 'Borrador';

    $transactionStarted = false;
    try {
        if (strlen($unidad_medida) > 50) {
            throw new Exception("La unidad de medida no puede tener más de 50 caracteres.");
        }
        if (!in_array($unidad_medida, $unidadesMedidaEstandar, true)) {
            throw new Exception("Debe seleccionar una unidad de medida válida.");
        }
        if ($cantidad > 100000) {
            throw new Exception("La cantidad requerida no puede ser mayor a 100,000 unidades.");
        }

        $ivaVigenteActual = obtener_iva_vigente($pdo);
        if (!$ivaVigenteActual) {
            throw new Exception("No hay una tasa de IVA vigente configurada. Contacte al administrador.");
        }
        $id_iva = intval($ivaVigenteActual['ID_IVA']);

        $pdo->beginTransaction();
        $transactionStarted = true;

        // 1. Resolver código UNSPSC si se ingresó uno: debe existir en el catálogo (ya no se crean códigos "al vuelo")
        if ($rawUnspsc !== '') {
            $stmtCheckUnspsc = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ? LIMIT 1");
            $stmtCheckUnspsc->execute([$rawUnspsc]);
            $found = $stmtCheckUnspsc->fetchColumn();
            if (!$found) {
                throw new Exception("El código UNSPSC ingresado no existe en el catálogo. Selecciónelo de la lista de sugerencias.");
            }
            $id_unspsc = intval($found);
        }

        // Si no se proporciona un ID UNSPSC, resolver usando la Ficha Técnica
        if ($id_unspsc === 0 && $id_ficha_tecnica > 0) {
            $stmtF = $pdo->prepare("SELECT CODIGO_UNSPSC_FK FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? LIMIT 1");
            $stmtF->execute([$id_ficha_tecnica]);
            $cod = trim($stmtF->fetchColumn() ?: '');
            if ($cod !== '') {
                $stmtCu = $pdo->prepare("SELECT ID_CODIGO FROM codigo_unspsc WHERE CODIGO_UNSPSC = ? LIMIT 1");
                $stmtCu->execute([$cod]);
                $found = $stmtCu->fetchColumn();
                if ($found) {
                    $id_unspsc = intval($found);
                }
            }
        }
        
        if ($id_unspsc === 0) {
            throw new Exception("Debe seleccionar un código UNSPSC del catálogo.");
        }

        // 2. Insertar el ítem en matriz_item (con ID_NECESIDAD y ID_FICHA_TECNICA como NULL temporalmente)
        $sqlInsert = "INSERT INTO matriz_item (ID_LOTE, ID_NECESIDAD, ID_CODIGO_UNSPSC, ID_IVA, DESCRIPCION_BIEN, UNIDAD_MEDIDA, CANTIDAD_REGULAR, NOTAS_TECNICAS, ESTADO_ITEM, INSTRUCTOR_APOYO, ID_FICHA_TECNICA)
                      VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
        $pdo->prepare($sqlInsert)->execute([
            $id_lote, 
            $id_unspsc, 
            $id_iva, 
            $descripcion, 
            $unidad_medida, 
            $cantidad, 
            $ficha, 
            $estadoItem, 
            $instructor_apoyo
        ]);
        $id_matriz_item = $pdo->lastInsertId();

        // 3. Si se ingresaron cantidades por estrategias, insertar nueva necesidad y asociar con el item
        if (count($cantidades) > 0) {
            $map = [
                'cantidad_regular' => 'CANTIDAD_REGULAR',
                'cantidad_campesina_complementaria' => 'CANTIDAD_CAMPESINA_COMPLEMENTARIA',
                'cantidad_campesina_titulada' => 'CANTIDAD_CAMPESINA_TITULADA',
                'cantidad_vulnerable' => 'CANTIDAD_VULNERABLE',
                'cantidad_media_tecnica' => 'CANTIDAD_MEDIA_TECNICA',
                'cantidad_fic' => 'CANTIDAD_FIC',
                'cantidad_economia_popular' => 'CANTIDAD_ECONOMIA_POPULAR',
                'cantidad_eni' => 'CANTIDAD_ENI',
                'cantidad_fc_campesina' => 'CANTIDAD_FC_CAMPESINA',
            ];
            
            $strategyValues = [];
            foreach ($map as $key => $columnName) {
                $strategyValues[$columnName] = isset($cantidades[$key]) ? max(0, intval($cantidades[$key])) : 0;
            }

            $sqlInsertNeed = "INSERT INTO necesidad (
                ID_MATRIZ, CANTIDAD_REGULAR, CANTIDAD_NESECIDAD,
                CANTIDAD_CAMPESINA_COMPLEMENTARIA, CANTIDAD_CAMPESINA_TITULADA,
                CANTIDAD_VULNERABLE, CANTIDAD_MEDIA_TECNICA, CANTIDAD_FIC,
                CANTIDAD_ECONOMIA_POPULAR, CANTIDAD_ENI, CANTIDAD_FC_CAMPESINA
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtNeed = $pdo->prepare($sqlInsertNeed);
            $stmtNeed->execute([
                $id_matriz_item,
                $strategyValues['CANTIDAD_REGULAR'], $cantidad,
                $strategyValues['CANTIDAD_CAMPESINA_COMPLEMENTARIA'],
                $strategyValues['CANTIDAD_CAMPESINA_TITULADA'],
                $strategyValues['CANTIDAD_VULNERABLE'],
                $strategyValues['CANTIDAD_MEDIA_TECNICA'],
                $strategyValues['CANTIDAD_FIC'],
                $strategyValues['CANTIDAD_ECONOMIA_POPULAR'],
                $strategyValues['CANTIDAD_ENI'],
                $strategyValues['CANTIDAD_FC_CAMPESINA']
            ]);
            $id_necesidad = $pdo->lastInsertId();

            // Actualizar la matriz con el ID_NECESIDAD generado
            $stmtUpdateM = $pdo->prepare("UPDATE matriz_item SET ID_NECESIDAD = ? WHERE ID_MATRIZ_ITEM = ?");
            $stmtUpdateM->execute([$id_necesidad, $id_matriz_item]);
        } else {
            // Si ya venía una necesidad seleccionada en el formulario (por prefill de catálogo)
            if ($id_necesidad > 0) {
                $stmtUpdateM = $pdo->prepare("UPDATE matriz_item SET ID_NECESIDAD = ? WHERE ID_MATRIZ_ITEM = ?");
                $stmtUpdateM->execute([$id_necesidad, $id_matriz_item]);
            }
        }

        // 4. Crear automáticamente la ficha técnica si no está asociada
        if (empty($id_ficha_tecnica)) {
            // Procesar archivo de imagen si se subió uno. Se valida el contenido
            // real (getimagesize), no solo la extensión del nombre del archivo:
            // un archivo cualquiera renombrado a ".jpg" pasaría un chequeo por
            // extensión pero no es realmente una imagen.
            $imagenPath = null;
            if (isset($_FILES['imagen_referencia']) && $_FILES['imagen_referencia']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['imagen_referencia']['tmp_name'];

                $allowedImageTypes = [
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG  => 'png',
                    IMAGETYPE_GIF  => 'gif',
                    IMAGETYPE_WEBP => 'webp',
                ];
                $imageInfo = @getimagesize($fileTmpPath);

                if ($imageInfo !== false && isset($allowedImageTypes[$imageInfo[2]])) {
                    $ext = $allowedImageTypes[$imageInfo[2]];
                    $uploadFileDir = '../uploads/fichas/';
                    if (!file_exists($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $dest_path = $uploadFileDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $imagenPath = 'uploads/fichas/' . $newFileName;
                    }
                }
            }

            // Obtener el código unspsc en texto
            $codUnspscStr = '';
            if ($id_unspsc > 0) {
                $stmtGetCod = $pdo->prepare("SELECT CODIGO_UNSPSC FROM codigo_unspsc WHERE ID_CODIGO = ? LIMIT 1");
                $stmtGetCod->execute([$id_unspsc]);
                $codUnspscStr = trim($stmtGetCod->fetchColumn() ?: '');
            }
            if ($codUnspscStr === '' && $rawUnspsc !== '') {
                $codUnspscStr = $rawUnspsc;
            }

            // Nombre del ítem
            $nombreItem = $descripcion;
            $denominacion = $descripcion;
            $parts = explode(' - ', $descripcion, 2);
            if (count($parts) === 2) {
                $nombreItem = trim($parts[0]);
                $denominacion = trim($parts[1]);
            }
            if (strlen($nombreItem) > 150) {
                $nombreItem = substr($nombreItem, 0, 147) . '...';
            }

            $sqlInsertFicha = "INSERT INTO ficha_tecnica
                (ID_MATRIZ_ITEM, ID_CREADOR, NOMBRE_ITEM, CODIGO_UNSPSC_FK, DENOMINACION_TECNICA_BIEN, UNIDAD_MEDIDA, DESCRIPCION_GENERAL, COMENTARIOS, CANTIDAD, IMAGEN)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsertF = $pdo->prepare($sqlInsertFicha);
            $stmtInsertF->execute([
                $id_matriz_item,
                $usuarioId,
                $nombreItem,
                $codUnspscStr,
                $denominacion,
                $unidad_medida,
                $ficha, // Ficha técnica (detalles para el coordinador)
                '', // Comentarios
                $cantidad,
                $imagenPath
            ]);
            $id_ficha_tecnica = $pdo->lastInsertId();

            // Vincular el ID_FICHA_TECNICA recién creado de vuelta a la tabla matriz_item
            $stmtUpdateMatrizItem = $pdo->prepare("UPDATE matriz_item SET ID_FICHA_TECNICA = ? WHERE ID_MATRIZ_ITEM = ?");
            $stmtUpdateMatrizItem->execute([$id_ficha_tecnica, $id_matriz_item]);
        } else {
            // Si ya venía una ficha técnica seleccionada (prefill)
            $stmtUpdateMatrizItem = $pdo->prepare("UPDATE matriz_item SET ID_FICHA_TECNICA = ? WHERE ID_MATRIZ_ITEM = ?");
            $stmtUpdateMatrizItem->execute([$id_ficha_tecnica, $id_matriz_item]);
        }

        $pdo->commit();

        header("Location: matriz.php?lote=" . urlencode($id_lote) . "&msg=guardado");
        exit;
    } catch (PDOException $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error guardando ítem/necesidad: ' . $e->getMessage());
        die('No se pudo guardar el ítem. Intente de nuevo más tarde.');
    } catch (Exception $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error guardando ítem/necesidad: ' . $e->getMessage());
        die('Error al agregar el ítem: ' . htmlspecialchars($e->getMessage()));
    }
}

// Consultar los ítems asociados a este lote
$stmtItems = $pdo->prepare("SELECT m.*, c.CODIGO_UNSPSC, i.PORCENTAJE, ua.NOMBRE AS APOYO_NOMBRE, ua.APELLIDO AS APOYO_APELLIDO, n.ID_MATRIZ AS NECESIDAD_MATRIZ, n.CANTIDAD_REGULAR AS NECESIDAD_BASE, n.CANTIDAD_NESECIDAD, n.CANTIDAD_CAMPESINA_COMPLEMENTARIA, n.CANTIDAD_CAMPESINA_TITULADA, n.CANTIDAD_VULNERABLE, n.CANTIDAD_MEDIA_TECNICA, n.CANTIDAD_FIC, n.CANTIDAD_ECONOMIA_POPULAR, n.CANTIDAD_ENI, n.CANTIDAD_FC_CAMPESINA
                            FROM matriz_item m 
                            LEFT JOIN codigo_unspsc c ON m.ID_CODIGO_UNSPSC = c.ID_CODIGO
                            LEFT JOIN iva i ON m.ID_IVA = i.ID_IVA
                            LEFT JOIN usuario ua ON m.INSTRUCTOR_APOYO = ua.ID_USUARIO
                            LEFT JOIN necesidad n ON m.ID_NECESIDAD = n.ID_NECESIDAD
                            WHERE m.ID_LOTE = ?");
$stmtItems->execute([$id_lote]);
$items = $stmtItems->fetchAll();

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
    <title>BICERGAM - Matriz de Ítems</title>
    <link rel="stylesheet" href="../estilos.css">
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
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
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
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="matriz_consulta.php" class="sidebar-link">Consulta de Ítems</a>
            <a href="certificado_existencia.php" class="sidebar-link">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="instructor_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="container fade-in" style="margin: 0; max-width: 100%;">
    <h2>Componentes del Lote: <span style="color: var(--verde-sena);"><?= htmlspecialchars($loteInfo['LOTE_NOMBRE'] ?? 'Desconocido') ?></span></h2>

    <?php if (!empty($messageText)): ?>
        <div class="profile-alert success" style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 14px; background: #eff8f1; color: #270; border: 1px solid #d4ebd5;">
            <?= htmlspecialchars($messageText) ?>
        </div>
    <?php endif; ?>

    <?php if ($loteInfo['ESTADO_TRAMITE'] !== 'Borrador'): ?>
    <div style="background: #fff3cd; padding: 16px 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #ffc107; color: #664d03;">
        Este lote está en estado <strong><?= htmlspecialchars($loteInfo['ESTADO_TRAMITE']) ?></strong> y ya no admite nuevos ítems.
        <?php if ($loteInfo['ESTADO_TRAMITE'] === 'Rechazado'): ?>
            Puedes corregirlo desde <a href="mis_lotes.php">Mis Lotes</a> con la opción "Corregir y Reenviar".
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="background: var(--gris-claro); padding: 20px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid var(--verde-sena);">
        <h3>Añadir Material / Bien al Lote</h3>
        <form action="matriz.php?lote=<?= htmlspecialchars($id_lote) ?>" method="POST" id="formItem" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="id_ficha_tecnica" value="<?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['ID_FICHA_TECNICA']) : '' ?>">
            
            <div class="form-grid-2">
                <div class="form-group" style="position: relative;">
                    <label for="id_codigo_unspsc_busqueda">Código UNSPSC *:</label>
                    <input type="text" id="id_codigo_unspsc_busqueda" class="form-control" autocomplete="off"
                           placeholder="Escriba el nombre o código del producto para buscar"
                           value="<?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['CODIGO_UNSPSC_FK']) : '' ?>"
                           <?= $ficha_tecnica_prefill ? 'disabled' : 'required' ?> />
                    <input type="hidden" id="id_codigo_unspsc" name="id_codigo_unspsc" value="<?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['CODIGO_UNSPSC_FK']) : '' ?>" />
                    <div id="unspsc_resultados" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:20; max-height:220px; overflow-y:auto; box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
                </div>
                <div class="form-group">
                    <label for="unidad_medida">Unidad de Medida *:</label>
                    <?php $unidadActual = $ficha_tecnica_prefill ? $ficha_tecnica_prefill['UNIDAD_MEDIDA'] : 'Unidad'; ?>
                    <select id="unidad_medida" name="unidad_medida" class="form-control" required>
                        <?php foreach ($unidadesMedidaEstandar as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>" <?= $u === $unidadActual ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tasa de IVA:</label>
                    <?php if ($ivaVigente): ?>
                        <input type="text" class="form-control" disabled value="<?= htmlspecialchars($ivaVigente['DESCRIPCION']) ?> (<?= htmlspecialchars(rtrim(rtrim(number_format($ivaVigente['PORCENTAJE'], 2), '0'), '.')) ?>%) — aplicada automáticamente">
                    <?php else: ?>
                        <input type="text" class="form-control" disabled value="Sin tasa de IVA vigente configurada" style="color:#de3a3a;">
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($ficha_tecnica_prefill): ?>
                <input type="hidden" id="id_necesidad" name="id_necesidad" value="<?= !empty($needs) ? (int)$needs[0]['ID_NECESIDAD'] : '' ?>">
            <?php else: ?>
                <div class="form-group" style="margin-top: 20px; margin-bottom: 25px; grid-column: span 2;">
                    <label style="font-weight: 700; color: var(--texto-oscuro); display: block; margin-bottom: 12px; font-size: 15px;">Seleccionar Estrategias Académicas *:</label>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px;">

                        <!-- Regular -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Regular</span>
                                <input type="number"
                                       name="cantidades_estrategias[cantidad_regular]"
                                       class="form-control val-estrategia"
                                       value="0"
                                       min="0"
                                       max="100000"
                                       disabled
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_regular">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox"
                                       class="check-estrategia"
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_regular">
                            </div>
                        </div>

                        <!-- Campesina Complementaria -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Campesina Complementaria</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_campesina_complementaria]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_campesina_complementaria">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_campesina_complementaria">
                            </div>
                        </div>
                        
                        <!-- Campesina Titulada -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Campesina Titulada</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_campesina_titulada]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_campesina_titulada">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_campesina_titulada">
                            </div>
                        </div>
                        
                        <!-- Vulnerable -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Vulnerable</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_vulnerable]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_vulnerable">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_vulnerable">
                            </div>
                        </div>
                        
                        <!-- Media Técnica -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Media Técnica</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_media_tecnica]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_media_tecnica">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_media_tecnica">
                            </div>
                        </div>
                        
                        <!-- FIC -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">FIC</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_fic]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_fic">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_fic">
                            </div>
                        </div>
                        
                        <!-- Economía Popular -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">Economía Popular</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_economia_popular]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_economia_popular">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_economia_popular">
                            </div>
                        </div>
                        
                        <!-- ENI -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">ENI</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_eni]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_eni">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_eni">
                            </div>
                        </div>
                        
                        <!-- FC Campesina -->
                        <div class="strategy-card" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; background: #fff; display: flex; align-items: center; justify-content: space-between; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; flex-direction: column; gap: 4px; flex-grow: 1;">
                                <span style="font-weight: 600; font-size: 13px; color: var(--texto-oscuro);">FC Campesina</span>
                                <input type="number" 
                                       name="cantidades_estrategias[cantidad_fc_campesina]" 
                                       class="form-control val-estrategia" 
                                       value="0" 
                                       min="0" 
                                       max="100000" 
                                       disabled 
                                       style="width: 80px; padding: 4px 8px; font-size: 13px; margin-top: 4px;"
                                       data-strategy="cantidad_fc_campesina">
                            </div>
                            <div style="padding-left: 10px;">
                                <input type="checkbox" 
                                       class="check-estrategia" 
                                       style="width: 22px; height: 22px; cursor: pointer; accent-color: var(--verde-sena);"
                                       data-target="cantidad_fc_campesina">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ficha_tecnica_prefill): ?>
                <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                    <label for="columna_necesidad" style="font-weight:bold; color:#00324D;">Necesidad (Columna de la tabla Necesidad):</label>
                    <select id="columna_necesidad" name="columna_necesidad" class="form-control" style="border: 2px solid #00324D;"
                        <?php if (!empty($needs)): ?>
                            <?php $dn = $needs[0]; ?>
                            data-cantidad_regular="<?= intval($dn['CANTIDAD_REGULAR']) ?>"
                            data-cantidad_campesina_complementaria="<?= intval($dn['CANTIDAD_CAMPESINA_COMPLEMENTARIA']) ?>"
                            data-cantidad_campesina_titulada="<?= intval($dn['CANTIDAD_CAMPESINA_TITULADA']) ?>"
                            data-cantidad_vulnerable="<?= intval($dn['CANTIDAD_VULNERABLE']) ?>"
                            data-cantidad_media_tecnica="<?= intval($dn['CANTIDAD_MEDIA_TECNICA']) ?>"
                            data-cantidad_fic="<?= intval($dn['CANTIDAD_FIC']) ?>"
                            data-cantidad_economia_popular="<?= intval($dn['CANTIDAD_ECONOMIA_POPULAR']) ?>"
                            data-cantidad_eni="<?= intval($dn['CANTIDAD_ENI']) ?>"
                            data-cantidad_fc_campesina="<?= intval($dn['CANTIDAD_FC_CAMPESINA']) ?>"
                            data-cantidad_nesecidad="<?= intval($dn['CANTIDAD_NESECIDAD']) ?>"
                        <?php endif; ?>
                    >
                        <option value="cantidad_regular">Regular (CANTIDAD_REGULAR)</option>
                        <option value="cantidad_campesina_complementaria">Campesina Complementaria (CANTIDAD_CAMPESINA_COMPLEMENTARIA)</option>
                        <option value="cantidad_campesina_titulada">Campesina Titulada (CANTIDAD_CAMPESINA_TITULADA)</option>
                        <option value="cantidad_vulnerable">Vulnerable (CANTIDAD_VULNERABLE)</option>
                        <option value="cantidad_media_tecnica">Media Técnica (CANTIDAD_MEDIA_TECNICA)</option>
                        <option value="cantidad_fic">FIC (CANTIDAD_FIC)</option>
                        <option value="cantidad_economia_popular">Economía Popular (CANTIDAD_ECONOMIA_POPULAR)</option>
                        <option value="cantidad_eni">ENI (CANTIDAD_ENI)</option>
                        <option value="cantidad_fc_campesina">FC Campesina (CANTIDAD_FC_CAMPESINA)</option>
                        <option value="cantidad_nesecidad">Necesidad (CANTIDAD_NESECIDAD)</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="descripcion_bien">Descripción Detallada del Bien / Ficha técnica breve:</label>
                <textarea id="descripcion_bien" name="descripcion_bien" class="form-control" rows="2" required <?= $ficha_tecnica_prefill ? 'readonly' : '' ?>><?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['NOMBRE_ITEM'] . ' - ' . $ficha_tecnica_prefill['DENOMINACION_TECNICA_BIEN']) : '' ?></textarea>
            </div>

            <div class="form-group" style="width: 32%;">
                <label for="cantidad_regular">Cantidad Requerida:</label>
                <input type="number" id="cantidad_regular" name="cantidad_regular" class="form-control" min="1" max="100000" value="1" required>
            </div>

            <div class="form-group">
                <label for="instructor_apoyo">Instructor de Apoyo (opcional):</label>
                <select id="instructor_apoyo" name="instructor_apoyo" class="form-control">
                    <option value="">-- Ninguno --</option>
                    <?php foreach($instructors as $ins): ?>
                        <option value="<?= htmlspecialchars($ins['ID_USUARIO']) ?>"><?= htmlspecialchars($ins['NOMBRE'] . ' ' . $ins['APELLIDO']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ficha_tecnica">Ficha Técnica (detalles para el coordinador):</label>
                <textarea id="ficha_tecnica" name="ficha_tecnica" class="form-control" rows="3" placeholder="Detalles adicionales, enlaces o requisitos" <?= $ficha_tecnica_prefill ? 'readonly' : '' ?>><?= $ficha_tecnica_prefill ? htmlspecialchars($ficha_tecnica_prefill['DESCRIPCION_GENERAL']) : '' ?></textarea>
            </div>

            <?php if (!$ficha_tecnica_prefill): ?>
            <div class="form-group">
                <label for="imagen_referencia">Imagen de Referencia (opcional):</label>
                <input type="file" id="imagen_referencia" name="imagen_referencia" class="form-control" accept="image/*" />
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn">Guardar y Continuar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
    <h3>Artículos Solicitados en este Lote</h3>
    <table>
        <thead>
            <tr>
                <th>ID Ítem</th>
                <th>Código UNSPSC</th>
                <th>Descripción del Bien</th>
                <th>Necesidad</th>
                <th>U. Medida</th>
                <th>Cantidad</th>
                <th>IVA</th>
                <th>Instructor Apoyo</th>
                <th>Estado</th>
                <?php if ($loteInfo['ESTADO_TRAMITE'] === 'Borrador'): ?><th>Acciones</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?></td>
                        <td><?= htmlspecialchars($item['CODIGO_UNSPSC'] ?? 'SIN_ASIGNAR') ?></td>
                        <td><strong><?= htmlspecialchars($item['DESCRIPCION_BIEN']) ?></strong></td>
                        <td><?= htmlspecialchars($item['ID_NECESIDAD'] ? 'Necesidad #' . $item['ID_NECESIDAD'] : 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['UNIDAD_MEDIDA'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['CANTIDAD_REGULAR']) ?></td>
                        <td><?= $item['PORCENTAJE'] !== null ? htmlspecialchars(rtrim(rtrim(number_format($item['PORCENTAJE'], 2), '0'), '.')) . '%' : 'N/A' ?></td>
                        <td>
                            <?php 
                            if ($item['INSTRUCTOR_APOYO']) {
                                $stmtUser = $pdo->prepare("SELECT NOMBRE, APELLIDO FROM usuario WHERE ID_USUARIO = ?");
                                $stmtUser->execute([$item['INSTRUCTOR_APOYO']]);
                                $u = $stmtUser->fetch();
                                echo htmlspecialchars($u['NOMBRE'] . ' ' . $u['APELLIDO']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><span class="badge-estado badge-<?= strtolower(htmlspecialchars($item['ESTADO_ITEM'] ?? 'Borrador')) ?>"><?= htmlspecialchars($item['ESTADO_ITEM'] ?? 'Borrador') ?></span></td>
                        <?php if ($loteInfo['ESTADO_TRAMITE'] === 'Borrador'): ?>
                        <td>
                            <form method="POST" action="matriz.php?lote=<?= htmlspecialchars($id_lote) ?>">
                                <input type="hidden" name="accion" value="eliminar_item">
                                <input type="hidden" name="id_matriz_item" value="<?= htmlspecialchars($item['ID_MATRIZ_ITEM']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <button type="submit" class="btn btn-danger js-confirm-submit" style="padding: 4px 10px; font-size: 11px; border: none; background: var(--alerta-rojo); color: white; border-radius: 4px;" data-confirm-title="Quitar ítem" data-confirm-message="¿Quitar este ítem del lote?" data-confirm-label="Quitar">Quitar</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($loteInfo['ESTADO_TRAMITE'] === 'Borrador'): ?>
    <div style="display:flex; justify-content:flex-end; margin-top:15px;">
        <form method="GET" action="fichas_tecnicas_creadas.php">
            <input type="hidden" name="lote" value="<?= htmlspecialchars($id_lote) ?>">
            <button type="submit" class="btn btn-sena js-confirm-submit"
                data-confirm-title="Guardar y cerrar"
                data-confirm-message="¿Ya terminaste de agregar fichas a este lote? Podrás seguir agregando más después, pero ahora pasarás a Ver Fichas Técnicas para enviarlas a revisión."
                data-confirm-label="Sí, continuar"
                data-confirm-danger="false">Guardar y Cerrar</button>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const qtyInput = document.getElementById('cantidad_regular');
        const colSelect = document.getElementById('columna_necesidad');
        const checkboxes = document.querySelectorAll('.check-estrategia');
        const inputs = document.querySelectorAll('.val-estrategia');

        // Permitir que el usuario edite la cantidad requerida
        if (qtyInput) {
            qtyInput.removeAttribute('readonly');
        }

        const calculateTotal = function () {
            if (colSelect) {
                // Modo catálogo de Ficha Técnica pre-llenada
                const columnName = colSelect.value;
                const attributeName = 'data-' + columnName;
                const cantidadVal = colSelect.getAttribute(attributeName);
                if (cantidadVal && Number(cantidadVal) > 0) {
                    qtyInput.value = cantidadVal;
                }
                return;
            }

            let sum = 0;
            inputs.forEach(input => {
                const strategy = input.getAttribute('data-strategy');
                const checkbox = document.querySelector(`.check-estrategia[data-target="${strategy}"]`);
                if (checkbox && checkbox.checked) {
                    sum += parseInt(input.value || '0', 10);
                }
            });
            if (qtyInput) {
                qtyInput.value = sum > 0 ? sum : '0';
            }
        };

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const targetStrategy = this.getAttribute('data-target');
                const relatedInput = document.querySelector(`.val-estrategia[data-strategy="${targetStrategy}"]`);
                const card = this.closest('.strategy-card');

                if (relatedInput) {
                    if (this.checked) {
                        relatedInput.removeAttribute('disabled');
                        if (parseInt(relatedInput.value || '0', 10) === 0) {
                            relatedInput.value = '1';
                        }
                        if (card) {
                            card.style.borderColor = 'var(--verde-sena)';
                            card.style.background = '#f0fdf4';
                        }
                        relatedInput.focus();
                    } else {
                        relatedInput.setAttribute('disabled', 'true');
                        relatedInput.value = '0';
                        if (card) {
                            card.style.borderColor = '#cbd5e1';
                            card.style.background = '#fff';
                        }
                    }
                }
                calculateTotal();
            });
        });

        inputs.forEach(input => {
            input.addEventListener('input', calculateTotal);
        });

        // Click en la tarjeta para activar/desactivar (excepto al interactuar con el input directamente)
        document.querySelectorAll('.strategy-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.tagName.toLowerCase() === 'input' && e.target.type === 'number') {
                    return;
                }
                const cb = this.querySelector('.check-estrategia');
                if (cb && e.target !== cb) {
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        });

        if (colSelect) {
            colSelect.addEventListener('change', calculateTotal);
        }

        // Ejecutar inicialmente
        calculateTotal();
    });
</script>
    </main>
</div>
<script src="../js/apartados.js"></script>
<script src="../js/unspsc-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initUnspscAutocomplete({
        inputSelector: '#id_codigo_unspsc_busqueda',
        hiddenCodeSelector: '#id_codigo_unspsc',
        resultsSelector: '#unspsc_resultados',
        searchUrl: '../ajax/buscar_unspsc.php',
        onSelect: function (item) {
            const descripcion = document.getElementById('descripcion_bien');
            if (descripcion && !descripcion.hasAttribute('readonly') && descripcion.value.trim() === '') {
                descripcion.value = item.nombre;
            }
        }
    });
});
</script>
</body>
</html>