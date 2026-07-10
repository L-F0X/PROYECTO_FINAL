<?php
// notificaciones.php - helpers de notificaciones dentro de la app

function asegurar_tabla_notificacion(PDO $pdo): void {
    // "CREATE TABLE IF NOT EXISTS" es DDL: MySQL hace commit implícito de
    // cualquier transacción activa al ejecutarlo, exista ya la tabla o no.
    // Por eso se verifica primero por information_schema (solo SELECT) y
    // solo se ejecuta el CREATE la primera vez que la tabla realmente falta;
    // así, llamar a esta función dentro de una transacción abierta (como
    // hacen crear_notificacion/notificar_por_rol) no la rompe silenciosamente.
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
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
    $verificada = true;
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

function notificar_por_rol(PDO $pdo, string $nombreRol, string $mensaje, ?string $enlace = null): void {
    $stmt = $pdo->prepare("SELECT u.ID_USUARIO FROM usuario u INNER JOIN rol r ON u.ID_ROL = r.ID_ROL WHERE LOWER(r.NOMBRE_ROL) = LOWER(?)");
    $stmt->execute([$nombreRol]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idUsuario) {
        crear_notificacion($pdo, (int) $idUsuario, $mensaje, $enlace);
    }
}

// Borra una notificación puntual, siempre acotado al dueño (ID_USUARIO) para
// que nadie pueda borrar la notificación de otra cuenta adivinando su ID.
function eliminar_notificacion(PDO $pdo, int $idUsuario, int $idNotificacion): void {
    $pdo->prepare("DELETE FROM notificacion WHERE ID_NOTIFICACION = ? AND ID_USUARIO = ?")->execute([$idNotificacion, $idUsuario]);
}

function eliminar_notificaciones_usuario(PDO $pdo, int $idUsuario): void {
    $pdo->prepare("DELETE FROM notificacion WHERE ID_USUARIO = ?")->execute([$idUsuario]);
}

// Limpieza perezosa: borra notificaciones ya leídas y con más de $dias de
// antigüedad. Se llama de forma oportunista al cargar la bandeja (mismo
// espíritu que las migraciones perezosas del proyecto), en vez de depender
// de una tarea programada que este proyecto no tiene configurada. Las no
// leídas nunca se borran automáticamente, para no ocultar un aviso que el
// usuario ni siquiera ha visto todavía.
function limpiar_notificaciones_antiguas(PDO $pdo, int $dias = 30): void {
    asegurar_tabla_notificacion($pdo);
    $pdo->prepare("DELETE FROM notificacion WHERE LEIDA = 1 AND FECHA < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$dias]);
}
