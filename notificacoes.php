<?php
// notificacoes.php - Sistema de notificações para agendamentos

require_once 'config.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION[ADMIN_SESSION_NAME])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $agora = new DateTime();
    $notificacoes = [];
    
    // 1. Agendamentos de hoje
    $stmt = $pdo->prepare("
        SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
        FROM agendamentos a 
        LEFT JOIN cadastros c ON a.cadastro_id = c.id
        WHERE DATE(a.data_agendamento) = CURDATE() 
        AND a.status IN ('agendado', 'confirmado')
        ORDER BY a.hora_inicio ASC
    ");
    $stmt->execute();
    $agendamentos_hoje = $stmt->fetchAll();
    
    foreach ($agendamentos_hoje as $ag) {
        $hora_agendamento = new DateTime($ag['data_agendamento'] . ' ' . $ag['hora_inicio']);
        $diferenca_minutos = ($hora_agendamento->getTimestamp() - $agora->getTimestamp()) / 60;
        
        $tipo_notificacao = '';
        $urgencia = 'normal';
        
        if ($diferenca_minutos <= 0 && $diferenca_minutos >= -60) {
            $tipo_notificacao = 'agora';
            $urgencia = 'critica';
        } elseif ($diferenca_minutos <= 15) {
            $tipo_notificacao = '15min';
            $urgencia = 'alta';
        } elseif ($diferenca_minutos <= 60) {
            $tipo_notificacao = '1hora';
            $urgencia = 'media';
        } elseif ($diferenca_minutos <= 120) {
            $tipo_notificacao = '2horas';
            $urgencia = 'baixa';
        }
        
        if ($tipo_notificacao) {
            $mensagem = '';
            switch ($tipo_notificacao) {
                case 'agora':
                    $mensagem = '🚨 AGORA: ' . $ag['titulo'];
                    break;
                case '15min':
                    $mensagem = '⏰ Em 15 min: ' . $ag['titulo'];
                    break;
                case '1hora':
                    $mensagem = '🕐 Em ' . round($diferenca_minutos) . ' min: ' . $ag['titulo'];
                    break;
                case '2horas':
                    $mensagem = '📅 Em ' . round($diferenca_minutos/60, 1) . 'h: ' . $ag['titulo'];
                    break;
            }
            
            $notificacoes[] = [
                'id' => $ag['id'],
                'tipo' => 'agendamento_proximo',
                'urgencia' => $urgencia,
                'titulo' => $mensagem,
                'descricao' => ($ag['cliente_nome'] ? 'Cliente: ' . $ag['cliente_nome'] . ' - ' : '') . 
                              'Horário: ' . date('H:i', strtotime($ag['hora_inicio'])) .
                              ($ag['local'] ? ' - Local: ' . $ag['local'] : ''),
                'dados' => $ag,
                'timestamp' => $agora->getTimestamp()
            ];
        }
    }
    
    // 2. Agendamentos atrasados (que deveriam ter acontecido mas ainda não foram marcados como realizados)
    $stmt = $pdo->prepare("
        SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
        FROM agendamentos a 
        LEFT JOIN cadastros c ON a.cadastro_id = c.id
        WHERE (
            (DATE(a.data_agendamento) < CURDATE()) OR 
            (DATE(a.data_agendamento) = CURDATE() AND TIME(a.hora_inicio) < TIME(NOW()))
        )
        AND a.status IN ('agendado', 'confirmado')
        ORDER BY a.data_agendamento DESC, a.hora_inicio DESC
        LIMIT 10
    ");
    $stmt->execute();
    $agendamentos_atrasados = $stmt->fetchAll();
    
    foreach ($agendamentos_atrasados as $ag) {
        $data_agendamento = new DateTime($ag['data_agendamento'] . ' ' . $ag['hora_inicio']);
        $horas_atraso = ($agora->getTimestamp() - $data_agendamento->getTimestamp()) / 3600;
        
        $urgencia = 'media';
        if ($horas_atraso > 24) $urgencia = 'alta';
        if ($horas_atraso > 72) $urgencia = 'critica';
        
        $notificacoes[] = [
            'id' => $ag['id'],
            'tipo' => 'agendamento_atrasado',
            'urgencia' => $urgencia,
            'titulo' => '⏰ Atrasado: ' . $ag['titulo'],
            'descricao' => 'Agendado para ' . date('d/m/Y H:i', strtotime($ag['data_agendamento'] . ' ' . $ag['hora_inicio'])) .
                          ($ag['cliente_nome'] ? ' - Cliente: ' . $ag['cliente_nome'] : '') .
                          ' - Atrasado há ' . round($horas_atraso, 1) . ' horas',
            'dados' => $ag,
            'timestamp' => $agora->getTimestamp()
        ];
    }
    
    // 3. Agendamentos para amanhã (preparação)
    $stmt = $pdo->prepare("
        SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
        FROM agendamentos a 
        LEFT JOIN cadastros c ON a.cadastro_id = c.id
        WHERE DATE(a.data_agendamento) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND a.status IN ('agendado', 'confirmado')
        AND a.prioridade IN ('alta', 'urgente')
        ORDER BY a.hora_inicio ASC
    ");
    $stmt->execute();
    $agendamentos_amanha = $stmt->fetchAll();
    
    foreach ($agendamentos_amanha as $ag) {
        $notificacoes[] = [
            'id' => $ag['id'],
            'tipo' => 'agendamento_amanha',
            'urgencia' => 'baixa',
            'titulo' => '📅 Amanhã: ' . $ag['titulo'],
            'descricao' => 'Agendamento importante para amanhã às ' . date('H:i', strtotime($ag['hora_inicio'])) .
                          ($ag['cliente_nome'] ? ' com ' . $ag['cliente_nome'] : '') .
                          ($ag['local'] ? ' em ' . $ag['local'] : ''),
            'dados' => $ag,
            'timestamp' => $agora->getTimestamp()
        ];
    }
    
    // 4. Conflitos de horário (agendamentos sobrepostos)
    $stmt = $pdo->prepare("
        SELECT a1.*, a2.id as conflito_id, a2.titulo as conflito_titulo,
               c1.nome as cliente_nome, c2.nome as conflito_cliente_nome
        FROM agendamentos a1
        LEFT JOIN cadastros c1 ON a1.cadastro_id = c1.id
        JOIN agendamentos a2 ON (
            a1.data_agendamento = a2.data_agendamento 
            AND a1.id != a2.id
            AND a1.status IN ('agendado', 'confirmado')
            AND a2.status IN ('agendado', 'confirmado')
            AND (
                (a1.hora_inicio <= a2.hora_inicio AND a1.hora_fim > a2.hora_inicio) OR
                (a2.hora_inicio <= a1.hora_inicio AND a2.hora_fim > a1.hora_inicio) OR
                (a1.hora_inicio <= a2.hora_inicio AND a1.hora_fim >= a2.hora_fim) OR
                (a2.hora_inicio <= a1.hora_inicio AND a2.hora_fim >= a1.hora_fim)
            )
        )
        LEFT JOIN cadastros c2 ON a2.cadastro_id = c2.id
        WHERE a1.data_agendamento >= CURDATE()
        ORDER BY a1.data_agendamento ASC, a1.hora_inicio ASC
    ");
    $stmt->execute();
    $conflitos = $stmt->fetchAll();
    
    $conflitos_processados = [];
    foreach ($conflitos as $conflito) {
        $chave = min($conflito['id'], $conflito['conflito_id']) . '_' . max($conflito['id'], $conflito['conflito_id']);
        
        if (!in_array($chave, $conflitos_processados)) {
            $conflitos_processados[] = $chave;
            
            $notificacoes[] = [
                'id' => $conflito['id'],
                'tipo' => 'conflito_horario',
                'urgencia' => 'alta',
                'titulo' => '⚠️ Conflito de Horário',
                'descricao' => 'Conflito em ' . date('d/m/Y', strtotime($conflito['data_agendamento'])) . ': "' . 
                              $conflito['titulo'] . '" vs "' . $conflito['conflito_titulo'] . '"',
                'dados' => $conflito,
                'timestamp' => $agora->getTimestamp()
            ];
        }
    }
    
    // 5. Aniversários de clientes hoje
    $stmt = $pdo->prepare("
        SELECT c.*, 
               YEAR(CURDATE()) - YEAR(c.data_nascimento) as idade
        FROM cadastros c
        WHERE MONTH(c.data_nascimento) = MONTH(CURDATE())
        AND DAY(c.data_nascimento) = DAY(CURDATE())
        AND c.status = 'ativo'
        ORDER BY c.nome ASC
    ");
    $stmt->execute();
    $aniversariantes = $stmt->fetchAll();
    
    foreach ($aniversariantes as $aniversariante) {
        $notificacoes[] = [
            'id' => 'aniv_' . $aniversariante['id'],
            'tipo' => 'aniversario',
            'urgencia' => 'baixa',
            'titulo' => '🎂 Aniversário: ' . $aniversariante['nome'],
            'descricao' => $aniversariante['nome'] . ' faz ' . $aniversariante['idade'] . ' anos hoje! ' .
                          'Telefone: ' . $aniversariante['telefone'] . 
                          ($aniversariante['email'] ? ' - Email: ' . $aniversariante['email'] : ''),
            'dados' => $aniversariante,
            'timestamp' => $agora->getTimestamp()
        ];
    }
    
    // Ordenar notificações por urgência e timestamp
    $ordem_urgencia = ['critica' => 4, 'alta' => 3, 'media' => 2, 'baixa' => 1, 'normal' => 1];
    
    usort($notificacoes, function($a, $b) use ($ordem_urgencia) {
        $urgencia_diff = $ordem_urgencia[$b['urgencia']] - $ordem_urgencia[$a['urgencia']];
        if ($urgencia_diff !== 0) {
            return $urgencia_diff;
        }
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Limitar a 20 notificações para não sobrecarregar
    $notificacoes = array_slice($notificacoes, 0, 20);
    
    // Estatísticas das notificações
    $estatisticas = [
        'total' => count($notificacoes),
        'criticas' => count(array_filter($notificacoes, fn($n) => $n['urgencia'] === 'critica')),
        'altas' => count(array_filter($notificacoes, fn($n) => $n['urgencia'] === 'alta')),
        'medias' => count(array_filter($notificacoes, fn($n) => $n['urgencia'] === 'media')),
        'baixas' => count(array_filter($notificacoes, fn($n) => $n['urgencia'] === 'baixa')),
        'agendamentos_hoje' => count($agendamentos_hoje),
        'agendamentos_atrasados' => count($agendamentos_atrasados),
        'conflitos' => count($conflitos_processados),
        'aniversarios' => count($aniversariantes)
    ];
    
    echo json_encode([
        'sucesso' => true,
        'notificacoes' => $notificacoes,
        'estatisticas' => $estatisticas,
        'timestamp' => $agora->getTimestamp(),
        'proxima_verificacao' => $agora->modify('+5 minutes')->getTimestamp()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao buscar notificações: ' . $e->getMessage()
    ]);
}