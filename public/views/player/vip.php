<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$jugador = strtoupper($_SESSION['jugador']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Soporte VIP | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent;}
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; display:flex; flex-direction:column; height:100vh; overflow: hidden; }
        
        /* NAVBAR OFICIAL */
        .navbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.5); z-index: 10; }
        .btn-volver { background: transparent; color: var(--text-muted); text-decoration: none; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); transition: 0.3s; text-transform: uppercase; }
        .btn-volver:hover { background: var(--border); color: #fff; }
        .titulo-nav { font-weight: 900; font-size: 18px; letter-spacing: -0.5px; color: #fff; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
        .titulo-nav span { color: var(--blue); text-shadow: 0 0 10px rgba(112,0,255,0.4);}
        .user-tag { font-family: 'Roboto Mono', monospace; font-size: 11px; color: var(--green); font-weight: 900; background: rgba(0,255,136,0.1); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(0,255,136,0.3);}

        .chat-container { flex: 1; display: flex; flex-direction: column; max-width: 800px; width: 100%; margin: 0 auto; padding: 20px; overflow: hidden; }
        
        /* CAJA DE MENSAJES */
        .chat-box { flex: 1; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 15px; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth; position: relative;}
        .chat-box::-webkit-scrollbar { width: 6px; }
        .chat-box::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        
        .msg-alerta { text-align:center; color:var(--text-muted); font-size:11px; font-weight:900; text-transform:uppercase; margin-bottom:10px; letter-spacing: 1px; padding: 10px; border: 1px dashed var(--border); border-radius: 8px;}
        
        /* BURBUJAS DE CHAT */
        .message { padding: 12px 18px; border-radius: 12px; max-width: 85%; font-size: 14px; line-height: 1.5; position: relative; word-wrap: break-word; font-weight: bold;}
        .msg-yo { align-self: flex-end; background: var(--blue); color: #fff; border-bottom-right-radius: 2px; box-shadow: 0 4px 15px rgba(112,0,255,0.2); }
        .msg-otro { align-self: flex-start; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--border); border-bottom-left-radius: 2px; }
        .msg-otro strong { color: var(--green); display: block; font-size: 11px; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px;}
        
        /* ZONA DE INPUT */
        .input-area { display: flex; gap: 10px; background: var(--panel); padding: 15px; border-radius: 12px; border: 1px solid var(--border); align-items: center;}
        
        .btn-clip { background: transparent; border: 1px dashed var(--text-muted); color: var(--text-muted); width: 48px; height: 48px; min-width: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; transition: 0.3s;}
        .btn-clip:hover { border-color: var(--blue); color: var(--blue); background: rgba(112,0,255,0.1);}
        
        .input-msg { flex: 1; background: #000; border: 1px solid var(--border); color: #fff; padding: 0 15px; height: 48px; border-radius: 8px; outline: none; font-family: 'Inter', sans-serif; font-size: 14px; transition: 0.3s; }
        .input-msg:focus { border-color: var(--blue); }
        
        .btn-enviar { background: var(--blue); border: none; color: #fff; padding: 0 25px; height: 48px; border-radius: 8px; font-weight: 900; font-size: 14px; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px;}
        .btn-enviar:hover { filter: brightness(1.2); box-shadow: 0 0 15px rgba(112,0,255,0.5); }
        .btn-enviar:disabled { background: #333; color: #888; cursor: not-allowed; box-shadow: none; }
        
        /* Imágenes en el chat */
        .msg-img { max-width: 100%; border-radius: 8px; margin-top: 5px; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);}
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="lobby.php" class="btn-volver">← LOBBY</a>
        <div class="titulo-nav">SOPORTE <span>VIP</span></div>
        <div class="user-tag"><?php echo $jugador; ?></div>
    </nav>

    <div class="chat-container">
        <div class="chat-box" id="chatBox">
            <div class="msg-alerta">
                🔒 Conexión Segura. <br>Por favor, enviá tu comprobante de pago o tu consulta y un cajero te responderá a la brevedad.
            </div>
            </div>
        
        <div class="input-area">
            <label for="fotoFile" class="btn-clip" title="Enviar Comprobante">📷</label>
            <input type="file" id="fotoFile" accept="image/*" style="display:none" onchange="enviarMensaje(true)">
            
            <input type="text" id="inputMsg" class="input-msg" placeholder="Escribí tu mensaje..." autocomplete="off">
            <button id="btnEnviar" class="btn-enviar" onclick="enviarMensaje(false)">ENVIAR</button>
        </div>
    </div>

<script>
    const API = '../../../backend/api/api_chat.php';
    let chatBox = document.getElementById('chatBox');
    let isFirstLoad = true;
    let ultimoContenido = "";

    // 🔥 SCROLL INTELIGENTE Y CARGA DE MENSAJES (ANTI-LAG)
    function cargarMensajes() {
        let fd = new FormData(); 
        fd.append('accion', 'fetch_msgs');
        
        fetch(API, {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            // Solo actualiza el DOM si hay cambios nuevos
            if(ultimoContenido !== data.html) {
                let isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
                
                chatBox.innerHTML = '<div class="msg-alerta">🔒 Conexión Segura.</div>' + data.html;
                ultimoContenido = data.html;
                
                if(isFirstLoad || isAtBottom) {
                    chatBox.scrollTop = chatBox.scrollHeight;
                    isFirstLoad = false;
                }
            }
        }).catch(e => console.log("Buscando mensajes..."));
    }

    // 🔥 ENVÍO BLINDADO (FOTOS Y TEXTO)
    function enviarMensaje(esFoto) {
        let btn = document.getElementById('btnEnviar');
        let fd = new FormData(); 
        fd.append('accion', 'send_msg'); 
        
        if (esFoto) {
            let inputF = document.getElementById('fotoFile');
            if(!inputF.files[0]) return;
            
            // En un futuro acá podés procesar la imagen. Por ahora mandamos un aviso.
            // Para subir imágenes reales hay que configurar un upload en api_chat.php
            fd.append('mensaje', "📎 [Comprobante Enviado]"); 
            inputF.value = ''; // Limpiamos el input
        } else {
            let inputM = document.getElementById('inputMsg');
            let txt = inputM.value.trim();
            if(!txt) return; // No manda vacío
            
            fd.append('mensaje', txt);
            inputM.value = '';
        }

        // Bloqueamos botón Anti-Spam
        btn.disabled = true;
        btn.innerText = "⏳";
        
        fetch(API, {method:'POST', body:fd})
        .then(() => {
            btn.disabled = false;
            btn.innerText = "ENVIAR";
            cargarMensajes();
            
            // Forzamos el scroll al fondo
            setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerText = "ENVIAR";
            alert("Error al enviar. Revisá tu conexión de internet.");
        });
    }

    // Listener para la tecla ENTER
    document.getElementById('inputMsg').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { enviarMensaje(false); }
    });

    // Latido del chat: Busca mensajes nuevos cada 3 segundos
    setInterval(cargarMensajes, 3000); 
    cargarMensajes();
</script>
</body>
</html>