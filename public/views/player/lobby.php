<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$jugador = $_SESSION['jugador'];

// Optimizado: 1 sola consulta para traer todo rápido
$query_datos = mysqli_query($conexion, "SELECT saldo, bono FROM usuarios WHERE username = '$jugador' AND rol = 'jugador' LIMIT 1");

if ($query_datos && mysqli_num_rows($query_datos) > 0) {
    $datos = mysqli_fetch_assoc($query_datos);
    $saldo_actual = $datos['saldo'];
    $bono_actual = $datos['bono'];
} else {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lobby VIP | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent;}
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; display: flex; flex-direction: column; align-items: center; padding-bottom: 50px;}
        
        .header { width: 100%; max-width: 900px; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);}
        .logo { font-weight: 900; font-size: 24px; text-transform: uppercase; letter-spacing: -1px;}
        .logo span { color: var(--green); text-shadow: 0 0 15px rgba(0,255,136,0.4);}
        .top-buttons { display: flex; gap: 10px; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 900; font-size: 11px; text-transform: uppercase; transition: 0.3s; }
        .btn-outline:hover { color: #fff; border-color: #fff; }
        .btn-soporte { border-color: var(--blue); color: var(--blue); }
        .btn-soporte:hover { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 0 15px rgba(112,0,255,0.4);}

        .saldo-container { width: 100%; max-width: 900px; background: var(--panel); border: 1px solid var(--green); border-radius: 16px; padding: 30px; text-align: center; margin-bottom: 30px; box-shadow: 0 0 30px rgba(0,255,136,0.1); position: relative; overflow: hidden;}
        .saldo-container::before { content:''; position: absolute; top:0; left:0; right:0; height: 4px; background: var(--green); box-shadow: 0 0 20px var(--green);}
        .saldo-title { font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 900; letter-spacing: 2px; margin-bottom: 10px; }
        .saldo-monto { font-family: 'Roboto Mono', monospace; font-size: 48px; font-weight: 900; color: var(--green); text-shadow: 0 0 20px rgba(0,255,136,0.4); }
        .jugador-tag { font-size: 11px; color: var(--text-muted); margin-top: 10px; font-family: 'Roboto Mono';}
        
        /* Banner Wager Conectado */
        .banner-wager { width: 100%; max-width: 900px; background: rgba(112,0,255,0.05); border: 1px solid var(--blue); border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; cursor: pointer; transition: 0.3s; text-decoration: none;}
        .banner-wager:hover { background: rgba(112,0,255,0.1); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(112,0,255,0.2);}
        .w-title { font-weight: 900; color: #fff; font-size: 16px; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px;}
        .w-desc { font-size: 12px; color: var(--blue); font-weight: bold;}
        .w-icon { font-size: 30px; filter: drop-shadow(0 0 10px var(--blue));}

        .caja-rapida { width: 100%; max-width: 900px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        @media (max-width: 768px) { .caja-rapida { grid-template-columns: 1fr; } }
        
        .box-caja { background: var(--panel); border: 1px solid var(--border); padding: 25px; border-radius: 16px; }
        .box-title { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 900; margin-bottom: 15px; letter-spacing: 1px;}
        .btn-caja-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        .btn-monto { background: #000; border: 1px solid var(--border); color: #fff; padding: 12px 5px; border-radius: 8px; font-weight: 900; font-size: 12px; cursor: pointer; transition: 0.2s; font-family: 'Roboto Mono';}
        .btn-monto:hover { border-color: var(--green); color: var(--green); }
        .input-caja { width: 100%; background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-family: 'Roboto Mono'; font-size: 16px; margin-bottom: 15px; outline:none; transition: 0.3s; text-align: center;}
        .input-caja:focus { border-color: var(--green); box-shadow: 0 0 10px rgba(0,255,136,0.2);}
        
        .btn-generar { width: 100%; background: var(--green); color: #000; border: none; padding: 16px; border-radius: 8px; font-weight: 900; text-transform: uppercase; cursor: pointer; transition: 0.3s; font-size: 14px;}
        .btn-generar:hover { box-shadow: 0 0 20px rgba(0,255,136,0.4); }
        .btn-generar:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        
        .btn-retirar { width: 100%; background: transparent; color: var(--red); border: 1px solid var(--red); padding: 16px; border-radius: 8px; font-weight: 900; text-transform: uppercase; cursor: pointer; transition: 0.3s; text-decoration:none; display:block; text-align:center; font-size: 14px;}
        .btn-retirar:hover { background: rgba(255,51,102,0.1); box-shadow: 0 0 20px rgba(255,51,102,0.4);}
        
        .juegos-grid { width: 100%; max-width: 900px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .juego-card { background: var(--panel); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; text-decoration: none; transition: 0.3s; display: block; position: relative;}
        .juego-card:hover { border-color: var(--blue); transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.8); }
        .juego-img { width: 100%; height: 160px; background: #000; display: flex; align-items: center; justify-content: center; font-size: 60px; border-bottom: 1px solid var(--border); }
        .juego-info { padding: 20px; text-align: center; }
        .juego-titulo { color: #fff; font-weight: 900; font-size: 18px; text-transform: uppercase; margin-bottom: 5px; letter-spacing: -0.5px;}
        .juego-desc { color: var(--text-muted); font-size: 12px; }
        .badge-live { position: absolute; top: 15px; right: 15px; background: rgba(255,51,102,0.2); color: var(--red); border: 1px solid var(--red); padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 900; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255,51,102,0.4); } 70% { box-shadow: 0 0 0 10px rgba(255,51,102,0); } 100% { box-shadow: 0 0 0 0 rgba(255,51,102,0); } }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">EL <span>POINT</span></div>
    <div class="top-buttons">
        <a href="vip.php" class="btn-outline btn-soporte">🎧 Soporte</a>
        <a href="../../logout_jugador.php" class="btn-outline">Salir</a>
    </div>
</div>

<div class="saldo-container">
    <div class="saldo-title">Saldo Disponible</div>
    <div class="saldo-monto" id="displaySaldo">$<?php echo number_format($saldo_actual, 2, '.', ''); ?></div>
    <div class="jugador-tag">JUGADOR: <b style="color:#fff;"><?php echo strtoupper($jugador); ?></b></div>
</div>

<a href="referidos.php" class="banner-wager">
    <div>
        <div class="w-title">Programa VIP de Referidos</div>
        <div class="w-desc">Invitá amigos y ganá saldo extra en tu cuenta.</div>
    </div>
    <div class="w-icon">🤝</div>
</a>

<div class="caja-rapida">
    <div class="box-caja">
        <div class="box-title">Ingresar Fichas</div>
        <div class="btn-caja-grid">
            <button class="btn-monto" onclick="sumarMonto(2000)">+$2.000</button>
            <button class="btn-monto" onclick="sumarMonto(5000)">+$5.000</button>
            <button class="btn-monto" onclick="sumarMonto(10000)">+$10.000</button>
        </div>
        <input type="number" id="inputCarga" class="input-caja" placeholder="$ Monto a cargar" min="500">
        <button class="btn-generar" id="btnTicket" onclick="generarTicket()">GENERAR TICKET</button>
    </div>
    
    <div class="box-caja">
        <div class="box-title">Retirar Ganancias</div>
        <div style="height: 100%; display: flex; flex-direction: column; justify-content: center;">
            <p style="color:var(--text-muted); font-size:12px; text-align:center; margin-bottom:20px; font-weight:bold;">Retirá tus ganancias de forma rápida y segura mediante transferencia bancaria.</p>
            <a href="retiros.php" class="btn-retirar">💸 SOLICITAR RETIRO</a>
        </div>
    </div>
</div>

<div class="juegos-grid">
    <a href="ruleta.php" class="juego-card">
        <span class="badge-live">EN VIVO</span>
        <div class="juego-img">🎡</div>
        <div class="juego-info">
            <div class="juego-titulo">Ruleta Europea</div>
            <div class="juego-desc">Multiplicá x2 o x14 al instante.</div>
        </div>
    </a>
    <a href="slots.php" class="juego-card">
        <div class="juego-img">🍒</div>
        <div class="juego-info">
            <div class="juego-titulo">Fruit Slots</div>
            <div class="juego-desc">Buscá el Jackpot x50.</div>
        </div>
    </a>
</div>

<script>
// --- MOTOR DE CAJA RÁPIDA (ANTI-LAG) ---
function sumarMonto(cantidad) {
    let input = document.getElementById('inputCarga');
    let actual = parseFloat(input.value) || 0;
    input.value = actual + cantidad;
}

function generarTicket() {
    let monto = document.getElementById('inputCarga').value;
    let btn = document.getElementById('btnTicket');
    
    if(monto < 500) {
        alert("El mínimo de carga es de $500.");
        return;
    }

    // Bloqueamos botón anti-doble clic
    btn.disabled = true;
    btn.innerText = "PROCESANDO...";

    let fd = new FormData();
    fd.append('monto', monto);
    fd.append('tipo', 'carga'); // 🔥 CRÍTICO: Le avisa al motor que es un depósito

    fetch('../../../backend/api/procesar_ticket.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerText = "GENERAR TICKET";

        if(data.error) {
            alert("❌ " + data.error);
        } else {
            alert("✅ " + data.mensaje);
            document.getElementById('inputCarga').value = '';
            // Redirigir al chat de soporte para que envíe el comprobante
            window.location.href = "vip.php";
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerText = "GENERAR TICKET";
        alert("❌ Error de red. Intentá de nuevo.");
    });
}

// --- RADAR DE SALDO EN VIVO ---
function actualizarSaldo() {
    fetch('../../../backend/api/obtener_saldo.php')
    .then(r => r.json())
    .then(data => {
        if(!data.error) {
            // Formateo visual impecable
            let saldoFormateado = parseFloat(data.saldo).toFixed(2);
            document.getElementById('displaySaldo').innerText = "$" + saldoFormateado;
        } else if (data.error === 'Sesión expirada') {
            window.location.href = "../../index.php";
        }
    })
    .catch(e => console.log("Radar de saldo en pausa."));
}
// Latido cada 3 segundos
setInterval(actualizarSaldo, 3000);
</script>
</body>
</html>