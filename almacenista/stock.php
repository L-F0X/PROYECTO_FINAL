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
    <div class="actions-bar no-print" style="border: none; padding: 0; margin: 0 0 20px; justify-content: flex-end;">
        <button class="btn btn-sena" onclick="mostrarModalNuevoArticulo()">+ Nuevo Artículo de Stock</button>
    </div>

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
            <button type="submit" class="btn btn-sena" style="padding: 10px 20px; height: 42px;">Buscar</button>
            <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                <a href="index.php?tab=stock" class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; height: 42px;">Limpiar Filtros</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabla de Inventario Principal -->
<div class="panel-card" style="overflow-x: auto;">
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
                    <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">No se encontraron artículos con los criterios especificados.</td>
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
                                <button class="btn-action-small" style="background: #e0f2fe; color: #0369a1;" 
                                        onclick="cargarDatosEdicion(<?= htmlspecialchars(json_encode($item)) ?>)">Editar</button>
                                
                                <form action="index.php" method="POST" onsubmit="return confirm('¿Seguro que desea eliminar este artículo del stock?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="id_ficha_tecnica" value="<?= $item['ID_FICHA_TECNICA'] ?>">
                                    <button type="submit" class="btn-action-small" style="background: #fee2e2; color: #b91c1c;">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
