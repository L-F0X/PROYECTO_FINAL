<?php
// auditoria_helper.php - crea la tabla auditoria_actividad de forma perezosa
// (igual que asegurar_tabla_notificacion en notificaciones.php) antes de
// insertar en ella. Antes solo Administrador/index.php la creaba; los demás
// puntos que insertan (Administrador/crear_usuario.php, editar_usuario.php,
// almacenista/index.php, instructor/eliminar.php) asumían que ya existía,
// lo que fallaba con un error genérico si se llegaba a ellos sin haber
// pasado antes por Administrador/index.php en esa base de datos.
function asegurar_tabla_auditoria(PDO $pdo): void {
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_actividad'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria_actividad (
            ID_AUDITORIA INT AUTO_INCREMENT PRIMARY KEY,
            ID_USUARIO INT NOT NULL,
            ACCION VARCHAR(255) NOT NULL,
            DETALLE TEXT DEFAULT NULL,
            FECHA TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $verificada = true;
}
