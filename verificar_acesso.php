<?php
// verificar_acesso.php - Endpoint para verificar senha de acesso administrativo

header('Content-Type: application/json');
header('X-Robots-Tag: noindex, nofollow');

// Incluir configurações de acesso
require_once 'admin_access.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

// Obter IP do cliente
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

$ip_cliente = getClientIP();

// Verificar tentativas de força bruta
if (!verificarTentativas($ip_cliente)) {
    http_response_code(429);
    echo json_encode([
        'sucesso' => false, 
        'erro' => 'Muitas tentativas. Tente novamente em 15 minutos.',
        'bloqueado' => true
    ]);
    exit;
}

// Obter senha enviada
$input = json_decode(file_get_contents('php://input'), true);
$senha = $input['senha'] ?? '';

// Validar entrada
if (empty($senha) || strlen($senha) < 3) {
    incrementarTentativas($ip_cliente);
    echo json_encode(['sucesso' => false, 'erro' => 'Senha inválida']);
    exit;
}

// Verificar senha
$acesso_valido = verificarSenhaAdmin($senha);

if ($acesso_valido) {
    // Sucesso - resetar tentativas e permitir acesso
    resetarTentativas($ip_cliente);
    logTentativaAcesso($ip_cliente, $senha, true);
    
    echo json_encode([
        'sucesso' => true, 
        'redirect' => 'admin/login.php',
        'mensagem' => 'Acesso autorizado'
    ]);
} else {
    // Falha - incrementar tentativas
    incrementarTentativas($ip_cliente);
    logTentativaAcesso($ip_cliente, $senha, false);
    
    echo json_encode([
        'sucesso' => false, 
        'erro' => 'Senha incorreta',
        'tentativas_restantes' => 5 - ($_SESSION['tentativas_' . md5($ip_cliente)] ?? 0)
    ]);
}
?>