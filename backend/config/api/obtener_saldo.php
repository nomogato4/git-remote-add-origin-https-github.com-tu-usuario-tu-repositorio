<?php
session_start();
header('Content-Type: application/json');
// RUTA BLINDADA
require_once '../config/conexion.php';

// 1. Patovica de Sesión
if (!isset($_SESSION['jugador'])) {
    echo json_encode(['error' => 'Sesión expirada']);
    exit;
}

$jugador = mysqli_real_escape_string($conexion, $_SESSION['jugador']);

// 2. Consulta Ultra Rápida (Solo traemos lo estrictamente necesario)
$query = mysqli_query($conexion, "SELECT saldo, bono, estado FROM usuarios WHERE username = '$jugador' AND rol = 'jugador' LIMIT 1");

if ($query && mysqli_num_rows($query) > 0) {
    $row = mysqli_fetch_assoc($query);
    
    // 3. KILL-SWITCH: Si el Admin lo baneó (estado = 0) mientras jugaba, lo pateamos en vivo
    if ($row['estado'] == 0) {
        unset($_SESSION['jugador']);
        echo json_encode(['error' => 'Sesión expirada']);
        exit;
    }

    // 4. Todo OK: Devolvemos la plata limpia
    echo json_encode([
        'saldo' => $row['saldo'],
        'bono'  => $row['bono']
    ]);
} else {
    // Si borraron al usuario de la base de datos
    unset($_SESSION['jugador']);
    echo json_encode(['error' => 'Sesión expirada']);
}
?>