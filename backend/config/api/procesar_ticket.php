<?php
session_start();
header('Content-Type: application/json');
// RUTA BLINDADA
require_once '../config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    echo json_encode(['error' => 'Sesión expirada. Volvé a ingresar.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jugador = mysqli_real_escape_string($conexion, $_SESSION['jugador']);
    $monto = floatval($_POST['monto']);
    $tipo = $_POST['tipo'] ?? 'carga'; // Recibe si es 'carga' o 'retiro'

    if ($monto <= 0) {
        echo json_encode(['error' => 'Monto inválido.']);
        exit;
    }

    // ==========================================
    // LÓGICA 1: GENERAR CARGA (DEPOSITAR)
    // ==========================================
    if ($tipo == 'carga') {
        if ($monto < 500) {
            echo json_encode(['error' => 'El mínimo de carga es de $500.']);
            exit;
        }

        // 🔥 BLINDAJE ANTI-SPAM
        $q_spam = mysqli_query($conexion, "SELECT COUNT(id) as pendientes FROM tickets WHERE jugador = '$jugador' AND estado = 0");
        if (mysqli_fetch_assoc($q_spam)['pendientes'] >= 3) {
            echo json_encode(['error' => 'Ya tenés 3 tickets pendientes. Aguardá que un cajero los procese.']);
            exit;
        }

        // Generamos un código corto y guardamos en tickets
        $codigo_ticket = 'TK-' . strtoupper(substr(md5(time() . $jugador), 0, 6));
        $sql = "INSERT INTO tickets (codigo, jugador, monto, estado, fecha) VALUES ('$codigo_ticket', '$jugador', $monto, 0, NOW())";
        
        if (mysqli_query($conexion, $sql)) {
            echo json_encode(['exito' => true, 'mensaje' => 'Ticket generado con éxito. Contactá al soporte.']);
        } else {
            echo json_encode(['error' => 'Error en la base de datos al generar el ticket.']);
        }
    } 
    // ==========================================
    // LÓGICA 2: PEDIR RETIRO (EXTRAER GANANCIAS)
    // ==========================================
    elseif ($tipo == 'retiro') {
        if ($monto < 1000) {
            echo json_encode(['error' => 'El mínimo de retiro es de $1000.']);
            exit;
        }

        // 🔥 BLINDAJE ANTI-SPAM RETIROS
        $q_spam_ret = mysqli_query($conexion, "SELECT COUNT(id) as pendientes FROM auditoria WHERE jugador = '$jugador' AND accion = 'RETIRO' AND cajero = 'PENDIENTE'");
        if (mysqli_fetch_assoc($q_spam_ret)['pendientes'] >= 2) {
            echo json_encode(['error' => 'Ya tenés retiros en proceso. Aguardá tu pago.']);
            exit;
        }

        // 🛡️ BÓVEDA MATEMÁTICA: DESCUENTO ATÓMICO (ADIÓS SALDO NEGATIVO)
        // La magia de "AND saldo >= $monto" impide físicamente que se endeuden.
        $sql_descuento = "UPDATE usuarios SET saldo = saldo - $monto WHERE username = '$jugador' AND saldo >= $monto";
        mysqli_query($conexion, $sql_descuento);

        // Verificamos si MySQL realmente descontó la plata
        if (mysqli_affected_rows($conexion) > 0) {
            // ÉXITO: Tenía la plata y se la sacamos. Registramos el retiro en la cola del Admin.
            $sql_retiro = "INSERT INTO auditoria (cajero, jugador, accion, monto, fecha) VALUES ('PENDIENTE', '$jugador', 'RETIRO', $monto, NOW())";
            mysqli_query($conexion, $sql_retiro);
            
            echo json_encode(['exito' => true, 'mensaje' => 'Retiro solicitado. El saldo ya fue descontado de tu cuenta.']);
        } else {
            // FALLO: No tenía saldo suficiente o quiso "buguear" el botón.
            echo json_encode(['error' => 'Fondos insuficientes. No podés retirar plata que no tenés.']);
        }
    } else {
        echo json_encode(['error' => 'Acción no reconocida.']);
    }
}
?>