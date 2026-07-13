<?php
// almacenista/index.php
require_once '../conexion.php';
require_once '../csrf.php';
require_once '../notificaciones.php';
require_once '../auditoria_helper.php';
require_once '../certificado_helper.php';

// Definir constante de acceso seguro para archivos incluidos
define('ACCESO_VALIDO', true);

// Control de acceso: si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
// Permitimos al almacenista acceder. Si es otro rol, redirigir al index general para que lo enrute correctamente.
if ($rolNombre !== 'almacenista') {
    header("Location: ../index.php");
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);

// Migración aditiva: asegurar columnas de auditoría en certificado_existencia
function almacen_columna_existe(PDO $pdo, string $tabla, string $columna): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$tabla, $columna]);
    return (bool) $stmt->fetchColumn();
}
if (!almacen_columna_existe($pdo, 'certificado_existencia', 'FECHA_EMISION')) {
    $pdo->exec("ALTER TABLE certificado_existencia ADD COLUMN FECHA_EMISION TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}
if (!almacen_columna_existe($pdo, 'certificado_existencia', 'ID_ALMACENISTA')) {
    $pdo->exec("ALTER TABLE certificado_existencia ADD COLUMN ID_ALMACENISTA INT DEFAULT NULL");
}

// Inicializar variables de feedback
$successMsg = "";
$errorMsg = "";

// Determinar pestaña activa
$tabActiva = isset($_GET['tab']) ? trim($_GET['tab']) : 'stock';

// --- PROCESAMIENTO DE ACCIONES CRUD (LOCALES) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Token CSRF inválido. Recargue la página e intente de nuevo.";
    }

    // 1. Agregar nuevo artículo al Stock
    elseif ($action === 'add_item') {
        $nombre = trim($_POST['nombre_item'] ?? '');
        $codigo_unspsc = trim($_POST['codigo_unspsc'] ?? '');
        $unidad = trim($_POST['unidad_medida'] ?? '');
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $descripcion = trim($_POST['descripcion_general'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        if ($nombre === '' || $unidad === '') {
            $errorMsg = "El nombre del artículo y la unidad de medida son obligatorios.";
        } elseif ($cantidad < 0) {
            $errorMsg = "La cantidad no puede ser negativa.";
        } else {
            $codigoValido = true;
            if ($codigo_unspsc !== '') {
                $stmtCheckCod = $pdo->prepare("SELECT 1 FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
                $stmtCheckCod->execute([$codigo_unspsc]);
                $found = $stmtCheckCod->fetchColumn();
                if (!$found) {
                    $stmtMaxCodigo = $pdo->query("SELECT COALESCE(MAX(ID_CODIGO), 0) + 1 FROM codigo_unspsc");
                    $id_unspsc = intval($stmtMaxCodigo->fetchColumn());
                    $stmtInsertUnspsc = $pdo->prepare("INSERT INTO codigo_unspsc (ID_CODIGO, CODIGO_UNSPSC, NOMBRE_PRODUCTO) VALUES (?, ?, ?)");
                    $stmtInsertUnspsc->execute([$id_unspsc, $codigo_unspsc, 'Ingresado Manualmente - ' . $codigo_unspsc]);
                }
            }
            if (false) { // Skip old validation check
                $errorMsg = "El código UNSPSC seleccionado no existe en el catálogo.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO ficha_tecnica (NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD, DESCRIPCION_GENERAL, COMENTARIOS) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nombre, $codigo_unspsc, $unidad, $cantidad, $descripcion, $comentarios]);
                    $successMsg = "Artículo '$nombre' agregado exitosamente al inventario.";
                    $tabActiva = 'stock';
                } catch (Exception $e) {
                    error_log('Error al guardar el artículo: ' . $e->getMessage());
                    $errorMsg = "Error al guardar el artículo. Intente de nuevo más tarde.";
                }
            }
        }
    }

    // 2. Editar artículo existente
    elseif ($action === 'edit_item') {
        $id_ficha = intval($_POST['id_ficha_tecnica'] ?? 0);
        $nombre = trim($_POST['nombre_item'] ?? '');
        $codigo_unspsc = trim($_POST['codigo_unspsc'] ?? '');
        $unidad = trim($_POST['unidad_medida'] ?? '');
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $descripcion = trim($_POST['descripcion_general'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        if ($id_ficha <= 0 || $nombre === '' || $unidad === '') {
            $errorMsg = "Datos inválidos para actualizar el artículo.";
        } elseif ($cantidad < 0) {
            $errorMsg = "La cantidad no puede ser negativa.";
        } else {
            $codigoValido = true;
            if ($codigo_unspsc !== '') {
                $stmtCheckCod = $pdo->prepare("SELECT 1 FROM codigo_unspsc WHERE CODIGO_UNSPSC = ?");
                $stmtCheckCod->execute([$codigo_unspsc]);
                $found = $stmtCheckCod->fetchColumn();
                if (!$found) {
                    $stmtMaxCodigo = $pdo->query("SELECT COALESCE(MAX(ID_CODIGO), 0) + 1 FROM codigo_unspsc");
                    $id_unspsc = intval($stmtMaxCodigo->fetchColumn());
                    $stmtInsertUnspsc = $pdo->prepare("INSERT INTO codigo_unspsc (ID_CODIGO, CODIGO_UNSPSC, NOMBRE_PRODUCTO) VALUES (?, ?, ?)");
                    $stmtInsertUnspsc->execute([$id_unspsc, $codigo_unspsc, 'Ingresado Manualmente - ' . $codigo_unspsc]);
                }
            }
            if (false) { // Skip old validation check
                $errorMsg = "El código UNSPSC seleccionado no existe en el catálogo.";
            } else {
                try {
                    // Solo se edita stock físico propio del almacén (ID_MATRIZ_ITEM NULL);
                    // una ficha técnica creada por un instructor es una solicitud, no un artículo de stock.
                    $stmt = $pdo->prepare("UPDATE ficha_tecnica SET NOMBRE_ITEM = ?, CODIGO_UNSPSC_FK = ?, UNIDAD_MEDIDA = ?, CANTIDAD = ?, DESCRIPCION_GENERAL = ?, COMENTARIOS = ? WHERE ID_FICHA_TECNICA = ? AND ID_MATRIZ_ITEM IS NULL");
                    $stmt->execute([$nombre, $codigo_unspsc, $unidad, $cantidad, $descripcion, $comentarios, $id_ficha]);
                    if ($stmt->rowCount() === 0) {
                        $errorMsg = "El artículo no existe o no es un artículo de stock editable.";
                    } else {
                        $successMsg = "Artículo actualizado correctamente.";
                        $tabActiva = 'stock';
                    }
                } catch (Exception $e) {
                    error_log('Error al actualizar artículo: ' . $e->getMessage());
                    $errorMsg = "Error al actualizar el artículo. Intente de nuevo más tarde.";
                }
            }
        }
    }

    // 3. Eliminar artículo
    elseif ($action === 'delete_item') {
        $id_ficha = intval($_POST['id_ficha_tecnica'] ?? 0);
        if ($id_ficha > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ?");
                $stmt->execute([$id_ficha]);
                $successMsg = "Artículo eliminado del inventario.";
                $tabActiva = 'stock';
            } catch (Exception $e) {
                $errorMsg = "No se puede eliminar el artículo. Es posible que esté asociado a una solicitud de instructor.";
            }
        }
    }

    // 4. Registrar Entrada de Mercancía
    elseif ($action === 'registrar_entrada') {
        $id_ficha = intval($_POST['id_ficha_tecnica'] ?? 0);
        $cantidad_entrada = intval($_POST['cantidad'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? 'Entrada registrada');

        if ($id_ficha <= 0 || $cantidad_entrada <= 0) {
            $errorMsg = "Seleccione un artículo válido y especifique una cantidad mayor a cero.";
            $tabActiva = 'entrada';
        } else {
            try {
                asegurar_tabla_auditoria($pdo);
                $pdo->beginTransaction();
                // Solo se recibe mercancía sobre stock físico propio del almacén (ID_MATRIZ_ITEM NULL);
                // una ficha técnica creada por un instructor es una solicitud, no un artículo de stock.
                $stmt = $pdo->prepare("UPDATE ficha_tecnica SET CANTIDAD = CANTIDAD + ? WHERE ID_FICHA_TECNICA = ? AND ID_MATRIZ_ITEM IS NULL");
                $stmt->execute([$cantidad_entrada, $id_ficha]);

                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    $errorMsg = "El artículo no existe o no es un artículo de stock válido.";
                    $tabActiva = 'entrada';
                } else {
                    // Registrar auditoría local
                    $stmtAudit = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, 'Entrada Inventario', ?)");
                    $stmtAudit->execute([$usuarioId, "Entrada de $cantidad_entrada unidades al item ID: $id_ficha. Detalle: $comentario"]);

                    $pdo->commit();
                    $successMsg = "Entrada de mercancía registrada con éxito.";
                    $tabActiva = 'stock';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Error al registrar la entrada: ' . $e->getMessage());
                $errorMsg = "Error al registrar la entrada. Intente de nuevo más tarde.";
                $tabActiva = 'entrada';
            }
        }
    }

    // 5. Registrar Salida de Mercancía
    elseif ($action === 'registrar_salida') {
        $id_ficha = intval($_POST['id_ficha_tecnica'] ?? 0);
        $cantidad_salida = intval($_POST['cantidad'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? 'Salida registrada');

        if ($id_ficha <= 0 || $cantidad_salida <= 0) {
            $errorMsg = "Seleccione un artículo válido y especifique una cantidad mayor a cero.";
            $tabActiva = 'salida';
        } else {
            try {
                asegurar_tabla_auditoria($pdo);
                $pdo->beginTransaction();

                // Bloquear la fila y verificar stock actual dentro de la transacción.
                // Solo se despacha stock físico propio del almacén (ID_MATRIZ_ITEM NULL);
                // una ficha técnica creada por un instructor es una solicitud, no un artículo de stock.
                $stmtCheck = $pdo->prepare("SELECT CANTIDAD, NOMBRE_ITEM FROM ficha_tecnica WHERE ID_FICHA_TECNICA = ? AND ID_MATRIZ_ITEM IS NULL FOR UPDATE");
                $stmtCheck->execute([$id_ficha]);
                $item = $stmtCheck->fetch();

                if (!$item) {
                    $pdo->rollBack();
                    $errorMsg = "El artículo seleccionado no existe o no es un artículo de stock válido.";
                    $tabActiva = 'salida';
                } elseif ($item['CANTIDAD'] < $cantidad_salida) {
                    $pdo->rollBack();
                    $errorMsg = "Stock insuficiente para realizar esta salida. Stock actual de '{$item['NOMBRE_ITEM']}': {$item['CANTIDAD']} unidades.";
                    $tabActiva = 'salida';
                } else {
                    // Guarda adicional en el UPDATE para evitar stock negativo ante condiciones de carrera
                    $stmt = $pdo->prepare("UPDATE ficha_tecnica SET CANTIDAD = CANTIDAD - ? WHERE ID_FICHA_TECNICA = ? AND CANTIDAD >= ?");
                    $stmt->execute([$cantidad_salida, $id_ficha, $cantidad_salida]);

                    if ($stmt->rowCount() === 0) {
                        $pdo->rollBack();
                        $errorMsg = "Stock insuficiente para realizar esta salida. Intente de nuevo.";
                        $tabActiva = 'salida';
                    } else {
                        // Registrar auditoría
                        $stmtAudit = $pdo->prepare("INSERT INTO auditoria_actividad (ID_USUARIO, ACCION, DETALLE) VALUES (?, 'Salida Inventario', ?)");
                        $stmtAudit->execute([$usuarioId, "Salida de $cantidad_salida unidades del item ID: $id_ficha. Motivo: $comentario"]);

                        $pdo->commit();
                        $successMsg = "Salida de mercancía registrada con éxito.";
                        $tabActiva = 'stock';
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Error al registrar la salida: ' . $e->getMessage());
                $errorMsg = "Error al registrar la salida. Intente de nuevo más tarde.";
                $tabActiva = 'salida';
            }
        }
    }

    // 6. Emitir Certificado de Existencia (para Lotes de Instructores, solo si el lote está Aprobado)
    elseif ($action === 'emitir_certificado') {
        $id_lote = intval($_POST['id_lote'] ?? 0);
        if ($id_lote > 0) {
            try {
                $stmtLoteCheck = $pdo->prepare("SELECT ESTADO_TRAMITE, LOTE_NOMBRE, ID_SOLICITANTE FROM lote_requerimiento WHERE ID_LOTE = ?");
                $stmtLoteCheck->execute([$id_lote]);
                $loteCert = $stmtLoteCheck->fetch();
                $estadoLote = $loteCert ? $loteCert['ESTADO_TRAMITE'] : null;

                $stmtCertCheck = $pdo->prepare("SELECT NUMERO_CERTIFICADO FROM certificado_existencia WHERE ID_LOTE = ?");
                $stmtCertCheck->execute([$id_lote]);

                if ($estadoLote !== 'Aprobado') {
                    $errorMsg = "Solo se puede emitir certificado de existencia para lotes aprobados.";
                } elseif ($stmtCertCheck->fetch()) {
                    $errorMsg = "Este lote ya cuenta con un certificado de existencia emitido.";
                } else {
                    $numCertificado = "CERT-" . str_pad($id_lote, 6, "0", STR_PAD_LEFT) . "-" . time();
                    $stmtInsert = $pdo->prepare("INSERT INTO certificado_existencia (ID_LOTE, NUMERO_CERTIFICADO, ID_ALMACENISTA) VALUES (?, ?, ?)");
                    $stmtInsert->execute([$id_lote, $numCertificado, $usuarioId]);
                    $idCertificadoNuevo = (int) $pdo->lastInsertId();

                    // Registra, ítem por ítem, si ya hay esa cantidad en
                    // existencia real en el almacén (emparejado por código
                    // UNSPSC contra el stock físico) — una foto fija al
                    // momento de emitir, no un valor que cambie después.
                    registrar_existencia_certificado($pdo, $idCertificadoNuevo, $id_lote);

                    crear_notificacion(
                        $pdo,
                        intval($loteCert['ID_SOLICITANTE']),
                        "Se emitió el certificado de existencia de tu lote '" . $loteCert['LOTE_NOMBRE'] . "': $numCertificado",
                        "../instructor/certificado_existencia.php"
                    );

                    $successMsg = "Certificado de existencia emitido exitosamente: $numCertificado";
                }
                $tabActiva = 'instructor';
            } catch (Exception $e) {
                error_log('Error al emitir el certificado: ' . $e->getMessage());
                $errorMsg = "Error al emitir el certificado. Intente de nuevo más tarde.";
                $tabActiva = 'instructor';
            }
        }
    }

    // 7. Emitir Certificado de Inventario General (todo el stock físico
    // actual, sin depender de ningún lote/solicitud de instructor)
    elseif ($action === 'emitir_certificado_inventario') {
        try {
            emitir_certificado_inventario($pdo, $usuarioId);
            $successMsg = "Certificado de inventario emitido exitosamente.";
            $tabActiva = 'stock';
        } catch (Exception $e) {
            error_log('Error al emitir el certificado de inventario: ' . $e->getMessage());
            $errorMsg = "Error al emitir el certificado de inventario. Intente de nuevo más tarde.";
            $tabActiva = 'stock';
        }
    }

    // Patrón Post-Redirect-Get: sin esto, el navegador guarda el POST en el
    // historial y al volver atrás/recargar ofrece "reenviar formulario" —
    // si el usuario acepta, la acción se repite (ej. duplica un certificado
    // o una entrada de inventario). El mensaje viaja por la URL en vez de
    // quedar en una variable, porque tras el redirect esto vuelve a
    // ejecutarse como un GET nuevo.
    $paramsRedirect = ['tab' => $tabActiva];
    if ($successMsg !== '') {
        $paramsRedirect['msg'] = 'exito';
        $paramsRedirect['texto'] = $successMsg;
    } elseif ($errorMsg !== '') {
        $paramsRedirect['msg'] = 'error';
        $paramsRedirect['texto'] = $errorMsg;
    }
    header('Location: index.php?' . http_build_query($paramsRedirect));
    exit;
}

// Reconstruir el mensaje de feedback tras el redirect (ver PRG arriba).
if (isset($_GET['msg'], $_GET['texto'])) {
    if ($_GET['msg'] === 'exito') {
        $successMsg = (string) $_GET['texto'];
    } elseif ($_GET['msg'] === 'error') {
        $errorMsg = (string) $_GET['texto'];
    }
}

// --- CONSULTA DE DATOS ---

// Listado de códigos UNSPSC para los formularios
$codigosUnspsc = [];
try {
    // "CLASE" no es una columna real de codigo_unspsc (es CLASE_TITULO) —
    // esto hacía fallar la consulta en silencio y dejaba el desplegable de
    // códigos del modal "Nuevo Artículo" siempre vacío. El catálogo tiene
    // ~150k filas, así que además se limita a 300 para no intentar renderizar
    // un <select> gigante e inutilizable.
    $stmtC = $pdo->query("SELECT ID_CODIGO, CODIGO_UNSPSC, CLASE_TITULO FROM codigo_unspsc ORDER BY CODIGO_UNSPSC ASC LIMIT 300");
    $codigosUnspsc = $stmtC->fetchAll();

    // Los códigos ya usados por artículos de stock existentes deben estar
    // siempre presentes, aunque queden fuera de ese límite de 300 — si no,
    // "Editar" no puede dejar seleccionado ese código (el <select> no puede
    // marcar un value que no tiene como <option>) y lo borra en silencio al
    // guardar.
    $codigosYaUsados = $pdo->query("SELECT DISTINCT CODIGO_UNSPSC_FK FROM ficha_tecnica WHERE ID_MATRIZ_ITEM IS NULL AND CODIGO_UNSPSC_FK IS NOT NULL AND CODIGO_UNSPSC_FK <> ''")->fetchAll(PDO::FETCH_COLUMN);
    $yaIncluidos = array_column($codigosUnspsc, 'CODIGO_UNSPSC');
    $faltantes = array_values(array_diff($codigosYaUsados, $yaIncluidos));
    if (!empty($faltantes)) {
        $placeholders = implode(',', array_fill(0, count($faltantes), '?'));
        $stmtFaltantes = $pdo->prepare("SELECT ID_CODIGO, CODIGO_UNSPSC, CLASE_TITULO FROM codigo_unspsc WHERE CODIGO_UNSPSC IN ($placeholders)");
        $stmtFaltantes->execute($faltantes);
        $codigosUnspsc = array_merge($codigosUnspsc, $stmtFaltantes->fetchAll());
    }
} catch (Exception $e) {
    error_log("Error al cargar códigos UNSPSC: " . $e->getMessage());
}

// Búsqueda y filtrado para la Vista de Stock
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

try {
    // Solo se cuenta como stock físico lo que el almacenista agregó/recibió directamente
    // (ID_MATRIZ_ITEM NULL). Las filas creadas por un instructor (ID_MATRIZ_ITEM no nulo)
    // son solicitudes, no inventario real, y se consultan aparte en el Panel Instructor.
    $sql = "SELECT ID_FICHA_TECNICA, NOMBRE_ITEM, CODIGO_UNSPSC_FK, UNIDAD_MEDIDA, CANTIDAD, DESCRIPCION_GENERAL, COMENTARIOS FROM ficha_tecnica WHERE ID_MATRIZ_ITEM IS NULL";
    $params = [];
    if ($busqueda !== '') {
        $sql .= " AND (NOMBRE_ITEM LIKE ? OR CODIGO_UNSPSC_FK LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    if ($filtroEstado === 'disponible') {
        $sql .= " AND CANTIDAD > 5";
    } elseif ($filtroEstado === 'critico') {
        $sql .= " AND CANTIDAD > 0 AND CANTIDAD <= 5";
    } elseif ($filtroEstado === 'agotado') {
        $sql .= " AND CANTIDAD = 0";
    }

    $sql .= " ORDER BY ID_FICHA_TECNICA DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $itemsInventario = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error al consultar ítems para almacenista: ' . $e->getMessage());
    $itemsInventario = [];
}
$totalItems = count($itemsInventario);

// Certificados de inventario general ya emitidos (para el enlace en Vista de Stock)
asegurar_tablas_certificado_inventario($pdo);
$certificadosInventario = $pdo->query("SELECT ID_CERTIFICADO_INV, NUMERO_CERTIFICADO, FECHA_EMISION FROM certificado_inventario ORDER BY FECHA_EMISION DESC LIMIT 10")->fetchAll();

// Búsqueda para el Panel Instructor (lote o instructor solicitante)
$busquedaInstructor = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta de Lotes e ítems asociados de Instructores para el Panel Instructor
$lotesInstructores = [];
try {
    $sqlLotes = "SELECT lr.*, u.NOMBRE, u.APELLIDO, ce.NUMERO_CERTIFICADO, ce.ID_CERTIFICADO
                 FROM lote_requerimiento lr
                 INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
                 LEFT JOIN certificado_existencia ce ON lr.ID_LOTE = ce.ID_LOTE
                 WHERE 1=1";
    $paramsLotes = [];
    if ($busquedaInstructor !== '') {
        $sqlLotes .= " AND (lr.LOTE_NOMBRE LIKE ? OR CONCAT(u.NOMBRE, ' ', u.APELLIDO) LIKE ?)";
        $paramsLotes[] = "%$busquedaInstructor%";
        $paramsLotes[] = "%$busquedaInstructor%";
    }
    $sqlLotes .= " ORDER BY lr.FECHA_CREACION DESC, lr.ID_LOTE DESC";
    $stmtLotes = $pdo->prepare($sqlLotes);
    $stmtLotes->execute($paramsLotes);
    $lotesInstructores = $stmtLotes->fetchAll();
} catch (Exception $e) {
    error_log('Error al consultar lotes para almacenista: ' . $e->getMessage());
}

// Foto de perfil
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
    <title>BICERGAM - Módulo de Almacén</title>
    <link rel="stylesheet" href="../estilos.css?v=<?= filemtime(__DIR__ . '/../estilos.css') ?>">
    <style>
        /* Ajustes de Responsividad y Ancho Máximo para Almacenista (Evitar que se vea tan centrado y angosto) */
        .dashboard-page {
            max-width: 98% !important;
            width: 96% !important;
            grid-template-columns: 280px minmax(0, 1fr) !important;
            gap: 20px;
            margin: 20px auto 40px !important;
        }

        /* Responsive sidebar integration */
        @media (max-width: 992px) {
            .dashboard-page {
                /* minmax(0, 1fr), no "1fr" a secas: evita que una tabla ancha reviente el layout */
                grid-template-columns: minmax(0, 1fr) !important;
            }
            .dashboard-sidebar {
                position: relative !important;
                top: 0 !important;
                min-height: auto !important;
            }
        }

        /* Estilos Premium de Pestañas y Formularios */
        .tab-content-wrapper {
            animation: fadeIn 0.4s ease-in-out forwards;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background-color: rgba(57, 169, 0, 0.1);
            color: #2e8800;
            border: 1px solid rgba(57, 169, 0, 0.2);
        }
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-align: center;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #075985; }

        /* Formularios modernos */
        .modern-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 20px;
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        @media (max-width: 768px) {
            .modern-form {
                grid-template-columns: 1fr;
            }
        }
        .form-full-width {
            grid-column: 1 / -1;
        }
        .form-control-modern {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            background-color: #f8fafc;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control-modern:focus {
            border-color: var(--verde-sena);
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(57, 169, 0, 0.15);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM | <span class="accent-color">Almacén Central</span></h1>
            <div class="user-greeting">Solicitante: <strong><?= $usuarioNombre ?></strong></div>
        </div>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <a href="notificaciones.php" class="header-bell-link" title="Notificaciones"><img src="../iconos/notificacion.png" alt="Notificaciones" class="header-bell-icon"><?php $notifNoLeidas = contar_notificaciones_no_leidas($pdo, intval($_SESSION['usuario_id'])); $wsToken = generar_ws_token($pdo, intval($_SESSION['usuario_id']), $_SESSION['rol_nombre'] ?? ''); ?><?php if ($notifNoLeidas > 0): ?><span class="header-bell-badge" id="header-bell-badge"><?= $notifNoLeidas > 9 ? '9+' : $notifNoLeidas ?></span><?php endif; ?>
        </a>
        <a href="almacenista_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <!-- Barra lateral de navegación interna -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
            <span>BICERGAM</span>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Inventario</h4>
            <a href="index.php?tab=stock" class="sidebar-link <?= $tabActiva === 'stock' ? 'sidebar-link--primary' : '' ?>">Vista de Stock</a>
            <a href="index.php?tab=entrada" class="sidebar-link <?= $tabActiva === 'entrada' ? 'sidebar-link--primary' : '' ?>">Registrar Entrada</a>
            <a href="index.php?tab=salida" class="sidebar-link <?= $tabActiva === 'salida' ? 'sidebar-link--primary' : '' ?>">Registrar Salida</a>
            <a href="historial_movimientos.php" class="sidebar-link">Historial de Movimientos</a>
        </div>
        <div class="sidebar-group">
            <h4>Módulos del Sistema</h4>
            <a href="index.php?tab=instructor" class="sidebar-link <?= $tabActiva === 'instructor' ? 'sidebar-link--primary' : '' ?>">Panel Instructor</a>
            <a href="proveedores.php" class="sidebar-link">Proveedores</a>
            <a href="notificaciones.php" class="sidebar-link">Notificaciones</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="almacenista_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <!-- Área principal del contenido -->
    <main class="dashboard-main">
        
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <strong>✔ Éxito:</strong> <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-error">
                <strong>❌ Error:</strong> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Panel de estadísticas del dashboard (Extra) -->
        <?php require_once 'dashboard_stats.php'; ?>

        <!-- Sección de carga dinámica -->
        <div class="tab-content-wrapper">
            <?php 
                switch ($tabActiva) {
                    case 'entrada':
                        require_once 'registrar_entrada.php';
                        break;
                    case 'salida':
                        require_once 'registrar_salida.php';
                        break;
                    case 'instructor':
                        require_once 'panel_instructor.php';
                        break;
                    case 'stock':
                    default:
                        require_once 'stock.php';
                        break;
                }
            ?>
        </div>
    </main>
</div>

<!-- ================= MODAL / FORMULARIO FLOTANTE EDICIÓN Y CREACIÓN (Para Vista de Stock) ================= -->
<div id="modalArticulo" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modal-titulo" style="margin-top:0;">Agregar Nuevo Artículo</h3>
        
        <form action="index.php?tab=stock" method="POST" id="form-modal">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="modal-action" value="add_item">
            <input type="hidden" name="id_ficha_tecnica" id="modal-id-ficha" value="">

            <div style="margin-bottom:15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Nombre del Artículo</label>
                <input type="text" name="nombre_item" id="modal-nombre" class="form-control-modern" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Código UNSPSC</label>
                <select name="codigo_unspsc" id="modal-codigo" class="form-control-modern">
                    <option value="">— Ninguno / Seleccionar —</option>
                    <?php foreach ($codigosUnspsc as $cod): ?>
                        <option value="<?= htmlspecialchars($cod['CODIGO_UNSPSC']) ?>">
                            <?= htmlspecialchars($cod['CODIGO_UNSPSC']) ?> (<?= htmlspecialchars($cod['CLASE_TITULO']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Unidad de Medida</label>
                <input type="text" name="unidad_medida" id="modal-unidad" class="form-control-modern" placeholder="Ej. unidad, caja, rollo" required
                       pattern="[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]+" title="Solo se permiten letras y espacios">
            </div>

            <div style="margin-bottom:15px;" id="modal-cantidad-container">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Cantidad Inicial en Stock</label>
                <input type="number" name="cantidad" id="modal-cantidad" class="form-control-modern" min="0" value="0">
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Descripción General</label>
                <textarea name="descripcion_general" id="modal-descripcion" class="form-control-modern" rows="3"></textarea>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Comentarios Adicionales</label>
                <textarea name="comentarios" id="modal-comentarios" class="form-control-modern" rows="2"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()" style="padding:10px 20px;">Cancelar</button>
                <button type="submit" class="btn btn-sena" style="padding:10px 20px;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/apartados.js"></script>
    <script src="../js/realtime.js" data-ws-token="<?= htmlspecialchars($wsToken ?? '') ?>"></script>
<script>
    // Lógica de Modales para CRUD
    function mostrarModalNuevoArticulo() {
        document.getElementById('modal-titulo').innerText = "Agregar Nuevo Artículo al Stock";
        document.getElementById('modal-action').value = "add_item";
        document.getElementById('modal-id-ficha').value = "";
        document.getElementById('modal-nombre').value = "";
        document.getElementById('modal-codigo').value = "";
        document.getElementById('modal-unidad').value = "";
        document.getElementById('modal-cantidad').value = "0";
        document.getElementById('modal-cantidad-container').style.display = "block";
        document.getElementById('modal-descripcion').value = "";
        document.getElementById('modal-comentarios').value = "";
        document.getElementById('modalArticulo').classList.add('is-open');
    }

    function cargarDatosEdicion(item) {
        document.getElementById('modal-titulo').innerText = "Editar Artículo del Stock #" + item.ID_FICHA_TECNICA;
        document.getElementById('modal-action').value = "edit_item";
        document.getElementById('modal-id-ficha').value = item.ID_FICHA_TECNICA;
        document.getElementById('modal-nombre').value = item.NOMBRE_ITEM;
        document.getElementById('modal-codigo').value = item.CODIGO_UNSPSC_FK || "";
        document.getElementById('modal-unidad').value = item.UNIDAD_MEDIDA;
        document.getElementById('modal-cantidad').value = item.CANTIDAD;
        document.getElementById('modal-cantidad-container').style.display = "block";
        document.getElementById('modal-descripcion').value = item.DESCRIPCION_GENERAL || "";
        document.getElementById('modal-comentarios').value = item.COMENTARIOS || "";
        document.getElementById('modalArticulo').classList.add('is-open');
    }

    function cerrarModal() {
        document.getElementById('modalArticulo').classList.remove('is-open');
    }

    // Toggle de Acordeón para Lotes de Instructores
    function toggleLoteDetalle(idLote) {
        const body = document.getElementById('lote-body-' + idLote);
        if (body) {
            body.classList.toggle('is-open');
        }
    }
</script>

</body>
</html>
