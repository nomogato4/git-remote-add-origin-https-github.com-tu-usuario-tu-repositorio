<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$jugador = $_SESSION['jugador'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Retirar Ganancias | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        
        .card { background: var(--panel); border: 1px solid var(--border); padding: 40px; border-radius: 16px; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative; overflow: hidden;}
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--red); box-shadow: 0 0 15px var(--red); }
        
        .titulo { margin-top: 0; font-weight: 900; letter-spacing: -1px; text-transform: uppercase; font-size: 24px; margin-bottom: 30px;}
        
        .caja-saldo { background: #000; border: 1px dashed var(--red); padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        .saldo-label { color: var(--text-muted); font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .saldo-disponible { font-family: 'Roboto Mono', monospace; color: var(--red); font-size: 32px; font-weight: 900; text-shadow: 0 0 15px rgba(255,51,102,0.3); }
        
        .input-group { text-align: left; margin-bottom: 20px; }
        .input-label { display: block; font-size: 11px; color: var(--text-muted); font-weight: 900; text-transform: uppercase; margin-bottom: 8px; margin-left: 5px;}
        .input-dark { width: 100%; background: #000; border: 1px solid var(--border); padding: 15px; border-radius: 8px; color: #fff; outline: none; font-family: 'Roboto Mono', monospace; font-size: 16px; text-align: center; transition: 0.3s; }
        .input-dark:focus { border-color: var(--red); box-shadow: 0 0 10px rgba(255,51,102,0.2); }
        
        .btn-send { width: 100%; background: var(--red); color: #fff; border: none; padding: 18px; border-radius: 8px; font-weight: 900; cursor: pointer; text-transform: uppercase; font-size: 15px; transition: 0.3s; letter-spacing: 1px; margin-top: 10px;}
        .btn-send:hover { filter: brightness(1.2); box-shadow: 0 0 20px rgba(255,51,102,0.4); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        
        .link-volver { display: inline-block; color: var(--text-muted); text-decoration: none; font-size: 12px; font-weight: 900; text-transform: uppercase; transition: 0.3s; margin-top: 30px; border: 1px solid var(--border); padding: 10px 20px; border-radius: 8px;}
        .link-volver:hover { background: var(--border); color: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <h2 class="titulo">💸 SOLICITAR RETIRO</h2>
        
        <div class="caja-saldo">
            <div class="saldo-label">Fichas Disponibles</div>
            <div class="saldo-disponible" id="displaySaldo">CARGANDO...</div>
        </div>
        
        <form id="formRetiro" onsubmit="procesarRetiro(event)">
            <div class="input-group">
                <span class="input-label">Monto a Retirar (Mínimo $1000)</span>
                <input type="number" id="monto_retiro" class="input-dark" placeholder="$ 0.00" required min="1000">
            </div>

            <div class="input-group">
                <span class="input-label">Tu CBU / CVU / Alias</span>
                <input type="text" id="cbu_cvu" class="input-dark" placeholder="Ej: leito.mp" required autocomplete="off">
            </div>

            <button type="submit" id="btn-retirar" class="btn-send">EXTRAER GANANCIAS</button>
        </form>
        
        <a href="lobby.php" class="link-volver">← VOLVER AL LOBBY</a>
    </div>

    <script>
    // 1. RADAR DE SALDO EN VIVO
    function actualizarSaldo() {
        fetch('../../../backend/api/obtener_saldo.php')
        .then(r => r.json())
        .then(data => {
            if(!data.error) {
                let saldoFormateado = parseFloat(data.saldo).toFixed(2);
                document.getElementById('displaySaldo').innerText = "$" + saldoFormateado;
            } else if (data.error === 'Sesión expirada') {
                window.location.href = "../../index.php";
            }
        }).catch(e => console.log("Cargando saldo..."));
    }
    // Carga inicial y actualización cada 3 segundos
    actualizarSaldo();
    setInterval(actualizarSaldo, 3000);

    // 2. PROCESAR RETIRO (Conectado a la API Blindada)
    function procesarRetiro(e) {
        e.preventDefault(); // Evita que la página se recargue

        let monto = document.getElementById('monto_retiro').value;
        let cbu = document.getElementById('cbu_cvu').value;
        let btn = document.getElementById('btn-retirar');

        if(monto < 1000) { alert("El retiro mínimo es de $1000."); return; }
        if(cbu.trim() === '') { alert("Por favor ingresá tu CBU o Alias."); return; }

        // Bloqueo Anti-Spam / Anti-Lag
        btn.disabled = true;
        btn.innerText = "⏳ VERIFICANDO FONDOS...";

        let fd = new FormData();
        fd.append('monto', monto);
        fd.append('tipo', 'retiro'); // 🔥 Le avisa al motor que descuente plata

        fetch('../../../backend/api/procesar_ticket.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if(data.error) {
                alert("❌ " + data.error);
                btn.disabled = false;
                btn.innerText = "EXTRAER GANANCIAS";
            } else {
                // ÉXITO ATÓMICO
                alert("✅ ¡Retiro Aprobado!\nEl saldo ya fue descontado.\n\nPor favor, enviá este CBU/Alias ('" + cbu + "') en la cabina de soporte para que el cajero te transfiera la plata.");
                
                // Lo mandamos al soporte para que cierre el trato
                window.location.href = "vip.php";
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerText = "EXTRAER GANANCIAS";
            alert("❌ Error de red. Revisá tu conexión.");
        });
    }
    </script>
</body>
</html>