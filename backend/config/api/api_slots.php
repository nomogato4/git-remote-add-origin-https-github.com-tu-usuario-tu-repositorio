<?php
session_start();
header('Content-Type: application/json');

// Conexión al config de al lado
require_once '../config/conexion.php';

if (!isset($_SESSION['jugador'])) {
    echo json_encode(['error' => 'Sesión expirada.']);
    exit;
}

$jugador = $_SESSION['jugador'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $monto_apuesta = floatval($_POST['monto']);

    if ($monto_apuesta <= 0) {
        echo json_encode(['error' => 'Monto inválido.']);
        exit;
    }

    $q_saldo = mysqli_query($conexion, "SELECT saldo FROM usuarios WHERE username = '$jugador' LIMIT 1");
    $row_saldo = mysqli_fetch_assoc($q_saldo);
    
    if ($row_saldo['saldo'] < $monto_apuesta) {
        echo json_encode(['error' => 'Saldo insuficiente.']);
        exit;
    }

    // Cobramos la apuesta al instante
    mysqli_query($conexion, "UPDATE usuarios SET saldo = saldo - $monto_apuesta WHERE username = '$jugador'");

    // Matemática de las Slots (Podés ajustar los multiplicadores)
    $simbolos = ['🍒', '🍋', '🍉', '⭐', '💎', '🔔'];
    $r1 = $simbolos[array_rand($simbolos)];
    $r2 = $simbolos[array_rand($simbolos)];
    $r3 = $simbolos[array_rand($simbolos)];

    $premio = 0;
    
    // Si saca 3 iguales
    if ($r1 == $r2 && $r2 == $r3) {
        if ($r1 == '💎') $premio = $monto_apuesta * 50; // Jackpot
        elseif ($r1 == '⭐') $premio = $monto_apuesta * 20;
        elseif ($r1 == '🔔') $premio = $monto_apuesta * 10;
        else $premio = $monto_apuesta * 5; // Frutas
    } 
    // Si saca 2 iguales al principio
    elseif ($r1 == $r2) {
        $premio = $monto_apuesta * 1.5;
    }

    if ($premio > 0) {
        mysqli_query($conexion, "UPDATE usuarios SET saldo = saldo + $premio WHERE username = '$jugador'");
        mysqli_query($conexion, "INSERT INTO auditoria (cajero, jugador, accion, monto, fecha) VALUES ('SISTEMA', '$jugador', 'PREMIO SLOTS', $premio, NOW())");
    } else {
        mysqli_query($conexion, "INSERT INTO auditoria (cajero, jugador, accion, monto, fecha) VALUES ('SISTEMA', '$jugador', 'APUESTA SLOTS', $monto_apuesta, NOW())");
    }

    $q_saldo_final = mysqli_query($conexion, "SELECT saldo FROM usuarios WHERE username = '$jugador' LIMIT 1");
    $saldo_final = mysqli_fetch_assoc($q_saldo_final)['saldo'];

    echo json_encode([
        'rodillos' => [$r1, $r2, $r3],
        'ganancia' => $premio,
        'nuevo_saldo' => number_format($saldo_final, 2, '.', '')
    ]);
    exit;
}
?>