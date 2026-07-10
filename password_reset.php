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

// El token pasó de ser un enlace de un solo uso (hash largo) a un código corto
// de 6 dígitos que el usuario escribe a mano. Con un espacio de solo 1,000,000
// de combinaciones, dos usuarios distintos sí pueden recibir el mismo código
// al mismo tiempo, así que ya no puede tener una restricción UNIQUE global:
// se valida junto con el ID_USUARIO, no de forma aislada.
function asegurar_token_no_unico(PDO $pdo): void {
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_reset_token' AND INDEX_NAME = 'TOKEN'");
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE password_reset_token DROP INDEX TOKEN");
    }
    $verificada = true;
}

// Evita que se generen/reenvíen códigos en ráfaga para la misma cuenta.
function tiene_solicitud_reset_reciente(PDO $pdo, int $idUsuario, int $segundosCooldown = 120): bool {
    asegurar_tabla_password_reset($pdo);
    $stmt = $pdo->prepare("SELECT 1 FROM password_reset_token WHERE ID_USUARIO = ? AND FECHA_CREACION > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1");
    $stmt->execute([$idUsuario, $segundosCooldown]);
    return (bool) $stmt->fetchColumn();
}

// Genera un código numérico de 6 dígitos (con ceros a la izquierda si aplica),
// válido por 15 minutos, mucho más corto que el anterior enlace de 1 hora ya
// que un código de este tamaño es adivinable por fuerza bruta si se deja
// vigente demasiado tiempo.
function generar_token_reset(PDO $pdo, int $idUsuario): string {
    asegurar_tabla_password_reset($pdo);
    asegurar_token_no_unico($pdo);
    invalidar_tokens_usuario($pdo, $idUsuario);
    $token = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO password_reset_token (ID_USUARIO, TOKEN, EXPIRA) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $stmt->execute([$idUsuario, $token]);
    return $token;
}

// Devuelve la fila del token si es válido para ESE usuario, no usado y no
// vencido; false en caso contrario. Ya no se busca por TOKEN solo (dejó de
// ser único al pasar a un código corto de 6 dígitos).
function validar_token_reset(PDO $pdo, int $idUsuario, string $token) {
    asegurar_tabla_password_reset($pdo);
    $stmt = $pdo->prepare("SELECT * FROM password_reset_token WHERE ID_USUARIO = ? AND TOKEN = ? AND USADO = 0 AND EXPIRA > NOW() LIMIT 1");
    $stmt->execute([$idUsuario, $token]);
    return $stmt->fetch();
}

function marcar_token_usado(PDO $pdo, int $idToken): void {
    $pdo->prepare("UPDATE password_reset_token SET USADO = 1 WHERE ID_TOKEN = ?")->execute([$idToken]);
}
