<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['cajero'])) {
    header("Location: auth.php");
    exit;
}

$es_admin = isset($_SESSION['admin']);

// ==========================================
// MOTOR AJAX: BUSCADOR EN VIVO (ANTI-LAG)
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $busqueda = mysqli_real_escape_string($conexion, $_GET['buscar'] ?? '');
    $where = "WHERE rol = 'jugador'";
    if ($busqueda != '') {
        $where .= " AND username LIKE '%$busqueda%'";
    }
    
    // Traemos los últimos 50 para no saturar
    $query = "SELECT id, username, saldo, estado, last_ip FROM usuarios $where ORDER BY id DESC LIMIT 50";
    $res = mysqli_query($conexion, $query);
    
    $html = "";
    if ($res && mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $estado_badge = $row['estado'] == 1 ? '<span class="badge-verde">ACTIVO</span>' : '<span class="badge-rojo">BANEADO</span>';
            $btn_estado = $row['estado'] == 1 
                ? "<button onclick='cambiarEstado({$row['id']}, 0)' class='btn-chico btn-rojo'>Banear</button>" 
                : "<button onclick='cambiarEstado({$row['id']}, 1)' class='btn-chico btn-verde'>Desbanear</button>";
            
            $html .= "<tr class='fila-jugador'>";
            $html .= "<td style='font-weight:900;'>" . strtoupper($row['username']) . "</td>";
            $html .= "<td style='font-family:\"Roboto Mono\"; color:var(--green); font-weight:bold;'>$" . number_format($row['saldo'], 2) . "</td>";
            $html .= "<td>" . $estado_badge . "</td>";
            $html .= "<td><span style='font-size:11px; color:var(--text-muted);'>" . $row['last_ip'] . "</span></td>";
            $html .= "<td style='text-align:right;'>
                        <button onclick='abrirModalPass({$row['id']}, \"{$row['username']}\")' class='btn-chico btn-azul'>🔑 Clave</button>
                        $btn_estado
                      </td>";
            $html .= "</tr>";
        }
    } else {
        $html = "<tr><td colspan='5' style='text-align:center; padding:20px; color:var(--text-muted);'>No se encontraron jugadores.</td></tr>";
    }
    echo $html;
    exit;
}

// ==========================================
// MOTOR AJAX: ACCIONES (BISTURÍ)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];
    $id_user = intval($_POST['id_usuario']);
    
    // BLINDAJE: Nos aseguramos de que solo afecte a JUGADORES (nadie le puede cambiar la clave al Admin)
    $q_check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE id = $id_user AND rol = 'jugador'");
    if(mysqli_num_rows($q_check) == 0) { echo json_encode(['error' => 'Usuario inválido.']); exit; }

    if ($accion == 'cambiar_pass') {
        $nueva_pass = trim($_POST['nueva_pass']);
        if(strlen($nueva_pass) < 4) { echo json_encode(['error' => 'La clave debe tener mínimo 4 caracteres.']); exit; }
        
        $hash = password_hash($nueva_pass, PASSWORD_DEFAULT);
        mysqli_query($conexion, "UPDATE usuarios SET password = '$hash' WHERE id = $id_user");
        echo json_encode(['status' => 'ok', 'msg' => 'Contraseña actualizada.']);
        exit;
    }
    
    if ($accion == 'cambiar_estado') {
        $nuevo_estado = intval($_POST['estado']);
        mysqli_query($conexion, "UPDATE usuarios SET estado = $nuevo_estado WHERE id = $id_user");
        echo json_encode(['status' => 'ok']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Jugadores | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 40px;}
        
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;}
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; color: #fff; text-decoration: none;}
        .logo span { color: var(--blue); }
        .btn-volver { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; text-decoration: none; transition: 0.3s;}
        .btn-volver:hover { background: var(--border); color: #fff; }

        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
        .panel-busqueda { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; display: flex; gap: 10px;}
        .input-buscar { flex: 1; background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-size: 14px; outline: none; transition: 0.3s;}
        .input-buscar:focus { border-color: var(--blue); }
        
        table { width: 100%; border-collapse: collapse; background: var(--panel); border-radius: 12px; overflow: hidden; border: 1px solid var(--border);}
        th { background: rgba(0,0,0,0.5); padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 900; border-bottom: 1px solid var(--border);}
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle;}
        .fila-jugador:hover { background: rgba(255,255,255,0.02); }
        
        .badge-verde { background: rgba(0,255,136,0.1); color: var(--green); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 900; border: 1px solid var(--green);}
        .badge-rojo { background: rgba(255,51,102,0.1); color: var(--red); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 900; border: 1px solid var(--red);}
        
        .btn-chico { border: none; padding: 6px 12px; border-radius: 6px; font-weight: 900; font-size: 11px; cursor: pointer; text-transform: uppercase; transition: 0.2s;}
        .btn-azul { background: transparent; border: 1px solid var(--blue); color: var(--blue); }
        .btn-azul:hover { background: var(--blue); color: #fff; }
        .btn-rojo { background: transparent; border: 1px solid var(--red); color: var(--red); }
        .btn-rojo:hover { background: var(--red); color: #fff; }
        .btn-verde { background: transparent; border: 1px solid var(--green); color: var(--green); }
        .btn-verde:hover { background: var(--green); color: #000; }

        /* MODAL BISTURÍ */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px);}
        .modal-caja { background: var(--panel); border: 1px solid var(--blue); width: 90%; max-width: 400px; border-radius: 16px; padding: 30px; box-shadow: 0 0 30px rgba(112,0,255,0.2);}
        .modal-titulo { font-weight: 900; font-size: 18px; margin-bottom: 20px; text-transform: uppercase;}
        .input-modal { width: 100%; background: #000; border: 1px solid var(--border); color: #fff; padding: 15px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; outline: none;}
        .modal-botones { display: flex; gap: 10px; justify-content: flex-end;}
        .btn-modal-ok { background: var(--blue); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 900; cursor: pointer;}
        .btn-modal-cancel { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 10px 20px; border-radius: 8px; font-weight: 900; cursor: pointer;}
    </style>
</head>
<body>

<nav class="topbar">
    <a href="dashboard.php" class="logo">EL <span>POINT</span></a>
    <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
</nav>

<div class="container">
    <div style="font-size: 24px; font-weight: 900; margin-bottom: 20px; text-transform: uppercase;">🕵️ Control de Jugadores</div>
    
    <div class="panel-busqueda">
        <input type="text" id="buscador" class="input-buscar" placeholder="Buscar por nombre de usuario..." onkeyup="cargarJugadores()">
    </div>

    <table>
        <thead>
            <tr>
                <th>Jugador</th>
                <th>Saldo Actual</th>
                <th>Estado</th>
                <th>Última IP</th>
                <th style="text-align:right;">Acciones (Bisturí)</th>
            </tr>
        </thead>
        <tbody id="tablaJugadores">
            <tr><td colspan="5" style="text-align:center; padding:20px;">Cargando jugadores...</td></tr>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modalPass">
    <div class="modal-caja">
        <div class="modal-titulo">🔑 Nueva Clave para: <span id="lblJugador" style="color:var(--blue);"></span></div>
        <input type="hidden" id="idJugadorPass">
        <input type="text" id="nuevaPass" class="input-modal" placeholder="Escribí la nueva contraseña...">
        <div class="modal-botones">
            <button class="btn-modal-cancel" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-modal-ok" onclick="guardarNuevaPass()">Guardar Clave</button>
        </div>
    </div>
</div>

<script>
// 1. CARGA Y BUSCADOR EN VIVO
function cargarJugadores() {
    let buscar = document.getElementById('buscador').value;
    fetch(`jugadores.php?ajax=1&buscar=${buscar}`)
    .then(r => r.text())
    .then(html => {
        document.getElementById('tablaJugadores').innerHTML = html;
    });
}

// 2. BANEAR / DESBANEAR
function cambiarEstado(id, nuevoEstado) {
    let accionTxt = nuevoEstado === 0 ? "BANEAR" : "DESBANEAR";
    if(!confirm(`¿Seguro que querés ${accionTxt} a este jugador?`)) return;

    let fd = new FormData();
    fd.append('accion', 'cambiar_estado');
    fd.append('id_usuario', id);
    fd.append('estado', nuevoEstado);

    fetch('jugadores.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'ok') cargarJugadores();
    });
}

// 3. CAMBIAR CONTRASEÑA (BISTURÍ)
function abrirModalPass(id, username) {
    document.getElementById('idJugadorPass').value = id;
    document.getElementById('lblJugador').innerText = username.toUpperCase();
    document.getElementById('nuevaPass').value = '';
    document.getElementById('modalPass').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalPass').style.display = 'none';
}

function guardarNuevaPass() {
    let id = document.getElementById('idJugadorPass').value;
    let pass = document.getElementById('nuevaPass').value;

    if(pass.length < 4) { alert("La clave debe tener al menos 4 caracteres."); return; }

    let fd = new FormData();
    fd.append('accion', 'cambiar_pass');
    fd.append('id_usuario', id);
    fd.append('nueva_pass', pass);

    fetch('jugadores.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.error) {
            alert("❌ " + data.error);
        } else {
            alert("✅ " + data.msg);
            cerrarModal();
        }
    });
}

// Cargar la lista apenas entrás a la página
cargarJugadores();
</script>
</body>
</html>