<?php
// almacenista/registrar_salida.php
// Componente de Formulario de Salida
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}
?>

<div class="dashboard-topbar" style="margin-bottom: 20px;">
    <div>
        <h2>Registrar Salida / Despacho de Elementos</h2>
        <p class="dashboard-subtitle">Registra la salida o despacho de materiales a instructores, verificando que no se exceda el stock actual.</p>
    </div>
</div>

<div class="panel-card">
    <form action="index.php?tab=salida" method="POST" class="modern-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="registrar_salida">
        
        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Seleccionar Artículo a Despachar</label>
            <select name="id_ficha_tecnica" class="form-control-modern" required>
                <option value="">— Seleccione un elemento —</option>
                <?php foreach ($itemsInventario as $i): ?>
                    <option value="<?= (int)$i['ID_FICHA_TECNICA'] ?>" <?= $i['CANTIDAD'] == 0 ? 'disabled style="color: #cbd5e1;"' : '' ?>>
                        <?= htmlspecialchars($i['NOMBRE_ITEM']) ?> (Disponible: <?= $i['CANTIDAD'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Cantidad a Salir</label>
            <input type="number" name="cantidad" class="form-control-modern" min="1" placeholder="Ej. 10" required>
        </div>

        <div class="form-full-width">
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Motivo del Despacho / Solicitud</label>
            <textarea name="comentario" class="form-control-modern" rows="3" placeholder="Ej. Asignado a instructor Carlos Gómez para práctica de laboratorio de redes..."></textarea>
        </div>

        <div class="form-full-width" style="text-align: right; margin-top: 10px;">
            <button type="submit" class="btn btn-secondary" style="padding: 12px 30px; background-color: #ef4444; border-color: #ef4444; color: #ffffff;">Procesar Salida de Inventario</button>
        </div>
    </form>
</div>
