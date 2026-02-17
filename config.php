<?php
// config.php - Configurações do banco de dados e sistema
//DepChamon - Admin@!010203
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'extremes_deputado_chamonzinho');
define('DB_USER', 'extremes_chamonzinho'); // Altere conforme seu usuário MySQL
define('DB_PASS', 'fu7@tAq%UQyB#T;e'); // Altere conforme sua senha MySQL
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema
define('SITE_NAME', 'Deputado Chamonzinho - MDB');
define('ADMIN_SESSION_NAME', 'chamonzinho_admin');
define('TIMEZONE', 'America/Sao_Paulo');

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Classe para conexão com banco de dados
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// Funções auxiliares
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function getClientIP() {
    $ipkeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipkeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logActivity($tipo, $descricao) {
    try {
        $db = new Database();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO logs_acesso (tipo, descricao, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $tipo,
            $descricao,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erro ao gravar log: " . $e->getMessage());
    }
}

// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>