<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

// 🛡️ SEGURIDAD PATOVICA: Solo el Dueño (Admin) entra acá
if (!isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

$admin_user = $_SESSION['admin'];

// ==========================================
// MOTOR AJAX: RENDERIZAR CUADRÍCULA (ANTI-LAG)
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $resultado = mysqli_query($conexion, "SELECT * FROM usuarios WHERE rol = 'cajero' ORDER BY username ASC");
    $html = "";
    
    while($row = mysqli_fetch_assoc($resultado)) {
        $offline = (strtotime($row['ultimo_acceso']) < strtotime('-5 minutes'));
        $dot_color = $offline ? '#555' : 'var(--green)';
        $status_text = $offline ? 'Desconectado' : 'En línea';
        $opacidad = $row['estado'] ? '1' : '0.5';
        $btn_estado = $row['estado'] ? 'Suspender' : 'Activar';
        $clase_btn = $row['estado'] ? 'btn-red' : 'btn-green';
        $fecha_conn = $row['ultimo_acceso'] ? date('H:i d/m', strtotime($row['ultimo_acceso'])) : 'Nunca';
        $saldo_formateado = number_format($row['saldo_cajero'], 2, '.', ',');

        $html .= "
        <div class='card-cajero' style='opacity: $opacidad;'>
            <div class='c-header'>
                <div class='c-name'>" . strtoupper($row['username']) . "</div>
                <div style='font-size: 10px; font-weight: 900; color: $dot_color;'>
                    <span class='status-dot' style='background: $dot_color;'></span> $status_text
                </div>
            </div>

            <div class='stock-box'>
                <span class='stock-label'>Stock de Fichas</span>
                <span class='stock-valor'>$$saldo_formateado</span>
            </div>

            <div class='info-row'>
                <span>Última conexión:</span>
                <strong>$fecha_conn</strong>
            </div>

            <div class='form-inline'>
                <input type='number' id='monto_{$row['id']}' class='input-mini' placeholder='Monto stock'>
                <button id='btnCargar_{$row['id']}' class='btn btn-green' onclick='cargarStock({$row['id']}, \"{$row['username']}\")'>Cargar</button>
            </div>

            <div class='acciones-cajero'>
                <button class='btn btn-outline' style='width:100%' onclick='resetPass({$row['id']})'>Reset Clave</button>
                <button class='btn $clase_btn' style='width:100%' onclick='toggleEstado({$row['id']})'>$btn_estado</button>
            </div>
        </div>";
    }
    echo $html;
    exit;
}

// ==========================================
// MOTOR AJAX: ACCIONES (CREAR, CARGAR, RESET)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];
    $id_cajero = intval($_POST['id_cajero'] ?? 0);

    if ($accion == 'crear_cajero') {
        $user = mysqli_real_escape_string($conexion, trim($_POST['username']));
        $pass = trim($_POST['password']);
        
        if(strlen($user) < 3 || strlen($pass) < 4) { echo json_encode(['error' => 'Datos muy cortos.']); exit; }
        
        // Verificamos que no exista
        $check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE username = '$user'");
        if(mysqli_num_rows($check) > 0) { echo json_encode(['error' => 'El usuario ya existe.']); exit; }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        mysqli_query($conexion, "INSERT INTO usuarios (username, password, rol, estado, saldo_cajero) VALUES ('$user', '$hash', 'cajero', 1, 0)");
        echo json_encode(['status' => 'ok', 'msg' => 'Cajero creado con éxito.']);
        exit;
    }

    if ($accion == 'cargar_stock') {
        $monto = floatval($_POST['monto']);
        $username_cajero = mysqli_real_escape_string($conexion, $_POST['username_cajero']);
        
        if ($monto > 0) {
            mysqli_query($conexion, "UPDATE usuarios SET saldo_cajero = saldo_cajero + $monto WHERE id = $id_cajero AND rol = 'cajero'");
            // Guardamos en la tabla auditoria para que el dueño lo vea en Finanzas
            mysqli_query($conexion, "INSERT INTO auditoria (cajero, jugador, accion, monto, fecha) VALUES ('$admin_user', 'STAFF: $username_cajero', 'CARGA STOCK', $monto, NOW())");
            echo json_encode(['status' => 'ok', 'msg' => "Stock cargado correctamente."]);
        } else {
            echo json_encode(['error' => 'Monto inválido.']);
        }
        exit;
    }

    if ($accion == 'reset_pass') {
        $nueva_pass = password_hash("123456", PASSWORD_DEFAULT);
        mysqli_query($conexion, "UPDATE usuarios SET password = '$nueva_pass' WHERE id = $id_cajero AND rol = 'cajero'");
        echo json_encode(['status' => 'ok', 'msg' => 'Clave reseteada a: 123456']);
        exit;
    }

    if ($accion == 'toggle_estado') {
        mysqli_query($conexion, "UPDATE usuarios SET estado = NOT estado WHERE id = $id_cajero AND rol = 'cajero'");
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
    <title>Gestión de Staff | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 40px; }
        
        .topbar { background: var(--panel); border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top:0; z-index: 100;}
        .logo { font-weight: 900; font-size: 20px; text-transform: uppercase; color: #fff; text-decoration: none;}
        .logo span { color: var(--blue); }
        .btn-volver { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; text-decoration: none; transition: 0.3s;}
        .btn-volver:hover { background: var(--border); color: #fff; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .header-seccion { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .titulo { font-size: 24px; font-weight: 900; text-transform: uppercase; }

        .btn { padding: 12px 20px; border-radius: 8px; border: none; font-weight: 900; cursor: pointer; text-transform: uppercase; font-size: 12px; transition: 0.3s;}
        .btn-blue { background: var(--blue); color: #fff; }
        .btn-blue:hover { box-shadow: 0 0 15px rgba(112,0,255,0.4); }
        .btn-green { background: var(--green); color: #000; }
        .btn-green:hover { box-shadow: 0 0 15px rgba(0,255,136,0.4); }
        .btn-red { background: rgba(255,51,102,0.1); color: var(--red); border: 1px solid var(--red); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
        .btn-outline:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

        .grid-cajeros { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .card-cajero { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 25px; transition: 0.3s; position: relative;}
        .card-cajero:hover { border-color: var(--blue); transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        .c-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .c-name { font-weight: 900; font-size: 18px; letter-spacing: -0.5px;}
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; box-shadow: 0 0 8px currentColor;}

        .stock-box { background: #000; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed var(--border); text-align: center;}
        .stock-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; font-weight: 900; letter-spacing: 1px;}
        .stock-valor { font-family: 'Roboto Mono', monospace; font-size: 28px; font-weight: 900; color: var(--green); display: block; text-shadow: 0 0 15px rgba(0,255,136,0.3);}

        .info-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 15px; color: var(--text-muted); }
        .info-row strong { color: #fff; font-family: 'Roboto Mono';}

        .acciones-cajero { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        
        .form-inline { display: flex; gap: 10px; margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px; }
        .input-mini { flex: 1; background: #000; border: 1px solid var(--border); color: #fff; padding: 12px; border-radius: 8px; outline: none; font-size: 14px; font-family: 'Roboto Mono'; transition: 0.3s;}
        .input-mini:focus { border-color: var(--green); }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px);}
        .modal-caja { background: var(--panel); border: 1px solid var(--blue); width: 90%; max-width: 400px; border-radius: 16px; padding: 30px; box-shadow: 0 0 30px rgba(112,0,255,0.2);}
    </style>
</head>
<body>

<nav class="topbar">
    <a href="dashboard.php" class="logo">EL <span>POINT</span></a>
    <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
</nav>

<div class="container">
    <div class="header-seccion">
        <div class="titulo">👥 Control de Staff</div>
        <button class="btn btn-blue" onclick="document.getElementById('modalCrear').style.display='flex'">+ Nuevo Cajero</button>
    </div>

    <div class="grid-cajeros" id="contenedorCajeros">
        <div style="text-align:center; padding:50px; color:var(--text-muted); grid-column: 1 / -1;">Cargando datos del staff...</div>
    </div>
</div>

<div class="modal-overlay" id="modalCrear">
    <div class="modal-caja">
        <h2 style="margin-top:0; font-weight:900; text-transform:uppercase;">Nuevo Cajero</h2>
        <div style="display:flex; flex-direction:column; gap:15px;">
            <input type="text" id="nuevoUser" class="input-mini" placeholder="Nombre de usuario" autocomplete="off">
            <input type="password" id="nuevaPass" class="input-mini" placeholder="Contraseña">
            <button id="btnCrear" class="btn btn-blue" onclick="crearCajero()">Crear Acceso</button>
            <button class="btn btn-outline" onclick="document.getElementById('modalCrear').style.display='none'">Cancelar</button>
        </div>
    </div>
</div>

<script>
// 1. CARGAR LISTA EN VIVO
function cargarLista() {
    fetch('cajeros.php?ajax=1')
    .then(r => r.text())
    .then(html => {
        document.getElementById('contenedorCajeros').innerHTML = html;
    });
}

// 2. CREAR CAJERO
function crearCajero() {
    let user = document.getElementById('nuevoUser').value;
    let pass = document.getElementById('nuevaPass').value;
    let btn = document.getElementById('btnCrear');

    if(user.length < 3 || pass.length < 4) { alert("Datos muy cortos."); return; }

    btn.disabled = true; btn.innerText = "⏳";
    
    let fd = new FormData();
    fd.append('accion', 'crear_cajero');
    fd.append('username', user);
    fd.append('password', pass);

    fetch('cajeros.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.innerText = "CREAR ACCESO";
        if(data.error) { alert("❌ " + data.error); } 
        else {
            document.getElementById('nuevoUser').value = '';
            document.getElementById('nuevaPass').value = '';
            document.getElementById('modalCrear').style.display = 'none';
            alert("✅ " + data.msg);
            cargarLista();
        }
    });
}

// 3. CARGAR STOCK (CON BLOQUEO ANTI-LAG)
function cargarStock(id, username) {
    let input = document.getElementById('monto_' + id);
    let monto = input.value;
    let btn = document.getElementById('btnCargar_' + id);

    if (monto === '' || monto <= 0) { alert("Monto inválido."); return; }
    if (!confirm(`¿Cargar $${monto} a la caja de ${username.toUpperCase()}?`)) return;

    // Bloqueamos botón para evitar doble carga
    btn.disabled = true; btn.innerText = "⏳";

    let fd = new FormData();
    fd.append('accion', 'cargar_stock');
    fd.append('id_cajero', id);
    fd.append('username_cajero', username);
    fd.append('monto', monto);

    fetch('cajeros.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.error) { alert("❌ " + data.error); btn.disabled = false; btn.innerText = "CARGAR"; } 
        else { alert("✅ " + data.msg); cargarLista(); }
    });
}

// 4. BISTURÍ: RESET Y ESTADO
function resetPass(id) {
    if(!confirm("¿Resetear la clave a '123456'?")) return;
    let fd = new FormData(); fd.append('accion', 'reset_pass'); fd.append('id_cajero', id);
    fetch('cajeros.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => { alert("✅ " + d.msg); cargarLista(); });
}

function toggleEstado(id) {
    let fd = new FormData(); fd.append('accion', 'toggle_estado'); fd.append('id_cajero', id);
    fetch('cajeros.php', { method: 'POST', body: fd }).then(() => cargarLista());
}

// Arranca cargando la grilla
cargarLista();
</script>
</body>
</html>