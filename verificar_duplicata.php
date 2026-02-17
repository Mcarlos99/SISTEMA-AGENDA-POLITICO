<?php
// verificar_duplicata.php - Sistema RIGOROSO para prevenir cadastros duplicados por TELEFONE OU EMAIL

header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$nome = sanitizeInput($input['nome'] ?? '');
$telefone = sanitizeInput($input['telefone'] ?? '');
$email = sanitizeInput($input['email'] ?? '');

if (empty($telefone)) {
    echo json_encode(['duplicata' => false]);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Normalizar telefone (remover formatação)
    $telefone_limpo = preg_replace('/\D/', '', $telefone);
    
    // VERIFICAÇÃO RIGOROSA 1: Buscar por telefone exato (com e sem formatação)
    $stmt = $pdo->prepare("
        SELECT id, nome, cidade, cargo, telefone, email, data_cadastro, status 
        FROM cadastros 
        WHERE (telefone = ? OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), ' ', ''), '-', '') = ?)
        AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$telefone, $telefone_limpo]);
    $cadastro_por_telefone = $stmt->fetch();
    
    if ($cadastro_por_telefone) {
        // TELEFONE JÁ EXISTE - BLOQUEAR CADASTRO
        echo json_encode([
            'duplicata' => true,
            'bloqueado' => true,
            'motivo' => 'telefone_existente',
            'campo_duplicado' => 'telefone',
            'mensagem' => 'Este número de telefone já está cadastrado no sistema.',
            'cadastro_existente' => [
                'nome' => $cadastro_por_telefone['nome'],
                'cidade' => $cadastro_por_telefone['cidade'],
                'cargo' => $cadastro_por_telefone['cargo'],
                'telefone' => $cadastro_por_telefone['telefone'],
                'email' => $cadastro_por_telefone['email'] ?: 'Não informado',
                'data_cadastro' => date('d/m/Y', strtotime($cadastro_por_telefone['data_cadastro']))
            ]
        ]);
        
        // Log da tentativa de duplicata
        logActivity('cadastro_duplicado_bloqueado', "TELEFONE DUPLICADO - Telefone: $telefone - Tentativa: $nome - Existente: {$cadastro_por_telefone['nome']}");
        exit;
    }
    
    // VERIFICAÇÃO RIGOROSA 2: Buscar por email (se fornecido e não vazio)
    if (!empty($email) && strlen(trim($email)) > 0) {
        $email_limpo = strtolower(trim($email));
        
        $stmt = $pdo->prepare("
            SELECT id, nome, cidade, cargo, telefone, email, data_cadastro, status 
            FROM cadastros 
            WHERE LOWER(TRIM(email)) = ? AND email IS NOT NULL AND email != ''
            AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$email_limpo]);
        $cadastro_por_email = $stmt->fetch();
        
        if ($cadastro_por_email) {
            // EMAIL JÁ EXISTE - BLOQUEAR CADASTRO
            echo json_encode([
                'duplicata' => true,
                'bloqueado' => true,
                'motivo' => 'email_existente',
                'campo_duplicado' => 'email',
                'mensagem' => 'Este endereço de email já está cadastrado no sistema.',
                'cadastro_existente' => [
                    'nome' => $cadastro_por_email['nome'],
                    'cidade' => $cadastro_por_email['cidade'],
                    'cargo' => $cadastro_por_email['cargo'],
                    'telefone' => $cadastro_por_email['telefone'],
                    'email' => $cadastro_por_email['email'],
                    'data_cadastro' => date('d/m/Y', strtotime($cadastro_por_email['data_cadastro']))
                ]
            ]);
            
            // Log da tentativa de duplicata
            logActivity('cadastro_duplicado_bloqueado', "EMAIL DUPLICADO - Email: $email - Telefone tentativa: $telefone - Nome tentativa: $nome - Existente: {$cadastro_por_email['nome']} ({$cadastro_por_email['telefone']})");
            exit;
        }
    }
    
    // Se chegou até aqui, não há duplicata por telefone
    // Verificar outras possíveis duplicatas como aviso (sem bloquear)
    $avisos = [];
    
    // Verificar por email (apenas aviso)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT nome, telefone FROM cadastros WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $cadastro_email = $stmt->fetch();
        
        if ($cadastro_email) {
            $avisos[] = [
                'tipo' => 'email_existente',
                'mensagem' => 'Este email já está sendo usado por: ' . $cadastro_email['nome'] . ' (' . $cadastro_email['telefone'] . ')'
            ];
        }
    }
    
    // Verificar nomes muito similares (apenas aviso)
    if (!empty($nome) && strlen($nome) > 3) {
        $stmt = $pdo->prepare("
            SELECT nome, telefone, cidade 
            FROM cadastros 
            WHERE SOUNDEX(nome) = SOUNDEX(?) 
            AND status = 'ativo' 
            AND nome != ?
            LIMIT 3
        ");
        $stmt->execute([$nome, $nome]);
        $nomes_similares = $stmt->fetchAll();
        
        foreach ($nomes_similares as $similar) {
            $similaridade = 1 - (levenshtein(strtolower($nome), strtolower($similar['nome'])) / max(strlen($nome), strlen($similar['nome'])));
            
            if ($similaridade > 0.85) { // 85% de similaridade
                $avisos[] = [
                    'tipo' => 'nome_similar',
                    'mensagem' => 'Nome similar encontrado: ' . $similar['nome'] . ' (' . $similar['telefone'] . ') - ' . $similar['cidade']
                ];
            }
        }
    }
    
    // Retornar resultado
    if (!empty($avisos)) {
        echo json_encode([
            'duplicata' => false,
            'avisos' => $avisos,
            'permitir_cadastro' => true
        ]);
    } else {
        echo json_encode([
            'duplicata' => false,
            'permitir_cadastro' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao verificar duplicata: " . $e->getMessage());
    echo json_encode([
        'duplicata' => false, 
        'erro' => 'Erro interno na verificação',
        'permitir_cadastro' => true
    ]);
}
?>