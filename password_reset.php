<?php
// password_reset.php - helpers para recuperación de contraseña por enlace de un solo uso

function asegurar_tabla_password_reset(PDO $pdo): void {
    // Igual que asegurar_tabla_notificacion(): CREATE TABLE es DDL y haría un
    // commit implícito de una transacción activa aunque la tabla ya exista,
    // así que se evita ejecutarlo salvo la primera vez que realmente falta.
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_reset_token'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_token (
            ID_TOKEN INT AUTO_INCREMENT PRIMARY KEY,
            ID_USUARIO INT NOT NULL,
            TOKEN VARCHAR(64) NOT NULL UNIQUE,
            EXPIRA DATETIME NOT NULL,
            USADO TINYINT(1) NOT NULL DEFAULT 0,
            FECHA_CREACION TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $verificada = true;
}

function invalidar_tokens_usuario(PDO $pdo, int $idUsuario): void {
    $pdo->prepare("UPDATE password_reset_token SET USADO = 1 WHERE ID_USUARIO = ? AND USADO = 0")->execute([$idUsuario]);
}

// Evita que se generen/reenvíen enlaces en ráfaga para la misma cuenta.
function tiene_solicitud_reset_reciente(PDO $pdo, int $idUsuario, int $segundosCooldown = 120): bool {
    asegurar_tabla_password_reset($pdo);
    $stmt = $pdo->prepare("SELECT 1 FROM password_reset_token WHERE ID_USUARIO = ? AND FECHA_CREACION > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1");
    $stmt->execute([$idUsuario, $segundosCooldown]);
    return (bool) $stmt->fetchColumn();
}

function generar_token_reset(PDO $pdo, int $idUsuario): string {
    asegurar_tabla_password_reset($pdo);
    invalidar_tokens_usuario($pdo, $idUsuario);
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO password_reset_token (ID_USUARIO, TOKEN, EXPIRA) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $stmt->execute([$idUsuario, $token]);
    return $token;
}

// Devuelve la fila del token (con ID_USUARIO) si es válido, no usado y no vencido; false en caso contrario.
function validar_token_reset(PDO $pdo, string $token) {
    asegurar_tabla_password_reset($pdo);
    $stmt = $pdo->prepare("SELECT * FROM password_reset_token WHERE TOKEN = ? AND USADO = 0 AND EXPIRA > NOW() LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

function marcar_token_usado(PDO $pdo, int $idToken): void {
    $pdo->prepare("UPDATE password_reset_token SET USADO = 1 WHERE ID_TOKEN = ?")->execute([$idToken]);
}
