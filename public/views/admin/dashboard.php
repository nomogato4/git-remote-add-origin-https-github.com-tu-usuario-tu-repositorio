<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

// Si no es ni cajero ni dueño, lo echamos al login
if (!isset($_SESSION['admin']) && !isset($_SESSION['cajero'])) {
    header("Location: auth.php");
    exit;
}

$rol = isset($_SESSION['admin']) ? 'admin' : 'cajero';
$es_admin = ($rol === 'admin');
$mi_usuario = $_SESSION[$rol];

// --- MOTOR DE DATOS EN VIVO (ANTI-LAG) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $data = [];
    
    // Contamos tickets pendientes (Cargas) y auditoria pendiente (Retiros)
    $q_tickets = mysqli_query($conexion, "SELECT COUNT(*) as t FROM tickets WHERE estado = 0");
    $tickets = $q_tickets ? mysqli_fetch_assoc($q_tickets)['t'] : 0;
    
    $q_retiros = mysqli_query($conexion, "SELECT COUNT(*) as r FROM auditoria WHERE accion = 'RETIRO' AND cajero = 'PENDIENTE'");
    $retiros = $q_retiros ? mysqli_fetch_assoc($q_retiros)['r'] : 0;
    
    $data['alertas'] = $tickets + $retiros;
    
    // Pizarra del turno (Lo guardamos temporalmente en sesión para no saturar archivos)
    $data['anuncio'] = $_SESSION['anuncio_turno'] ?? "Bienvenidos al turno. Cargas y retiros activos.";
    
    if ($es_admin) {
        $data['staff_online'] = ["Admin"]; // En esta versión simplificamos para no saturar DB
        
        $q_log = mysqli_query($conexion, "SELECT cajero, accion, monto FROM auditoria ORDER BY id DESC LIMIT 5");
        $logs = [];
        if($q_log) { while($r = mysqli_fetch_assoc($q_log)) { $logs[] = "👁️ {$r['cajero']} operó $" . number_format($r['monto'],0); } }
        $data['logs'] = implode(" &nbsp;&nbsp;|&nbsp;&nbsp; ", $logs);
        
        $data['chart'] = [20, 50, 30, 80, 40, 90, 60]; 
    }
    echo json_encode($data);
    exit;
}

// Guardar anuncio nuevo (Solo Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $es_admin && isset($_POST['guardar_anuncio'])) {
    $_SESSION['anuncio_turno'] = strip_tags($_POST['texto_anuncio']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 40px;}
        
        /* NAVBAR */
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;}
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; letter-spacing: 1px;}
        .logo span { color: var(--blue); text-shadow: 0 0 15px rgba(112,0,255,0.4); }
        .reloj-caja { font-family: 'Roboto Mono', monospace; font-size: 14px; color: var(--green); display: flex; align-items: center; gap: 10px;}
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .bienvenida { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;}
        .b-texto h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: -1px; }
        .b-texto p { margin: 5px 0 0 0; font-size: 11px; color: var(--text-muted); font-family: 'Roboto Mono';}
        
        .pizarra { background: rgba(112,0,255,0.05); border: 1px dashed var(--blue); padding: 15px; border-radius: 12px; margin-bottom: 30px; display:flex; flex-direction:column; gap:10px; }
        .input-pizarra { width: 100%; background: #000; border: 1px solid var(--border); color: #fff; padding: 10px; border-radius: 8px; outline:none;}
        
        .grid-apps { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 30px;}
        .app-btn { background: var(--panel); border: 1px solid var(--border); border-radius: 16px; padding: 30px 20px; text-align: center; text-decoration: none; color: #fff; transition: 0.3s; position: relative; cursor: pointer; display:flex; flex-direction:column; align-items:center; justify-content:center;}
        .app-btn:hover { transform: translateY(-5px); border-color: var(--blue); box-shadow: 0 15px 40px rgba(112,0,255,0.2);}
        .app-icon { font-size: 40px; margin-bottom: 15px; }
        .app-title { font-weight: 900; font-size: 16px; text-transform: uppercase;}
        .badge-radar { position: absolute; top: 15px; right: 15px; background: var(--red); color: #fff; padding: 5px 12px; border-radius: 20px; font-weight: 900; box-shadow: 0 0 10px rgba(255,51,102,0.5);}
        
        .admin-zone { border-top: 1px solid var(--border); padding-top: 30px; margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;}
        .card-sauron { background: #000; border: 1px solid #161b22; padding: 20px; border-radius: 12px; }
        .cs-title { font-size: 11px; font-weight: 900; color: var(--text-muted); margin-bottom: 15px; text-transform:uppercase;}
        .staff-list { margin: 0; padding: 0; list-style: none; font-size: 13px; font-weight: bold;}
        .chart-svg { width: 100%; height: 60px; stroke: var(--blue); stroke-width: 3; fill: none; }
        
        .ticker-gh { position: fixed; bottom: 0; left: 0; width: 100%; background: #000; border-top: 1px solid var(--border); height: 30px; display: flex; align-items: center; overflow: hidden; z-index: 100;}
        .ticker-move { white-space: nowrap; font-family: 'Roboto Mono'; font-size: 11px; color: var(--text-muted); animation: moveGH 20s linear infinite; padding-left: 100%;}
        @keyframes moveGH { to { transform: translateX(-100%); } }
    </style>
</head>
<body>
<nav class="topbar">
    <div class="logo">EL <span>POINT</span></div>
    <div class="reloj-caja" id="relojSistema">00:00:00</div>
</nav>

<div class="container">
    <div class="bienvenida">
        <div class="b-texto">
            <h1>Hola, <?php echo strtoupper($mi_usuario); ?></h1>
            <p>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?> | ROL: <?php echo strtoupper($rol); ?></p>
        </div>
        <a href="logout_staff.php" style="color:var(--red); font-size:12px; font-weight:900; text-transform:uppercase; text-decoration:none; border:1px solid var(--red); padding:5px 10px; border-radius:6px;">Salir</a>
    </div>

    <div class="pizarra">
        <div style="font-size:10px; font-weight:900; color:var(--blue); letter-spacing:1px;">📣 ANUNCIO DEL TURNO</div>
        <?php if($es_admin): ?>
            <form method="POST" style="display:flex; gap:10px;">
                <input type="text" name="texto_anuncio" id="txtAnuncio" class="input-pizarra" placeholder="Escribir anuncio...">
                <button type="submit" name="guardar_anuncio" style="background:var(--blue); color:#fff; border:none; border-radius:8px; padding:0 20px; font-weight:bold; cursor:pointer;">Fijar</button>
            </form>
        <?php else: ?>
            <div style="font-size:14px; font-weight:bold; color:#fff;" id="lblAnuncio">Cargando anuncio...</div>
        <?php endif; ?>
    </div>

    <div class="grid-apps">
        <a href="interno.php" class="app-btn" style="border-color: var(--blue);">
            <span class="badge-radar" id="badgeAlertas" style="display:none;">0</span>
            <span class="app-icon">🎧</span>
            <span class="app-title">Central de Alertas</span>
        </a>
        
        <a href="jugadores.php" class="app-btn">
            <span class="app-icon">🕵️</span>
            <span class="app-title">Jugadores</span>
        </a>

        <?php if($es_admin): ?>
            <a href="cajeros.php" class="app-btn"><span class="app-icon">👥</span><span class="app-title">Staff</span></a>
            <a href="historial.php" class="app-btn"><span class="app-icon">📊</span><span class="app-title">Finanzas</span></a>
        <?php else: ?>
            <div class="app-btn" style="cursor:default; border-color:var(--green);">
                <span class="app-icon">💰</span>
                <span class="app-title" style="font-size:11px;">Mi Caja Fuerte</span>
                <div style="font-family:'Roboto Mono'; font-size:28px; font-weight:900; color:var(--green); margin-top:10px; text-shadow:0 0 15px rgba(0,255,136,0.4);">
                    <?php 
                        $q_stock = mysqli_query($conexion, "SELECT saldo_cajero FROM usuarios WHERE username='$mi_usuario'");
                        echo "$" . number_format(mysqli_fetch_assoc($q_stock)['saldo_cajero'], 2, '.', '');
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if($es_admin): ?>
        <div class="admin-zone">
            <div class="card-sauron">
                <div class="cs-title">🟢 Estado de Red</div>
                <ul class="staff-list" id="listaStaff"></ul>
            </div>
            <div class="card-sauron">
                <div class="cs-title">📈 Latidos de Apuestas</div>
                <div style="height: 100px; display:flex; align-items:flex-end;">
                    <svg class="chart-svg" viewBox="0 0 100 40" preserveAspectRatio="none"><polyline id="lineaLatidos" points="0,40 100,40"></polyline></svg>
                </div>
            </div>
        </div>
        <div class="ticker-gh"><div class="ticker-move" id="tickerGH">Buscando actividad financiera...</div></div>
    <?php endif; ?>
</div>

<script>
// Reloj Neón
function actualizarReloj() {
    document.getElementById('relojSistema').innerText = new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date());
}
setInterval(actualizarReloj, 1000);

// Motor de Datos Anti-Lag
function cargarDatosMagicos() {
    fetch('dashboard.php?ajax=1')
    .then(r => r.json())
    .then(data => {
        // Alertas combinadas (Mensajes + Tickets + Retiros)
        let b = document.getElementById('badgeAlertas');
        if (data.alertas > 0) {
            b.innerText = data.alertas;
            b.style.display = 'inline-block';
        } else {
            b.style.display = 'none';
        }
        
        // Pizarra
        let txtA = document.getElementById('txtAnuncio'); if(txtA) txtA.value = data.anuncio;
        let lblA = document.getElementById('lblAnuncio'); if(lblA) lblA.innerText = data.anuncio;

        <?php if($es_admin): ?>
            // Ticker Financiero y Grafico (Solo Dueño)
            document.getElementById('tickerGH').innerHTML = data.logs;
            let max = Math.max(...data.chart, 1);
            let puntos = data.chart.map((val, i) => `${(i / (data.chart.length - 1)) * 100},${40 - ((val / max) * 40)}`).join(' ');
            document.getElementById('lineaLatidos').setAttribute('points', puntos);
        <?php endif; ?>
    });
}
// Latido cada 4 segundos
setInterval(cargarDatosMagicos, 4000); 
cargarDatosMagicos();
</script>
</body>
</html>