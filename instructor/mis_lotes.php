<?php
// instructor/mis_lotes.php
// Panel "Mis Lotes" del instructor: búsqueda, filtro y tabla de lotes propios.
// Se incluye desde index.php, que ya define $lotes, $busqueda, $filtroEstado
// y ya cargó conexion.php / csrf.php.
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}
?>
<div class="panel-card" id="lotes-panel-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h3>Mis Lotes</h3>
        <div class="actions-bar" style="border: none; padding: 0; margin: 0;">
            <a href="crear.php" class="btn btn-sena">+ Crear Nuevo Lote</a>
        </div>
    </div>

    <!-- Formulario de búsqueda -->
    <form method="GET" action="index.php" id="form-busqueda" style="margin-bottom: 20px;">
        <div class="search-bar" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="field-group" style="flex: 1; min-width: 200px; display: flex; flex-direction: column;">
                <label for="q" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Buscar lote</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    class="search-input"
                    placeholder="Buscar por nombre o ID..."
                    value="<?= htmlspecialchars($busqueda) ?>"
                    style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                    autocomplete="off"
                >
            </div>
            <div class="field-group" style="min-width: 160px; display: flex; flex-direction: column;">
                <label for="estado" style="font-weight: bold; margin-bottom: 5px; font-size: 14px;">Filtrar por estado</label>
                <select name="estado" id="estado" class="search-input" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">— Todos —</option>
                    <?php
                    $estados = ['Borrador','Enviado','Aprobado','Rechazado'];
                    foreach ($estados as $e):
                        $sel = ($filtroEstado === $e) ? 'selected' : '';
                    ?>
                        <option value="<?= $e ?>" <?= $sel ?>><?= $e ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sena" style="padding: 8px 16px;">Buscar</button>
            <?php if ($busqueda !== '' || $filtroEstado !== ''): ?>
                <a href="index.php" class="btn btn-secondary" style="padding: 8px 16px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5; color: #333;">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Lote</th>
                <th>Estado Trámite</th>
                <th>Fecha Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($lotes)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">No hay lotes registrados o que coincidan con la búsqueda.</td>
                </tr>
            <?php else: ?>
                <?php foreach($lotes as $lote): ?>
                    <tr>
                        <td><?= htmlspecialchars($lote['ID_LOTE']) ?></td>
                        <td><?= htmlspecialchars($lote['LOTE_NOMBRE']) ?></td>
                        <td><strong><?= htmlspecialchars($lote['ESTADO_TRAMITE']) ?></strong></td>
                        <td><?= htmlspecialchars($lote['FECHA_CREACION']) ?></td>
                        <td>
                            <a href="matriz.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #39A900;">Gestionar Ítems</a>
                            <a href="fichas_tecnicas_creadas.php?lote=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px; background-color: #00324D;">Ver Fichas Tecnicas</a>
                            <a href="editar.php?id=<?= htmlspecialchars($lote['ID_LOTE']) ?>" class="btn btn-sena" style="padding: 5px 10px; font-size: 12px;">Editar Lote</a>
                            <form action="eliminar.php" method="POST" style="display:inline; margin:0;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($lote['ID_LOTE']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <button type="submit" class="btn btn-danger btn-eliminar" style="padding: 5px 10px; font-size: 12px; border: none; background: var(--alerta-rojo); color: white;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
