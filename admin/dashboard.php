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
                case 'delete':
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("DELETE FROM cadastros WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        logActivity('admin_action', "Cadastro ID $id deletado por " . $admin['usuario']);
                        $message = "Cadastro excluído com sucesso!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'toggle_status':
                    $id = (int)$_POST['id'];
                    $status = $_POST['status'] == 'ativo' ? 'inativo' : 'ativo';
                    $stmt = $pdo->prepare("UPDATE cadastros SET status = ? WHERE id = ?");
                    if ($stmt->execute([$status, $id])) {
                        logActivity('admin_action', "Status do cadastro ID $id alterado para $status por " . $admin['usuario']);
                        $message = "Status alterado com sucesso!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'edit':
                    $id = (int)$_POST['id'];
                    $nome = sanitizeInput($_POST['nome']);
                    $cidade = sanitizeInput($_POST['cidade']);
                    $cargo = sanitizeInput($_POST['cargo']);
                    $telefone = sanitizeInput($_POST['telefone']);
                    $email = sanitizeInput($_POST['email']); // Campo email
                    $data_nascimento_input = sanitizeInput($_POST['data_nascimento']);
                    $observacoes = sanitizeInput($_POST['observacoes']);
                    $observacoes_admin = sanitizeInput($_POST['observacoes_admin']);
                    
                    // Converter data se necessário (DD/MM/AAAA para YYYY-MM-DD)
                    $data_nascimento = $data_nascimento_input;
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data_nascimento_input)) {
                        $partes = explode('/', $data_nascimento_input);
                        $data_nascimento = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
                    }
                    
                    if (empty($nome) || empty($cidade) || empty($cargo) || empty($telefone) || empty($data_nascimento)) {
                        throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
                    }
                    
                    // Validar email se fornecido
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Formato de email inválido.');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE cadastros SET nome = ?, cidade = ?, cargo = ?, telefone = ?, email = ?, data_nascimento = ?, observacoes = ?, observacoes_admin = ? WHERE id = ?");
                    if ($stmt->execute([$nome, $cidade, $cargo, $telefone, $email, $data_nascimento, $observacoes, $observacoes_admin, $id])) {
                        logActivity('admin_action', "Cadastro ID $id editado por " . $admin['usuario'] . " - Obs Admin: " . substr($observacoes_admin, 0, 50));
                        $message = "Cadastro atualizado com sucesso!";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao atualizar cadastro.');
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar cadastros
$db = new Database();
$pdo = $db->getConnection();

// Filtros
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$cidade_filter = $_GET['cidade'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(nome LIKE ? OR cargo LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($cidade_filter) {
    $where_conditions[] = "cidade = ?";
    $params[] = $cidade_filter;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Paginação
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Contar total
$count_sql = "SELECT COUNT(*) as total FROM cadastros $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Buscar registros
$sql = "SELECT * FROM cadastros $where_clause ORDER BY data_cadastro DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cadastros = $stmt->fetchAll();

// Buscar cidades para filtro
$cidades_stmt = $pdo->query("SELECT DISTINCT cidade FROM cadastros ORDER BY cidade");
$cidades = $cidades_stmt->fetchAll(PDO::FETCH_COLUMN);

// Estatísticas
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'ativo' THEN 1 END) as ativos,
        COUNT(CASE WHEN DATE(data_cadastro) = CURDATE() THEN 1 END) as hoje,
        COUNT(CASE WHEN WEEK(data_cadastro) = WEEK(NOW()) THEN 1 END) as semana,
        COUNT(CASE WHEN email IS NOT NULL AND email != '' THEN 1 END) as com_email
    FROM cadastros
");
$stats = $stats_stmt->fetch();

// Buscar agendamentos de hoje para notificação
$agendamentos_hoje_stmt = $pdo->query("
    SELECT COUNT(*) as total_hoje,
           COUNT(CASE WHEN prioridade IN ('alta', 'urgente') THEN 1 END) as urgentes_hoje
    FROM agendamentos 
    WHERE data_agendamento = CURDATE() 
    AND status IN ('agendado', 'confirmado')
");
$agendamentos_hoje_info = $agendamentos_hoje_stmt->fetch();

// Buscar próximos agendamentos (próximos 3 dias)
$proximos_agendamentos_stmt = $pdo->query("
    SELECT COUNT(*) as total_proximos
    FROM agendamentos 
    WHERE data_agendamento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND status IN ('agendado', 'confirmado')
");
$proximos_agendamentos_info = $proximos_agendamentos_stmt->fetch();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - <?php echo SITE_NAME; ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 5px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1.1em;
            text-align: center;
            min-width: 36px;
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary { background: #0066cc; color: white; }
        .btn-secondary { background: #6c757d; color: white; text-decoration: none; }
        .btn-edit { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }

        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-buttons .btn {
            flex: 1;
            min-width: 90px;
            font-size: 0.85em;
            padding: 10px 12px;
            text-align: center;
            white-space: nowrap;
            min-height: auto;
        }

        /* Tooltip para ações */
        .actions .btn {
            position: relative;
        }

        .actions .btn:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7em;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
        }

        .actions .btn:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #333;
            z-index: 1000;
            margin-bottom: 1px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }

        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #003366;
        }

        .actions {
            display: flex;
            gap: 3px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .actions .btn {
            font-size: 1em;
            min-width: 32px;
            min-height: 32px;
            padding: 6px;
        }

        .actions form {
            display: inline-block;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-ativo { background: #d4edda; color: #155724; }
        .status-inativo { background: #f8d7da; color: #721c24; }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .email-display {
            color: #0066cc;
            font-size: 0.8em;
            word-break: break-word;
        }

        .email-display:empty:before {
            content: "Não informado";
            color: #999;
            font-style: italic;
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
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .modal-form {
            display: grid;
            gap: 15px;
        }

        .modal-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .modal-form input, .modal-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .modal-form label {
            font-weight: bold;
            color: #003366;
            margin-bottom: 5px;
            display: block;
        }

        .modal-form textarea {
            min-height: 80px;
            resize: vertical;
        }

        .obs-admin {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 10px;
            border-radius: 5px;
        }

        /* Modal de Visualização */
        .modal-view {
            display: none;
            position: fixed;
            z-index: 10001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-view.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .modal-view-content {
            background-color: white;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .view-header {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-header h3 {
            margin: 0;
            font-size: 1.5em;
        }

        .view-actions {
            display: flex;
            gap: 10px;
        }

        .btn-print {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-close-view {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .view-body {
            padding: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0066cc;
        }

        .info-label {
            font-weight: bold;
            color: #003366;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1em;
            color: #333;
            word-break: break-word;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #003366;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .obs-section {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0066cc;
        }

        .status-display {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .status-display.ativo {
            background: #d4edda;
            color: #155724;
        }

        .status-display.inativo {
            background: #f8d7da;
            color: #721c24;
        }

        /* Estilos para impressão */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-size: 12pt;
                line-height: 1.4;
            }
            
            .modal-view {
                display: block !important;
                position: static !important;
                width: 100% !important;
                height: auto !important;
                background: none !important;
            }
            
            .modal-view-content {
                position: static !important;
                width: 100% !important;
                max-width: none !important;
                max-height: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .view-header {
                background: #003366 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-inside: avoid;
            }
            
            .view-actions {
                display: none !important;
            }
            
            .view-body {
                padding: 20pt !important;
            }
            
            .info-section {
                page-break-inside: avoid;
                margin-bottom: 20pt !important;
            }
            
            .section-title {
                color: #003366 !important;
                font-size: 14pt !important;
                font-weight: bold !important;
                margin-bottom: 12pt !important;
                border-bottom: 2pt solid #003366 !important;
                padding-bottom: 6pt !important;
            }
            
            .info-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 15pt !important;
                margin-bottom: 15pt !important;
            }
            
            .info-item {
                background: #f8f9fa !important;
                border-left: 3pt solid #0066cc !important;
                padding: 10pt !important;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .info-label {
                font-weight: bold !important;
                color: #003366 !important;
                font-size: 10pt !important;
                margin-bottom: 4pt !important;
            }
            
            .info-value {
                font-size: 11pt !important;
                color: #333 !important;
                word-wrap: break-word !important;
                white-space: pre-wrap !important;
            }
            
            .obs-section {
                background: #e7f3ff !important;
                border-left: 4pt solid #0066cc !important;
                padding: 12pt !important;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .status-display {
                padding: 4pt 8pt !important;
                border-radius: 10pt !important;
                font-weight: bold !important;
                font-size: 10pt !important;
            }
            
            .status-display.ativo {
                background: #d4edda !important;
                color: #155724 !important;
                border: 1pt solid #c3e6cb !important;
            }
            
            .status-display.inativo {
                background: #f8d7da !important;
                color: #721c24 !important;
                border: 1pt solid #f5c6cb !important;
            }
            
            /* Ocultar elementos que não devem aparecer na impressão */
            .header,
            .container > .stats-grid,
            .container > .filters,
            .container > .table-container,
            .container > div:last-child,
            #modalEdit {
                display: none !important;
            }
            
            /* Forçar quebra de página antes de seções importantes */
            .info-section:nth-child(3) {
                page-break-before: auto;
            }
            
            /* Garantir que textos longos quebrem adequadamente */
            .info-value {
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
        }

        @media (max-width: 768px) {
            .modal-form-row,
            .modal-form-row-three { 
                grid-template-columns: 1fr; 
            }
            .filters-grid { 
                grid-template-columns: 1fr; 
            }
            .table-container { 
                overflow-x: auto; 
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .filter-buttons .btn {
                flex: none;
                width: 100%;
            }
            
            .actions {
                gap: 2px;
                justify-content: center;
            }
            
            .actions .btn {
                min-width: 28px;
                min-height: 28px;
                padding: 4px;
                font-size: 0.9em;
            }
            
            .table th, .table td {
                padding: 8px 4px;
                font-size: 0.8em;
            }
            
            .table th:last-child, .table td:last-child {
                min-width: 120px;
            }

            .info-grid { 
                grid-template-columns: 1fr; 
            }
        }

        .calendario-widget {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
        }

        .calendario-widget:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
            text-decoration: none;
        }

    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Painel Administrativo - Chamonzinho</h1>
            <div class="user-info">
                <span>Bem-vindo, <?php echo htmlspecialchars($admin['nome']); ?></span>
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

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div>Total de Cadastros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['ativos']; ?></div>
                <div>Cadastros Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['hoje']; ?></div>
                <div>Cadastros Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['semana']; ?></div>
                <div>Esta Semana</div>
            </div>
            <a href="calendario.php" class="calendario-widget">
             <h3>📅 Calendário</h3>
             <div class="stat-number" style="color: white; margin-bottom: 5px;">
             <?php echo $proximos_agendamentos_info['total_proximos']; ?>
             </div>
             <div class="info">Próximos 3 dias</div>
             </a>
        </div>

        <?php if ($agendamentos_hoje_info['total_hoje'] > 0): ?>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; animation: pulse 2s infinite;">
            <h3 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 10px;">
                🚨 Agendamentos de Hoje
                <span style="background: #856404; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8em;">
                    <?php echo $agendamentos_hoje_info['total_hoje']; ?>
                </span>
                <?php if ($agendamentos_hoje_info['urgentes_hoje'] > 0): ?>
                    <span style="background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8em;">
                        🔴 <?php echo $agendamentos_hoje_info['urgentes_hoje']; ?> urgente(s)
                    </span>
                <?php endif; ?>
            </h3>
            <p style="margin: 0;">
                Você tem <strong><?php echo $agendamentos_hoje_info['total_hoje']; ?> agendamento(s)</strong> programado(s) para hoje.
                <a href="calendario.php" style="color: #003366; font-weight: bold; text-decoration: none; margin-left: 10px;">
                    📅 Ver Calendário →
                </a>
            </p>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Buscar por Nome/Cargo/Email</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Digite para buscar...">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="ativo" <?php echo $status_filter == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo $status_filter == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <select name="cidade">
                            <option value="">Todas</option>
                            <?php foreach ($cidades as $cidade): ?>
                                <option value="<?php echo htmlspecialchars($cidade); ?>" <?php echo $cidade_filter == $cidade ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cidade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">🗑️ Limpar</a>
                            <button type="button" class="btn btn-success" onclick="atualizarPagina()">🔄 Atualizar</button>
                            <button type="button" class="btn" onclick="copiarTodosParaExcel()" style="background: #28a745; color: white;" title="Copiar todos os dados para Excel">
                                📋 Copiar Todos
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela de Cadastros -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Cidade</th>
                        <th>Cargo</th>
                        <th>Contato</th>
                        <th>Status</th>
                        <th>Obs. Admin</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cadastros)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Nenhum cadastro encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cadastros as $cadastro): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($cadastro['nome']); ?></strong>
                                    <?php if ($cadastro['observacoes']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($cadastro['observacoes'], 0, 30)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cadastro['cidade']); ?></td>
                                <td><?php echo htmlspecialchars($cadastro['cargo']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cadastro['telefone']); ?></strong><br>
                                    <div class="email-display">
                                        <?php if (!empty($cadastro['email'])): ?>
                                            <?php echo htmlspecialchars($cadastro['email']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $cadastro['status']; ?>">
                                        <?php echo ucfirst($cadastro['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cadastro['observacoes_admin']): ?>
                                        <small style="background: #e7f3ff; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                                            <?php echo htmlspecialchars(substr($cadastro['observacoes_admin'], 0, 30)); ?>...
                                        </small>
                                    <?php else: ?>
                                        <small style="color: #999;">Sem observações</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($cadastro['data_cadastro'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button type="button" class="btn btn-primary" onclick="verCadastro(<?php echo $cadastro['id']; ?>)" style="background: #6f42c1;" title="Visualizar cadastro">
                                            👁️
                                        </button>
                                        <button type="button" class="btn btn-edit" onclick="editarCadastro(<?php echo $cadastro['id']; ?>)" title="Editar cadastro">
                                            ✏️
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $cadastro['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $cadastro['status']; ?>">
                                            <button type="submit" class="btn <?php echo $cadastro['status'] == 'ativo' ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $cadastro['status'] == 'ativo' ? 'Desativar cadastro' : 'Ativar cadastro'; ?>">
                                                <?php echo $cadastro['status'] == 'ativo' ? '⏸️' : '▶️'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este cadastro?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $cadastro['id']; ?>">
                                            <button type="submit" class="btn btn-danger" title="Excluir cadastro">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; margin-top: 20px; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-primary">&laquo; Anterior</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="btn" style="background: #0066cc; color: white;"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="btn" style="background: #f8f9fa; color: #003366;"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-primary">Próximo &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Visualização -->
    <div id="modalView" class="modal-view">
        <div class="modal-view-content">
            <div class="view-header">
                <h3>📋 Detalhes do Cadastro</h3>
                <div class="view-actions">
                    <button type="button" class="btn" onclick="copiarParaExcel()" style="background: #28a745; color: white;" title="Copiar dados para Excel">
                        📋 Copiar Excel
                    </button>
                    <button type="button" class="btn-print" onclick="imprimirCadastro()">🖨️ Imprimir</button>
                    <button type="button" class="btn-close-view" onclick="fecharModalView()">✖️ Fechar</button>
                </div>
            </div>
            <div class="view-body">
                <!-- Dados Pessoais -->
                <div class="info-section">
                    <div class="section-title">👤 Dados Pessoais</div>
                    <div class="info-grid">
                        <div class="info-item" style="background: #e7f3ff; border-left: 4px solid #28a745;">
                            <div class="info-label">Nome Completo</div>
                            <div class="info-value" id="view_nome" style="font-weight: bold; color: #28a745;">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Data de Nascimento</div>
                            <div class="info-value" id="view_nascimento">-</div>
                        </div>
                        <div class="info-item" style="background: #e7f3ff; border-left: 4px solid #28a745;">
                            <div class="info-label">Cidade</div>
                            <div class="info-value" id="view_cidade" style="font-weight: bold; color: #28a745;">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Cargo/Profissão</div>
                            <div class="info-value" id="view_cargo">-</div>
                        </div>
                        <div class="info-item" style="background: #e7f3ff; border-left: 4px solid #28a745;">
                            <div class="info-label">Telefone</div>
                            <div class="info-value" id="view_telefone" style="font-weight: bold; color: #28a745;">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value" id="view_email">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-display" id="view_status">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 12px; border-radius: 5px; border-left: 4px solid #ffc107; font-size: 0.9em; color: #856404; margin-top: 15px;">
                        📋 <strong>Para Excel:</strong> Os campos destacados em azul (Nome, Cidade, Telefone) serão copiados quando clicar em "Copiar Excel"
                    </div>
                </div>

                <!-- Observações do Cidadão -->
                <div class="info-section">
                    <div class="section-title">💬 Observações do Cidadão</div>
                    <div class="info-item">
                        <div class="info-value" id="view_observacoes" style="font-style: italic; min-height: 50px; white-space: pre-wrap; line-height: 1.6;">
                            Nenhuma observação registrada.
                        </div>
                    </div>
                </div>

                <!-- Observações Administrativas -->
                <div class="info-section">
                    <div class="section-title">📋 Observações Administrativas</div>
                    <div class="obs-section">
                        <div class="info-value" id="view_observacoes_admin" style="min-height: 80px; white-space: pre-wrap; line-height: 1.6;">
                            Nenhuma observação administrativa registrada.
                        </div>
                    </div>
                </div>

                <!-- Informações do Sistema -->
                <div class="info-section">
                    <div class="section-title">🕒 Informações do Sistema</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Data de Cadastro</div>
                            <div class="info-value" id="view_data_cadastro">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Última Alteração</div>
                            <div class="info-value" id="view_ultima_alteracao">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">IP de Cadastro</div>
                            <div class="info-value" id="view_ip">-</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">ID do Registro</div>
                            <div class="info-value" id="view_id">-</div>
                        </div>
                    </div>
                </div>

                <!-- Rodapé para impressão -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #666; font-size: 0.9em;">
                    <strong>Deputado Chamonzinho - MDB</strong><br>
                    Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?><br>
                    Sistema de Gerenciamento de Cadastros
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Cadastro</h3>
                <button type="button" class="close" onclick="fecharModal()">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-form-row">
                    <div>
                        <label for="edit_nome">Nome Completo *</label>
                        <input type="text" id="edit_nome" name="nome" required maxlength="255">
                    </div>
                    <div>
                        <label for="edit_cidade">Cidade *</label>
                        <input type="text" id="edit_cidade" name="cidade" required maxlength="100">
                    </div>
                </div>

                <div class="modal-form-row-three">
                    <div>
                        <label for="edit_cargo">Cargo *</label>
                        <input type="text" id="edit_cargo" name="cargo" required maxlength="100">
                    </div>
                    <div>
                        <label for="edit_telefone">Telefone *</label>
                        <input type="text" id="edit_telefone" name="telefone" required maxlength="15">
                    </div>
                    <div>
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" maxlength="255" placeholder="email@exemplo.com">
                    </div>
                </div>

                <div>
                    <label for="edit_data_nascimento">Data de Nascimento *</label>
                    <input type="text" id="edit_data_nascimento" name="data_nascimento" required placeholder="DD/MM/AAAA" maxlength="10">
                </div>

                <div>
                    <label for="edit_observacoes">Observações do Cidadão</label>
                    <textarea id="edit_observacoes" name="observacoes" placeholder="Observações feitas pelo próprio cidadão..."></textarea>
                </div>

                <div class="obs-admin">
                    <label for="edit_observacoes_admin">📋 Observações Administrativas</label>
                    <textarea id="edit_observacoes_admin" name="observacoes_admin" placeholder="Ex: Visitou gabinete em 15/06/2025 - Solicitou apoio para projeto social. Agendar reunião com equipe técnica."></textarea>
                    <small style="color: #666; font-style: italic;">
                        Use este campo para registrar visitas, atendimentos, solicitações e outras informações importantes sobre o cidadão.
                    </small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Armazenar dados dos cadastros para o modal
        const cadastrosData = <?php echo json_encode($cadastros, JSON_HEX_QUOT | JSON_HEX_APOS); ?>;

        // Variável global para armazenar ID do cadastro sendo visualizado
        let cadastroAtualVisualizacao = null;

        // Função para copiar dados do cadastro atual para Excel
        function copiarParaExcel() {
            const nome = document.getElementById('view_nome').textContent;
            const cidade = document.getElementById('view_cidade').textContent;
            const telefone = document.getElementById('view_telefone').textContent;
            
            // Criar texto separado por TAB para colar no Excel
            const textoParaCopiar = `${nome}\t${cidade}\t${telefone}`;
            
            // Copiar para clipboard
            navigator.clipboard.writeText(textoParaCopiar).then(function() {
                // Mostrar confirmação
                const btn = event.target;
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '✅ Copiado!';
                btn.style.background = '#28a745';
                
                setTimeout(() => {
                    btn.innerHTML = textoOriginal;
                    btn.style.background = '#28a745';
                }, 2000);
                
                // Mostrar instruções
                alert('✅ Dados copiados!\n\n📋 Cole no Excel:\n1. Abra o Excel\n2. Selecione a célula A1 (ou onde desejar)\n3. Pressione Ctrl+V\n\nOs dados serão colados em 3 colunas:\nA: Nome | B: Cidade | C: Telefone');
            }).catch(function(err) {
                // Fallback para navegadores mais antigos
                const textArea = document.createElement('textarea');
                textArea.value = textoParaCopiar;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('✅ Dados copiados para a área de transferência!\n\nCole no Excel com Ctrl+V');
            });
        }

        // Função para copiar todos os cadastros filtrados
        function copiarTodosParaExcel() {
            if (!confirm('Deseja copiar todos os cadastros filtrados?\n\nIsso copiará NOME, CIDADE e TELEFONE de todos os registros visíveis na tela.')) {
                return;
            }
            
            // Coletar dados de todos os cadastros visíveis
            let textoCompleto = '';
            
            // Adicionar cabeçalho
            //textoCompleto += 'NOME COMPLETO\tCIDADE\tTELEFONE\n';
            
            // Percorrer cadastros da página atual
            cadastrosData.forEach(cadastro => {
                textoCompleto += `${cadastro.nome}\t${cadastro.cidade}\t${cadastro.telefone}\n`;
            });
            
            // Copiar para clipboard
            navigator.clipboard.writeText(textoCompleto).then(function() {
                //alert(`✅ ${cadastrosData.length} registros copiados!\n\n📋 Cole no Excel:\n1. Abra o Excel\n2. Selecione a célula A1\n3. Pressione Ctrl+V\n\nOs dados serão organizados em colunas automaticamente.`);
            }).catch(function(err) {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = textoCompleto;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('✅ Dados copiados! Cole no Excel com Ctrl+V');
            });
        }

        // Função para visualizar cadastro
        function verCadastro(id) {
            console.log('Visualizando cadastro ID:', id);
            
            // Buscar dados do cadastro
            const cadastro = cadastrosData.find(c => c.id == id);
            if (!cadastro) {
                alert('Cadastro não encontrado!');
                return;
            }

            // Armazenar globalmente para exportação
            cadastroAtualVisualizacao = cadastro;

            // Preencher dados pessoais
            document.getElementById('view_nome').textContent = cadastro.nome;
            document.getElementById('view_cidade').textContent = cadastro.cidade;
            document.getElementById('view_cargo').textContent = cadastro.cargo;
            document.getElementById('view_telefone').textContent = cadastro.telefone;
            document.getElementById('view_email').textContent = cadastro.email || 'Não informado';
            document.getElementById('view_id').textContent = cadastro.id;
            document.getElementById('view_ip').textContent = cadastro.ip_address || 'Não registrado';
            
            // Formatar e exibir data de nascimento
            if (cadastro.data_nascimento) {
                const partes = cadastro.data_nascimento.split('-');
                if (partes.length === 3) {
                    const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
                    const nascimento = new Date(cadastro.data_nascimento);
                    const idade = new Date().getFullYear() - nascimento.getFullYear();
                    document.getElementById('view_nascimento').textContent = `${dataFormatada} (${idade} anos)`;
                }
            }
            
            // Status
            const statusElement = document.getElementById('view_status');
            statusElement.textContent = cadastro.status.charAt(0).toUpperCase() + cadastro.status.slice(1);
            statusElement.className = `status-display ${cadastro.status}`;
            
            // Observações do cidadão
            const obsElement = document.getElementById('view_observacoes');
            if (cadastro.observacoes && cadastro.observacoes.trim()) {
                obsElement.textContent = cadastro.observacoes;
                obsElement.style.fontStyle = 'normal';
                obsElement.style.whiteSpace = 'pre-wrap';
                obsElement.style.lineHeight = '1.6';
            } else {
                obsElement.textContent = 'Nenhuma observação registrada.';
                obsElement.style.fontStyle = 'italic';
            }
            
            // Observações administrativas
            const obsAdminElement = document.getElementById('view_observacoes_admin');
            if (cadastro.observacoes_admin && cadastro.observacoes_admin.trim()) {
                obsAdminElement.textContent = cadastro.observacoes_admin;
                obsAdminElement.style.fontStyle = 'normal';
                obsAdminElement.style.whiteSpace = 'pre-wrap';
                obsAdminElement.style.lineHeight = '1.6';
            } else {
                obsAdminElement.textContent = 'Nenhuma observação administrativa registrada.';
                obsAdminElement.style.fontStyle = 'italic';
            }
            
            // Datas do sistema
            if (cadastro.data_cadastro) {
                const dataCadastro = new Date(cadastro.data_cadastro);
                document.getElementById('view_data_cadastro').textContent = dataCadastro.toLocaleString('pt-BR');
            }
            
            if (cadastro.data_ultima_alteracao) {
                const dataAlteracao = new Date(cadastro.data_ultima_alteracao);
                document.getElementById('view_ultima_alteracao').textContent = dataAlteracao.toLocaleString('pt-BR');
            } else {
                document.getElementById('view_ultima_alteracao').textContent = 'Nunca alterado';
            }
            
            // Mostrar modal
            document.getElementById('modalView').classList.add('show');
        }

        // Função para fechar modal de visualização
        function fecharModalView() {
            document.getElementById('modalView').classList.remove('show');
        }

        // Função para imprimir cadastro
        function imprimirCadastro() {
            const modal = document.getElementById('modalView');
            if (!modal.classList.contains('show')) {
                alert('Erro: Modal não está aberto.');
                return;
            }
            
            setTimeout(function() {
                window.print();
            }, 100);
        }

        // Função para atualizar página
        function atualizarPagina() {
            if (confirm('Atualizar a página para buscar novos cadastros?')) {
                location.reload();
            }
        }

        function editarCadastro(id) {
            console.log('Abrindo modal para ID:', id);
            
            const cadastro = cadastrosData.find(c => c.id == id);
            if (!cadastro) {
                alert('Cadastro não encontrado!');
                return;
            }

            // Preencher campos
            document.getElementById('edit_id').value = cadastro.id;
            document.getElementById('edit_nome').value = cadastro.nome;
            document.getElementById('edit_cidade').value = cadastro.cidade;
            document.getElementById('edit_cargo').value = cadastro.cargo;
            document.getElementById('edit_telefone').value = cadastro.telefone;
            document.getElementById('edit_email').value = cadastro.email || '';
            document.getElementById('edit_observacoes').value = cadastro.observacoes || '';
            document.getElementById('edit_observacoes_admin').value = cadastro.observacoes_admin || '';
            
            // Converter data de YYYY-MM-DD para DD/MM/AAAA
            if (cadastro.data_nascimento) {
                const partes = cadastro.data_nascimento.split('-');
                if (partes.length === 3) {
                    document.getElementById('edit_data_nascimento').value = `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
            }
            
            document.getElementById('modalEdit').classList.add('show');
        }

        function fecharModal() {
            document.getElementById('modalEdit').classList.remove('show');
        }

        // Fechar modais clicando no fundo
        document.getElementById('modalView').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalView();
            }
        });

        document.getElementById('modalEdit').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });

        // Função para validar email
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Máscara para telefone
            const telefoneInput = document.getElementById('edit_telefone');
            telefoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            });

            // Máscara para data
            const dataInput = document.getElementById('edit_data_nascimento');
            dataInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(\d{2})(\d)/, '$1/$2');
                value = value.replace(/(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
                e.target.value = value;
            });

            // Validação em tempo real do email
            const emailInput = document.getElementById('edit_email');
            emailInput.addEventListener('input', function(e) {
                const email = e.target.value;
                if (email.length > 0 && !validateEmail(email)) {
                    e.target.style.borderColor = '#dc3545';
                } else {
                    e.target.style.borderColor = '#ddd';
                }
            });

            // Auto-hide mensagens de sucesso
            const messages = document.querySelectorAll('.message.success');
            messages.forEach(function(msg) {
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 5000);
            });
        });

        // Validação do formulário de edição
        document.querySelector('#modalEdit form').addEventListener('submit', function(e) {
            const telefone = document.getElementById('edit_telefone').value;
            const data = document.getElementById('edit_data_nascimento').value;
            const email = document.getElementById('edit_email').value.trim();
            
            // Validar telefone
            if (!/^\(\d{2}\) \d{4,5}-\d{4}$/.test(telefone)) {
                e.preventDefault();
                alert('Por favor, digite um telefone válido no formato (XX) XXXXX-XXXX');
                return false;
            }
            
            // Validar data
            if (!/^\d{2}\/\d{2}\/\d{4}$/.test(data)) {
                e.preventDefault();
                alert('Por favor, digite uma data válida no formato DD/MM/AAAA');
                return false;
            }

            // Validar email se fornecido
            if (email && !validateEmail(email)) {
                e.preventDefault();
                alert('Por favor, digite um email válido');
                return false;
            }
        });

        // Confirmar exclusões
        document.querySelectorAll('form').forEach(function(form) {
            const deleteAction = form.querySelector('input[name="action"][value="delete"]');
            if (deleteAction) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Tem certeza que deseja excluir este cadastro? Esta ação não pode ser desfeita.')) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModal();
                fecharModalView();
            }
            
            if (e.ctrlKey && e.key === 'p' && document.getElementById('modalView').classList.contains('show')) {
                e.preventDefault();
                imprimirCadastro();
            }
        });

        console.log('Dashboard carregado com sucesso! Cadastros:', cadastrosData.length);
        console.log('📋 Funcionalidade de cópia para Excel ativada!');
    </script>
</body>
</html>