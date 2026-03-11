<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

// 🔥 PATOVICA SUPREMO: Solo el Dueño (Admin) puede ver la plata del negocio
if (!isset($_SESSION['admin'])) {
    // Si es un cajero de metido, lo mandamos de vuelta a su panel
    header("Location: dashboard.php");
    exit;
}

// ==========================================
// CÁLCULO DE FINANZAS EN VIVO (SIN LAG)
// ==========================================

// 1. Total Ingresos (Cargas Aprobadas)
$q_ingresos = mysqli_query($conexion, "SELECT SUM(monto) as total FROM auditoria WHERE accion LIKE '%CARGA%'");
$ingresos = mysqli_fetch_assoc($q_ingresos)['total'] ?? 0;

// 2. Total Egresos (Retiros Pagados)
$q_egresos = mysqli_query($conexion, "SELECT SUM(monto) as total FROM auditoria WHERE accion LIKE '%PAGADO%'");
$egresos = mysqli_fetch_assoc($q_egresos)['total'] ?? 0;

// 3. Retiros Pendientes (Plata que debes pero no pagaste aún)
$q_pendientes = mysqli_query($conexion, "SELECT SUM(monto) as total FROM auditoria WHERE accion = 'RETIRO' AND cajero = 'PENDIENTE'");
$pendientes = mysqli_fetch_assoc($q_pendientes)['total'] ?? 0;

// 4. Ganancia Neta (Balance)
$balance = $ingresos - $egresos;

// Traemos los últimos 100 movimientos generales del casino
$q_historial = mysqli_query($conexion, "SELECT * FROM auditoria ORDER BY id DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finanzas y Cierre | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 40px;}
        
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;}
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; color: #fff; text-decoration: none;}
        .logo span { color: var(--blue); }
        .btn-volver { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; text-decoration: none; transition: 0.3s;}
        .btn-volver:hover { background: var(--border); color: #fff; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .titulo-seccion { font-size: 24px; font-weight: 900; margin-bottom: 20px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center;}
        
        .btn-cierre { background: transparent; border: 1px dashed var(--red); color: var(--red); padding: 10px 20px; border-radius: 8px; font-weight: 900; cursor: pointer; text-transform: uppercase; transition: 0.3s;}
        .btn-cierre:hover { background: rgba(255,51,102,0.1); box-shadow: 0 0 15px rgba(255,51,102,0.4); }

        /* TARJETAS DE MÉTRICAS (KPIs) */
        .grid-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 25px; display: flex; flex-direction: column; position: relative; overflow: hidden;}
        .kpi-title { font-size: 11px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;}
        .kpi-monto { font-family: 'Roboto Mono', monospace; font-size: 32px; font-weight: 900; }
        
        .kpi-in { border-bottom: 3px solid var(--green); }
        .kpi-in .kpi-monto { color: var(--green); text-shadow: 0 0 15px rgba(0,255,136,0.3); }
        
        .kpi-out { border-bottom: 3px solid var(--red); }
        .kpi-out .kpi-monto { color: var(--red); text-shadow: 0 0 15px rgba(255,51,102,0.3); }
        
        .kpi-neto { border-bottom: 3px solid var(--blue); background: rgba(112,0,255,0.05);}
        .kpi-neto .kpi-monto { color: #fff; }
        
        .kpi-pend { border-bottom: 3px solid var(--yellow); }
        .kpi-pend .kpi-monto { color: var(--yellow); font-size: 24px;}

        /* TABLA DE AUDITORÍA */
        .tabla-container { background: var(--panel); border-radius: 12px; border: 1px solid var(--border); overflow-x: auto;}
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: rgba(0,0,0,0.5); padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 900; border-bottom: 1px solid var(--border);}
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; color: #e1e1e1;}
        .fila-log:hover { background: rgba(255,255,255,0.02); }
        
        .log-in { color: var(--green); font-weight: bold;}
        .log-out { color: var(--red); font-weight: bold;}
        .log-pend { color: var(--yellow); font-weight: bold;}
    </style>
</head>
<body>

<nav class="topbar">
    <a href="dashboard.php" class="logo">EL <span>POINT</span></a>
    <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
</nav>

<div class="container">
    <div class="titulo-seccion">
        📊 Finanzas y Auditoría
        <button class="btn-cierre" onclick="ejecutarCierre()">🗑️ Cierre de Caja</button>
    </div>

    <div class="grid-kpi">
        <div class="kpi-card kpi-in">
            <div class="kpi-title">Ingresos Brutos (Cargas)</div>
            <div class="kpi-monto">$<?php echo number_format($ingresos, 2, '.', ','); ?></div>
        </div>
        <div class="kpi-card kpi-out">
            <div class="kpi-title">Egresos (Retiros Pagados)</div>
            <div class="kpi-monto">$<?php echo number_format($egresos, 2, '.', ','); ?></div>
        </div>
        <div class="kpi-card kpi-neto">
            <div class="kpi-title">Balance Neto (Ganancia)</div>
            <div class="kpi-monto">$<?php echo number_format($balance, 2, '.', ','); ?></div>
        </div>
        <div class="kpi-card kpi-pend">
            <div class="kpi-title">Retiros Pendientes de Pago</div>
            <div class="kpi-monto">$<?php echo number_format($pendientes, 2, '.', ','); ?></div>
        </div>
    </div>

    <div class="tabla-container">
        <table>
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Cajero / Operador</th>
                    <th>Jugador Afectado</th>
                    <th>Acción</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($q_historial) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($q_historial)): 
                        $accion = strtoupper($row['accion']);
                        $monto = number_format($row['monto'], 2, '.', ',');
                        $fecha = date("d/m/Y - H:i:s", strtotime($row['fecha']));
                        
                        // Colores según movimiento
                        $clase_monto = '';
                        $prefijo = '';
                        if (strpos($accion, 'CARGA') !== false) { $clase_monto = 'log-in'; $prefijo = '+'; }
                        elseif (strpos($accion, 'PAGADO') !== false) { $clase_monto = 'log-out'; $prefijo = '-'; }
                        elseif ($accion == 'RETIRO') { $clase_monto = 'log-pend'; $prefijo = '⏳ '; }
                    ?>
                    <tr class="fila-log">
                        <td style="font-family:'Roboto Mono'; font-size:11px; color:var(--text-muted);"><?php echo $fecha; ?></td>
                        <td style="font-weight:900; color:var(--blue);"><?php echo strtoupper($row['cajero']); ?></td>
                        <td style="font-weight:bold;"><?php echo strtoupper($row['jugador']); ?></td>
                        <td>
                            <?php if($clase_monto == 'log-in') echo '🟢 '; ?>
                            <?php if($clase_monto == 'log-out') echo '🔴 '; ?>
                            <?php if($clase_monto == 'log-pend') echo '🟡 '; ?>
                            <?php echo $accion; ?>
                        </td>
                        <td style="text-align:right; font-family:'Roboto Mono'; font-size:15px;" class="<?php echo $clase_monto; ?>">
                            <?php echo $prefijo . '$' . $monto; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">El libro mayor está limpio. Aún no hay movimientos financieros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function ejecutarCierre() {
    let confirmacion = confirm("⚠️ ATENCIÓN: Estás a punto de hacer el Cierre de Caja.\n\nEsto va a mover todos los registros actuales al historial de cierres y dejará los contadores en $0.\n\n¿Estás completamente seguro de continuar?");
    
    if (confirmacion) {
        // Acá llamamos al script de cierre que movimos a la carpeta de backend/scripts/
        window.location.href = '../../../backend/scripts/cierre.php';
    }
}
</script>
</body>
</html>