<?php
// websocket/ws_server.php
// Servidor WebSocket de BICERGAM: proceso PHP CLI aparte (no lo sirve Apache).
// Se inicia con:  php websocket/ws_server.php
// o con el doble-clic de websocket/iniciar_websocket.bat
//
// Expone dos sockets:
//  - Puerto 8090 (público, WebSocket): a este se conectan los navegadores
//    (js/realtime.js) para recibir eventos en vivo (notificaciones nuevas,
//    avisos de cancelación de envío, etc).
//  - Puerto 8091 (interno, solo 127.0.0.1): a este "empujan" las páginas PHP
//    normales (vía push_ws_evento() en notificaciones.php) cuando ocurre algo
//    que debe avisarse en vivo. No es WebSocket, es texto plano línea-a-línea
//    en JSON, porque quien conecta ahí es siempre PHP, nunca un navegador.
//
// El servidor no comparte memoria con Apache/PHP-FPM, así que no puede leer
// la sesión de un usuario logueado directamente. Por eso valida la identidad
// de cada conexión de navegador contra la tabla notificacion_ws_token (ver
// generar_ws_token() en notificaciones.php), que si vive en la misma base de
// datos.

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

const WS_PUERTO_PUBLICO = 8090;
const WS_PUERTO_INTERNO = 8091; // debe coincidir con WS_PUERTO_INTERNO en notificaciones.php

function conectar_bd(): PDO
{
    // Mismas credenciales que conexion.php, pero sin session_start() ni
    // configuración de cookies: este proceso CLI no maneja peticiones HTTP.
    return new PDO('mysql:host=localhost;dbname=bicergam;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

final class NotificacionesWsHandler implements MessageComponentInterface
{
    private PDO $pdo;
    private \SplObjectStorage $clientes;
    /** @var array<int, array{usuario:int, rol:string, canales: array<string,bool>}> */
    private array $info = [];
    /** @var array<int, array<int, ConnectionInterface>> */
    private array $porUsuario = [];
    /** @var array<string, array<int, ConnectionInterface>> */
    private array $porRol = [];
    /** @var array<string, array<int, ConnectionInterface>> */
    private array $porCanal = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->clientes = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clientes->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!is_array($data)) {
            return;
        }
        $objId = spl_object_id($from);

        // Primer mensaje esperado: autenticación con el token de la página.
        if (!isset($this->info[$objId])) {
            if (empty($data['token']) || !is_string($data['token'])) {
                return;
            }
            $stmt = $this->pdo->prepare("SELECT ID_USUARIO, ROL FROM notificacion_ws_token WHERE TOKEN = ? AND CREADO > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->execute([$data['token']]);
            $row = $stmt->fetch();
            if (!$row) {
                $from->send(json_encode(['evento' => 'auth_error']));
                return;
            }
            $idUsuario = (int) $row['ID_USUARIO'];
            $rol = (string) $row['ROL'];
            $this->info[$objId] = ['usuario' => $idUsuario, 'rol' => $rol, 'canales' => []];
            $this->porUsuario[$idUsuario][$objId] = $from;
            $this->porRol[$rol][$objId] = $from;
            $from->send(json_encode(['evento' => 'auth_ok']));
            return;
        }

        // Tras autenticado, permite unirse/salir de canales opcionales (por
        // ejemplo "lote_42") para eventos dirigidos a quienes ven una pantalla
        // concreta, sin tener que difundir a todo un rol.
        $accion = $data['accion'] ?? null;
        $canal = is_string($data['canal'] ?? null) ? $data['canal'] : null;
        if ($canal === null) {
            return;
        }
        if ($accion === 'unirse') {
            $this->porCanal[$canal][$objId] = $from;
            $this->info[$objId]['canales'][$canal] = true;
        } elseif ($accion === 'salir') {
            unset($this->porCanal[$canal][$objId], $this->info[$objId]['canales'][$canal]);
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $objId = spl_object_id($conn);
        if (isset($this->info[$objId])) {
            $info = $this->info[$objId];
            unset($this->porUsuario[$info['usuario']][$objId]);
            unset($this->porRol[$info['rol']][$objId]);
            foreach (array_keys($info['canales']) as $canal) {
                unset($this->porCanal[$canal][$objId]);
            }
            unset($this->info[$objId]);
        }
        $this->clientes->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }

    public function difundir(array $payload): void
    {
        $destinoTipo = $payload['destino_tipo'] ?? null;
        $destino = $payload['destino'] ?? null;
        $mensaje = json_encode([
            'evento' => $payload['evento'] ?? 'evento',
            'data' => $payload['data'] ?? [],
        ]);
        if ($mensaje === false) {
            return;
        }

        $destinatarios = [];
        if ($destinoTipo === 'usuario' && isset($this->porUsuario[(int) $destino])) {
            $destinatarios = $this->porUsuario[(int) $destino];
        } elseif ($destinoTipo === 'rol' && isset($this->porRol[(string) $destino])) {
            $destinatarios = $this->porRol[(string) $destino];
        } elseif ($destinoTipo === 'canal' && isset($this->porCanal[(string) $destino])) {
            $destinatarios = $this->porCanal[(string) $destino];
        }

        foreach ($destinatarios as $conn) {
            $conn->send($mensaje);
        }
    }
}

$loop = Loop::get();
$handler = new NotificacionesWsHandler(conectar_bd());

// Socket público de WebSocket para los navegadores. Se escucha en IPv4 e
// IPv6 por separado (mismo $handler en ambos): en Windows, "localhost" suele
// resolverse primero a ::1 (IPv6), y si el servidor solo escuchara en
// 0.0.0.0 (IPv4), esa conexión se rechaza y el navegador nunca llega a
// intentar la ruta IPv4 — el bell nunca se actualiza en vivo.
$wsSocketV4 = new SocketServer('0.0.0.0:' . WS_PUERTO_PUBLICO, [], $loop);
new IoServer(new HttpServer(new WsServer($handler)), $wsSocketV4, $loop);
try {
    $wsSocketV6 = new SocketServer('[::]:' . WS_PUERTO_PUBLICO, [], $loop);
    new IoServer(new HttpServer(new WsServer($handler)), $wsSocketV6, $loop);
} catch (\Throwable $e) {
    echo "Aviso: no se pudo abrir el socket IPv6 (" . $e->getMessage() . "); solo IPv4 activo." . PHP_EOL;
}

// Socket interno (solo localhost) para que las páginas PHP empujen eventos.
$internoSocket = new SocketServer('127.0.0.1:' . WS_PUERTO_INTERNO, [], $loop);
$internoSocket->on('connection', function ($conn) use ($handler): void {
    $buffer = '';
    $conn->on('data', function ($chunk) use (&$buffer, $handler): void {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $linea = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $payload = json_decode($linea, true);
            if (is_array($payload)) {
                $handler->difundir($payload);
            }
        }
    });
});

echo "BICERGAM WebSocket activo: ws://0.0.0.0:" . WS_PUERTO_PUBLICO . " | interno 127.0.0.1:" . WS_PUERTO_INTERNO . PHP_EOL;

$loop->run();
