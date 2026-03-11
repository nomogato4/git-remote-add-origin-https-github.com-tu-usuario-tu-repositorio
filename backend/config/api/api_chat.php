<?php
session_start();
header('Content-Type: application/json');
// RUTA BLINDADA
require_once '../config/conexion.php';

// Patovica de seguridad general
if (!isset($_SESSION['jugador']) && !isset($_SESSION['admin']) && !isset($_SESSION['cajero'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? '';
$mi_usuario = $_SESSION['jugador'] ?? $_SESSION['admin'] ?? $_SESSION['cajero'] ?? 'SISTEMA';
$es_staff = isset($_SESSION['admin']) || isset($_SESSION['cajero']);

// ==========================================
// 1. ENVIAR MENSAJE (Jugador y Admin)
// ==========================================
if ($accion == 'send_msg') {
    $mensajeRaw = trim($_POST['mensaje'] ?? '');
    if ($mensajeRaw === '') { echo json_encode(['error' => 'Mensaje vacío']); exit; }
    
    // 🔥 VACUNA ANTI-XSS: Arranca el HTML de cuajo y sanitiza caracteres
    $mensajeLimpio = strip_tags($mensajeRaw);
    $mensaje = mysqli_real_escape_string($conexion, htmlspecialchars($mensajeLimpio, ENT_QUOTES, 'UTF-8'));
    
    // Si soy staff, el receptor es el que viene por POST. Si soy jugador, va a la central.
    $receptor = $es_staff ? mysqli_real_escape_string($conexion, trim($_POST['receptor'])) : 'ADMIN';
    $remitente = $es_staff ? 'ADMIN' : $mi_usuario;

    $sql = "INSERT INTO chat_mensajes (remitente, receptor, mensaje, fecha) VALUES ('$remitente', '$receptor', '$mensaje', NOW())";
    if (mysqli_query($conexion, $sql)) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['error' => 'Error de conexión con el servidor']);
    }
    exit;
}

// ==========================================
// 2. LEER MENSAJES (Burbujas del Chat)
// ==========================================
if ($accion == 'fetch_msgs' || $accion == 'fetch_admin_chat') {
    $yo = $es_staff ? 'ADMIN' : $mi_usuario;
    $otro = $es_staff ? mysqli_real_escape_string($conexion, $_POST['receptor']) : 'ADMIN';

    // LIMIT 50 Anti-Lag: Trae los últimos 50 pero ordenados cronológicamente
    $query = "SELECT * FROM (
                SELECT * FROM chat_mensajes 
                WHERE (remitente = '$yo' AND receptor = '$otro') 
                   OR (remitente = '$otro' AND receptor = '$yo') 
                ORDER BY id DESC LIMIT 50
              ) sub ORDER BY id ASC";
              
    $res = mysqli_query($conexion, $query);
    $html = "";

    if ($res && mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $es_mio = ($row['remitente'] == $yo);
            
            // Clases CSS dinámicas
            if ($es_staff) {
                $clase = $es_mio ? 'msg-yo' : 'msg-jugador';
            } else {
                $clase = $es_mio ? 'msg-yo' : 'msg-otro';
            }
            
            $hora = date("H:i", strtotime($row['fecha']));
            
            $html .= "<div class='msg-burbuja message $clase'>";
            if(!$es_mio && !$es_staff) { $html .= "<strong>Soporte VIP</strong>"; }
            if(!$es_mio && $es_staff) { $html .= "<strong>" . strtoupper($otro) . "</strong>"; }
            $html .= "<span>" . $row['mensaje'] . "</span>";
            $html .= "<div style='font-size:9px; margin-top:6px; opacity:0.6; text-align:right;'>$hora</div>";
            $html .= "</div>";
        }
    } else {
        $html = "<div style='text-align:center; color:#6b7280; font-size:12px; margin-top:20px; text-transform:uppercase; font-weight:bold;'>No hay mensajes en el historial.</div>";
    }
    echo json_encode(['html' => $html]);
    exit;
}

// ==========================================
// 3. RADAR DE ALERTAS (Solo Staff)
// ==========================================
if ($accion == 'fetch_admin_users' && $es_staff) {
    // Buscamos usuarios que tengan tickets pendientes o retiros pendientes
    $query = "SELECT DISTINCT u.username, 
              (SELECT COUNT(id) FROM tickets WHERE jugador = u.username AND estado = 0) as cargas,
              (SELECT COUNT(id) FROM auditoria WHERE jugador = u.username AND accion = 'RETIRO' AND cajero = 'PENDIENTE') as retiros
              FROM usuarios u 
              WHERE u.rol = 'jugador' 
              HAVING cargas > 0 OR retiros > 0
              ORDER BY cargas DESC, retiros DESC LIMIT 30";
              
    $res = mysqli_query($conexion, $query);
    
    if ($res && mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $user = strtoupper($row['username']);
            $alertas = "";
            if ($row['cargas'] > 0) $alertas .= "<span style='background:#00ff88; color:#000; padding:2px 5px; border-radius:4px; font-size:10px; font-weight:bold;'>CARGA PEND.</span> ";
            if ($row['retiros'] > 0) $alertas .= "<span style='background:#ff3366; color:#fff; padding:2px 5px; border-radius:4px; font-size:10px; font-weight:bold;'>RETIRO PEND.</span>";
            
            echo "<div class='contacto' onclick='cargarChatAdmin(\"{$row['username']}\", false)'>
                    <div class='c-nombre'>$user</div>
                    <div class='c-prev'>" . $alertas . "</div>
                  </div>";
        }
    } else {
        echo "<div style='padding:20px; text-align:center; color:#6b7280; font-size:11px; font-weight:bold; text-transform:uppercase;'>Sin alertas pendientes.</div>";
    }
    exit;
}

// ==========================================
// 4. MOTOR FINANCIERO: CAJA RÁPIDA (Staff)
// ==========================================
if ($accion == 'accion_rapida' && $es_staff) {
    $jugador = mysqli_real_escape_string($conexion, trim($_POST['id_usuario']));
    $tipo = $_POST['tipo_accion']; // 'sumar' o 'restar'
    $monto = floatval($_POST['monto']);
    
    if ($monto <= 0) {
        echo json_encode(['error' => 'El monto debe ser mayor a 0.']); exit;
    }

    if ($tipo == 'sumar') {
        // 🔥 CONTROL ESTRICTO: Si es cajero, verificamos que tenga stock antes de aprobar
        if (isset($_SESSION['cajero'])) {
            $q_stock = mysqli_query($conexion, "SELECT saldo_cajero FROM usuarios WHERE username = '$mi_usuario'");
            $stock = mysqli_fetch_assoc($q_stock)['saldo_cajero'];
            
            if ($stock < $monto) {
                echo json_encode(['error' => 'No tenés stock suficiente en tu caja para aprobar esta carga.']);
                exit;
            }
            // Descontamos stock del cajero
            mysqli_query($conexion, "UPDATE usuarios SET saldo_cajero = saldo_cajero - $monto WHERE username = '$mi_usuario'");
        }

        // 1. Le sumamos al jugador de forma atómica
        mysqli_query($conexion, "UPDATE usuarios SET saldo = saldo + $monto WHERE username = '$jugador'");
        
        // 2. Limpiamos tickets pendientes
        mysqli_query($conexion, "UPDATE tickets SET estado = 1 WHERE jugador = '$jugador' AND estado = 0");
        
        // 3. Registramos en auditoria
        mysqli_query($conexion, "INSERT INTO auditoria (cajero, jugador, accion, monto, fecha) VALUES ('$mi_usuario', '$jugador', 'CARGA APROBADA', $monto, NOW())");
        
        // 4. Aviso automático al jugador
        $msg = "✅ Tu carga de $" . number_format($monto, 2, '.', '') . " ha sido aprobada. ¡Mucha suerte!";
        mysqli_query($conexion, "INSERT INTO chat_mensajes (remitente, receptor, mensaje, fecha) VALUES ('ADMIN', '$jugador', '$msg', NOW())");
        
        echo json_encode(['exito' => true]);
        
    } elseif ($tipo == 'restar') {
        // 1. Marcamos el retiro como pagado
        mysqli_query($conexion, "UPDATE auditoria SET cajero = '$mi_usuario', accion = 'RETIRO PAGADO' WHERE jugador = '$jugador' AND accion = 'RETIRO' AND cajero = 'PENDIENTE'");
        
        // 2. Sumamos stock a la caja del cajero (Si retira, el cajero se queda con el billete)
        if (isset($_SESSION['cajero'])) {
            mysqli_query($conexion, "UPDATE usuarios SET saldo_cajero = saldo_cajero + $monto WHERE username = '$mi_usuario'");
        }
        
        // 3. Aviso automático
        $msg = "💸 Tu retiro de $" . number_format($monto, 2, '.', '') . " ha sido transferido con éxito. ¡Que lo disfrutes!";
        mysqli_query($conexion, "INSERT INTO chat_mensajes (remitente, receptor, mensaje, fecha) VALUES ('ADMIN', '$jugador', '$msg', NOW())");
        
        echo json_encode(['exito' => true]);
    }
    exit;
}

// Mantenimiento de vistas rápidas (Tomar, liberar, transferir)
if (in_array($accion, ['tomar_chat', 'liberar_chat', 'transferir_admin']) && $es_staff) {
    echo json_encode(['status' => 'ok']);
    exit;
}
?>