<?php
// almacenista/certificado_inventario_pdf.php
// Vista de detalle + exportación a PDF (impresión del navegador) del
// certificado de inventario general — a diferencia del certificado por
// lote, no depende de ninguna solicitud de instructor: certifica el
// inventario físico completo tal como estaba al momento de emitirlo.
require_once '../conexion.php';
require_once '../certificado_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

asegurar_tablas_certificado_inventario($pdo);

$idCertificado = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idCertificado === 0) {
    die('Certificado no válido.');
}

$stmtCert = $pdo->prepare("
    SELECT ci.*, u.NOMBRE, u.APELLIDO
    FROM certificado_inventario ci
    LEFT JOIN usuario u ON ci.ID_ALMACENISTA = u.ID_USUARIO
    WHERE ci.ID_CERTIFICADO_INV = ?
    LIMIT 1
");
$stmtCert->execute([$idCertificado]);
$cert = $stmtCert->fetch();

if (!$cert) {
    die('Certificado no encontrado.');
}

$stmtItems = $pdo->prepare("SELECT * FROM certificado_inventario_item WHERE ID_CERTIFICADO_INV = ? ORDER BY NOMBRE_ITEM");
$stmtItems->execute([$idCertificado]);
$materiales = $stmtItems->fetchAll();

$rolNombre = htmlspecialchars($_SESSION['rol_nombre'] ?? 'Usuario');
$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

function firma_nombre_corto_inv(?string $nombre, ?string $apellido): string {
    $primerNombre = trim(explode(' ', trim((string) $nombre))[0] ?? '');
    $primerApellido = trim(explode(' ', trim((string) $apellido))[0] ?? '');
    return trim($primerNombre . ' ' . $primerApellido);
}
$firmaAlmacenista = firma_nombre_corto_inv($cert['NOMBRE'] ?? null, $cert['APELLIDO'] ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Inventario - BICERGAM</title>
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
            justify-content: center;
            margin-top: 50px;
        }
        .cert-sign-block {
            flex: 0 0 300px;
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
            <h1>CERTIFICADO DE INVENTARIO GENERAL</h1>
        </div>

        <div class="cert-meta">
            <div><strong>Número de Certificado:</strong> <?= htmlspecialchars($cert['NUMERO_CERTIFICADO']) ?></div>
            <div><strong>Fecha de Emisión:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cert['FECHA_EMISION']))) ?></div>
            <div><strong>Emitido por:</strong> <?= htmlspecialchars(trim(($cert['NOMBRE'] ?? '') . ' ' . ($cert['APELLIDO'] ?? '')) ?: 'Almacén Central SENA') ?></div>
            <div><strong>Total de artículos certificados:</strong> <?= count($materiales) ?></div>
        </div>

        <p class="cert-footer-text">
            El Almacén Central del Servicio Nacional de Aprendizaje (SENA) certifica que, a la fecha de emisión
            de este documento, el inventario físico del almacén cuenta con los siguientes materiales y cantidades
            en existencia real, verificados directamente en bodega:
        </p>

        <table class="cert-table">
            <thead>
                <tr>
                    <th>Descripción del Material</th>
                    <th>Código UNSPSC</th>
                    <th>Unidad de Medida</th>
                    <th>Cantidad en Existencia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materiales)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b;">No había artículos en el inventario al momento de emitir este certificado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materiales as $mat): ?>
                        <tr>
                            <td><?= htmlspecialchars($mat['NOMBRE_ITEM']) ?></td>
                            <td><?= htmlspecialchars($mat['CODIGO_UNSPSC'] ?: 'Sin asignar') ?></td>
                            <td><?= htmlspecialchars($mat['UNIDAD_MEDIDA'] ?: 'Unidad') ?></td>
                            <td style="font-weight: 600;"><?= (int) $mat['CANTIDAD'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="cert-footer-text">
            Este certificado refleja el estado del inventario únicamente al momento de su emisión;
            las existencias pueden variar con entradas y salidas posteriores registradas en el sistema.
        </p>

        <div class="cert-signatures">
            <div class="cert-sign-block">
                <div class="cert-sign-digital"><?= $firmaAlmacenista !== '' ? htmlspecialchars($firmaAlmacenista) : 'Almacén Central SENA' ?></div>
                <div class="cert-sign-line">Firma del Almacenista</div>
            </div>
        </div>
    </div>
</div>

<script src="../js/apartados.js"></script>
</body>
</html>
