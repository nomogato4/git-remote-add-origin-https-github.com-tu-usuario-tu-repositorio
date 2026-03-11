<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

// Patovica: Solo jugadores logueados
if (!isset($_SESSION['jugador'])) {
    header("Location: ../../index.php");
    exit;
}

$mi_usuario = $_SESSION['jugador'];

// 1. Buscamos el código único y la plata ganada por el jugador
$q_datos = mysqli_query($conexion, "SELECT codigo_referido, bono FROM usuarios WHERE username = '$mi_usuario' AND rol = 'jugador'");
$datos = mysqli_fetch_assoc($q_datos);

$mi_codigo = $datos['codigo_referido'] ?? 'ERROR';
$mi_bono = $datos['bono'] ?? 0;

// 2. Buscamos cuánta gente trajo este jugador al casino
$q_referidos = mysqli_query($conexion, "SELECT username, fecha_registro FROM usuarios WHERE referido_por = '$mi_codigo' ORDER BY id DESC LIMIT 50");
$total_invitados = mysqli_num_rows($q_referidos);

// 3. Armamos el Link de Invitación (Detecta tu dominio automáticamente)
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
// Ajustá "/public/index.php" si tu index está en otra ruta
$link_invitacion = $protocolo . "://" . $dominio . "/public/index.php?ref=" . $mi_codigo;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mis Referidos | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --yellow: #ffd700; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 40px; }
        
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top:0; z-index: 100;}
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; color: #fff; text-decoration: none; letter-spacing: -1px;}
        .logo span { color: var(--green); text-shadow: 0 0 15px rgba(0,255,136,0.4); }
        .btn-volver { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; text-decoration: none; transition: 0.3s;}
        .btn-volver:hover { background: var(--border); color: #fff; }

        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        
        .header-seccion { text-align: center; margin-bottom: 30px; }
        .titulo { font-size: 28px; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; letter-spacing: -1px;}
        .subtitulo { color: var(--text-muted); font-size: 14px; }

        /* LINK DE INVITACIÓN */
        .caja-link { background: var(--panel); border: 1px dashed var(--blue); padding: 25px; border-radius: 16px; text-align: center; margin-bottom: 30px; position: relative; overflow: hidden;}
        .caja-link::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--blue); box-shadow: 0 0 15px var(--blue); }
        .label-link { font-size: 11px; font-weight: 900; color: var(--blue); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px; display: block;}
        .input-link { width: 100%; background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-family: 'Roboto Mono', monospace; font-size: 13px; text-align: center; margin-bottom: 15px; outline: none; user-select: all;}
        
        .btn-copiar { background: var(--blue); color: #fff; border: none; padding: 15px 30px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 14px; cursor: pointer; transition: 0.3s; letter-spacing: 1px; width: 100%;}
        .btn-copiar:hover { filter: brightness(1.2); box-shadow: 0 0 20px rgba(112,0,255,0.4); }
        .btn-copiar.copiado { background: var(--green); color: #000; box-shadow: 0 0 20px rgba(0,255,136,0.4); }

        /* MÉTRICAS (KPIs) */
        .grid-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .kpi-card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-align: center; }
        .kpi-title { font-size: 11px; font-weight: 900; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px;}
        .kpi-valor { font-family: 'Roboto Mono', monospace; font-size: 28px; font-weight: 900; }
        .valor-amigos { color: #fff; }
        .valor-plata { color: var(--green); text-shadow: 0 0 15px rgba(0,255,136,0.3); }

        /* TABLA DE AMIGOS */
        .tabla-amigos { width: 100%; background: var(--panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; border-collapse: collapse; }
        .tabla-amigos th { background: rgba(0,0,0,0.5); padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 900; border-bottom: 1px solid var(--border);}
        .tabla-amigos td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 13px; color: #fff; font-weight: bold;}
        .tabla-amigos tr:last-child td { border-bottom: none; }
        .empty-state { text-align: center; padding: 30px; color: var(--text-muted); font-size: 13px; font-weight: 900; text-transform: uppercase; }
        
        /* Enmascarar nombres para privacidad */
        .nombre-mask { font-family: 'Roboto Mono', monospace; color: var(--blue); }
    </style>
</head>
<body>

<nav class="topbar">
    <a href="lobby.php" class="logo">EL <span>POINT</span></a>
    <a href="lobby.php" class="btn-volver">← LOBBY</a>
</nav>

<div class="container">
    <div class="header-seccion">
        <div class="titulo">🤝 Programa VIP</div>
        <div class="subtitulo">Invitá amigos y ganá saldo extra cuando realicen su primera carga.</div>
    </div>

    <div class="caja-link">
        <span class="label-link">Tu Enlace de Invitación Exclusivo</span>
        <input type="text" id="linkReferido" class="input-link" value="<?php echo $link_invitacion; ?>" readonly>
        <button id="btnCopiar" class="btn-copiar" onclick="copiarLink()">📋 COPIAR ENLACE</button>
    </div>

    <div class="grid-kpi">
        <div class="kpi-card">
            <div class="kpi-title">Amigos Invitados</div>
            <div class="kpi-valor valor-amigos"><?php echo $total_invitados; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Bono Generado</div>
            <div class="kpi-valor valor-plata">$<?php echo number_format($mi_bono, 2, '.', ','); ?></div>
        </div>
    </div>

    <div style="font-size: 14px; font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 1px;">
        👥 Tu Red de Jugadores
    </div>

    <table class="tabla-amigos">
        <thead>
            <tr>
                <th>Jugador</th>
                <th style="text-align: right;">Fecha de Registro</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($total_invitados > 0): ?>
                <?php while ($amigo = mysqli_fetch_assoc($q_referidos)): 
                    // Enmascaramos el nombre (Ej: Martin -> Mar***)
                    $nombre_real = $amigo['username'];
                    $nombre_oculto = substr($nombre_real, 0, 3) . '***';
                    $fecha_reg = date('d/m/Y - H:i', strtotime($amigo['fecha_registro'] ?? 'now'));
                ?>
                <tr>
                    <td class="nombre-mask"><?php echo strtoupper($nombre_oculto); ?></td>
                    <td style="text-align: right; color: var(--text-muted); font-size: 11px; font-family: 'Roboto Mono';">
                        <?php echo $fecha_reg; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2" class="empty-state">
                        Aún no invitaste a nadie. ¡Compartí tu enlace para empezar a ganar!
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Motor UX Anti-Lag para copiar al portapapeles
function copiarLink() {
    let input = document.getElementById('linkReferido');
    let btn = document.getElementById('btnCopiar');

    // Selecciona y copia
    input.select();
    input.setSelectionRange(0, 99999); // Para celulares
    navigator.clipboard.writeText(input.value).then(() => {
        // Efecto visual de éxito
        btn.innerText = "¡COPIADO! ✅";
        btn.classList.add('copiado');

        // Vuelve a la normalidad después de 2 segundos
        setTimeout(() => {
            btn.innerText = "📋 COPIAR ENLACE";
            btn.classList.remove('copiado');
        }, 2000);
    }).catch(err => {
        alert("Tu navegador no permite copiar automáticamente. Copialo manualmente.");
    });
}
</script>
</body>
</html>