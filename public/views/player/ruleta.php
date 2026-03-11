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
    <title>Ruleta VIP | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .header { width: 100%; max-width: 800px; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px; }
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; }
        .logo span { color: var(--blue); }
        .saldo-box { font-family: 'Roboto Mono', monospace; font-size: 20px; font-weight: 900; color: var(--green); }
        .btn-volver { background: transparent; border: 1px solid var(--text); color: var(--text); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .ruleta-container { width: 100%; max-width: 800px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 30px; text-align: center; }
        .numero-ganador { font-size: 80px; font-weight: 900; font-family: 'Roboto Mono'; margin: 20px 0; text-shadow: 0 0 20px rgba(255,255,255,0.2); height: 100px;}
        .color-rojo { color: var(--red); text-shadow: 0 0 20px rgba(255,51,102,0.4); }
        .color-negro { color: #fff; }
        .color-verde { color: var(--green); text-shadow: 0 0 20px rgba(0,255,136,0.4); }
        .input-apuesta { background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-size: 18px; width: 100%; max-width: 200px; text-align: center; font-family: 'Roboto Mono'; margin-bottom: 20px; outline: none; }
        .botones-apuesta { display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 30px; }
        .btn-apostar { border: none; padding: 15px 30px; border-radius: 8px; font-weight: 900; font-size: 16px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        .btn-rojo { background: var(--red); color: #fff; }
        .btn-negro { background: #333; color: #fff; border: 1px solid #555; }
        .btn-verde { background: var(--green); color: #000; }
        .btn-apostar:hover { transform: translateY(-3px); }
        .btn-apostar:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        #mensaje { margin-top: 20px; font-weight: bold; font-size: 14px; min-height: 20px; }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">EL <span>POINT</span></div>
    <div class="saldo-box" id="displaySaldo">$<?php echo number_format($saldo_actual, 2, '.', ''); ?></div>
    <a href="lobby.php" class="btn-volver">← Lobby</a>
</div>

<div class="ruleta-container">
    <div style="color:var(--text-muted); font-weight:900; text-transform:uppercase; font-size:12px;">Último Tiro</div>
    <div class="numero-ganador" id="displayNumero">--</div>
    
    <input type="number" id="montoApuesta" class="input-apuesta" placeholder="$ Monto" min="10">
    
    <div class="botones-apuesta">
        <button class="btn-apostar btn-verde" onclick="girarRuleta('verde')" id="btnVerde">Cero (x14)</button>
        <button class="btn-apostar btn-rojo" onclick="girarRuleta('rojo')" id="btnRojo">Rojo (x2)</button>
        <button class="btn-apostar btn-negro" onclick="girarRuleta('negro')" id="btnNegro">Negro (x2)</button>
    </div>

    <div id="mensaje"></div>
</div>

<script>
// 🔥 ACÁ ESTÁ EL CABLE CONECTADO A LA API DEL BACKEND
const API_URL = '../../../backend/api/api_ruleta.php';

function girarRuleta(colorApostado) {
    let monto = parseFloat(document.getElementById('montoApuesta').value);
    let msgBox = document.getElementById('mensaje');
    
    if (isNaN(monto) || monto <= 0) {
        msgBox.innerHTML = "<span style='color:var(--red)'>⚠️ Ingresá un monto válido.</span>";
        return;
    }

    // Bloqueamos botones para evitar doble clic (anti-lag)
    document.getElementById('btnVerde').disabled = true;
    document.getElementById('btnRojo').disabled = true;
    document.getElementById('btnNegro').disabled = true;
    
    msgBox.innerHTML = "<span style='color:var(--text-muted)'>🎲 Girando la ruleta...</span>";
    document.getElementById('displayNumero').innerText = "🔄";
    document.getElementById('displayNumero').className = "numero-ganador";

    let formData = new FormData();
    formData.append('monto', monto);
    formData.append('color', colorApostado);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Desbloqueamos botones
        document.getElementById('btnVerde').disabled = false;
        document.getElementById('btnRojo').disabled = false;
        document.getElementById('btnNegro').disabled = false;

        if (data.error) {
            msgBox.innerHTML = "<span style='color:var(--red)'>❌ " + data.error + "</span>";
            document.getElementById('displayNumero').innerText = "--";
            return;
        }

        // Actualizamos Saldo en vivo
        document.getElementById('displaySaldo').innerText = "$" + data.nuevo_saldo;

        // Mostramos el número que salió
        let displayNum = document.getElementById('displayNumero');
        displayNum.innerText = data.numero;
        
        if (data.color_salida === 'rojo') displayNum.className = "numero-ganador color-rojo";
        else if (data.color_salida === 'negro') displayNum.className = "numero-ganador color-negro";
        else displayNum.className = "numero-ganador color-verde";

        // Mostramos si ganó o perdió
        if (data.ganancia > 0) {
            msgBox.innerHTML = "<span style='color:var(--green)'>🎉 ¡GANASTE $" + data.ganancia + "!</span>";
        } else {
            msgBox.innerHTML = "<span style='color:var(--text-muted)'>💔 Salió " + data.color_salida + ". Perdiste.</span>";
        }
    })
    .catch(error => {
        msgBox.innerHTML = "<span style='color:var(--red)'>❌ Error de conexión con el servidor.</span>";
        document.getElementById('btnVerde').disabled = false;
        document.getElementById('btnRojo').disabled = false;
        document.getElementById('btnNegro').disabled = false;
    });
}
</script>
</body>
</html>