<?php 
session_start();
// RUTA BLINDADA AL BACKEND
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['cajero'])) {
    header("Location: auth.php");
    exit;
}

$rol = isset($_SESSION['admin']) ? 'admin' : 'cajero';
$es_admin = ($rol === 'admin');
$mi_usuario = $_SESSION[$rol];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Central de Alertas | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700;900&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --green: #00ff88; --blue: #7000ff; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* NAVBAR */
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; z-index: 100;}
        .logo { color: #fff; font-weight: 900; font-size: 20px; text-decoration: none; text-transform: uppercase; letter-spacing: 1px;} 
        .logo span { color: var(--blue); text-shadow: 0 0 15px rgba(112,0,255,0.4); }
        .badge-rol { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 900; text-transform: uppercase; margin-left: 15px;}
        .badge-admin { background: rgba(255,51,102,0.1); color: var(--red); border: 1px solid var(--red); }
        .badge-cajero { background: rgba(112,0,255,0.1); color: var(--blue); border: 1px solid var(--blue); }
        
        .btn-supremo { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; font-size: 12px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 6px; font-weight: 900; text-transform: uppercase; text-decoration: none;}
        .btn-supremo:hover { background: var(--border); color: #fff; }
        
        .main-wrapper { flex: 1; display: flex; padding: 20px; gap: 20px; max-width: 1400px; margin: 0 auto; width: 100%; height: calc(100vh - 70px);}
        
        /* LISTA CONTACTOS */
        .lista-contactos { width: 320px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden;}
        .header-lista { padding: 20px; background: rgba(0,0,0,0.5); border-bottom: 1px solid var(--border); font-weight: 900; font-size: 13px; text-transform: uppercase; color: var(--text-muted); display:flex; justify-content:space-between; align-items:center;}
        .toggle-espectador { font-size:10px; cursor:pointer; padding:4px 8px; border:1px solid var(--text-muted); border-radius:4px; transition:0.3s;}
        .toggle-espectador.activo { background:var(--blue); color:#fff; border-color:var(--blue); box-shadow: 0 0 10px rgba(112,0,255,0.4); }
        .buscador-caja { padding: 15px; border-bottom: 1px solid var(--border);}
        .input-buscador { width: 100%; background: #000; border: 1px solid var(--border); padding: 12px; border-radius: 8px; color: #fff; font-size: 13px; outline: none; transition: 0.2s;}
        .input-buscador:focus { border-color: var(--blue); }
        .contactos-box { flex: 1; overflow-y: auto; }
        .contactos-box::-webkit-scrollbar { width: 4px; }
        .contactos-box::-webkit-scrollbar-thumb { background: #333; }
        .contacto { padding: 15px 20px; border-bottom: 1px solid var(--border); cursor: pointer; transition: 0.2s; border-left: 3px solid transparent; position: relative;} 
        .contacto.tomado { opacity: 0.5; cursor: not-allowed; }
        .contacto:hover:not(.tomado) { background: rgba(255,255,255,0.02); border-left-color: var(--blue);}
        .c-nombre { font-weight: 900; color: #fff; font-size: 13px; margin-bottom: 5px; display:flex; justify-content:space-between; align-items: center; text-transform: uppercase;} 
        .c-prev { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        
        /* CHAT AREA */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; position: relative;}
        .esperando-box { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; color: var(--text-muted); font-weight: 900; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;}
        #contenidoChat { display: none; flex-direction: column; height: 100%; }
        
        .chat-header { padding: 15px 20px; background: rgba(0,0,0,0.5); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .chat-header-izq { font-weight: 900; color: #fff; font-size: 18px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
        
        /* PANEL DE ACCIONES RÁPIDAS (PAGOS Y CARGAS) */
        .panel-acciones { display: flex; gap: 10px; padding: 15px 20px; background: #000; border-bottom: 1px solid var(--border); align-items: center; flex-wrap: wrap;}
        .input-monto { background: var(--panel); border: 1px solid var(--border); color: #fff; padding: 12px; border-radius: 8px; width: 140px; outline: none; font-family: 'Roboto Mono', monospace; font-size: 16px; font-weight: 900; text-align: center; transition: 0.3s;} 
        .input-monto:focus { border-color: var(--green); box-shadow: 0 0 10px rgba(0,255,136,0.2);}
        .btn-accion { border: none; padding: 12px 20px; border-radius: 8px; font-weight: 900; cursor: pointer; font-size: 11px; text-transform: uppercase; display: flex; align-items: center; gap: 6px; transition: 0.3s;}
        .btn-cargar { background: var(--green); color: #000; } 
        .btn-cargar:hover { box-shadow: 0 0 15px rgba(0,255,136,0.4); }
        .btn-pagar { background: rgba(255,51,102,0.1); border: 1px solid var(--red); color: var(--red); }
        .btn-pagar:hover { background: var(--red); color: #fff; box-shadow: 0 0 15px rgba(255,51,102,0.4);}
        .btn-limpiar { background: transparent; border: 1px solid var(--text-muted); color: var(--text-muted); margin-left: auto; }
        .btn-limpiar:hover { background: var(--border); color: #fff; }
        .btn-transferir { background: transparent; border: 1px dashed var(--yellow); color: var(--yellow); }
        .btn-accion:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

        /* BURBUJAS */
        .mensajes-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth;}
        .mensajes-box::-webkit-scrollbar { width: 6px; }
        .mensajes-box::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
        
        .chat-input-area { padding: 15px 20px; display: flex; gap: 10px; align-items: center; border-top: 1px solid var(--border); background: rgba(0,0,0,0.5);}
        .chat-input { flex: 1; background: #000; border: 1px solid var(--border); padding: 15px; border-radius: 8px; color: #fff; font-size: 13px; outline: none; transition: 0.3s; } 
        .chat-input:focus { border-color: var(--blue); }
        .btn-enviar { background: var(--blue); color: #fff; border: none; padding: 0 25px; height: 48px; border-radius: 8px; font-weight: 900; cursor: pointer; text-transform: uppercase; font-size: 12px; transition: 0.3s;} 
        .btn-enviar:hover { filter: brightness(1.2); box-shadow: 0 0 15px rgba(112,0,255,0.4); }
        .btn-enviar:disabled { background: #333; color: #888; cursor: not-allowed; box-shadow: none; }
    </style>
</head>
<body>

<nav class="topbar">
    <div style="display:flex; align-items:center;">
        <a href="dashboard.php" class="logo">EL <span>POINT</span></a>
        <span class="badge-rol <?php echo ($rol == 'admin') ? 'badge-admin' : 'badge-cajero'; ?>"><?php echo strtoupper($rol); ?></span>
    </div>
    <a href="dashboard.php" class="btn-supremo">← Volver al Panel</a>
</nav>

<div class="main-wrapper">
    <div class="lista-contactos">
        <div class="header-lista">
            💬 Tíckets Activos
            <?php if($es_admin): ?>
                <span class="toggle-espectador" id="btnEspectador" onclick="toggleEspectador()">Espectador OFF</span>
            <?php endif; ?>
        </div>
        <div class="buscador-caja"><input type="text" id="filtroContactos" class="input-buscador" placeholder="Buscar jugador..." onkeyup="filtrarLista()"></div>
        <div class="contactos-box" id="listaContactos">
            <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 12px; font-weight:bold;">Conectando al servidor...</div>
        </div>
    </div>
    
    <div class="chat-area">
        <div class="esperando-box" id="msgEsperando"><div style="font-size: 40px; margin-bottom: 10px;">🎧</div>SELECCIONÁ UN TICKET PARA OPERAR</div>

        <div id="contenidoChat">
            <div class="chat-header">
                <div class="chat-header-izq">
                    <span id="chatNombre">JUGADOR</span>
                </div>
            </div>
            
            <div class="panel-acciones">
                <input type="number" id="montoRapido" class="input-monto" placeholder="$ Monto">
                <button class="btn-accion btn-cargar" id="btnCargar" onclick="accionRapida('sumar')">+ APROBAR CARGA</button>
                <button class="btn-accion btn-pagar" id="btnPagar" onclick="accionRapida('restar')">- APROBAR RETIRO</button>
                
                <?php if(!$es_admin): ?>
                    <button class="btn-accion btn-transferir" onclick="transferirAdmin()">PASAR AL ADMIN</button>
                <?php endif; ?>
                
                <button class="btn-accion btn-limpiar" onclick="cerrarChat()">🧹 CERRAR TICKET</button>
            </div>
            
            <div class="mensajes-box" id="cajaMensajes"></div>
            
            <div class="chat-input-area">
                <input type="text" id="inputAdmin" class="chat-input" placeholder="Escribí tu respuesta..." onkeypress="if(event.key === 'Enter') enviarAdmin()">
                <button class="btn-enviar" id="btnEnviarMsg" onclick="enviarAdmin()">ENVIAR</button>
            </div>
        </div>
    </div>
</div>

<script>
// 🔥 RUTA CORREGIDA AL MOTOR BLINDADO
const API_URL = '../../../backend/api/api_chat.php';

let chatActualId = ""; 
let listaCache = "";
let modoEspectador = false;

// 1. CARGAR LISTA DE TICKETS
function cargarContactos() { 
    let fd = new FormData(); fd.append('accion', 'fetch_admin_users');
    fetch(API_URL, {method: 'POST', body: fd}).then(r=>r.text()).then(h => { 
        if(listaCache !== h) { 
            document.getElementById('listaContactos').innerHTML = h; 
            listaCache = h; 
            filtrarLista(); 
        }
    }).catch(e => console.error("Error cargando contactos:", e)); 
}

function filtrarLista() {
    let input = document.getElementById('filtroContactos').value.toLowerCase();
    let contactos = document.getElementsByClassName('contacto');
    for (let i = 0; i < contactos.length; i++) {
        let nombre = contactos[i].innerText.toLowerCase();
        contactos[i].style.display = (nombre.indexOf(input) > -1) ? "" : "none";
    }
}

// 2. ABRIR CHAT
function cargarChatAdmin(username, tomadoPorOtro) { 
    if (tomadoPorOtro && !modoEspectador) { alert("⚠️ Este ticket ya lo está atendiendo otro cajero."); return; }
    
    chatActualId = username; 
    document.getElementById('chatNombre').innerText = username; 
    document.getElementById('msgEsperando').style.display = 'none'; 
    document.getElementById('contenidoChat').style.display = 'flex';
    document.getElementById('montoRapido').value = ''; 
    
    if(!modoEspectador) {
        let fd = new FormData(); fd.append('accion', 'tomar_chat'); fd.append('id_usuario', username);
        fetch(API_URL, {method: 'POST', body: fd});
    }
    refrescarChat(true); 
}

// 3. ACTUALIZAR BURBUJAS
function refrescarChat(forzarScroll = false) { 
    if(chatActualId === "") return; 
    let fd = new FormData(); fd.append('accion', 'fetch_admin_chat'); fd.append('receptor', chatActualId);
    
    fetch(API_URL, {method: 'POST', body: fd})
    .then(r=>r.json())
    .then(data => { 
        let div = document.getElementById('cajaMensajes'); 
        let isBottom = div.scrollHeight - div.scrollTop <= div.clientHeight + 50; 
        
        if(div.innerHTML !== data.html) { 
            div.innerHTML = data.html; 
            if(isBottom || forzarScroll) div.scrollTop = div.scrollHeight; 
        }
    }).catch(e => console.log(e)); 
}

// 4. ENVÍO DE MENSAJE (ANTI-LAG)
function enviarAdmin() { 
    if(chatActualId === "" || modoEspectador) return; 
    
    let inputM = document.getElementById('inputAdmin'); 
    let txt = inputM.value.trim();
    if(txt === '') return; 
    
    let btn = document.getElementById('btnEnviarMsg');
    btn.disabled = true; btn.innerText = '⏳';
    
    let fd = new FormData(); 
    fd.append('accion', 'send_msg'); 
    fd.append('receptor', chatActualId);
    fd.append('mensaje', txt); 
    
    fetch(API_URL, {method: 'POST', body: fd})
    .then(() => { 
        inputM.value = ''; 
        btn.disabled = false; btn.innerText = 'ENVIAR';
        refrescarChat(true); 
    })
    .catch(() => {
        btn.disabled = false; btn.innerText = 'ENVIAR';
        alert("Error de conexión");
    }); 
}

// 5. CAJA RÁPIDA: CARGAS Y PAGOS (ANTI-DOBLE PAGO)
function accionRapida(tipoAccion) { 
    if(chatActualId === "" || modoEspectador) return; 
    
    let inputMonto = document.getElementById('montoRapido');
    let monto = inputMonto.value; 
    if (monto === '' || monto <= 0) {
        alert("Ingresá un monto válido.");
        return; 
    }
    
    // Bloqueo Anti-Spam (Evita que sume 2 veces si hacen doble clic rápido)
    let btnCargar = document.getElementById('btnCargar');
    let btnPagar = document.getElementById('btnPagar');
    btnCargar.disabled = true; btnPagar.disabled = true;
    
    let fd = new FormData(); 
    fd.append('accion', 'accion_rapida'); 
    fd.append('id_usuario', chatActualId); 
    fd.append('tipo_accion', tipoAccion); 
    fd.append('monto', monto); 
    
    fetch(API_URL, {method: 'POST', body: fd})
    .then(r => r.json())
    .then(data => { 
        btnCargar.disabled = false; btnPagar.disabled = false;
        
        if(data.error) {
            alert("❌ " + data.error);
        } else {
            inputMonto.value = ''; 
            alert("✅ Operación procesada con éxito.");
            refrescarChat(true); 
            cargarContactos();
        }
    })
    .catch(() => {
        btnCargar.disabled = false; btnPagar.disabled = false;
        alert("❌ Error de red procesando la operación.");
    }); 
}

function cerrarChat() {
    if(chatActualId === "") return;
    if(!confirm('¿Estás seguro de cerrar este ticket?')) return;
    
    let fd = new FormData(); fd.append('accion', 'liberar_chat'); fd.append('id_usuario', chatActualId); 
    fetch(API_URL, {method: 'POST', body: fd}).then(() => {
        chatActualId = ""; 
        document.getElementById('contenidoChat').style.display = 'none'; 
        document.getElementById('msgEsperando').style.display = 'flex'; 
        cargarContactos();
    });
}

function transferirAdmin() {
    if(!confirm('¿Pasar este ticket a un Administrador?')) return;
    let fd = new FormData(); fd.append('accion', 'transferir_admin'); fd.append('id_usuario', chatActualId); 
    fetch(API_URL, {method: 'POST', body: fd}).then(() => cerrarChat());
}

function toggleEspectador() {
    modoEspectador = !modoEspectador;
    let btn = document.getElementById('btnEspectador');
    if(modoEspectador) { btn.classList.add('activo'); btn.innerText = "Espectador ON"; } 
    else { btn.classList.remove('activo'); btn.innerText = "Espectador OFF"; }
}

// Latidos del sistema
setInterval(cargarContactos, 3000); 
setInterval(refrescarChat, 3000); 
cargarContactos();
</script>
</body>
</html>