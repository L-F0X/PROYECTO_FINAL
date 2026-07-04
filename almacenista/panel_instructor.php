<?php
// almacenista/panel_instructor.php
// Panel de control para verificar lotes de instructores y emitir certificados
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}
?>

<div class="dashboard-topbar" style="margin-bottom: 20px;">
    <div>
        <h2>Panel de Requerimientos de Instructores</h2>
        <p class="dashboard-subtitle">Consulta los lotes y requerimientos solicitados por instructores y emite certificados de existencia.</p>
    </div>
</div>

<?php if (empty($lotesInstructores)): ?>
    <div class="panel-card" style="text-align: center; padding: 40px; color: #64748b;">
        No existen lotes de requerimientos registrados en el sistema.
    </div>
<?php else: ?>
    <?php foreach ($lotesInstructores as $lote): ?>
        <div class="lote-card" style="margin-bottom: 15px; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; background: #ffffff;">
            <div class="lote-header" onclick="toggleLoteDetalle(<?= $lote['ID_LOTE'] ?>)" style="padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; cursor: pointer; transition: background 0.2s;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span style="font-weight: 700; font-size: 1.15rem; color: #0f172a;">Lote: <?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></span>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; font-size: 0.85rem; color: #64748b;">
                        <span>Solicitante: <strong><?= htmlspecialchars($lote['NOMBRE'] . ' ' . $lote['APELLIDO']) ?></strong></span>
                        <span>•</span>
                        <span>Creado: <?= htmlspecialchars($lote['FECHA_CREACION']) ?></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span class="badge <?= $lote['ESTADO_TRAMITE'] === 'Aprobado' ? 'badge-success' : ($lote['ESTADO_TRAMITE'] === 'Rechazado' ? 'badge-danger' : 'badge-warning') ?>">
                        <?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?>
                    </span>
                    <?php if ($lote['NUMERO_CERTIFICADO']): ?>
                        <span class="badge badge-info" title="<?= htmlspecialchars($lote['NUMERO_CERTIFICADO']) ?>">Certificado Emitido</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Sin Certificado</span>
                    <?php endif; ?>
                    <button class="btn-action-small" style="background: #e2e8f0; color: #334155; margin-left: 5px;">Detalles</button>
                </div>
            </div>
            
            <div class="lote-body" id="lote-body-<?= $lote['ID_LOTE'] ?>" style="background: #ffffff; padding-left: 24px; padding-right: 24px;">
                <h4 style="margin-top: 0; margin-bottom: 12px; color: var(--gris-oscuro);">Ítems del Requerimiento:</h4>
                
                <?php
                // Consultar los ítems asociados a este lote
                $itemsLote = [];
                try {
                    $stmtItems = $pdo->prepare("SELECT mi.*, u.CODIGO_UNSPSC 
                                                 FROM matriz_item mi
                                                 LEFT JOIN codigo_unspsc u ON mi.ID_CODIGO_UNSPSC = u.ID_CODIGO
                                                 WHERE mi.ID_LOTE = ?");
                    $stmtItems->execute([$lote['ID_LOTE']]);
                    $itemsLote = $stmtItems->fetchAll();
                } catch (Exception $e) {
                    error_log("Error al cargar ítems del lote " . $lote['ID_LOTE'] . ": " . $e->getMessage());
                }
                ?>

                <?php if (empty($itemsLote)): ?>
                    <p style="color: #64748b; font-style: italic; margin-bottom: 20px;">Este lote no contiene ningún ítem aún.</p>
                <?php else: ?>
                    <div style="overflow-x: auto; margin-bottom: 20px;">
                        <table style="width: 100%; min-width: 600px; font-size: 0.9rem;">
                            <thead>
                                <tr style="background-color: #f1f5f9; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 10px; text-align: left;">Descripción Item</th>
                                    <th style="padding: 10px; text-align: left;">Código UNSPSC</th>
                                    <th style="padding: 10px; text-align: center;">Cantidad</th>
                                    <th style="padding: 10px; text-align: center;">U. Medida</th>
                                    <th style="padding: 10px; text-align: left;">Ficha Técnica</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itemsLote as $itemL): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 10px; font-weight: 500;"><?= htmlspecialchars($itemL['DESCRIPCION_BIEN']) ?></td>
                                        <td style="padding: 10px;"><code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($itemL['CODIGO_UNSPSC'] ?: 'Sin Asignar') ?></code></td>
                                        <td style="padding: 10px; text-align: center; font-weight: bold;"><?= htmlspecialchars($itemL['CANTIDAD_REGULAR']) ?></td>
                                        <td style="padding: 10px; text-align: center;"><?= htmlspecialchars($itemL['UNIDAD_MEDIDA'] ?: 'Unidad') ?></td>
                                        <td style="padding: 10px; font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($itemL['FICHA_TECNICA'] ?: 'Ninguna') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background-color: #f8fafc; padding: 20px; border-radius: 10px; border: 1px dashed #cbd5e1; margin-top: 15px;">
                    <div>
                        <strong>Estado de Existencia en Almacén:</strong>
                        <?php if ($lote['NUMERO_CERTIFICADO']): ?>
                            <div style="margin-top: 4px; color: #166534; font-weight: bold;">
                                Certificado: <span style="font-family: monospace; font-size: 0.95rem; background: #dcfce7; padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(22,101,52,0.15);"><?= htmlspecialchars($lote['NUMERO_CERTIFICADO']) ?></span>
                            </div>
                        <?php else: ?>
                            <span style="color: #b91c1c; font-weight: bold; margin-left: 5px;">Firma de existencias pendiente</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <?php if ($lote['NUMERO_CERTIFICADO']): ?>
                            <button onclick="imprimirCertificado('<?= htmlspecialchars($lote['NUMERO_CERTIFICADO']) ?>', '<?= htmlspecialchars($lote['LOTE_NOMBRE']) ?>', '<?= htmlspecialchars($lote['NOMBRE'] . ' ' . $lote['APELLIDO']) ?>')" class="btn-action-small" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;">🖨 Imprimir Certificado</button>
                        <?php endif; ?>

                        <?php if (!$lote['NUMERO_CERTIFICADO']): ?>
                            <form action="index.php?tab=instructor" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="emitir_certificado">
                                <input type="hidden" name="id_lote" value="<?= $lote['ID_LOTE'] ?>">
                                <button type="submit" class="btn btn-sena" style="padding: 10px 20px; font-size: 0.9rem;">Emitir Certificado de Existencia</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary" style="padding: 10px 20px; font-size: 0.9rem;" disabled>Certificado Firmado</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Estilo e Impresora Mockup Extra -->
<script>
function imprimirCertificado(numero, lote, instructor) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Certificado de Existencia - ${numero}</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; padding: 40px; color: #333; line-height: 1.6; }
                .cert-box { border: 4px double #39A900; padding: 30px; border-radius: 12px; }
                .logo { text-align: center; margin-bottom: 20px; }
                h1 { text-align: center; color: #1e293b; margin-top: 0; }
                .details { margin: 30px 0; background: #f8fafc; padding: 20px; border-radius: 8px; }
                .footer-sign { margin-top: 50px; display: flex; justify-content: space-between; }
                .sign-line { border-top: 1px solid #ccc; width: 220px; text-align: center; padding-top: 8px; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class="cert-box">
                <div class="logo">
                    <strong style="color: #39A900; font-size: 1.5rem;">SENA | BICERGAM</strong>
                </div>
                <h1>CERTIFICADO DE CONSOLIDACIÓN Y EXISTENCIA</h1>
                <p>El Almacén Central del Servicio Nacional de Aprendizaje (SENA) certifica que se ha realizado la revisión técnica del requerimiento correspondiente al lote institucional.</p>
                
                <div class="details">
                    <div><strong>Número de Radicado:</strong> ${numero}</div>
                    <div><strong>Lote Requerido:</strong> ${lote}</div>
                    <div><strong>Instructor Solicitante:</strong> ${instructor}</div>
                    <div><strong>Fecha de Certificación:</strong> ${new Date().toLocaleDateString('es-ES')}</div>
                </div>

                <p>Se confirma la validación de saldos y existencias físicas del inventario de materiales para la consolidación de la oferta académica actual.</p>

                <div class="footer-sign">
                    <div class="sign-line">Firma del Almacenista<br><small>Almacén Central SENA</small></div>
                    <div class="sign-line">Firma del Instructor<br><small>Solicitante</small></div>
                </div>
            </div>
            <script>window.onload = function() { window.print(); }</` + `script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
