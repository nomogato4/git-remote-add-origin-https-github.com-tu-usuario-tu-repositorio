<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$jugador = mysqli_real_escape_string($conexion, $_SESSION['jugador']);

// Optimizamos: Traemos saldo y estado rapidísimo
$q_user = mysqli_query($conexion, "SELECT saldo, estado FROM usuarios WHERE username = '$jugador' LIMIT 1");
$datos_user = mysqli_fetch_assoc($q_user);

// Patovica (Kill-Switch)
if (!$datos_user || $datos_user['estado'] == 0) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}
$saldo_actual = $datos_user['saldo'];

// 1. TRAEMOS TICKETS DE CARGA PENDIENTES
$q_cargas_pendientes = mysqli_query($conexion, "SELECT monto, fecha FROM tickets WHERE jugador = '$jugador' AND estado = 0 ORDER BY id DESC");

// 2. TRAEMOS RETIROS PENDIENTES
$q_retiros_pendientes = mysqli_query($conexion, "SELECT monto, fecha FROM auditoria WHERE jugador = '$jugador' AND accion = 'RETIRO' AND cajero = 'PENDIENTE' ORDER BY id DESC");

// 3. TRAEMOS HISTORIAL COMPLETADO (Omitimos los pendientes de retiro)
$q_historial = mysqli_query($conexion, "SELECT accion, monto, fecha FROM auditoria WHERE jugador = '$jugador' AND cajero != 'PENDIENTE' ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mi Historial | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 50px; -webkit-tap-highlight-color: transparent;}
        
        /* NAVBAR */
        .navbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .btn-volver { background: transparent; color: var(--text-muted); text-decoration: none; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); transition: 0.3s; text-transform: uppercase; }
        .btn-volver:hover { background: var(--border); color: #fff; }
        .saldo-box { background: #000; border: 1px solid var(--border); padding: 5px 15px; border-radius: 8px; display: flex; flex-direction: column; align-items: flex-end; }
        .saldo-lbl { font-size: 10px; color: var(--text-muted); font-weight: 900; text-transform: uppercase; }
        .saldo-val { font-family: 'Roboto Mono', monospace; font-size: 16px; font-weight: 900; color: var(--green); }

        .container { padding: 30px 20px; max-width: 800px; margin: 0 auto; }
        
        .titulo-seccion { font-size: 14px; font-weight: 900; margin-bottom: 15px; margin-top: 15px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px;}
        
        /* TARJETAS DE MOVIMIENTO */
        .historial-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px;}
        .mov-card { background: var(--panel); border: 1px solid var(--border); padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; position: relative; overflow: hidden;}
        .mov-card:hover { border-color: var(--blue); transform: translateX(5px); }
        
        /* Línea lateral de estado */
        .borde-pendiente { border-left: 4px solid var(--yellow); }
        .borde-ok { border-left: 4px solid var(--border); }

        .mov-info { display: flex; align-items: center; gap: 15px; }
        .mov-icon { width: 45px; height: 45px; min-width: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;}
        
        .icon-pend { background: rgba(255, 215, 0, 0.1); color: var(--yellow); }
        .icon-in { background: rgba(0, 255, 136, 0.1); color: var(--green); }
        .icon-out { background: rgba(255, 51, 102, 0.1); color: var(--red); }
        
        .mov-detalle { display: flex; flex-direction: column; gap: 4px; }
        .mov-tipo { font-size: 13px; font-weight: 900; color: #fff; text-transform: uppercase; letter-spacing: 0.5px;}
        .mov-fecha { font-size: 11px; color: var(--text-muted); font-weight: 700; font-family: 'Roboto Mono', monospace; }
        
        .mov-monto { font-size: 16px; font-weight: 900; font-family: 'Roboto Mono', monospace; text-align: right;}
        .monto-pend { color: var(--yellow); }
        .monto-in { color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,0.3); }
        .monto-out { color: var(--red); }

        .sin-datos { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 12px; font-weight: 900; background: var(--panel); border-radius: 12px; border: 1px dashed var(--border); text-transform: uppercase; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="lobby.php" class="btn-volver">← VOLVER</a>
        <div class="saldo-box">
            <span class="saldo-lbl">Disponible</span>
            <span class="saldo-val" id="displaySaldo">$<?php echo number_format($saldo_actual, 2, '.', ''); ?></span>
        </div>
    </nav>

    <div class="container">

        <?php 
        $hay_pendientes = (mysqli_num_rows($q_cargas_pendientes) > 0 || mysqli_num_rows($q_retiros_pendientes) > 0);
        if($hay_pendientes): 
        ?>
        <div class="titulo-seccion" style="color: var(--yellow);">⏳ En Proceso</div>
        <div class="historial-list">
            
            <?php while($c = mysqli_fetch_assoc($q_cargas_pendientes)): ?>
                <div class="mov-card borde-pendiente">
                    <div class="mov-info">
                        <div class="mov-icon icon-pend">⏳</div>
                        <div class="mov-detalle">
                            <span class="mov-tipo">CARGA PENDIENTE</span>
                            <span class="mov-fecha"><?php echo date("d/m/y - H:i", strtotime($c['fecha'])); ?></span>
                        </div>
                    </div>
                    <div class="mov-monto monto-pend">+$<?php echo number_format($c['monto'], 2, '.', ''); ?></div>
                </div>
            <?php endwhile; ?>

            <?php while($r = mysqli_fetch_assoc($q_retiros_pendientes)): ?>
                <div class="mov-card borde-pendiente">
                    <div class="mov-info">
                        <div class="mov-icon icon-pend">⏳</div>
                        <div class="mov-detalle">
                            <span class="mov-tipo">RETIRO PENDIENTE</span>
                            <span class="mov-fecha"><?php echo date("d/m/y - H:i", strtotime($r['fecha'])); ?></span>
                        </div>
                    </div>
                    <div class="mov-monto monto-pend">-$<?php echo number_format($r['monto'], 2, '.', ''); ?></div>
                </div>
            <?php endwhile; ?>
            
        </div>
        <?php endif; ?>


        <div class="titulo-seccion">✅ Operaciones Completadas</div>
        <div class="historial-list">
            <?php if(mysqli_num_rows($q_historial) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($q_historial)): 
                    $accion = strtoupper($row['accion']);
                    // Lógica blindada para detectar ingreso/egreso
                    $es_ingreso = (strpos($accion, 'CARGA') !== false || strpos($accion, 'PREMIO') !== false || strpos($accion, 'REEMBOLSO') !== false);
                    $monto_formateado = number_format(abs($row['monto']), 2, '.', '');
                ?>
                <div class="mov-card borde-ok">
                    <div class="mov-info">
                        <?php if($es_ingreso): ?>
                            <div class="mov-icon icon-in">↓</div>
                        <?php else: ?>
                            <div class="mov-icon icon-out">↑</div>
                        <?php endif; ?>
                        
                        <div class="mov-detalle">
                            <span class="mov-tipo"><?php echo $accion; ?></span>
                            <span class="mov-fecha"><?php echo date("d/m/y - H:i", strtotime($row['fecha'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="mov-monto <?php echo $es_ingreso ? 'monto-in' : 'monto-out'; ?>">
                        <?php echo $es_ingreso ? '+' : '-'; ?>$<?php echo $monto_formateado; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="sin-datos">
                    No hay operaciones completadas.
                </div>
            <?php endif; ?>
        </div>

    </div>

<script>
    // RADAR DE SALDO EN VIVO (Mantiene el saldo actualizado arriba)
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
        }).catch(e => console.log("Buscando latido..."));
    }
    // Consulta cada 3 segundos
    setInterval(actualizarSaldo, 3000);
</script>
</body>
</html>