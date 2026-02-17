<?php
require_once '../config.php';

// Verificar se está logado
if (!isset($_SESSION[ADMIN_SESSION_NAME])) {
    header('Location: login.php');
    exit;
}

$admin = $_SESSION[ADMIN_SESSION_NAME];
$message = '';
$messageType = '';

// Verificar se há mensagem temporária da sessão (após redirecionamento)
if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    $messageType = $_SESSION['temp_message_type'];
    unset($_SESSION['temp_message']);
    unset($_SESSION['temp_message_type']);
}

// Configurações para evitar timeout
set_time_limit(60);
ini_set('memory_limit', '256M');

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            $db = new Database();
            $pdo = $db->getConnection();
            
            if (!$pdo) {
                throw new Exception('Erro de conexão com banco de dados.');
            }
            
            switch ($_POST['action']) {
                case 'criar_agendamento':
                    $cadastro_id = !empty($_POST['cadastro_id']) ? (int)$_POST['cadastro_id'] : null;
                    $titulo = sanitizeInput($_POST['titulo'] ?? '');
                    $descricao = sanitizeInput($_POST['descricao'] ?? '');
                    $data_agendamento = sanitizeInput($_POST['data_agendamento'] ?? '');
                    $hora_inicio = sanitizeInput($_POST['hora_inicio'] ?? '');
                    $hora_fim = sanitizeInput($_POST['hora_fim'] ?? '');
                    $tipo = sanitizeInput($_POST['tipo'] ?? 'retorno');
                    $prioridade = sanitizeInput($_POST['prioridade'] ?? 'media');
                    $local = sanitizeInput($_POST['local'] ?? '');
                    $observacoes = sanitizeInput($_POST['observacoes'] ?? '');
                    $lembrete = (int)($_POST['lembrete_antecedencia'] ?? 60);
                    
                    if (empty($titulo) || empty($data_agendamento) || empty($hora_inicio)) {
                        throw new Exception('Título, data e hora de início são obrigatórios.');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO agendamentos (cadastro_id, titulo, descricao, data_agendamento, hora_inicio, hora_fim, tipo, prioridade, local, observacoes, lembrete_antecedencia, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$cadastro_id, $titulo, $descricao, $data_agendamento, $hora_inicio, $hora_fim ?: null, $tipo, $prioridade, $local, $observacoes, $lembrete, $admin['id']])) {
                        logActivity('admin_action', "Agendamento criado: $titulo para $data_agendamento por " . $admin['usuario']);
                        $message = "Agendamento criado com sucesso!";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao salvar agendamento no banco de dados.');
                    }
                    break;
                    
                case 'editar_agendamento':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID do agendamento inválido.');
                    }
                    
                    $cadastro_id = !empty($_POST['cadastro_id']) ? (int)$_POST['cadastro_id'] : null;
                    $titulo = sanitizeInput($_POST['titulo'] ?? '');
                    $descricao = sanitizeInput($_POST['descricao'] ?? '');
                    $data_agendamento = sanitizeInput($_POST['data_agendamento'] ?? '');
                    $hora_inicio = sanitizeInput($_POST['hora_inicio'] ?? '');
                    $hora_fim = sanitizeInput($_POST['hora_fim'] ?? '');
                    $tipo = sanitizeInput($_POST['tipo'] ?? 'retorno');
                    $status = sanitizeInput($_POST['status'] ?? 'agendado');
                    $prioridade = sanitizeInput($_POST['prioridade'] ?? 'media');
                    $local = sanitizeInput($_POST['local'] ?? '');
                    $observacoes = sanitizeInput($_POST['observacoes'] ?? '');
                    $lembrete = (int)($_POST['lembrete_antecedencia'] ?? 60);
                    
                    if (empty($titulo) || empty($data_agendamento) || empty($hora_inicio)) {
                        throw new Exception('Título, data e hora de início são obrigatórios.');
                    }
                    
                    $check_stmt = $pdo->prepare("SELECT id FROM agendamentos WHERE id = ?");
                    $check_stmt->execute([$id]);
                    if (!$check_stmt->fetch()) {
                        throw new Exception('Agendamento não encontrado.');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE agendamentos SET cadastro_id = ?, titulo = ?, descricao = ?, data_agendamento = ?, hora_inicio = ?, hora_fim = ?, tipo = ?, status = ?, prioridade = ?, local = ?, observacoes = ?, lembrete_antecedencia = ? WHERE id = ?");
                    
                    if ($stmt->execute([$cadastro_id, $titulo, $descricao, $data_agendamento, $hora_inicio, $hora_fim ?: null, $tipo, $status, $prioridade, $local, $observacoes, $lembrete, $id])) {
                        logActivity('admin_action', "Agendamento ID $id editado por " . $admin['usuario'] . " - Status: $status");
                        $message = "Agendamento atualizado com sucesso!";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao atualizar agendamento no banco de dados.');
                    }
                    break;
                    
                case 'excluir_agendamento':
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID do agendamento inválido.');
                    }
                    
                    $check_stmt = $pdo->prepare("SELECT titulo FROM agendamentos WHERE id = ?");
                    $check_stmt->execute([$id]);
                    $agendamento_existe = $check_stmt->fetch();
                    
                    if (!$agendamento_existe) {
                        throw new Exception('Agendamento não encontrado.');
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        logActivity('admin_action', "Agendamento ID $id '{$agendamento_existe['titulo']}' excluído por " . $admin['usuario']);
                        $message = "Agendamento excluído com sucesso!";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao excluir agendamento do banco de dados.');
                    }
                    break;
                    
                case 'alterar_status':
                    $id = (int)($_POST['id'] ?? 0);
                    $status = sanitizeInput($_POST['status'] ?? '');
                    
                    if ($id <= 0) {
                        throw new Exception('ID do agendamento inválido.');
                    }
                    
                    if (!in_array($status, ['agendado', 'confirmado', 'realizado', 'cancelado'])) {
                        throw new Exception('Status inválido.');
                    }
                    
                    $check_stmt = $pdo->prepare("SELECT titulo, status FROM agendamentos WHERE id = ?");
                    $check_stmt->execute([$id]);
                    $agendamento_atual = $check_stmt->fetch();
                    
                    if (!$agendamento_atual) {
                        throw new Exception('Agendamento não encontrado.');
                    }
                    
                    if ($agendamento_atual['status'] === $status) {
                        $message = "Status já era '$status'. Nenhuma alteração necessária.";
                        $messageType = 'success';
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE agendamentos SET status = ?, data_atualizacao = NOW() WHERE id = ?");
                    if ($stmt->execute([$status, $id])) {
                        logActivity('admin_action', "Status do agendamento ID $id '{$agendamento_atual['titulo']}' alterado de '{$agendamento_atual['status']}' para '$status' por " . $admin['usuario']);
                        
                        $status_texto = [
                            'agendado' => 'Agendado',
                            'confirmado' => 'Confirmado', 
                            'realizado' => 'Realizado',
                            'cancelado' => 'Cancelado'
                        ][$status];
                        
                        $message = "Status alterado para '$status_texto' com sucesso!";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao alterar status no banco de dados.');
                    }
                    break;
                    
                default:
                    throw new Exception('Ação não reconhecida.');
            }
            
            // Se chegou até aqui, redirecionar para evitar reenvio do formulário
            if ($messageType === 'success') {
                $_SESSION['temp_message'] = $message;
                $_SESSION['temp_message_type'] = $messageType;
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro no calendário: " . $e->getMessage() . " - " . print_r($_POST, true));
        $message = $e->getMessage();
        $messageType = 'error';
    } catch (PDOException $e) {
        error_log("Erro PDO no calendário: " . $e->getMessage());
        $message = 'Erro de banco de dados. Tente novamente em alguns momentos.';
        $messageType = 'error';
    }
}

// Obter mês e ano atual ou selecionado
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

// Validar mês e ano
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($ano < 2020 || $ano > 2030) $ano = date('Y');

$db = new Database();
$pdo = $db->getConnection();

// Buscar agendamentos do mês
$stmt = $pdo->prepare("
    SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone, c.cidade as cliente_cidade,
           admin.nome as criado_por_nome,
           CASE 
               WHEN a.data_agendamento < CURDATE() THEN 'passado'
               WHEN a.data_agendamento = CURDATE() THEN 'hoje'
               ELSE 'futuro'
           END as periodo
    FROM agendamentos a 
    LEFT JOIN cadastros c ON a.cadastro_id = c.id
    LEFT JOIN administradores admin ON a.criado_por = admin.id
    WHERE MONTH(a.data_agendamento) = ? AND YEAR(a.data_agendamento) = ?
    ORDER BY a.data_agendamento ASC, a.hora_inicio ASC
");
$stmt->execute([$mes, $ano]);
$agendamentos = $stmt->fetchAll();

// Organizar agendamentos por dia
$agendamentos_por_dia = [];
foreach ($agendamentos as $agendamento) {
    $dia = (int)date('j', strtotime($agendamento['data_agendamento']));
    $agendamentos_por_dia[$dia][] = $agendamento;
}

// Buscar cadastros para o select
$cadastros_stmt = $pdo->query("SELECT id, nome, cidade, telefone FROM cadastros WHERE status = 'ativo' ORDER BY nome");
$cadastros = $cadastros_stmt->fetchAll();

// Verificar agendamentos de hoje
$hoje = date('Y-m-d');
$stmt_hoje = $pdo->prepare("
    SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
    FROM agendamentos a 
    LEFT JOIN cadastros c ON a.cadastro_id = c.id
    WHERE a.data_agendamento = ? AND a.status IN ('agendado', 'confirmado')
    ORDER BY a.hora_inicio ASC
");
$stmt_hoje->execute([$hoje]);
$agendamentos_hoje = $stmt_hoje->fetchAll();

// Função para gerar calendário
function gerarCalendario($mes, $ano, $agendamentos_por_dia) {
    $primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);
    
    $html = '<div class="calendario-grid">';
    
    // Cabeçalho dos dias da semana
    $dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    foreach ($dias_semana as $dia) {
        $html .= '<div class="calendario-header">' . $dia . '</div>';
    }
    
    // Células vazias antes do primeiro dia
    for ($i = 0; $i < $dia_semana_inicio; $i++) {
        $html .= '<div class="calendario-dia vazio"></div>';
    }
    
    // Dias do mês
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $hoje = date('Y-m-d');
        $is_hoje = ($data_completa == $hoje);
        $is_passado = ($data_completa < $hoje);
        
        $classes = ['calendario-dia'];
        if ($is_hoje) $classes[] = 'hoje';
        if ($is_passado) $classes[] = 'passado';
        if (isset($agendamentos_por_dia[$dia])) $classes[] = 'tem-agendamento';
        
        $html .= '<div class="' . implode(' ', $classes) . '" data-dia="' . $dia . '" data-data="' . $data_completa . '">';
        $html .= '<div class="numero-dia">' . $dia . '</div>';
        
        if (isset($agendamentos_por_dia[$dia])) {
            $count = count($agendamentos_por_dia[$dia]);
            $urgentes = 0;
            
            foreach ($agendamentos_por_dia[$dia] as $ag) {
                if ($ag['prioridade'] == 'urgente' || $ag['prioridade'] == 'alta') {
                    $urgentes++;
                }
            }
            
            $html .= '<div class="agendamentos-indicador">';
            $html .= '<span class="total">' . $count . '</span>';
            if ($urgentes > 0) {
                $html .= '<span class="urgentes">⚠️ ' . $urgentes . '</span>';
            }
            $html .= '</div>';
            
            // Preview dos agendamentos com IDs
            $html .= '<div class="agendamentos-preview">';
            foreach (array_slice($agendamentos_por_dia[$dia], 0, 3) as $ag) {
                $prioridade_icon = [
                    'baixa' => '🟢',
                    'media' => '🟡', 
                    'alta' => '🟠',
                    'urgente' => '🔴'
                ][$ag['prioridade']];
                
                $html .= '<div class="agendamento-item agendamento-item-calendario priority-' . $ag['prioridade'] . '" data-agendamento-id="' . $ag['id'] . '" title="Clique para ver detalhes">';
                $html .= $prioridade_icon . ' ' . date('H:i', strtotime($ag['hora_inicio'])) . ' - ' . htmlspecialchars(substr($ag['titulo'], 0, 20));
                if (strlen($ag['titulo']) > 20) $html .= '...';
                $html .= '</div>';
            }
            if ($count > 3) {
                $html .= '<div class="mais-agendamentos">+' . ($count - 3) . ' mais</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .nav-calendario {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-calendario select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }

        .btn-primary { background: #0066cc; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }

        .btn:hover { transform: translateY(-1px); opacity: 0.9; }

        .calendario-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .calendario-header-mes {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 1.5em;
            font-weight: bold;
        }

        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }

        .calendario-header {
            background: #f8f9fa;
            padding: 15px 5px;
            text-align: center;
            font-weight: bold;
            color: #003366;
            border-right: 1px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }

        .calendario-dia {
            min-height: 120px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .calendario-dia:hover {
            background: #f8f9fa;
            transform: scale(1.02);
            z-index: 2;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .calendario-dia.vazio {
            background: #f8f9fa;
            cursor: default;
        }

        .calendario-dia.vazio:hover {
            transform: none;
            box-shadow: none;
        }

        .calendario-dia.hoje {
            background: #e7f3ff;
            border: 2px solid #0066cc;
        }

        .calendario-dia.passado {
            background: #f8f9fa;
            color: #6c757d;
        }

        .calendario-dia.tem-agendamento {
            background: #fff3e0;
        }

        .calendario-dia.tem-agendamento.hoje {
            background: #e3f2fd;
        }

        .numero-dia {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
            color: #003366;
        }

        .agendamentos-indicador {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 3px;
            flex-direction: column;
            align-items: center;
        }

        .agendamentos-indicador .total {
            background: #0066cc;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            font-weight: bold;
        }

        .agendamentos-indicador .urgentes {
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 1px 4px;
            font-size: 0.6em;
            font-weight: bold;
        }

        .agendamentos-preview {
            margin-top: 5px;
        }

        .agendamento-item {
            font-size: 0.7em;
            margin: 1px 0;
            padding: 2px 4px;
            border-radius: 3px;
            border-left: 3px solid;
            background: rgba(255,255,255,0.8);
            transition: all 0.2s ease;
        }

        .agendamento-item.priority-baixa { border-left-color: #28a745; }
        .agendamento-item.priority-media { border-left-color: #ffc107; }
        .agendamento-item.priority-alta { border-left-color: #fd7e14; }
        .agendamento-item.priority-urgente { border-left-color: #dc3545; }

        .agendamento-item:hover {
            background: rgba(111, 66, 193, 0.15);
            transform: translateX(3px);
            border-left-color: #6f42c1;
            border-radius: 4px;
            padding: 2px 4px;
            cursor: pointer;
        }

        .mais-agendamentos {
            font-size: 0.6em;
            color: #666;
            text-align: center;
            margin-top: 2px;
            font-style: italic;
        }

        .sidebar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .widget {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .widget-header {
            background: #003366;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .widget-content {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .agendamento-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .agendamento-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .agendamento-card.priority-baixa { border-left-color: #28a745; }
        .agendamento-card.priority-media { border-left-color: #ffc107; }
        .agendamento-card.priority-alta { border-left-color: #fd7e14; }
        .agendamento-card.priority-urgente { border-left-color: #dc3545; }

        .agendamento-card.passado {
            opacity: 0.9;
            background: #f8f9fa;
            border-left-color: #6c757d;
        }

        .agendamento-card.passado.nao-realizado {
            border-left-color: #ffc107;
            background: #fff9e6;
        }

        .agendamento-titulo {
            font-weight: bold;
            color: #003366;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .agendamento-info {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 3px;
        }

        .agendamento-acoes {
            margin-top: 10px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-agendado { background: #cce5ff; color: #004085; }
        .status-confirmado { background: #d4edda; color: #155724; }
        .status-realizado { background: #e2e3e5; color: #383d41; }
        .status-cancelado { background: #f8d7da; color: #721c24; }

        .tipo-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
            margin-left: 5px;
        }

        .tipo-retorno { background: #fff3cd; color: #856404; }
        .tipo-reuniao { background: #d1ecf1; color: #0c5460; }
        .tipo-visita { background: #d4edda; color: #155724; }
        .tipo-evento { background: #f8d7da; color: #721c24; }
        .tipo-outro { background: #e2e3e5; color: #383d41; }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .alertas-hoje {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px 20px;
        }

        .prioridade-icon {
            font-size: 1.2em;
            margin-right: 5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #003366;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        /* Estilos do modal de visualização */
        #modalVisualizarAgendamento .modal-content {
            max-width: 800px;
            animation: slideInFromTop 0.4s ease-out;
        }

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .agendamento-titulo-clicavel {
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border-radius: 4px;
            padding: 2px 4px;
            margin: -2px -4px;
        }

        .agendamento-titulo-clicavel:hover {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1 !important;
            text-decoration: none;
            transform: translateX(3px);
        }

        .view-status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .view-tipo-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            text-transform: capitalize;
        }

        @media (max-width: 768px) {
            .sidebar {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-calendario {
                justify-content: center;
            }
            
            .calendario-dia {
                min-height: 80px;
                font-size: 0.8em;
            }
            
            .numero-dia {
                font-size: 1em;
            }
            
            .agendamento-item {
                font-size: 0.6em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📅 Calendário de Agendamentos</h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary">← Voltar ao Dashboard</a>
                <a href="logout.php" class="btn btn-danger" style="margin-left: 15px;">Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Alertas de Hoje -->
        <?php if (!empty($agendamentos_hoje)): ?>
            <div class="alertas-hoje">
                <h3>🚨 Agendamentos de Hoje (<?php echo date('d/m/Y'); ?>)</h3>
                <p><strong><?php echo count($agendamentos_hoje); ?> agendamento(s)</strong> programado(s) para hoje:</p>
                <?php foreach ($agendamentos_hoje as $ag): ?>
                    <div style="margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.8); border-radius: 5px;">
                        <strong><?php echo date('H:i', strtotime($ag['hora_inicio'])); ?></strong> - 
                        <?php echo htmlspecialchars($ag['titulo']); ?>
                        <?php if ($ag['cliente_nome']): ?>
                            <br><small>👤 <?php echo htmlspecialchars($ag['cliente_nome']); ?> 
                            📱 <?php echo htmlspecialchars($ag['cliente_telefone']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Controles do Calendário -->
        <div class="top-actions">
            <div class="nav-calendario">
                <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                    <select name="mes" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>>
                                <?php echo ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                                           'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][$m]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <select name="ano" onchange="this.form.submit()">
                        <?php for ($a = date('Y') - 1; $a <= date('Y') + 2; $a++): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $ano ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <a href="?mes=<?php echo date('n'); ?>&ano=<?php echo date('Y'); ?>" class="btn btn-secondary">📅 Hoje</a>
                </form>
            </div>
            
            <button type="button" class="btn btn-success" onclick="abrirModalNovoAgendamento()">
                ➕ Novo Agendamento
            </button>
        </div>

        <!-- Calendário -->
        <div class="calendario-container">
            <div class="calendario-header-mes">
                <?php echo ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                           'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][$mes] . ' ' . $ano; ?>
            </div>
            
            <?php echo gerarCalendario($mes, $ano, $agendamentos_por_dia); ?>
        </div>

        <!-- Widgets Laterais -->
        <div class="sidebar">
            <!-- Agendamentos Pendentes e Próximos -->
            <div class="widget">
                <div class="widget-header">
                    📋 Agendamentos Pendentes
                </div>
                <div class="widget-content">
                    <?php 
                    // Buscar agendamentos passados não realizados
                    $stmt_pendentes = $pdo->prepare("
                        SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone,
                               DATEDIFF(CURDATE(), a.data_agendamento) as dias_atraso
                        FROM agendamentos a 
                        LEFT JOIN cadastros c ON a.cadastro_id = c.id
                        WHERE a.data_agendamento < CURDATE() 
                        AND a.status IN ('agendado', 'confirmado')
                        ORDER BY a.data_agendamento DESC, a.hora_inicio ASC
                        LIMIT 5
                    ");
                    $stmt_pendentes->execute();
                    $agendamentos_pendentes = $stmt_pendentes->fetchAll();
                    
                    // Buscar próximos agendamentos
                    $stmt_proximos = $pdo->prepare("
                        SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
                        FROM agendamentos a 
                        LEFT JOIN cadastros c ON a.cadastro_id = c.id
                        WHERE a.data_agendamento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                        AND a.status IN ('agendado', 'confirmado')
                        ORDER BY a.data_agendamento ASC, a.hora_inicio ASC
                        LIMIT 10
                    ");
                    $stmt_proximos->execute();
                    $agendamentos_proximos = $stmt_proximos->fetchAll();
                    ?>
                    
                    <?php if (!empty($agendamentos_pendentes)): ?>
                        <h5 style="color: #856404; margin: 0 0 15px 0; font-size: 1em;">⚠️ Agendamentos Passados Pendentes</h5>
                        <?php foreach ($agendamentos_pendentes as $ag): ?>
                            <div class="agendamento-card passado nao-realizado priority-<?php echo $ag['prioridade']; ?>">
                                <div class="agendamento-titulo">
                                    <span class="agendamento-titulo-clicavel" onclick="visualizarAgendamento(<?php echo $ag['id']; ?>)" title="Clique para ver detalhes">
                                        <span class="prioridade-icon">⏰</span>
                                        <?php echo htmlspecialchars($ag['titulo']); ?>
                                    </span>
                                    <span style="background: #ffc107; color: #856404; padding: 2px 6px; border-radius: 8px; font-size: 0.7em; margin-left: 5px;">
                                        <?php echo $ag['dias_atraso']; ?> dia(s) atrás
                                    </span>
                                </div>
                                
                                <div class="agendamento-info">
                                    📅 <?php echo date('d/m/Y', strtotime($ag['data_agendamento'])); ?> 
                                    🕐 <?php echo date('H:i', strtotime($ag['hora_inicio'])); ?>
                                    <?php if ($ag['hora_fim']): ?>
                                        - <?php echo date('H:i', strtotime($ag['hora_fim'])); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($ag['cliente_nome']): ?>
                                    <div class="agendamento-info">
                                        👤 <?php echo htmlspecialchars($ag['cliente_nome']); ?>
                                        📱 <?php echo htmlspecialchars($ag['cliente_telefone']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="agendamento-acoes acoes-passado">
                                    <button type="button" class="btn btn-primary" onclick="editarAgendamento(<?php echo $ag['id']; ?>)" style="font-size: 0.8em; padding: 4px 8px;">
                                        ✏️ Editar
                                    </button>
                                    <button type="button" class="btn btn-success" style="font-size: 0.8em; padding: 4px 8px;" onclick="marcarComoRealizado(<?php echo $ag['id']; ?>, '<?php echo addslashes($ag['titulo']); ?>')">
                                        ✅ Realizado
                                    </button>
                                    <button type="button" class="btn btn-danger" style="font-size: 0.8em; padding: 4px 8px;" onclick="marcarComoCancelado(<?php echo $ag['id']; ?>, '<?php echo addslashes($ag['titulo']); ?>')">
                                        ❌ Cancelar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #dee2e6;">
                    <?php endif; ?>
                    
                    <?php if (!empty($agendamentos_proximos)): ?>
                        <h5 style="color: #003366; margin: 0 0 15px 0; font-size: 1em;">🔮 Próximos 7 Dias</h5>
                        <?php foreach ($agendamentos_proximos as $ag): ?>
                            <div class="agendamento-card priority-<?php echo $ag['prioridade']; ?>">
                                <div class="agendamento-titulo">
                                    <span class="agendamento-titulo-clicavel" onclick="visualizarAgendamento(<?php echo $ag['id']; ?>)" title="Clique para ver detalhes">
                                        <span class="prioridade-icon">
                                            <?php echo ['baixa' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'urgente' => '🔴'][$ag['prioridade']]; ?>
                                        </span>
                                        <?php echo htmlspecialchars($ag['titulo']); ?>
                                    </span>
                                    <span class="status-badge status-<?php echo $ag['status']; ?>">
                                        <?php echo ucfirst($ag['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="agendamento-info">
                                    📅 <?php echo date('d/m/Y', strtotime($ag['data_agendamento'])); ?> 
                                    🕐 <?php echo date('H:i', strtotime($ag['hora_inicio'])); ?>
                                    <?php if ($ag['hora_fim']): ?>
                                        - <?php echo date('H:i', strtotime($ag['hora_fim'])); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($ag['cliente_nome']): ?>
                                    <div class="agendamento-info">
                                        👤 <?php echo htmlspecialchars($ag['cliente_nome']); ?>
                                        📱 <?php echo htmlspecialchars($ag['cliente_telefone']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($ag['local']): ?>
                                    <div class="agendamento-info">
                                        📍 <?php echo htmlspecialchars($ag['local']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="agendamento-acoes">
                                    <button type="button" class="btn btn-primary" onclick="editarAgendamento(<?php echo $ag['id']; ?>)" style="font-size: 0.8em; padding: 4px 8px;">
                                        ✏️ Editar
                                    </button>
                                    <button type="button" class="btn btn-success" style="font-size: 0.8em; padding: 4px 8px;" onclick="marcarComoRealizado(<?php echo $ag['id']; ?>, '<?php echo addslashes($ag['titulo']); ?>')">
                                        ✅ Realizado
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($agendamentos_pendentes) && empty($agendamentos_proximos)): ?>
                        <div class="empty-state">
                            ✅ Nenhum agendamento pendente ou próximo
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resumo do Mês -->
            <div class="widget">
                <div class="widget-header">
                    📊 Resumo do Mês
                </div>
                <div class="widget-content">
                    <?php 
                    $total_mes = count($agendamentos);
                    $por_status = [];
                    $por_tipo = [];
                    $por_prioridade = [];
                    
                    foreach ($agendamentos as $ag) {
                        $por_status[$ag['status']] = ($por_status[$ag['status']] ?? 0) + 1;
                        $por_tipo[$ag['tipo']] = ($por_tipo[$ag['tipo']] ?? 0) + 1;
                        $por_prioridade[$ag['prioridade']] = ($por_prioridade[$ag['prioridade']] ?? 0) + 1;
                    }
                    ?>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #003366; margin-bottom: 10px;">📈 Total: <?php echo $total_mes; ?> agendamentos</h4>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Por Status:</strong><br>
                        <?php foreach (['agendado', 'confirmado', 'realizado', 'cancelado'] as $status): ?>
                            <?php if (isset($por_status[$status])): ?>
                                <span class="status-badge status-<?php echo $status; ?>" style="margin: 2px;">
                                    <?php echo ucfirst($status); ?>: <?php echo $por_status[$status]; ?>
                                </span><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <strong>Por Tipo:</strong><br>
                        <?php foreach (['retorno', 'reuniao', 'visita', 'evento', 'outro'] as $tipo): ?>
                            <?php if (isset($por_tipo[$tipo])): ?>
                                <span class="tipo-badge tipo-<?php echo $tipo; ?>" style="margin: 2px;">
                                    <?php echo ucfirst($tipo); ?>: <?php echo $por_tipo[$tipo]; ?>
                                </span><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div>
                        <strong>Por Prioridade:</strong><br>
                        <?php foreach (['urgente', 'alta', 'media', 'baixa'] as $prioridade): ?>
                            <?php if (isset($por_prioridade[$prioridade])): ?>
                                <?php $icon = ['baixa' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'urgente' => '🔴'][$prioridade]; ?>
                                <div style="margin: 3px 0;">
                                    <?php echo $icon; ?> <?php echo ucfirst($prioridade); ?>: <?php echo $por_prioridade[$prioridade]; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <?php if (count($agendamentos) > 20): ?>
            <div style="display: flex; justify-content: center; margin-top: 20px; gap: 10px;">
                <span class="btn" style="background: #f8f9fa; color: #003366;">
                    Mostrando <?php echo count($agendamentos); ?> agendamentos
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Visualização Detalhada -->
    <div id="modalVisualizarAgendamento" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1, #8e44ad);">
                <h3 style="display: flex; align-items: center; gap: 10px;">
                    👁️ Detalhes do Agendamento
                    <span id="viewStatusBadge" class="status-badge" style="margin-left: auto;">Status</span>
                </h3>
                <button type="button" class="close" onclick="fecharModalVisualizacao()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Informações Principais -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #6f42c1;">
                    <h4 style="color: #6f42c1; margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                        📋 <span id="viewTitulo">Título do Agendamento</span>
                        <span id="viewPrioridadeIcon" style="font-size: 1.2em;">🟡</span>
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                        <div>
                            <strong style="color: #495057;">📅 Data:</strong><br>
                            <span id="viewData" style="font-size: 1.1em;">-</span>
                        </div>
                        <div>
                            <strong style="color: #495057;">🕐 Horário:</strong><br>
                            <span id="viewHorario" style="font-size: 1.1em;">-</span>
                        </div>
                        <div>
                            <strong style="color: #495057;">📂 Tipo:</strong><br>
                            <span id="viewTipo" class="tipo-badge">-</span>
                        </div>
                        <div>
                            <strong style="color: #495057;">📍 Local:</strong><br>
                            <span id="viewLocal" style="font-size: 1.1em;">-</span>
                        </div>
                    </div>
                </div>

                <!-- Cliente Vinculado -->
                <div id="viewClienteSection" style="background: #e7f3ff; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #0066cc; display: none;">
                    <h5 style="color: #0066cc; margin: 0 0 15px 0;">👤 Cliente Vinculado</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <strong>Nome:</strong><br>
                            <span id="viewClienteNome">-</span>
                        </div>
                        <div>
                            <strong>Telefone:</strong><br>
                            <span id="viewClienteTelefone">-</span>
                        </div>
                        <div>
                            <strong>Cidade:</strong><br>
                            <span id="viewClienteCidade">-</span>
                        </div>
                    </div>
                </div>

                <!-- Descrição -->
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #495057; margin: 0 0 10px 0;">📝 Descrição</h5>
                    <div id="viewDescricao" style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; min-height: 60px; white-space: pre-wrap; line-height: 1.6;">
                        Nenhuma descrição fornecida.
                    </div>
                </div>

                <!-- Observações -->
                <div style="margin-bottom: 20px;">
                    <h5 style="color: #495057; margin: 0 0 10px 0;">💭 Observações</h5>
                    <div id="viewObservacoes" style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; min-height: 60px; white-space: pre-wrap; line-height: 1.6;">
                        Nenhuma observação registrada.
                    </div>
                </div>

                <!-- Informações do Sistema -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6c757d;">
                    <h6 style="color: #6c757d; margin: 0 0 10px 0;">🛠️ Informações do Sistema</h6>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 0.9em; color: #6c757d;">
                        <div><strong>ID:</strong> <span id="viewId">-</span></div>
                        <div><strong>Criado por:</strong> <span id="viewCriadoPor">-</span></div>
                        <div><strong>Data de criação:</strong> <span id="viewDataCriacao">-</span></div>
                        <div><strong>Última atualização:</strong> <span id="viewUltimaAtualizacao">-</span></div>
                        <div><strong>Lembrete:</strong> <span id="viewLembrete">-</span></div>
                    </div>
                </div>

                <!-- Ações -->
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalVisualizacao()">
                        ❌ Fechar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editarDaVisualizacao()" id="btnEditarDaVisualizacao">
                        ✏️ Editar
                    </button>
                    <button type="button" class="btn btn-success" onclick="marcarRealizadoDaVisualizacao()" id="btnRealizadoDaVisualizacao" style="display: none;">
                        ✅ Marcar como Realizado
                    </button>
                    <button type="button" class="btn btn-warning" onclick="marcarConfirmadoDaVisualizacao()" id="btnConfirmadoDaVisualizacao" style="display: none;">
                        📋 Marcar como Confirmado
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Novo/Editar Agendamento -->
    <div id="modalAgendamento" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">➕ Novo Agendamento</h3>
                <button type="button" class="close" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="formAgendamento">
                    <input type="hidden" name="action" id="formAction" value="criar_agendamento">
                    <input type="hidden" name="id" id="agendamentoId">
                    
                    <div class="form-group">
                        <label for="titulo">Título *</label>
                        <input type="text" id="titulo" name="titulo" required maxlength="255">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_agendamento">Data *</label>
                            <input type="date" id="data_agendamento" name="data_agendamento" required>
                        </div>
                        <div class="form-group">
                            <label for="cadastro_id">Cliente (opcional)</label>
                            <select id="cadastro_id" name="cadastro_id">
                                <option value="">Selecione um cliente...</option>
                                <?php foreach ($cadastros as $cadastro): ?>
                                    <option value="<?php echo $cadastro['id']; ?>">
                                        <?php echo htmlspecialchars($cadastro['nome'] . ' - ' . $cadastro['cidade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="hora_inicio">Hora Início *</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" required>
                        </div>
                        <div class="form-group">
                            <label for="hora_fim">Hora Fim</label>
                            <input type="time" id="hora_fim" name="hora_fim">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo">
                                <option value="retorno">Retorno</option>
                                <option value="reuniao">Reunião</option>
                                <option value="visita">Visita</option>
                                <option value="evento">Evento</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prioridade">Prioridade</label>
                            <select id="prioridade" name="prioridade">
                                <option value="baixa">🟢 Baixa</option>
                                <option value="media" selected>🟡 Média</option>
                                <option value="alta">🟠 Alta</option>
                                <option value="urgente">🔴 Urgente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="statusRow" style="display: none;">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="agendado">Agendado</option>
                                <option value="confirmado">Confirmado</option>
                                <option value="realizado">Realizado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="lembrete_antecedencia">Lembrete (minutos antes)</label>
                            <select id="lembrete_antecedencia" name="lembrete_antecedencia">
                                <option value="15">15 minutos</option>
                                <option value="30">30 minutos</option>
                                <option value="60" selected>1 hora</option>
                                <option value="120">2 horas</option>
                                <option value="1440">1 dia</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="local">Local</label>
                        <input type="text" id="local" name="local" maxlength="255" placeholder="Gabinete, Câmara Municipal, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" placeholder="Detalhes do agendamento..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" placeholder="Observações internas..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="btnSalvar">💾 Salvar</button>
                        <button type="button" class="btn btn-danger" id="btnExcluir" onclick="excluirAgendamento()" style="display: none;">🗑️ Excluir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Dados dos agendamentos para JavaScript
        const agendamentosData = <?php echo json_encode($agendamentos, JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        
        // Variáveis globais
        let agendamentoAtualVisualizacao = null;

        // 1. FUNÇÃO PARA VISUALIZAR AGENDAMENTO
        function visualizarAgendamento(id) {
            console.log('👁️ Visualizando agendamento ID:', id);
            
            if (!agendamentosData || !Array.isArray(agendamentosData)) {
                alert('Erro: Dados não carregados. Recarregue a página.');
                return;
            }
            
            const agendamento = agendamentosData.find(a => a.id == id);
            if (!agendamento) {
                alert('Agendamento não encontrado!');
                console.log('IDs disponíveis:', agendamentosData.map(a => a.id));
                return;
            }
            
            fecharModal();
            fecharModalVisualizacao();
            
            agendamentoAtualVisualizacao = agendamento;
            
            // Preencher dados do modal
            document.getElementById('viewTitulo').textContent = agendamento.titulo;
            
            const statusBadge = document.getElementById('viewStatusBadge');
            statusBadge.textContent = agendamento.status.charAt(0).toUpperCase() + agendamento.status.slice(1);
            statusBadge.className = `status-badge view-status-badge status-${agendamento.status}`;
            
            const prioridadeIcons = {
                'baixa': '🟢',
                'media': '🟡', 
                'alta': '🟠',
                'urgente': '🔴'
            };
            document.getElementById('viewPrioridadeIcon').textContent = prioridadeIcons[agendamento.prioridade];
            
            const dataObj = new Date(agendamento.data_agendamento + 'T00:00:00');
            const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('viewData').textContent = dataFormatada;
            
            let horario = agendamento.hora_inicio.slice(0, 5);
            if (agendamento.hora_fim) {
                horario += ' às ' + agendamento.hora_fim.slice(0, 5);
            }
            document.getElementById('viewHorario').textContent = horario;
            
            const tipoBadge = document.getElementById('viewTipo');
            tipoBadge.textContent = agendamento.tipo.charAt(0).toUpperCase() + agendamento.tipo.slice(1);
            tipoBadge.className = `view-tipo-badge tipo-${agendamento.tipo}`;
            
            document.getElementById('viewLocal').textContent = agendamento.local || 'Não informado';
            
            const clienteSection = document.getElementById('viewClienteSection');
            if (agendamento.cliente_nome) {
                clienteSection.style.display = 'block';
                document.getElementById('viewClienteNome').textContent = agendamento.cliente_nome;
                document.getElementById('viewClienteTelefone').textContent = agendamento.cliente_telefone || 'Não informado';
                document.getElementById('viewClienteCidade').textContent = agendamento.cliente_cidade || 'Não informado';
            } else {
                clienteSection.style.display = 'none';
            }
            
            const descricaoDiv = document.getElementById('viewDescricao');
            if (agendamento.descricao && agendamento.descricao.trim()) {
                descricaoDiv.textContent = agendamento.descricao;
                descricaoDiv.style.fontStyle = 'normal';
                descricaoDiv.style.color = '#333';
            } else {
                descricaoDiv.textContent = 'Nenhuma descrição fornecida.';
                descricaoDiv.style.fontStyle = 'italic';
                descricaoDiv.style.color = '#999';
            }
            
            const observacoesDiv = document.getElementById('viewObservacoes');
            if (agendamento.observacoes && agendamento.observacoes.trim()) {
                observacoesDiv.textContent = agendamento.observacoes;
                observacoesDiv.style.fontStyle = 'normal';
                observacoesDiv.style.color = '#333';
            } else {
                observacoesDiv.textContent = 'Nenhuma observação registrada.';
                observacoesDiv.style.fontStyle = 'italic';
                observacoesDiv.style.color = '#999';
            }
            
            document.getElementById('viewId').textContent = agendamento.id;
            document.getElementById('viewCriadoPor').textContent = agendamento.criado_por_nome || 'N/A';
            
            if (agendamento.data_criacao) {
                const dataCriacao = new Date(agendamento.data_criacao);
                document.getElementById('viewDataCriacao').textContent = dataCriacao.toLocaleString('pt-BR');
            }
            
            if (agendamento.data_atualizacao) {
                const dataAtualizacao = new Date(agendamento.data_atualizacao);
                document.getElementById('viewUltimaAtualizacao').textContent = dataAtualizacao.toLocaleString('pt-BR');
            } else {
                document.getElementById('viewUltimaAtualizacao').textContent = 'Nunca alterado';
            }
            
            const lembrete = agendamento.lembrete_antecedencia || 60;
            let lembreteTexto = '';
            if (lembrete < 60) {
                lembreteTexto = `${lembrete} minutos antes`;
            } else if (lembrete < 1440) {
                lembreteTexto = `${Math.floor(lembrete / 60)} hora(s) antes`;
            } else {
                lembreteTexto = `${Math.floor(lembrete / 1440)} dia(s) antes`;
            }
            document.getElementById('viewLembrete').textContent = lembreteTexto;
            
            const btnRealizado = document.getElementById('btnRealizadoDaVisualizacao');
            const btnConfirmado = document.getElementById('btnConfirmadoDaVisualizacao');
            
            if (['agendado', 'confirmado'].includes(agendamento.status)) {
                btnRealizado.style.display = 'inline-flex';
            } else {
                btnRealizado.style.display = 'none';
            }
            
            if (agendamento.status === 'agendado') {
                btnConfirmado.style.display = 'inline-flex';
            } else {
                btnConfirmado.style.display = 'none';
            }
            
            document.getElementById('modalVisualizarAgendamento').classList.add('show');
            console.log('✅ Modal de visualização aberto!');
        }

        // 2. FUNÇÕES DOS MODAIS
        function fecharModalVisualizacao() {
            document.getElementById('modalVisualizarAgendamento').classList.remove('show');
            agendamentoAtualVisualizacao = null;
        }

        function fecharModal() {
            document.getElementById('modalAgendamento').classList.remove('show');
        }

        function editarDaVisualizacao() {
    console.log('🔧 Tentando editar da visualização...');
    
    // Método seguro - buscar ID do modal
    const idElement = document.getElementById('viewId');
    if (idElement && idElement.textContent) {
        const id = parseInt(idElement.textContent);
        if (id > 0) {
            console.log('✅ Editando agendamento ID:', id);
            fecharModalVisualizacao();
            setTimeout(() => editarAgendamento(id), 200);
            return;
        }
    }
    
    // Fallback - tentar usar variável global
    if (agendamentoAtualVisualizacao && agendamentoAtualVisualizacao.id) {
        fecharModalVisualizacao();
        setTimeout(() => editarAgendamento(agendamentoAtualVisualizacao.id), 200);
    } else {
        alert('Erro: Não foi possível identificar o agendamento. Feche e abra novamente os detalhes.');
    }
}

function marcarRealizadoDaVisualizacao() {
    const idElement = document.getElementById('viewId');
    const tituloElement = document.getElementById('viewTitulo');
    
    if (idElement && tituloElement && idElement.textContent && tituloElement.textContent) {
        const id = parseInt(idElement.textContent);
        const titulo = tituloElement.textContent;
        if (id > 0) {
            marcarComoRealizado(id, titulo);
            return;
        }
    }
    
    if (agendamentoAtualVisualizacao) {
        marcarComoRealizado(agendamentoAtualVisualizacao.id, agendamentoAtualVisualizacao.titulo);
    } else {
        alert('Erro: Não foi possível identificar o agendamento.');
    }
}

function marcarConfirmadoDaVisualizacao() {
    const idElement = document.getElementById('viewId');
    const tituloElement = document.getElementById('viewTitulo');
    
    if (idElement && tituloElement && idElement.textContent && tituloElement.textContent) {
        const id = parseInt(idElement.textContent);
        const titulo = tituloElement.textContent;
        
        if (id > 0 && confirm(`Marcar "${titulo}" como confirmado?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="action" value="alterar_status">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="status" value="confirmado">
            `;
            document.body.appendChild(form);
            form.submit();
            return;
        }
    }
    
    if (agendamentoAtualVisualizacao && confirm(`Marcar "${agendamentoAtualVisualizacao.titulo}" como confirmado?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="alterar_status">
            <input type="hidden" name="id" value="${agendamentoAtualVisualizacao.id}">
            <input type="hidden" name="status" value="confirmado">
        `;
        document.body.appendChild(form);
        form.submit();
    } else {
        alert('Erro: Não foi possível identificar o agendamento.');
    }
}

        // 3. FUNÇÕES PARA CRIAR/EDITAR AGENDAMENTOS
        function abrirModalNovoAgendamento(data = null) {
            document.getElementById('modalTitulo').textContent = '➕ Novo Agendamento';
            document.getElementById('formAction').value = 'criar_agendamento';
            document.getElementById('agendamentoId').value = '';
            document.getElementById('statusRow').style.display = 'none';
            document.getElementById('btnExcluir').style.display = 'none';
            document.getElementById('btnSalvar').textContent = '💾 Salvar';
            
            document.getElementById('formAgendamento').reset();
            document.getElementById('prioridade').value = 'media';
            
            if (data) {
                document.getElementById('data_agendamento').value = data;
            }
            
            document.getElementById('modalAgendamento').classList.add('show');
        }

        function editarAgendamento(id) {
            console.log('✏️ Editando agendamento ID:', id);
            
            const agendamento = agendamentosData.find(a => a.id == id);
            if (!agendamento) {
                alert('Agendamento não encontrado!');
                return;
            }
            
            const dataAgendamento = new Date(agendamento.data_agendamento);
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            const isPassado = dataAgendamento < hoje;
            
            document.getElementById('modalTitulo').textContent = isPassado ? '⏰ Editar Agendamento Passado' : '✏️ Editar Agendamento';
            document.getElementById('formAction').value = 'editar_agendamento';
            document.getElementById('agendamentoId').value = agendamento.id;
            document.getElementById('statusRow').style.display = 'grid';
            document.getElementById('btnExcluir').style.display = 'inline-flex';
            document.getElementById('btnSalvar').textContent = '💾 Atualizar';
            
            document.getElementById('titulo').value = agendamento.titulo;
            document.getElementById('descricao').value = agendamento.descricao || '';
            document.getElementById('data_agendamento').value = agendamento.data_agendamento;
            document.getElementById('hora_inicio').value = agendamento.hora_inicio;
            document.getElementById('hora_fim').value = agendamento.hora_fim || '';
            document.getElementById('tipo').value = agendamento.tipo;
            document.getElementById('status').value = agendamento.status;
            document.getElementById('prioridade').value = agendamento.prioridade;
            document.getElementById('local').value = agendamento.local || '';
            document.getElementById('observacoes').value = agendamento.observacoes || '';
            document.getElementById('lembrete_antecedencia').value = agendamento.lembrete_antecedencia;
            document.getElementById('cadastro_id').value = agendamento.cadastro_id || '';
            
            if (isPassado && ['agendado', 'confirmado'].includes(agendamento.status)) {
                const alertaPassado = document.createElement('div');
                alertaPassado.id = 'alertaPassado';
                alertaPassado.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px;';
                alertaPassado.innerHTML = `
                    <strong>⚠️ Agendamento Passado</strong><br>
                    Este agendamento era para ${dataAgendamento.toLocaleDateString('pt-BR')} e ainda não foi marcado como realizado ou cancelado.
                    <br><small>Recomenda-se atualizar o status adequadamente.</small>
                `;
                
                const alertaAnterior = document.getElementById('alertaPassado');
                if (alertaAnterior) {
                    alertaAnterior.remove();
                }
                
                const modalBody = document.querySelector('#modalAgendamento .modal-body');
                modalBody.insertBefore(alertaPassado, modalBody.firstChild);
            }
            
            document.getElementById('modalAgendamento').classList.add('show');
        }

        function excluirAgendamento() {
            if (!confirm('Tem certeza que deseja excluir este agendamento?')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="excluir_agendamento">
                <input type="hidden" name="id" value="${document.getElementById('agendamentoId').value}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // 4. FUNÇÕES PARA ALTERAR STATUS
        function marcarComoRealizado(id, titulo) {
            if (!id || id <= 0) {
                alert('ID do agendamento inválido.');
                return;
            }
            
            if (confirm(`Marcar "${titulo}" como realizado?`)) {
                const btn = event.target;
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '⏳ Processando...';
                btn.disabled = true;
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="alterar_status">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="status" value="realizado">
                    <input type="hidden" name="token" value="${Date.now()}">
                `;
                
                document.body.appendChild(form);
                
                setTimeout(() => {
                    try {
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao submeter formulário:', error);
                        btn.innerHTML = textoOriginal;
                        btn.disabled = false;
                        alert('Erro ao processar. Tente novamente.');
                    }
                }, 100);
            }
        }

        function marcarComoCancelado(id, titulo) {
            if (!id || id <= 0) {
                alert('ID do agendamento inválido.');
                return;
            }
            
            if (confirm(`Marcar "${titulo}" como cancelado?`)) {
                const btn = event.target;
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '⏳ Processando...';
                btn.disabled = true;
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="alterar_status">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="status" value="cancelado">
                    <input type="hidden" name="token" value="${Date.now()}">
                `;
                
                document.body.appendChild(form);
                
                setTimeout(() => {
                    try {
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao submeter formulário:', error);
                        btn.innerHTML = textoOriginal;
                        btn.disabled = false;
                        alert('Erro ao processar. Tente novamente.');
                    }
                }, 100);
            }
        }

        // 5. CONFIGURAR EVENTOS QUANDO A PÁGINA CARREGAR
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Configurando eventos...');
            setTimeout(function() {
                configurarEventos();
            }, 1000);
        });

        function configurarEventos() {
            console.log('⚙️ Configurando cliques nos agendamentos...');
            
            adicionarBotoesVer();
            configurarCalendario();
            configurarFechamentoModais();
            
            console.log('✅ Todos os eventos configurados!');
        }

        function adicionarBotoesVer() {
            const cards = document.querySelectorAll('.agendamento-card');
            console.log(`📋 Encontrados ${cards.length} cards de agendamento`);
            
            cards.forEach((card, index) => {
                if (card.querySelector('.btn-ver-detalhes')) return;
                
                const btnEditar = card.querySelector('button[onclick*="editarAgendamento"]');
                if (btnEditar) {
                    const onclick = btnEditar.getAttribute('onclick');
                    const idMatch = onclick.match(/editarAgendamento\((\d+)\)/);
                    if (idMatch) {
                        const id = idMatch[1];
                        
                        const btnVer = document.createElement('button');
                        btnVer.type = 'button';
                        btnVer.className = 'btn btn-ver-detalhes';
                        btnVer.style.cssText = 'background: #6f42c1; color: white; font-size: 0.8em; padding: 4px 8px; margin-right: 5px; border: none; border-radius: 4px; cursor: pointer;';
                        btnVer.innerHTML = '👁️ Ver';
                        btnVer.onclick = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            visualizarAgendamento(id);
                        };
                        
                        const acoesDiv = card.querySelector('.agendamento-acoes');
                        if (acoesDiv) {
                            acoesDiv.insertBefore(btnVer, acoesDiv.firstChild);
                        }
                        
                        console.log(`✅ Botão "Ver" adicionado ao card ${index + 1} (ID: ${id})`);
                    }
                }
            });
        }

        function configurarCalendario() {
            const dias = document.querySelectorAll('.calendario-dia:not(.vazio)');
            console.log(`📅 Encontrados ${dias.length} dias no calendário`);
            
            dias.forEach(dia => {
                dia.addEventListener('click', function(e) {
                    if (e.target.closest('.agendamento-item')) {
                        return;
                    }
                    
                    const data = this.getAttribute('data-data');
                    if (data) {
                        abrirModalNovoAgendamento(data);
                    }
                });
            });
            
            const agendamentosCalendario = document.querySelectorAll('.agendamento-item-calendario');
            console.log(`📊 Encontrados ${agendamentosCalendario.length} agendamentos no calendário mensal`);
            
            agendamentosCalendario.forEach((item, index) => {
                const agendamentoId = item.getAttribute('data-agendamento-id');
                
                if (agendamentoId) {
                    item.style.cssText += 'cursor: pointer; transition: all 0.2s ease;';
                    item.title = 'Clique para ver detalhes';
                    
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        console.log(`🎯 Clicou no agendamento ID: ${agendamentoId}`);
                        visualizarAgendamento(agendamentoId);
                    });
                    
                    console.log(`✅ Agendamento ${index + 1} configurado (ID: ${agendamentoId})`);
                }
            });
        }

        function configurarFechamentoModais() {
            document.getElementById('modalVisualizarAgendamento').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalVisualizacao();
                }
            });
            
            document.getElementById('modalAgendamento').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModal();
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    fecharModalVisualizacao();
                    fecharModal();
                }
            });
        }

        // 6. VALIDAÇÕES DO FORMULÁRIO
        document.getElementById('formAgendamento').addEventListener('submit', function(e) {
            const horaInicio = document.getElementById('hora_inicio').value;
            const horaFim = document.getElementById('hora_fim').value;
            
            if (horaFim && horaInicio >= horaFim) {
                e.preventDefault();
                alert('A hora de fim deve ser posterior à hora de início.');
                return false;
            }
            
            const alertaPassado = document.getElementById('alertaPassado');
            if (alertaPassado) {
                alertaPassado.remove();
            }
        });

        // Auto-hide mensagens de sucesso
        const messages = document.querySelectorAll('.message.success');
        messages.forEach(function(msg) {
            setTimeout(function() {
                msg.style.display = 'none';
            }, 5000);
        });

        console.log('🚀 Sistema completo carregado!');
        console.log('📊 Dados disponíveis:', agendamentosData.length + ' agendamentos');
    </script>
</body>
</html>