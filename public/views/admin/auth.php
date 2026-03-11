<?php
session_start();
// RUTA BLINDADA
require_once '../../../backend/config/conexion.php';

if (isset($_SESSION['admin']) || isset($_SESSION['cajero'])) {
    header("Location: dashboard.php");
    exit;
}

// 1. AUTO-CREACIÓN DE TABLAS DE SEGURIDAD (Si no existen)
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) PRIMARY KEY, intentos INT DEFAULT 0, ultimo_intento DATETIME)");
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS acceso_logs (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50), ip VARCHAR(45), estado VARCHAR(20), navegador TEXT, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)");

$check = mysqli_query($conexion, "SHOW COLUMNS FROM usuarios LIKE 'remember_token'");
if (mysqli_num_rows($check) == 0) mysqli_query($conexion, "ALTER TABLE usuarios ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL");

// 2. LIMPIEZA AUTOMÁTICA DE BLOQUEOS (Desbloquea IP después de 15 min)
mysqli_query($conexion, "DELETE FROM login_attempts WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

// 3. GENERACIÓN TOKEN CSRF (Anti falsificación)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$ip_actual = $_SERVER['REMOTE_ADDR'];
$user_agent = mysqli_real_escape_string($conexion, $_SERVER['HTTP_USER_AGENT']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificación CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de seguridad detectado. Refresque la página.");
    }

    // Comprobamos si la IP está bloqueada por Fuerza Bruta
    $q_intentos = mysqli_query($conexion, "SELECT intentos FROM login_attempts WHERE ip = '$ip_actual'");
    if (mysqli_num_rows($q_intentos) > 0 && mysqli_fetch_assoc($q_intentos)['intentos'] >= 3) {
        $error = "Demasiados intentos. Tu IP está bloqueada por 15 minutos.";
        mysqli_query($conexion, "INSERT INTO acceso_logs (username, ip, estado, navegador) VALUES ('BLOQUEADO', '$ip_actual', 'FUERZA_BRUTA', '$user_agent')");
    } else {
        $user = mysqli_real_escape_string($conexion, trim($_POST['username']));
        $pass = trim($_POST['password']);
        
        $query = mysqli_query($conexion, "SELECT id, password, rol, estado FROM usuarios WHERE username = '$user' AND (rol = 'admin' OR rol = 'cajero') LIMIT 1");
        
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            
            if ($row['estado'] == 0) {
                $error = "Tu cuenta está suspendida. Contactá al administrador.";
                mysqli_query($conexion, "INSERT INTO acceso_logs (username, ip, estado, navegador) VALUES ('$user', '$ip_actual', 'INTENTO_SUSPENDIDO', '$user_agent')");
            } elseif (password_verify($pass, $row['password'])) {
                
                // LOGIN EXITOSO: Limpiamos intentos fallidos y creamos token
                mysqli_query($conexion, "DELETE FROM login_attempts WHERE ip = '$ip_actual'");
                $token_sesion = bin2hex(random_bytes(16));
                mysqli_query($conexion, "UPDATE usuarios SET token_sesion = '$token_sesion', ultimo_acceso = NOW(), last_ip = '$ip_actual' WHERE id = " . $row['id']);
                
                // Creamos sesiones
                $_SESSION[$row['rol']] = $user;
                $_SESSION['token_sesion'] = $token_sesion;
                $_SESSION['bind_ip'] = $ip_actual;
                $_SESSION['bind_ua'] = $user_agent;
                $_SESSION['login_time'] = time();
                
                // Sistema "Recordarme" (Cookie segura por 7 días)
                if (isset($_POST['recordarme'])) {
                    $remember = bin2hex(random_bytes(32));
                    mysqli_query($conexion, "UPDATE usuarios SET remember_token = '$remember' WHERE id = " . $row['id']);
                    setcookie('elpoint_staff_rem', $remember, time() + (7 * 24 * 3600), "/", "", false, true);
                }
                
                mysqli_query($conexion, "INSERT INTO acceso_logs (username, ip, estado, navegador) VALUES ('$user', '$ip_actual', 'EXITO', '$user_agent')");
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Credenciales incorrectas.";
                registrarFallo($conexion, $ip_actual, $user, $user_agent);
            }
        } else {
            $error = "Credenciales incorrectas.";
            registrarFallo($conexion, $ip_actual, $user, $user_agent);
        }
    }
}

function registrarFallo($conexion, $ip, $user, $ua) {
    mysqli_query($conexion, "INSERT INTO login_attempts (ip, intentos, ultimo_intento) VALUES ('$ip', 1, NOW()) ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()");
    mysqli_query($conexion, "INSERT INTO acceso_logs (username, ip, estado, navegador) VALUES ('$user', '$ip', 'FALLO', '$ua')");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Staff | EL POINT</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #030304; --panel: #0a0c10; --border: #1a1e26; --blue: #7000ff; --red: #ff3366; --text: #ffffff; --text-muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; background-image: radial-gradient(circle at 50% 0%, rgba(112,0,255,0.1) 0%, transparent 50%); }
        
        .login-box { background: var(--panel); border: 1px solid var(--border); padding: 40px; border-radius: 16px; width: 100%; max-width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); position: relative; z-index: 2; }
        .login-box::before { content: ''; position: absolute; top: -1px; left: -1px; right: -1px; height: 4px; background: linear-gradient(90deg, transparent, var(--blue), transparent); border-radius: 16px 16px 0 0; }
        
        .logo { text-align: center; font-size: 28px; font-weight: 900; margin-bottom: 30px; letter-spacing: -1px; text-transform: uppercase; }
        .logo span { color: var(--blue); text-shadow: 0 0 20px rgba(112,0,255,0.5); }
        .subtitle { text-align: center; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; margin-top: -20px; margin-bottom: 30px; }
        
        .input-group { margin-bottom: 20px; }
        .input-dark { width: 100%; background: #000; border: 1px solid var(--border); padding: 15px; border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: 0.3s; font-family: 'Roboto Mono', monospace; }
        .input-dark:focus { border-color: var(--blue); box-shadow: 0 0 15px rgba(112,0,255,0.2); }
        
        .row-options { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted); margin-bottom: 25px; }
        .checkbox-container { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        
        .btn-login { width: 100%; background: var(--blue); color: #fff; border: none; padding: 16px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 14px; cursor: pointer; transition: 0.3s; letter-spacing: 1px; }
        .btn-login:hover { filter: brightness(1.2); box-shadow: 0 10px 20px rgba(112,0,255,0.4); }
        .btn-login:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        
        .error-msg { background: rgba(255,51,102,0.1); color: var(--red); border: 1px solid var(--red); padding: 12px; border-radius: 8px; font-size: 12px; font-weight: bold; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">EL <span>POINT</span></div>
    <div class="subtitle">Bóveda de Seguridad</div>
    
    <?php if ($error != '') echo "<div class='error-msg'>⚠️ $error</div>"; ?>
    
    <form method="POST" action="" onsubmit="bloquearBoton('btn-ingresar')">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="input-group">
            <input type="text" name="username" class="input-dark" placeholder="Usuario Staff" required autocomplete="off">
        </div>
        <div class="input-group">
            <input type="password" name="password" class="input-dark" placeholder="Contraseña Segura" required>
        </div>
        <div class="row-options">
            <label class="checkbox-container">
                <input type="checkbox" name="recordarme" value="1"><span>Recordar mi acceso (7 días)</span>
            </label>
        </div>
        <button type="submit" id="btn-ingresar" class="btn-login">INGRESAR AL SISTEMA</button>
    </form>
</div>

<script>
// 🔥 FUNCIÓN ANTI-LAG: Evita que disparen 5 veces el POST de login
function bloquearBoton(btnId) {
    let btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.innerText = "VERIFICANDO...";
}
</script>
</body>
</html>