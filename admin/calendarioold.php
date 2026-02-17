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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            $db = new Database();
            $pdo = $db->getConnection();
            
            switch ($_POST['action']) {
                case 'criar_agendamento':
                    $cadastro_id = !empty($_POST['cadastro_id']) ? (int)$_POST['cadastro_id'] : null;
                    $titulo = sanitizeInput($_POST['titulo']);
                    $descricao = sanitizeInput($_POST['descricao']);
                    $data_agendamento = sanitizeInput($_POST['data_agendamento']);
                    $hora_inicio = sanitizeInput($_POST['hora_inicio']);
                    $hora_fim = sanitizeInput($_POST['hora_fim']);
                    $tipo = sanitizeInput($_POST['tipo']);
                    $prioridade = sanitizeInput($_POST['prioridade']);
                    $local = sanitizeInput($_POST['local']);
                    $observacoes = sanitizeInput($_POST['observacoes']);
                    $lembrete = (int)$_POST['lembrete_antecedencia'];
                    
                    if (empty($titulo) || empty($data_agendamento) || empty($hora_inicio)) {
                        throw new Exception('Título, data e hora de início são obrigatórios.');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO agendamentos (cadastro_id, titulo, descricao, data_agendamento, hora_inicio, hora_fim, tipo, prioridade, local, observacoes, lembrete_antecedencia, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$cadastro_id, $titulo, $descricao, $data_agendamento, $hora_inicio, $hora_fim ?: null, $tipo, $prioridade, $local, $observacoes, $lembrete, $admin['id']])) {
                        logActivity('admin_action', "Agendamento criado: $titulo para $data_agendamento por " . $admin['usuario']);
                        $message = "Agendamento criado com sucesso!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'editar_agendamento':
                    $id = (int)$_POST['id'];
                    $cadastro_id = !empty($_POST['cadastro_id']) ? (int)$_POST['cadastro_id'] : null;
                    $titulo = sanitizeInput($_POST['titulo']);
                    $descricao = sanitizeInput($_POST['descricao']);
                    $data_agendamento = sanitizeInput($_POST['data_agendamento']);
                    $hora_inicio = sanitizeInput($_POST['hora_inicio']);
                    $hora_fim = sanitizeInput($_POST['hora_fim']);
                    $tipo = sanitizeInput($_POST['tipo']);
                    $status = sanitizeInput($_POST['status']);
                    $prioridade = sanitizeInput($_POST['prioridade']);
                    $local = sanitizeInput($_POST['local']);
                    $observacoes = sanitizeInput($_POST['observacoes']);
                    $lembrete = (int)$_POST['lembrete_antecedencia'];
                    
                    if (empty($titulo) || empty($data_agendamento) || empty($hora_inicio)) {
                        throw new Exception('Título, data e hora de início são obrigatórios.');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE agendamentos SET cadastro_id = ?, titulo = ?, descricao = ?, data_agendamento = ?, hora_inicio = ?, hora_fim = ?, tipo = ?, status = ?, prioridade = ?, local = ?, observacoes = ?, lembrete_antecedencia = ? WHERE id = ?");
                    
                    if ($stmt->execute([$cadastro_id, $titulo, $descricao, $data_agendamento, $hora_inicio, $hora_fim ?: null, $tipo, $status, $prioridade, $local, $observacoes, $lembrete, $id])) {
                        logActivity('admin_action', "Agendamento ID $id editado por " . $admin['usuario']);
                        $message = "Agendamento atualizado com sucesso!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'excluir_agendamento':
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        logActivity('admin_action', "Agendamento ID $id excluído por " . $admin['usuario']);
                        $message = "Agendamento excluído com sucesso!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'alterar_status':
                    $id = (int)$_POST['id'];
                    $status = sanitizeInput($_POST['status']);
                    $stmt = $pdo->prepare("UPDATE agendamentos SET status = ? WHERE id = ?");
                    if ($stmt->execute([$status, $id])) {
                        logActivity('admin_action', "Status do agendamento ID $id alterado para $status por " . $admin['usuario']);
                        $message = "Status alterado com sucesso!";
                        $messageType = 'success';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
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
           admin.nome as criado_por_nome
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

// Verificar agendamentos próximos (próximos 7 dias)
$stmt_proximos = $pdo->prepare("
    SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone
    FROM agendamentos a 
    LEFT JOIN cadastros c ON a.cadastro_id = c.id
    WHERE a.data_agendamento BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) 
    AND a.status IN ('agendado', 'confirmado')
    ORDER BY a.data_agendamento ASC, a.hora_inicio ASC
");
$stmt_proximos->execute([$hoje, $hoje]);
$agendamentos_proximos = $stmt_proximos->fetchAll();

// Função para gerar calendário
function gerarCalendario($mes, $ano, $agendamentos_por_dia) {
    $primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);
    $nome_mes = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    
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
            
            // Preview dos agendamentos
            $html .= '<div class="agendamentos-preview">';
            foreach (array_slice($agendamentos_por_dia[$dia], 0, 3) as $ag) {
                $prioridade_icon = [
                    'baixa' => '🟢',
                    'media' => '🟡', 
                    'alta' => '🟠',
                    'urgente' => '🔴'
                ][$ag['prioridade']];
                
                $html .= '<div class="agendamento-item priority-' . $ag['prioridade'] . '">';
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
        }

        .agendamento-item.priority-baixa { border-left-color: #28a745; }
        .agendamento-item.priority-media { border-left-color: #ffc107; }
        .agendamento-item.priority-alta { border-left-color: #fd7e14; }
        .agendamento-item.priority-urgente { border-left-color: #dc3545; }

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
            <!-- Próximos Agendamentos -->
            <div class="widget">
                <div class="widget-header">
                    🔮 Próximos 7 Dias
                </div>
                <div class="widget-content">
                    <?php if (empty($agendamentos_proximos)): ?>
                        <div class="empty-state">
                            Nenhum agendamento nos próximos 7 dias
                        </div>
                    <?php else: ?>
                        <?php foreach ($agendamentos_proximos as $ag): ?>
                            <div class="agendamento-card priority-<?php echo $ag['prioridade']; ?>">
                                <div class="agendamento-titulo">
                                    <span class="prioridade-icon">
                                        <?php echo ['baixa' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'urgente' => '🔴'][$ag['prioridade']]; ?>
                                    </span>
                                    <?php echo htmlspecialchars($ag['titulo']); ?>
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
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="alterar_status">
                                        <input type="hidden" name="id" value="<?php echo $ag['id']; ?>">
                                        <input type="hidden" name="status" value="realizado">
                                        <button type="submit" class="btn btn-success" style="font-size: 0.8em; padding: 4px 8px;" onclick="return confirm('Marcar como realizado?')">
                                            ✅ Realizado
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
        
        function abrirModalNovoAgendamento(data = null) {
            document.getElementById('modalTitulo').textContent = '➕ Novo Agendamento';
            document.getElementById('formAction').value = 'criar_agendamento';
            document.getElementById('agendamentoId').value = '';
            document.getElementById('statusRow').style.display = 'none';
            document.getElementById('btnExcluir').style.display = 'none';
            document.getElementById('btnSalvar').textContent = '💾 Salvar';
            
            // Limpar formulário
            document.getElementById('formAgendamento').reset();
            document.getElementById('prioridade').value = 'media';
            
            // Se uma data foi clicada no calendário
            if (data) {
                document.getElementById('data_agendamento').value = data;
            }
            
            document.getElementById('modalAgendamento').classList.add('show');
        }
        
        function editarAgendamento(id) {
            const agendamento = agendamentosData.find(a => a.id == id);
            if (!agendamento) {
                alert('Agendamento não encontrado!');
                return;
            }
            
            document.getElementById('modalTitulo').textContent = '✏️ Editar Agendamento';
            document.getElementById('formAction').value = 'editar_agendamento';
            document.getElementById('agendamentoId').value = agendamento.id;
            document.getElementById('statusRow').style.display = 'grid';
            document.getElementById('btnExcluir').style.display = 'inline-flex';
            document.getElementById('btnSalvar').textContent = '💾 Atualizar';
            
            // Preencher formulário
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
        
        function fecharModal() {
            document.getElementById('modalAgendamento').classList.remove('show');
        }
        
        // Fechar modal clicando no fundo
        document.getElementById('modalAgendamento').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // Adicionar eventos de clique nos dias do calendário
        document.addEventListener('DOMContentLoaded', function() {
            const dias = document.querySelectorAll('.calendario-dia:not(.vazio)');
            dias.forEach(dia => {
                dia.addEventListener('click', function() {
                    const data = this.getAttribute('data-data');
                    if (data) {
                        abrirModalNovoAgendamento(data);
                    }
                });
            });
            
            // Auto-hide mensagens de sucesso
            const messages = document.querySelectorAll('.message.success');
            messages.forEach(function(msg) {
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 5000);
            });
        });
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModal();
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                abrirModalNovoAgendamento();
            }
        });
        
        // Validação do formulário
        document.getElementById('formAgendamento').addEventListener('submit', function(e) {
            const horaInicio = document.getElementById('hora_inicio').value;
            const horaFim = document.getElementById('hora_fim').value;
            
            if (horaFim && horaInicio >= horaFim) {
                e.preventDefault();
                alert('A hora de fim deve ser posterior à hora de início.');
                return false;
            }
        });
        
        console.log('Sistema de calendário carregado! Agendamentos:', agendamentosData.length);
    </script>
</body>
</html>