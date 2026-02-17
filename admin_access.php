<?php
// admin_access.php - Configuração de senhas de acesso ao painel administrativo
// IMPORTANTE: Este arquivo deve ter permissões restritas (chmod 600)

// Senhas válidas para acessar o painel administrativo
// Altere estas senhas para aumentar a segurança
$senhas_admin_validas = [
    'chamonzinho2025',          // Senha principal
    'deputado@mdb#2025',        // Senha alternativa
    'admin!chamonzinho',        // Senha de emergência
    'mdb@para2025'              // Senha do partido
];

// Função para verificar se a senha é válida
function verificarSenhaAdmin($senha) {
    global $senhas_admin_validas;
    return in_array($senha, $senhas_admin_validas);
}

// Função para gerar nova senha segura (use quando necessário)
function gerarNovaSenha($tamanho = 12) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $senha = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $senha;
}

// Log de tentativas de acesso (opcional)
function logTentativaAcesso($ip, $senha_tentativa, $sucesso) {
    $log = date('Y-m-d H:i:s') . " - IP: $ip - Sucesso: " . ($sucesso ? 'SIM' : 'NÃO') . " - Senha: " . substr($senha_tentativa, 0, 3) . "***\n";
    file_put_contents('admin_access.log', $log, FILE_APPEND | LOCK_EX);
}

// Proteção contra ataques de força bruta
session_start();
function verificarTentativas($ip) {
    $tentativas_key = 'tentativas_' . md5($ip);
    $tempo_key = 'tempo_' . md5($ip);
    
    $tentativas = $_SESSION[$tentativas_key] ?? 0;
    $ultimo_tempo = $_SESSION[$tempo_key] ?? 0;
    
    // Reset após 15 minutos
    if (time() - $ultimo_tempo > 900) {
        $tentativas = 0;
    }
    
    // Máximo 5 tentativas por IP
    if ($tentativas >= 5) {
        return false;
    }
    
    return true;
}

function incrementarTentativas($ip) {
    $tentativas_key = 'tentativas_' . md5($ip);
    $tempo_key = 'tempo_' . md5($ip);
    
    $_SESSION[$tentativas_key] = ($_SESSION[$tentativas_key] ?? 0) + 1;
    $_SESSION[$tempo_key] = time();
}

function resetarTentativas($ip) {
    $tentativas_key = 'tentativas_' . md5($ip);
    $tempo_key = 'tempo_' . md5($ip);
    
    unset($_SESSION[$tentativas_key]);
    unset($_SESSION[$tempo_key]);
}
?>