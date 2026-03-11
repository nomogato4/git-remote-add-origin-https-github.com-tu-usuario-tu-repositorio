<?php
session_start();
// Conexión blindada a la nueva arquitectura
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$jugador = $_SESSION['jugador'];

// Traemos el saldo sin lag
$q_saldo = mysqli_query($conexion, "SELECT saldo FROM usuarios WHERE username = '$jugador' LIMIT 1");
$saldo_actual = ($q_saldo && mysqli_num_rows($q_saldo) > 0) ? mysqli_fetch_assoc($q_saldo)['saldo'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slots VIP | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .header { width: 100%; max-width: 800px; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px; }
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; }
        .logo span { color: var(--blue); }
        .saldo-box { font-family: 'Roboto Mono', monospace; font-size: 20px; font-weight: 900; color: var(--green); }
        .btn-volver { background: transparent; border: 1px solid var(--text); color: var(--text); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        
        .maquina-container { width: 100%; max-width: 600px; background: var(--panel); border: 2px solid var(--border); border-radius: 16px; padding: 30px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.8); }
        .rodillos { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; background: #000; padding: 20px; border-radius: 12px; border: 1px solid #222; box-shadow: inset 0 0 20px rgba(0,0,0,1); }
        .rodillo { font-size: 60px; width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; background: #111; border: 2px solid var(--border); border-radius: 12px; text-shadow: 0 0 15px rgba(255,255,255,0.2); }
        .rodillo.girando { animation: vibrar 0.1s linear infinite; filter: blur(2px); }
        
        .controles { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .input-apuesta { background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-size: 18px; width: 100%; max-width: 250px; text-align: center; font-family: 'Roboto Mono'; outline: none; }
        .btn-girar { background: var(--blue); color: #fff; border: none; padding: 15px 50px; border-radius: 8px; font-weight: 900; font-size: 18px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; max-width: 250px; box-shadow: 0 0 20px rgba(112,0,255,0.3); }
        .btn-girar:hover { transform: translateY(-3px); box-shadow: 0 0 30px rgba(112,0,255,0.6); }
        .btn-girar:disabled { background: #333; color: #666; cursor: not-allowed; transform: none; box-shadow: none; }
        
        #mensaje { margin-top: 25px; font-weight: 900; font-size: 16px; min-height: 24px; text-transform: uppercase; letter-spacing: 1px; }
        @keyframes vibrar { 0% { transform: translateY(-2px); } 50% { transform: translateY(2px); } 100% { transform: translateY(-2px); } }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">EL <span>POINT</span></div>
    <div class="saldo-box" id="displaySaldo">$<?php echo number_format($saldo_actual, 2, '.', ''); ?></div>
    <a href="lobby.php" class="btn-volver">← Lobby</a>
</div>

<div class="maquina-container">
    <h2 style="margin-top:0; color:var(--text-muted); font-size:14px; letter-spacing:2px;">🍒 FRUIT SLOTS 🍒</h2>
    
    <div class="rodillos">
        <div class="rodillo" id="r1">💎</div>
        <div class="rodillo" id="r2">💎</div>
        <div class="rodillo" id="r3">💎</div>
    </div>
    
    <div class="controles">
        <input type="number" id="montoApuesta" class="input-apuesta" placeholder="$ Apuesta (Min 10)" min="10">
        <button class="btn-girar" onclick="jugarSlots()" id="btnGirar">TIRAR (SPIN)</button>
    </div>

    <div id="mensaje"></div>
</div>

<script>
// RUTA AL CEREBRO DE LAS SLOTS
const API_URL = '../../../backend/api/api_slots.php';
const simbolos = ['🍒', '🍋', '🍉', '⭐', '💎', '🔔'];

function jugarSlots() {
    let monto = parseFloat(document.getElementById('montoApuesta').value);
    let msgBox = document.getElementById('mensaje');
    let btn = document.getElementById('btnGirar');
    let r1 = document.getElementById('r1');
    let r2 = document.getElementById('r2');
    let r3 = document.getElementById('r3');
    
    if (isNaN(monto) || monto <= 0) {
        msgBox.innerHTML = "<span style='color:var(--red)'>⚠️ Ingresá un monto válido.</span>";
        return;
    }

    btn.disabled = true;
    msgBox.innerHTML = "<span style='color:var(--text-muted)'>🎰 Girando...</span>";
    
    // Efecto visual de giro
    r1.classList.add('girando'); r2.classList.add('girando'); r3.classList.add('girando');
    let anim = setInterval(() => {
        r1.innerText = simbolos[Math.floor(Math.random() * simbolos.length)];
        r2.innerText = simbolos[Math.floor(Math.random() * simbolos.length)];
        r3.innerText = simbolos[Math.floor(Math.random() * simbolos.length)];
    }, 100);

    let formData = new FormData();
    formData.append('monto', monto);

    fetch(API_URL, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        setTimeout(() => {
            clearInterval(anim);
            r1.classList.remove('girando'); r2.classList.remove('girando'); r3.classList.remove('girando');
            btn.disabled = false;

            if (data.error) {
                msgBox.innerHTML = "<span style='color:var(--red)'>❌ " + data.error + "</span>";
                return;
            }

            // Seteamos los símbolos reales que devolvió el backend
            r1.innerText = data.rodillos[0];
            r2.innerText = data.rodillos[1];
            r3.innerText = data.rodillos[2];
            
            // Actualizamos saldo
            document.getElementById('displaySaldo').innerText = "$" + data.nuevo_saldo;

            // Mostramos resultado
            if (data.ganancia > 0) {
                msgBox.innerHTML = "<span style='color:var(--green)'>🎉 ¡PREMIO! GANASTE $" + data.ganancia + " 🎉</span>";
            } else {
                msgBox.innerHTML = "<span style='color:var(--text-muted)'>Casi... ¡Volvé a intentar!</span>";
            }
        }, 1000); // 1 segundo de suspenso visual
    })
    .catch(error => {
        clearInterval(anim);
        r1.classList.remove('girando'); r2.classList.remove('girando'); r3.classList.remove('girando');
        msgBox.innerHTML = "<span style='color:var(--red)'>❌ Error de conexión.</span>";
        btn.disabled = false;
    });
}
</script>
</body>
</html>