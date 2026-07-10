<?php
// instructor/certificado_pdf.php
// Vista de detalle + exportación a PDF (impresión del navegador) del
// certificado de existencia, incluyendo el listado de materiales del lote.
// Accesible por almacenista/coordinador/administrador para cualquier lote;
// un instructor solo puede ver los certificados de sus propios lotes.
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Migración aditiva: asegurar columnas de auditoría en certificado_existencia si no existen
if (!function_exists('certificado_columna_existe')) {
    function certificado_columna_existe(PDO $pdo, string $tabla, string $columna): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$tabla, $columna]);
        return (bool) $stmt->fetchColumn();
    }
}
if (!certificado_columna_existe($pdo, 'certificado_existencia', 'FECHA_EMISION')) {
    $pdo->exec("ALTER TABLE certificado_existencia ADD COLUMN FECHA_EMISION TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}
if (!certificado_columna_existe($pdo, 'certificado_existencia', 'ID_ALMACENISTA')) {
    $pdo->exec("ALTER TABLE certificado_existencia ADD COLUMN ID_ALMACENISTA INT DEFAULT NULL");
}

$idCertificado = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idCertificado === 0) {
    die('Certificado no válido.');
}

$rolNombreCheck = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
$usuarioIdCheck = intval($_SESSION['usuario_id']);

$stmtCert = $pdo->prepare("
    SELECT ce.*, lr.LOTE_NOMBRE, lr.FECHA_CREACION, lr.ID_SOLICITANTE, u.NOMBRE, u.APELLIDO, u.EMAIL,
           ua.NOMBRE AS ALMACENISTA_NOMBRE, ua.APELLIDO AS ALMACENISTA_APELLIDO
    FROM certificado_existencia ce
    INNER JOIN lote_requerimiento lr ON ce.ID_LOTE = lr.ID_LOTE
    INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
    LEFT JOIN usuario ua ON ce.ID_ALMACENISTA = ua.ID_USUARIO
    WHERE ce.ID_CERTIFICADO = ?
    LIMIT 1
");
$stmtCert->execute([$idCertificado]);
$cert = $stmtCert->fetch();

if (!$cert) {
    die('Certificado no encontrado.');
}

// Un instructor solo puede ver certificados de lotes que él mismo solicitó;
// almacenista/coordinador/administrador pueden ver cualquiera (lo necesitan para su labor).
if ($rolNombreCheck === 'instructor' && intval($cert['ID_SOLICITANTE']) !== $usuarioIdCheck) {
    die('No tiene permiso para ver este certificado.');
}

// Materiales (ítems) del lote: esto es lo que realmente certifica la existencia.
$stmtItems = $pdo->prepare("
    SELECT mi.*, cu.CODIGO_UNSPSC
    FROM matriz_item mi
    LEFT JOIN codigo_unspsc cu ON mi.ID_CODIGO_UNSPSC = cu.ID_CODIGO
    WHERE mi.ID_LOTE = ?
    ORDER BY mi.ID_MATRIZ_ITEM
");
$stmtItems->execute([$cert['ID_LOTE']]);
$materiales = $stmtItems->fetchAll();

$rolNombre = htmlspecialchars($_SESSION['rol_nombre'] ?? 'Usuario');
$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

// Firma digital: solo el primer nombre y primer apellido, no el nombre completo
function firma_nombre_corto(?string $nombre, ?string $apellido): string {
    $primerNombre = trim(explode(' ', trim((string)$nombre))[0] ?? '');
    $primerApellido = trim(explode(' ', trim((string)$apellido))[0] ?? '');
    return trim($primerNombre . ' ' . $primerApellido);
}
$firmaAlmacenista = firma_nombre_corto($cert['ALMACENISTA_NOMBRE'] ?? null, $cert['ALMACENISTA_APELLIDO'] ?? null);
$firmaInstructor = firma_nombre_corto($cert['NOMBRE'] ?? null, $cert['APELLIDO'] ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Existencia - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .cert-doc {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
        }
        .cert-doc-header {
            text-align: center;
            border-bottom: 3px double #39A900;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .cert-doc-header .brand {
            color: #39A900;
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }
        .cert-doc-header h1 {
            margin: 10px 0 0;
            font-size: 1.3rem;
            color: #0f172a;
        }
        .cert-meta {
            background: #f8fafc;
            border-radius: 8px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 10px 24px;
        }
        .cert-meta div strong { color: #334155; }
        .cert-table {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .cert-table thead, .cert-table tbody { display: table-row-group; }
        .cert-table tr { display: table-row; }
        .cert-table th, .cert-table td { display: table-cell; }
        .cert-table th, .cert-table td {
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            font-size: 0.9rem;
            text-align: left;
        }
        .cert-table th {
            background: #1e293b;
            color: #fff;
        }
        .cert-footer-text {
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        .cert-signatures {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            margin-top: 50px;
        }
        .cert-sign-block {
            flex: 1;
            text-align: center;
        }
        .cert-sign-digital {
            font-family: 'Segoe Script', 'Brush Script MT', 'Lucida Handwriting', cursive;
            font-size: 1.7rem;
            color: #00324D;
            min-height: 2.2rem;
            line-height: 2.2rem;
        }
        .cert-sign-line {
            border-top: 1px solid #334155;
            padding-top: 8px;
            font-size: 0.85rem;
            color: #475569;
        }
        @media print {
            body { background: #fff; }
            .cert-doc { border: none; padding: 0; }
        }
    </style>
</head>
<body>

<header class="header-main no-print">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;" class="sena-logo-img">
        <div>
            <h1 class="header-title">BICERGAM</h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(<?= $rolNombre ?>)</span></div>
        </div>
    </div>
</header>

<div style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">
    <div class="actions-bar no-print" style="justify-content: space-between; border: none; padding: 0; margin-bottom: 20px;">
        <a href="javascript:history.back()" class="btn btn-secondary">&larr; Volver</a>
        <button type="button" class="btn btn-sena" onclick="window.print()">Exportar a PDF</button>
    </div>

    <div class="cert-doc">
        <div class="cert-doc-header">
            <div class="brand">SENA | BICERGAM</div>
            <h1>CERTIFICADO DE CONSOLIDACIÓN Y EXISTENCIA</h1>
        </div>

        <div class="cert-meta">
            <div><strong>Número de Certificado:</strong> <?= htmlspecialchars($cert['NUMERO_CERTIFICADO']) ?></div>
            <div><strong>Lote:</strong> <?= htmlspecialchars($cert['LOTE_NOMBRE']) ?> (#<?= htmlspecialchars($cert['ID_LOTE']) ?>)</div>
            <div><strong>Instructor Solicitante:</strong> <?= htmlspecialchars($cert['NOMBRE'] . ' ' . $cert['APELLIDO']) ?></div>
            <div><strong>Correo:</strong> <?= htmlspecialchars($cert['EMAIL']) ?></div>
            <div><strong>Fecha de Creación del Lote:</strong> <?= htmlspecialchars($cert['FECHA_CREACION']) ?></div>
            <div><strong>Fecha de Certificación:</strong> <?= htmlspecialchars(!empty($cert['FECHA_EMISION']) ? date('d/m/Y', strtotime($cert['FECHA_EMISION'])) : date('d/m/Y')) ?></div>
        </div>

        <p class="cert-footer-text">
            El Almacén Central del Servicio Nacional de Aprendizaje (SENA) certifica que se ha realizado
            la revisión y consolidación del requerimiento correspondiente al lote institucional indicado,
            y que los siguientes materiales no se encuentran actualmente en existencia dentro del almacén,
            por lo cual se remite el presente certificado para dar trámite a su proceso de adquisición y compra:
        </p>

        <table class="cert-table">
            <thead>
                <tr>
                    <th>Descripción del Material</th>
                    <th>Código UNSPSC</th>
                    <th>Cantidad</th>
                    <th>Unidad de Medida</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materiales)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b;">Este lote no tiene materiales registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materiales as $mat): ?>
                        <tr>
                            <td><?= htmlspecialchars($mat['DESCRIPCION_BIEN']) ?></td>
                            <td><?= htmlspecialchars($mat['CODIGO_UNSPSC'] ?: 'Sin asignar') ?></td>
                            <td><?= htmlspecialchars($mat['CANTIDAD_REGULAR']) ?></td>
                            <td><?= htmlspecialchars($mat['UNIDAD_MEDIDA'] ?: 'Unidad') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="cert-footer-text">
            Se autoriza dar inicio al proceso de adquisición de los materiales aquí listados
            para la consolidación de la oferta académica actual.
        </p>

        <div class="cert-signatures">
            <div class="cert-sign-block">
                <div class="cert-sign-digital"><?= $firmaAlmacenista !== '' ? htmlspecialchars($firmaAlmacenista) : 'Almacén Central SENA' ?></div>
                <div class="cert-sign-line">Firma del Almacenista</div>
            </div>
            <div class="cert-sign-block">
                <div class="cert-sign-digital"><?= $firmaInstructor !== '' ? htmlspecialchars($firmaInstructor) : 'Solicitante' ?></div>
                <div class="cert-sign-line">Firma del Instructor</div>
            </div>
        </div>
    </div>
</div>

<script src="../js/apartados.js"></script>
</body>
</html>
