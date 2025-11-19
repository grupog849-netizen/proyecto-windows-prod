<?php
// ===============================
//  CONFIGURACIÓN SOLO WINDOWS (SQLyog en 10.50.30.253)
//  Página web en 10.50.31.153
// ===============================

const DB_HOSTS = [
    [
        'host' => '10.50.30.253',     // IP de la VM donde está XAMPP + SQLyog
        'port' => 3306,
        'name' => 'crud_db',          // Base de datos
        'user' => 'app_user',         // Usuario MySQL que creaste
        'pass' => 'utn12345678*',     // Contraseña de ese usuario
    ],
];

const DB_CHARSET = 'utf8mb4';

// Zona horaria
date_default_timezone_set('America/Costa_Rica');

// ===============================
//  Verificar si el puerto está arriba
// ===============================
function portUp(string $host, int $port, float $timeout = 1.0): bool {
    $errno = $errstr = null;
    $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($conn) {
        fclose($conn);
        return true;
    }
    return false;
}

// ===============================
//  Conexión PDO (solo Windows)
// ===============================
function getConnection(): PDO {
    $lastError = null;

    foreach (DB_HOSTS as $cfg) {
        $host = $cfg['host'];
        $port = $cfg['port'];
        $name = $cfg['name'];
        $user = $cfg['user'];
        $pass = $cfg['pass'];

        // 1) ¿Responde el puerto?
        if (!portUp($host, $port)) {
            $lastError = "Puerto no accesible en $host:$port";
            continue;
        }

        // 2) Intentar conexión PDO
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
            // Ajuste de zona horaria a nivel de sesión
            $pdo->exec("SET time_zone = '-06:00'");
            return $pdo; // ✅ Conexión exitosa
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
        }
    }

    // Si ninguno funcionó
    throw new RuntimeException("❌ No se pudo conectar a la base de datos. Último error: {$lastError}");
}

// ===============================
//  SESIÓN + REGISTRO DE ACCESOS
// ===============================

// 🧠 Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detectar en qué archivo estamos
$archivo_actual   = basename($_SERVER['PHP_SELF']);
$paginas_excluidas = ['login.php', 'register.php', 'logout.php'];

/*
   🚦 Registro automático SOLO para usuarios “anónimos” que visitan el sitio
   y no están en login/register/logout.
*/
if (!in_array($archivo_actual, $paginas_excluidas)) {
    if (!isset($_SESSION['usuario'])) {
        $_SESSION['session_id']        = session_id();
        $_SESSION['usuario']           = 'Usuario_' . substr(session_id(), 0, 8);
        $_SESSION['ingreso_timestamp'] = time();

        try {
            $pdo = getConnection();
            $sql = "INSERT INTO registro_accesos (usuario, user_agent, fecha_ingreso)
                    VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_SESSION['usuario'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
            ]);
            $_SESSION['registro_id'] = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            // No botar la página si falla DB
            error_log("Error registrando acceso: " . $e->getMessage());
        }
    }
}

/*
   🕐 Registrar salida SOLO cuando se llame explícitamente (logout o cierre manual).
*/
function registrarSalida() {
    if (isset($_SESSION['registro_id'])) {
        try {
            $pdo = getConnection();
            $duracion = time() - ($_SESSION['ingreso_timestamp'] ?? time());
            $sql = "UPDATE registro_accesos
                    SET fecha_salida = NOW(),
                        duracion_sesion = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$duracion, (int)$_SESSION['registro_id']]);
        } catch (Throwable $e) {
            error_log("Error al registrar salida: " . $e->getMessage());
        }
    }
}
