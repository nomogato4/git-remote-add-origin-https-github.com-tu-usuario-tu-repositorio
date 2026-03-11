<?php
// 🔥 ESCUDO ANTI-HACKEO (Se aplica a todo el casino porque este archivo se incluye siempre)
header('X-Frame-Options: SAMEORIGIN'); // Evita que clonen tu sitio en un iframe falso
header('X-XSS-Protection: 1; mode=block'); // Frena inyecciones de código en el navegador
header('X-Content-Type-Options: nosniff');

// 🔥 SILENCIADOR ABSOLUTO: Apagamos los reportes de error para que NUNCA filtre credenciales
mysqli_report(MYSQLI_REPORT_OFF);

// Credenciales
$host = "localhost"; 
$user = "root";        
$pass = "";            
$db   = "elpoint";     

// Conexión blindada
$conexion = @mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    // Pantalla de mantenimiento limpia, con diseño neón a tono con el sitio
    die("<div style='background:#030304; color:#ff3366; padding:20px; text-align:center; font-family:sans-serif; height:100vh; display:flex; align-items:center; justify-content:center; flex-direction:column;'>
            <h1 style='font-size:60px; margin:0; text-shadow: 0 0 30px rgba(255,51,102,0.6);'>🛠️</h1>
            <h2 style='color:#fff; margin-bottom:10px; text-transform:uppercase; font-weight:900; letter-spacing:1px;'>EL POINT está en mantenimiento</h2>
            <p style='color:#6b7280; font-size:13px; font-weight:bold; font-family:monospace;'>El servidor se está reiniciando. Volvé en unos minutos.</p>
         </div>");
}

// 1. Soporte total para Emojis (🔥💰🎰)
mysqli_set_charset($conexion, "utf8mb4");

// 2. Zona Horaria estricta (Argentina)
date_default_timezone_set('America/Argentina/Buenos_Aires');
mysqli_query($conexion, "SET time_zone = '-03:00'");

// 3. BLINDAJE FINANCIERO: Modo Estricto de MySQL
// Frena cualquier operación donde los tipos de datos (plata) no coincidan exactos.
mysqli_query($conexion, "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
?>