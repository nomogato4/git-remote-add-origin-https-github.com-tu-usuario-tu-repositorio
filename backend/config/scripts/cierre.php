<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

// Seguridad: Pueden entrar Vos y tus Cajeros
if (!isset($_SESSION['admin']) && !isset($_SESSION['cajero'])) {
    header("Location: auth.php");
    exit;
}

$rol = isset($_SESSION['admin']) ? 'admin' : 'cajero';
$usuario_actual = $_SESSION[$rol];

// Configuramos el "HOY"
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');

// 🔥 BLINDAJE DE PRIVACIDAD: 
// Si es Admin, ve todo. Si es cajero, solo ve SU propia plata.
$filtro_cajero = ($rol === 'admin') ? "" : "AND cajero = '$usuario_actual'";

// --- MOTOR FINANCIERO DIARIO (OPTIMIZADO) ---

// 1. Ingresos HOY (Cargas Aprobadas)
$q_cargas = mysqli_query($conexion, "SELECT SUM(monto) as total FROM auditoria WHERE accion LIKE '%CARGA%' AND DATE(fecha) = '$hoy' $filtro_cajero");
$cargas_hoy = mysqli_fetch_assoc($q_cargas)['total'] ?? 0;

// 2. Egresos HOY (Premios Pagados)
$q_retiros = mysqli_query($conexion, "SELECT SUM(monto) as total FROM auditoria WHERE accion LIKE '%PAGADO%' AND DATE(fecha) = '$hoy' $filtro_cajero");
$retiros_hoy = mysqli_fetch_assoc($q_retiros)['total'] ?? 0;

// 3. Balance de la caja de HOY
$caja_hoy = $cargas_hoy - $retiros_hoy;

// 4. Detalle de los movimientos del día
$q_movimientos = mysqli_query($conexion, "SELECT * FROM auditoria WHERE DATE(fecha) = '$hoy' $filtro_cajero ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidación Diaria | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --green: #00ff88; --red: #ff3366; --blue: #7000ff; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; padding-bottom: 40px; }

        /* Navbar Staff */
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.8); }
        .logo { color: #fff; font-weight: 900; font-size: 20px; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; }
        .logo span { color: var(--blue); text-shadow: 0 0 15px rgba(112, 0, 255, 0.4); }
        .badge-rol { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 900; text-transform: uppercase; margin-left: 10px; }
        .badge-admin { background: rgba(255, 51, 102, 0.1); color: var(--red); border: 1px solid var(--red); }
        .badge-cajero { background: rgba(112, 0, 255, 0.1); color: var(--blue); border: 1px solid var(--blue); }
        
        .btn-volver { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 900; transition: 0.3s; text-transform: uppercase; }
        .btn-volver:hover { color: #fff; background: var(--border); }

        .main-content { padding: 30px 20px; max-width: 900px; margin: 0 auto; }

        /* Ticket de Cierre */
        .ticket-cierre { background: var(--panel); border: 1px solid var(--border); padding: 30px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5); position: relative; overflow: hidden;}
        .ticket-cierre::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--blue); box-shadow: 0 0 15px var(--blue); }

        .ticket-header { text-align: center; border-bottom: 1px dashed var(--border); padding-bottom: 20px; margin-bottom: 20px; }
        .ticket-header h2 { font-size: 20px; font-weight: 900; text-transform: uppercase; margin: 0 0 5px 0; letter-spacing: 1px; }
        .ticket-header p { color: var(--text-muted); font-size: 12px; margin: 0; font-family: 'Roboto Mono', monospace; font-weight: bold;}

        .fila-monto { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 15px; font-weight: 900; }
        .fila-monto.ingreso { color: var(--green); }
        .fila-monto.egreso { color: var(--red); }

        .fila-total { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border); font-size: 22px; font-weight: 900; }
        .monto-valor { font-family: 'Roboto Mono', monospace; }

        .btn-imprimir { width: 100%; background: var(--blue); color: #fff; border: none; padding: 15px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 14px; cursor: pointer; transition: 0.3s; margin-top: 20px; letter-spacing: 1px;}
        .btn-imprimir:hover { filter: brightness(1.2); box-shadow: 0 0 20px rgba(112,0,255,0.4); }
        .btn-imprimir:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

        /* Tabla de Movimientos */
        .seccion-titulo { color: #fff; font-size: 14px; font-weight: 900; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; letter-spacing: 1px; }
        .seccion-titulo span { width: 10px; height: 10px; background: var(--blue); border-radius: 50%; display: inline-block; box-shadow: 0 0 10px var(--blue); }

        .table-container { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.5); color: var(--text-muted); padding: 15px; font-size: 11px; font-weight: 900; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: bold; color: #fff; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }

        .badge-tipo { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .tipo-carga { background: rgba(0, 255, 136, 0.1); color: var(--green); border: 1px solid rgba(0, 255, 136, 0.3); }
        .tipo-retiro { background: rgba(255, 51, 102, 0.1); color: var(--red); border: 1px solid rgba(255, 51, 102, 0.3); }
        .tipo-neutro { background: rgba(255, 215, 0, 0.1); color: var(--yellow); border: 1px solid rgba(255, 215, 0, 0.3); }

        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); font-size: 13px; font-weight: 900; text-transform: uppercase;}
    </style>
</head>
<body>

    <nav class="topbar">
        <div style="display:flex; align-items:center;">
            <a href="dashboard.php" class="logo">EL <span>POINT</span></a>
            <span class="badge-rol <?php echo ($rol == 'admin') ? 'badge-admin' : 'badge-cajero'; ?>">
                <?php echo strtoupper($rol); ?>
            </span>
        </div>
        <a href="dashboard.php" class="btn-volver">← VOLVER AL PANEL</a>
    </nav>

    <main class="main-content">

        <div class="ticket-cierre" id="ticketImprimible">
            <div class="ticket-header">
                <h2>Resumen de Turno</h2>
                <p>FECHA: <?php echo date('d/m/Y'); ?> | OPERADOR: <?php echo strtoupper($usuario_actual); ?></p>
            </div>

            <div class="fila-monto ingreso">
                <span>[+] Ingresos por Cargas:</span>
                <span class="monto-valor">$<?php echo number_format($cargas_hoy, 2, '.', ','); ?></span>
            </div>

            <div class="fila-monto egreso">
                <span>[-] Retiros Pagados:</span>
                <span class="monto-valor">-$<?php echo number_format($retiros_hoy, 2, '.', ','); ?></span>
            </div>

            <div class="fila-total">
                <span>BALANCE NETO:</span>
                <span class="monto-valor" style="color: <?php echo ($caja_hoy >= 0) ? 'var(--green)' : 'var(--red)'; ?>; text-shadow: 0 0 15px currentColor;">
                    $<?php echo number_format($caja_hoy, 2, '.', ','); ?>
                </span>
            </div>

            <button class="btn-imprimir" id="btnImprimir" onclick="imprimirTicket()">🖨️ Guardar / Imprimir Comprobante</button>
        </div>

        <div class="seccion-titulo"><span></span> Desglose de Operaciones</div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Jugador</th>
                        <th>Operación</th>
                        <th style="text-align:right;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($q_movimientos) > 0): ?>
                        <?php while ($mov = mysqli_fetch_assoc($q_movimientos)): 
                            $accion = strtoupper($mov['accion']);
                            $clase_badge = 'tipo-neutro';
                            $prefijo = '';
                            
                            if (strpos($accion, 'CARGA') !== false) { $clase_badge = 'tipo-carga'; $prefijo = '+'; }
                            elseif (strpos($accion, 'PAGADO') !== false) { $clase_badge = 'tipo-retiro'; $prefijo = '-'; }
                        ?>
                            <tr>
                                <td style="color:var(--text-muted); font-family:'Roboto Mono', monospace; font-size: 11px;">
                                    <?php echo date('H:i', strtotime($mov['fecha'])); ?>
                                </td>
                                <td><?php echo strtoupper($mov['jugador']); ?></td>
                                <td><span class="badge-tipo <?php echo $clase_badge; ?>"><?php echo $accion; ?></span></td>
                                <td style="text-align:right; font-family:'Roboto Mono', monospace; font-size: 14px;">
                                    <?php echo $prefijo . '$' . number_format($mov['monto'], 2, '.', ','); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4"><div class="empty-state">No hay operaciones registradas en este turno.</div></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

<script>
// Motor Anti-Lag para impresión
function imprimirTicket() {
    let btn = document.getElementById('btnImprimir');
    btn.disabled = true;
    btn.innerText = "⏳ GENERANDO...";
    
    // Oculta el botón momentáneamente para que no salga en la hoja impresa
    setTimeout(() => {
        btn.style.display = 'none';
        window.print();
        
        // Lo vuelve a mostrar después de imprimir
        setTimeout(() => {
            btn.style.display = 'block';
            btn.disabled = false;
            btn.innerText = "🖨️ GUARDAR / IMPRIMIR COMPROBANTE";
        }, 1000);
    }, 300);
}
</script>
</body>
</html>