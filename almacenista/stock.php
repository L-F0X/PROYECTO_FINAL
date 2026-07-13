<?php
// almacenista/stock.php
// Vista y operaciones CRUD de la lista de existencias
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}
?>

<div class="dashboard-topbar" style="margin-bottom: 20px;">
    <div>
        <span class="hud-brand">BICERGAM</span>
        <h2>Vista General de Stock</h2>
        <p class="dashboard-subtitle">Control detallado de existencias actuales, especificaciones técnicas y alertas de inventario crítico.</p>
    </div>
    <div class="hud-status">
        <span class="hud-dot"></span>
        <span><?= fecha_larga_es() ?></span>
    </div>
</div>

<!-- Panel de acciones rápidas y buscador -->
<div class="panel-card" style="margin-bottom: 25px;">
    <div class="actions-bar no-print" style="border: none; padding: 0; margin: 0 0 20px; justify-content: flex-end; gap: 10px;">
        <?php if (!empty($itemsInventario) || $totalItems > 0): ?>
        <form method="POST" action="index.php?tab=stock" style="margin: 0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="emitir_certificado_inventario">
            <button type="submit" class="btn btn-info js-confirm-submit" data-confirm-title="Emitir Certificado de Inventario" data-confirm-message="¿Emitir un certificado con el inventario físico completo tal como está en este momento?" data-confirm-label="Emitir" data-confirm-danger="false">📋 Emitir Certificado de Inventario</button>
        </form>
        <?php endif; ?>
        <button class="btn btn-sena" onclick="mostrarModalNuevoArticulo()">+ Nuevo Artículo de Stock</button>
    </div>

    <?php if (!empty($certificadosInventario)): ?>
    <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 18px;">
        <h3 style="margin: 0 0 4px; font-size: 15px;">Certificados de Inventario Emitidos</h3>
        <p class="dashboard-subtitle" style="margin: 0 0 10px;">Últimas fotos oficiales del inventario físico completo.</p>
        <ul style="list-style:none; margin:0; padding:0;">
            <?php foreach ($certificadosInventario as $ci): ?>
                <li style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 4px; border-bottom:1px solid #eee;">
                    <span style="display:flex; align-items:center; gap:10px;">
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; background:#e0f2fe; color:#0284c7; font-size:16px; flex-shrink:0;">📋</span>
                        <span>
                            <strong style="display:block; font-size:14px; color:#0f172a;"><?= htmlspecialchars($ci['NUMERO_CERTIFICADO']) ?></strong>
                            <span style="font-size:12px; color:#888;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ci['FECHA_EMISION']))) ?></span>
                        </span>
                    </span>
                    <a href="certificado_inventario_pdf.php?id=<?= (int) $ci['ID_CERTIFICADO_INV'] ?>" class="btn btn-info" style="padding: 6px 14px; font-size: 12px; text-decoration: none; white-space: nowrap;">Ver / Exportar</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="GET" action="index.php" id="form-busqueda" style="margin-bottom: 10px;">
        <input type="hidden" name="tab" value="stock">
        <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="field-group" style="flex: 1; min-width: 250px; display: flex; flex-direction: column;">
                <label style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por nombre o código</label>
                <input type="text" id="q" name="q" class="search-input" placeholder="Ej. esponja, 451278..." value="<?= htmlspecialchars($busqueda) ?>" style="padding: 10px; border: 1.5px solid #cbd5e1; border-radius: 8px; width: 100%;" autocomplete="off">
            </div>
            <div class="field-group" style="min-width: 180px; display: flex; flex-direction: column;">
                <label style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Nivel de Existencia</label>
                <select name="estado" id="estado" class="search-input" style="padding: 10px; border: 1.5px solid #cbd5e1; border-radius: 8px; width: 100%;">
                    <option value="">— Todos —</option>
                    <option value="disponible" <?= $filtroEstado === 'disponible' ? 'selected' : '' ?>>Stock Óptimo (> 5)</option>
                    <option value="critico" <?= $filtroEstado === 'critico' ? 'selected' : '' ?>>Stock Crítico (1 - 5)</option>
                    <option value="agotado" <?= $filtroEstado === 'agotado' ? 'selected' : '' ?>>Agotados (0)</option>
                </select>
            </div>
            <a href="index.php?tab=stock" class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; height: 42px;">Limpiar Filtros</a>
        </div>
    </form>
</div>

<div id="resultados-busqueda">
    <!-- Tabla de Inventario Principal -->
    <div class="panel-card" style="overflow-x: auto;">
        <h3 style="margin-top: 0;">Inventario Físico (<?= $totalItems ?> Artículos)</h3>
        <table style="width: 100%; min-width: 700px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descripción / Artículo</th>
                    <th>Código UNSPSC</th>
                    <th>Unidad Medida</th>
                    <th>Stock Actual</th>
                    <th>Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itemsInventario)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    <line x1="8" y1="11" x2="14" y2="11"/>
                                </svg>
                                <p>No se encontraron artículos con los criterios especificados.</p>
                                <span>Intenta con otro término o limpia la búsqueda.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($itemsInventario as $item): ?>
                        <?php
                            $cant = intval($item['CANTIDAD']);
                            if ($cant === 0) {
                                $badgeClass = 'badge-danger';
                                $estadoTxt = 'Agotado';
                            } elseif ($cant <= 5) {
                                $badgeClass = 'badge-warning';
                                $estadoTxt = 'Stock Crítico';
                            } else {
                                $badgeClass = 'badge-success';
                                $estadoTxt = 'Óptimo';
                            }
                        ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($item['ID_FICHA_TECNICA']) ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($item['NOMBRE_ITEM']) ?></div>
                                <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($item['DESCRIPCION_GENERAL']) ?></div>
                            </td>
                            <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($item['CODIGO_UNSPSC_FK'] ?: 'Sin asignar') ?></code></td>
                            <td><?= htmlspecialchars($item['UNIDAD_MEDIDA']) ?></td>
                            <td><strong style="font-size: 1.1rem;"><?= $cant ?></strong></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $estadoTxt ?></span></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 5px; justify-content: center;">
                                    <button class="btn btn-info" style="padding: 6px 12px; font-size: 0.8rem;"
                                            onclick="cargarDatosEdicion(<?= htmlspecialchars(json_encode($item)) ?>)">Editar</button>

                                    <form action="index.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id_ficha_tecnica" value="<?= (int)$item['ID_FICHA_TECNICA'] ?>">
                                        <button type="submit" class="btn btn-danger js-confirm-submit" style="padding: 6px 12px; font-size: 0.8rem;" data-confirm-title="Eliminar artículo" data-confirm-message="¿Seguro que desea eliminar este artículo del stock?" data-confirm-label="Eliminar">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
