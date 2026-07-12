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
    } else {
        // La tabla ya existía en algunos entornos sin AUTO_INCREMENT en
        // ID_NOTIFICACION (probablemente de una importación/dump anterior a
        // este helper). Sin AUTO_INCREMENT, todo INSERT que no indique el ID
        // usa 0 por defecto: el primero funciona, y cada uno después choca
        // con esa misma fila por clave primaria duplicada, fallando en
        // silencio. Se corrige una sola vez con MODIFY (no reinicia datos).
        $stmt = $pdo->prepare("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion' AND COLUMN_NAME = 'ID_NOTIFICACION'");
        $stmt->execute();
        $extra = $stmt->fetchColumn();
        if ($extra !== false && stripos((string) $extra, 'auto_increment') === false) {
            $pdo->exec("ALTER TABLE notificacion MODIFY ID_NOTIFICACION INT AUTO_INCREMENT");
        }
    }
    $verificada = true;
}

function crear_notificacion(PDO $pdo, int $idUsuario, string $mensaje, ?string $enlace = null): void {
    asegurar_tabla_notificacion($pdo);
    $stmt = $pdo->prepare("INSERT INTO notificacion (ID_USUARIO, MENSAJE, ENLACE) VALUES (?, ?, ?)");
    $stmt->execute([$idUsuario, $mensaje, $enlace]);
    push_ws_evento([
        'destino_tipo' => 'usuario',
        'destino' => $idUsuario,
        'evento' => 'notificacion',
        'data' => [
            'conteo' => contar_notificaciones_no_leidas($pdo, $idUsuario),
            'mensaje' => $mensaje,
            'enlace' => $enlace,
        ],
    ]);
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

// Puerto interno (solo localhost) donde websocket/ws_server.php escucha los
// eventos que las páginas PHP le reenvían para difundirlos en vivo a los
// navegadores conectados. El puerto público del WebSocket para el navegador
// es WS_PUERTO_PUBLICO (definido en websocket/ws_server.php como 8090).
define('WS_PUERTO_INTERNO', 8091);

function asegurar_tabla_ws_token(PDO $pdo): void {
    static $verificada = false;
    if ($verificada) {
        return;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion_ws_token'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notificacion_ws_token (
            TOKEN CHAR(64) PRIMARY KEY,
            ID_USUARIO INT NOT NULL,
            ROL VARCHAR(50) NOT NULL,
            CREADO TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ID_USUARIO) REFERENCES usuario(ID_USUARIO) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $verificada = true;
}

// Genera un token de un solo uso (por carga de página) para que el navegador
// se identifique ante websocket/ws_server.php al abrir la conexión en vivo.
// El servidor WS corre en un proceso PHP CLI aparte, sin acceso a la sesión
// de Apache/PHP, así que valida esta identidad consultando esta tabla en la
// misma base de datos en vez de leer la sesión directamente.
function generar_ws_token(PDO $pdo, int $idUsuario, string $rol): string {
    asegurar_tabla_ws_token($pdo);
    $pdo->prepare("DELETE FROM notificacion_ws_token WHERE CREADO < DATE_SUB(NOW(), INTERVAL 1 DAY)")->execute();
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO notificacion_ws_token (TOKEN, ID_USUARIO, ROL) VALUES (?, ?, ?)")->execute([$token, $idUsuario, $rol]);
    return $token;
}

// Reenvía un evento al servidor WebSocket para que lo difunda en vivo a las
// conexiones ya autenticadas que correspondan. No lanza excepción ni bloquea
// la petición HTTP si el servidor WS no está corriendo: las notificaciones
// siguen funcionando igual (se ven al recargar la página), solo se pierde el
// aviso instantáneo, con un timeout corto para no colgar la petición.
function push_ws_evento(array $payload): void {
    $ctx = stream_context_create(['socket' => ['connect_timeout' => 0.3]]);
    $fp = @stream_socket_client('tcp://127.0.0.1:' . WS_PUERTO_INTERNO, $errno, $errstr, 0.3, STREAM_CLIENT_CONNECT, $ctx);
    if ($fp === false) {
        return;
    }
    stream_set_timeout($fp, 1);
    @fwrite($fp, json_encode($payload) . "\n");
    @fclose($fp);
}
