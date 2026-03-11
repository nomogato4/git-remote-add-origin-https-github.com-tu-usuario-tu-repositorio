<?php
session_start();
// RUTA BLINDADA
require_once '../backend/config/conexion.php';

// Si ya está logueado, lo mandamos directo al lobby VIP
if (isset($_SESSION['jugador'])) {
    header("Location: views/player/lobby.php");
    exit;
}

// 1. AUTO-CREACIÓN DE SEGURIDAD
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) PRIMARY KEY, intentos INT DEFAULT 0, ultimo_intento DATETIME)");
mysqli_query($conexion, "DELETE FROM login_attempts WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = ''; $exito = '';
$ip_actual = $_SERVER['REMOTE_ADDR'];
$ref_code = isset($_GET['ref']) ? mysqli_real_escape_string($conexion, trim($_GET['ref'])) : '';
$mostrar_registro = ($ref_code != ''); // Variable para saber qué pestaña abrir

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // BLINDAJE 1: Anti Falsificación
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de seguridad detectado. Refresque la página.");
    }

    $accion = $_POST['accion'];
    
    // BLINDAJE 2: Anti Fuerza Bruta
    $q_intentos = mysqli_query($conexion, "SELECT intentos FROM login_attempts WHERE ip = '$ip_actual'");
    if (mysqli_num_rows($q_intentos) > 0 && mysqli_fetch_assoc($q_intentos)['intentos'] >= 5) {
        $error = "Demasiados intentos. Tu IP está bloqueada por 15 minutos.";
    } else {
        $user = mysqli_real_escape_string($conexion, trim($_POST['username']));
        $pass = trim($_POST['password']);
        
        if ($accion == 'login') {
            $mostrar_registro = false;
            
            if(strlen($user) < 3 || strlen($pass) < 4) {
                $error = "Credenciales inválidas.";
                registrarFalloJugador($conexion, $ip_actual);
            } else {
                $q = mysqli_query($conexion, "SELECT id, password, estado FROM usuarios WHERE username = '$user' AND rol = 'jugador' LIMIT 1");
                if ($q && mysqli_num_rows($q) > 0) {
                    $row = mysqli_fetch_assoc($q);
                    
                    if ($row['estado'] == 0) {
                        $error = "Cuenta suspendida. Contactate con soporte.";
                    } elseif (password_verify($pass, $row['password'])) {
                        // LOGIN EXITOSO
                        mysqli_query($conexion, "DELETE FROM login_attempts WHERE ip = '$ip_actual'");
                        mysqli_query($conexion, "UPDATE usuarios SET last_ip = '$ip_actual', ultimo_acceso = NOW() WHERE id = " . $row['id']);
                        $_SESSION['jugador'] = $user;
                        header("Location: views/player/lobby.php");
                        exit;
                    } else { 
                        $error = "Credenciales incorrectas."; 
                        registrarFalloJugador($conexion, $ip_actual);
                    }
                } else { 
                    $error = "Credenciales incorrectas."; 
                    registrarFalloJugador($conexion, $ip_actual);
                }
            }
        } 
        elseif ($accion == 'registro') {
            $mostrar_registro = true;
            
            // BLINDAJE 3: Validación de usuario limpio
            if(strlen($user) < 4 || strlen($pass) < 6) {
                $error = "El usuario debe tener mínimo 4 letras y la clave 6.";
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $user)) {
                $error = "El usuario solo puede contener letras, números y guión bajo (_).";
            } else {
                $referido_por = mysqli_real_escape_string($conexion, trim($_POST['referido_por']));
                $check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE username = '$user' LIMIT 1");
                
                if ($check && mysqli_num_rows($check) > 0) {
                    $error = "Ese usuario ya existe. Elegí otro.";
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $mi_codigo = strtoupper(substr(md5($user . time()), 0, 8));
                    
                    // Aseguramos que se guarde la fecha de registro si tenés la columna
                    $sql = "INSERT INTO usuarios (username, password, rol, estado, last_ip, ip_registro, codigo_referido, referido_por, saldo, bono) 
                            VALUES ('$user', '$hash', 'jugador', 1, '$ip_actual', '$ip_actual', '$mi_codigo', '$referido_por', 0, 0)";
                    
                    if (mysqli_query($conexion, $sql)) { 
                        $exito = "¡Cuenta creada! Ya podés ingresar al salón."; 
                        $mostrar_registro = false; // Lo mandamos a la pestaña de login automáticamente
                    } 
                    else { $error = "Error de red al crear la cuenta. Intente nuevamente."; }
                }
            }
        }
    }
}

function registrarFalloJugador($conexion, $ip) {
    mysqli_query($conexion, "INSERT INTO login_attempts (ip, intentos, ultimo_intento) VALUES ('$ip', 1, NOW()) ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>EL POINT | Casino VIP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --green: #00ff88; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background-image: radial-gradient(circle at 50% 0%, rgba(0,255,136,0.05) 0%, transparent 60%);}
        
        .auth-container { background: var(--panel); border: 1px solid var(--border); padding: 40px 30px; border-radius: 16px; width: 90%; max-width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative; overflow: hidden;}
        .auth-container::before { content: ''; position: absolute; top: -1px; left: -1px; right: -1px; height: 3px; background: linear-gradient(90deg, transparent, var(--green), transparent); border-radius: 16px 16px 0 0; }

        .logo { text-align: center; font-size: 32px; font-weight: 900; margin-bottom: 25px; text-transform: uppercase; letter-spacing: -1px; }
        .logo span { color: var(--green); text-shadow: 0 0 15px rgba(0,255,136,0.4); }
        
        .tabs { display: flex; background: #000; border-radius: 8px; margin-bottom: 25px; border: 1px solid var(--border); padding: 4px; }
        .tab { flex: 1; text-align: center; padding: 12px; font-size: 12px; font-weight: 900; cursor: pointer; border-radius: 6px; color: var(--text-muted); transition: 0.3s; text-transform: uppercase; }
        .tab.active { background: var(--blue); color: #fff; box-shadow: 0 5px 15px rgba(112,0,255,0.3); }
        
        .input-dark { width: 100%; background: #000; border: 1px solid var(--border); padding: 15px; border-radius: 8px; color: #fff; font-size: 14px; margin-bottom:15px; outline: none; transition: 0.3s; font-family: 'Roboto Mono', monospace;}
        .input-dark:focus { border-color: var(--blue); box-shadow: 0 0 10px rgba(112,0,255,0.2); }
        
        .btn-submit { width: 100%; background: var(--blue); color: #fff; border: none; padding: 16px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 14px; cursor: pointer; transition: 0.3s; letter-spacing: 1px;}
        .btn-submit:hover { filter: brightness(1.2); box-shadow: 0 0 20px rgba(112,0,255,0.4);}
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }
        
        .alert { padding: 15px; border-radius: 8px; font-size: 13px; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .alert-error { background: rgba(255,51,102,0.1); color: var(--red); border: 1px solid var(--red); }
        .alert-success { background: rgba(0,255,136,0.1); color: var(--green); border: 1px solid var(--green); }
        
        #form-registro { display: <?php echo $mostrar_registro ? 'block' : 'none'; ?>; }
        #form-login { display: <?php echo $mostrar_registro ? 'none' : 'block'; ?>; }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="logo">EL <span>POINT</span></div>
    
    <?php if ($error != '') echo "<div class='alert alert-error'>⚠️ $error</div>"; ?>
    <?php if ($exito != '') echo "<div class='alert alert-success'>✅ $exito</div>"; ?>
    
    <div class="tabs">
        <div class="tab <?php echo !$mostrar_registro ? 'active' : ''; ?>" id="tab-login" onclick="switchTab('login')">Ingresar</div>
        <div class="tab <?php echo $mostrar_registro ? 'active' : ''; ?>" id="tab-registro" onclick="switchTab('registro')">Crear Cuenta</div>
    </div>
    
    <form id="form-login" method="POST" onsubmit="bloquearBoton('btn-login')">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="accion" value="login">
        
        <input type="text" name="username" class="input-dark" placeholder="Usuario" required autocomplete="off">
        <input type="password" name="password" class="input-dark" placeholder="Contraseña" required>
        
        <button type="submit" id="btn-login" class="btn-submit">ENTRAR AL SALÓN</button>
    </form>
    
    <form id="form-registro" method="POST" onsubmit="bloquearBoton('btn-registro')">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="accion" value="registro">
        
        <input type="text" name="username" class="input-dark" placeholder="Usuario (Mín 4 letras)" required autocomplete="off" minlength="4" pattern="^[a-zA-Z0-9_]+$">
        <input type="password" name="password" class="input-dark" placeholder="Contraseña (Mín 6)" required minlength="6">
        <input type="text" name="referido_por" class="input-dark" placeholder="Código de Invitado (Opcional)" value="<?php echo $ref_code; ?>" autocomplete="off">
        
        <button type="submit" id="btn-registro" class="btn-submit" style="background:var(--green); color:#000;">REGISTRARSE</button>
    </form>
</div>

<script>
function switchTab(tab) {
    document.getElementById('tab-login').classList.remove('active'); 
    document.getElementById('tab-registro').classList.remove('active');
    document.getElementById('form-login').style.display = 'none'; 
    document.getElementById('form-registro').style.display = 'none';
    
    document.getElementById('tab-' + tab).classList.add('active'); 
    document.getElementById('form-' + tab).style.display = 'block';
}

// 🔥 FUNCIÓN ANTI-LAG
function bloquearBoton(btnId) {
    let btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.innerText = "PROCESANDO...";
}
</script>
</body>
</html>