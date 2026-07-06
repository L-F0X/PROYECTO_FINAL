<?php
// notificaciones.php - helpers de notificaciones dentro de la app

function asegurar_tabla_notificacion(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificacion (
        ID_NOTIFICACION INT AUTO_INCREMENT PRIMARY KEY,
        ID_USUARIO INT NOT NULL,
        MENSAJE VARCHAR(255) NOT NULL,
        ENLACE VARCHAR(255) DEFAULT NULL,
        LEIDA TINYINT(1) NOT NULL DEFAULT 0,
        FECHA TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function crear_notificacion(PDO $pdo, int $idUsuario, string $mensaje, ?string $enlace = null): void {
    asegurar_tabla_notificacion($pdo);
    $stmt = $pdo->prepare("INSERT INTO notificacion (ID_USUARIO, MENSAJE, ENLACE) VALUES (?, ?, ?)");
    $stmt->execute([$idUsuario, $mensaje, $enlace]);
}

function contar_notificaciones_no_leidas(PDO $pdo, int $idUsuario): int {
    asegurar_tabla_notificacion($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacion WHERE ID_USUARIO = ? AND LEIDA = 0");
    $stmt->execute([$idUsuario]);
    return (int) $stmt->fetchColumn();
}
