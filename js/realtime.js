// js/realtime.js
// Cliente WebSocket compartido por los 4 roles: mantiene el badge de
// notificaciones (campanita del header) actualizado en vivo, sin recargar la
// página. Se conecta a websocket/ws_server.php (proceso PHP aparte, no lo
// sirve Apache). Si ese servidor no está corriendo, la página sigue
// funcionando normal, solo sin el aviso instantáneo (se ve al recargar).
//
// Otras páginas pueden escuchar eventos adicionales (por ejemplo, un aviso de
// que el instructor canceló un envío mientras el coordinador lo revisa) sin
// tocar este archivo, suscribiéndose así:
//   document.addEventListener('bicergam-ws-lote_cancelado', function (e) {
//       console.log(e.detail); // payload "data" enviado por push_ws_evento()
//   });

(function () {
    var script = document.currentScript;
    if (!script) return;

    var token = script.getAttribute('data-ws-token');
    if (!token) return;

    var puerto = script.getAttribute('data-ws-port') || '8090';
    var intentos = 0;
    var socket = null;

    function actualizarBadge(conteo) {
        var link = document.querySelector('.header-bell-link');
        if (!link) return;
        var badge = document.getElementById('header-bell-badge');
        if (conteo > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'header-bell-badge';
                badge.className = 'header-bell-badge';
                link.appendChild(badge);
            }
            badge.textContent = conteo > 9 ? '9+' : String(conteo);
        } else if (badge) {
            badge.remove();
        }
    }

    function conectar() {
        var protocolo = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        socket = new WebSocket(protocolo + '//' + window.location.hostname + ':' + puerto);

        socket.addEventListener('open', function () {
            intentos = 0;
            socket.send(JSON.stringify({ token: token }));
        });

        socket.addEventListener('message', function (ev) {
            var payload;
            try {
                payload = JSON.parse(ev.data);
            } catch (e) {
                return;
            }
            if (!payload || !payload.evento) return;

            if (payload.evento === 'notificacion' && payload.data) {
                actualizarBadge(payload.data.conteo || 0);
                if (typeof showToast === 'function' && payload.data.mensaje) {
                    showToast(payload.data.mensaje, 'info', 6000);
                }
            }

            document.dispatchEvent(new CustomEvent('bicergam-ws-' + payload.evento, { detail: payload.data }));
        });

        socket.addEventListener('close', function () {
            if (intentos > 20) return; // evita reintentos infinitos si el servidor quedó apagado
            intentos++;
            setTimeout(conectar, Math.min(2000 * intentos, 20000));
        });

        socket.addEventListener('error', function () {
            socket.close();
        });
    }

    // Permite a otras páginas unirse/salir de un canal (p.ej. "lote_42") para
    // recibir eventos dirigidos a quienes están viendo esa pantalla concreta.
    window.bicergamWsCanal = function (accion, canal) {
        if (!socket || socket.readyState !== WebSocket.OPEN) return;
        socket.send(JSON.stringify({ accion: accion, canal: canal }));
    };

    conectar();
})();
