<?php
// almacenista/registrar_entrada.php
// Componente de Formulario de Entrada
if (!defined('ACCESO_VALIDO')) {
    exit('Acceso denegado');
}
?>

<div class="dashboard-topbar" style="margin-bottom: 20px;">
    <div>
        <h2>Registrar Entrada de Mercancía / Elementos</h2>
        <p class="dashboard-subtitle">Aumenta las existencias en inventario al ingresar suministros o compras recibidas.</p>
    </div>
</div>

<div class="panel-card">
    <form action="index.php?tab=entrada" method="POST" class="modern-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="action" value="registrar_entrada">
        
        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Seleccionar Artículo del Stock</label>
            <select name="id_ficha_tecnica" class="form-control-modern" required>
                <option value="">— Seleccione un elemento —</option>
                <?php foreach ($itemsInventario as $i): ?>
                    <option value="<?= $i['ID_FICHA_TECNICA'] ?>">
                        <?= htmlspecialchars($i['NOMBRE_ITEM']) ?> (Stock actual: <?= $i['CANTIDAD'] ?>) - [<?= htmlspecialchars($i['CODIGO_UNSPSC_FK'] ?: 'Sin Código') ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Cantidad de Ingreso</label>
            <input type="number" name="cantidad" class="form-control-modern" min="1" placeholder="Ej. 50" required>
        </div>

        <div class="form-full-width">
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">Comentarios / Referencia de Entrada</label>
            <textarea name="comentario" class="form-control-modern" rows="3" placeholder="Ej. Factura de compra 1024 o Donación institucional..."></textarea>
        </div>

        <div class="form-full-width" style="text-align: right; margin-top: 10px;">
            <button type="submit" class="btn btn-sena" style="padding: 12px 30px;">Procesar Entrada de Inventario</button>
        </div>
    </form>
</div>
