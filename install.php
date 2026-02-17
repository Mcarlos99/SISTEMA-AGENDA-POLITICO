<?php
// install.php - Script para facilitar a instalação do sistema

$error = '';
$success = '';
$debug = '';
$step = $_GET['step'] ?? 1;

// Debug: mostrar dados recebidos
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug .= "POST recebido. Dados: " . print_r($_POST, true) . "\n";
    $debug .= "Step atual: " . $step . "\n";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1) {
        // Usar dados fixos pré-configurados
        try {
            $host = 'localhost';
            $dbname = 'extremes_deputado_chamonzinho';
            $username = 'extremes_chamonzinho';
            $password = 'fu7@tAq%UQyB#T;e';
            
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Criar banco se não existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            
            // Criar tabelas
            $sql = "
                CREATE TABLE IF NOT EXISTS cadastros (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    cidade VARCHAR(100) NOT NULL,
                    cargo VARCHAR(100) NOT NULL,
                    telefone VARCHAR(20) NOT NULL,
                    data_nascimento DATE NOT NULL,
                    observacoes TEXT,
                    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                    INDEX idx_nome (nome),
                    INDEX idx_cidade (cidade),
                    INDEX idx_data_cadastro (data_cadastro)
                );

                CREATE TABLE IF NOT EXISTS administradores (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario VARCHAR(50) NOT NULL UNIQUE,
                    senha VARCHAR(255) NOT NULL,
                    nome VARCHAR(255) NOT NULL,
                    email VARCHAR(255),
                    ultimo_acesso TIMESTAMP NULL,
                    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS logs_acesso (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo ENUM('cadastro', 'admin_login', 'admin_action') NOT NULL,
                    descricao TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tipo (tipo),
                    INDEX idx_data_hora (data_hora)
                );
            ";
            
            $pdo->exec($sql);
            
            // Criar arquivo config.php
            $config_content = "<?php
// config.php - Configurações do banco de dados e sistema

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'extremes_deputado_chamonzinho');
define('DB_USER', 'extremes_chamonzinho');
define('DB_PASS', 'fu7@tAq%UQyB#T;e');
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema
define('SITE_NAME', 'Deputado Chamonzinho - MDB');
define('ADMIN_SESSION_NAME', 'chamonzinho_admin');
define('TIMEZONE', 'America/Sao_Paulo');

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Classe para conexão com banco de dados
class Database {
    private \$pdo;
    
    public function __construct() {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            \$this->pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            die(\"Erro de conexão: \" . \$e->getMessage());
        }
    }
    
    public function getConnection() {
        return \$this->pdo;
    }
}

// Funções auxiliares
function sanitizeInput(\$data) {
    \$data = trim(\$data);
    \$data = stripslashes(\$data);
    \$data = htmlspecialchars(\$data);
    return \$data;
}

function getClientIP() {
    \$ipkeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach (\$ipkeys as \$key) {
        if (array_key_exists(\$key, \$_SERVER) === true) {
            foreach (explode(',', \$_SERVER[\$key]) as \$ip) {
                \$ip = trim(\$ip);
                if (filter_var(\$ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return \$ip;
                }
            }
        }
    }
    return \$_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logActivity(\$tipo, \$descricao) {
    try {
        \$db = new Database();
        \$pdo = \$db->getConnection();
        
        \$stmt = \$pdo->prepare(\"INSERT INTO logs_acesso (tipo, descricao, ip_address, user_agent) VALUES (?, ?, ?, ?)\");
        \$stmt->execute([
            \$tipo,
            \$descricao,
            getClientIP(),
            \$_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception \$e) {
        error_log(\"Erro ao gravar log: \" . \$e->getMessage());
    }
}

// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>";
            
            file_put_contents('config.php', $config_content);
            
            $success = "Banco de dados configurado com sucesso!";
            header('Location: install.php?step=2');
            exit;
            
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
        }
    } elseif ($step == 2 || (isset($_POST['step']) && $_POST['step'] == '2')) {
        // Criar administrador
        $debug .= "Entrando na criação do administrador...\n";
        try {
            if (!file_exists('config.php')) {
                throw new Exception('Arquivo config.php não encontrado. Execute primeiro o passo 1.');
            }
            
            require_once 'config.php';
            $debug .= "Config.php carregado com sucesso.\n";
            
            $usuario = trim($_POST['admin_user'] ?? '');
            $senha = $_POST['admin_pass'] ?? '';
            $nome = trim($_POST['admin_nome'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            
            $debug .= "Dados recebidos - Usuario: '$usuario', Nome: '$nome', Email: '$email'\n";
            
            if (empty($usuario) || empty($senha) || empty($nome)) {
                throw new Exception("Campos obrigatórios vazios. Usuario: '$usuario', Senha: " . (empty($senha) ? 'vazia' : 'preenchida') . ", Nome: '$nome'");
            }
            
            if (strlen($senha) < 6) {
                throw new Exception('A senha deve ter pelo menos 6 caracteres. Tamanho atual: ' . strlen($senha));
            }
            
            $debug .= "Tentando conectar ao banco...\n";
            $db = new Database();
            $pdo = $db->getConnection();
            $debug .= "Conexão com banco estabelecida.\n";
            
            // Verificar se usuário já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $count = $stmt->fetchColumn();
            $debug .= "Verificação de usuário existente: $count registros encontrados.\n";
            
            if ($count > 0) {
                throw new Exception('Usuário já existe. Escolha outro nome de usuário.');
            }
            
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $debug .= "Senha criptografada gerada.\n";
            
            $stmt = $pdo->prepare("INSERT INTO administradores (usuario, senha, nome, email) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$usuario, $senha_hash, $nome, $email]);
            $debug .= "Resultado da inserção: " . ($result ? 'sucesso' : 'falhou') . "\n";
            
            if (!$result) {
                throw new Exception('Erro ao inserir administrador no banco de dados.');
            }
            
            $admin_id = $pdo->lastInsertId();
            $debug .= "Administrador criado com ID: $admin_id\n";
            
            $success = "Administrador criado com sucesso! Sistema pronto para uso.";
            header('Location: install.php?step=3');
            exit;
            
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
            $debug .= "ERRO: " . $e->getMessage() . " (Arquivo: " . $e->getFile() . ", Linha: " . $e->getLine() . ")\n";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Sistema Deputado Chamonzinho</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #003366, #0066cc);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .install-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 600px;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .install-header h1 {
            color: #003366;
            font-size: 2.2em;
            margin-bottom: 10px;
        }

        .install-header p {
            color: #666;
            font-size: 1.1em;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            position: relative;
        }

        .step.active {
            background: #0066cc;
            color: white;
        }

        .step.completed {
            background: #28a745;
            color: white;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #003366;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0066cc;
        }

        input[readonly] {
            background-color: #f8f9fa !important;
            border-color: #6c757d !important;
            cursor: not-allowed;
            color: #495057 !important;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            white-space: pre-line;
        }

        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0066cc;
        }

        .success-actions {
            text-align: center;
            margin-top: 20px;
        }

        .success-actions a {
            display: inline-block;
            margin: 5px 10px;
            padding: 10px 20px;
            background: #003366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .success-actions a:hover {
            background: #0066cc;
            transform: translateY(-1px);
        }

        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }

        .grid-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .config-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0066cc;
        }

        .config-display code {
            background: #e9ecef;
            padding: 3px 6px;
            border-radius: 3px;
            display: block;
            margin: 5px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        @media (max-width: 600px) {
            .grid-form {
                grid-template-columns: 1fr;
            }
            
            .install-container {
                padding: 20px;
            }
            
            .install-header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>Instalação do Sistema</h1>
            <p>Deputado Chamonzinho - MDB</p>
        </div>

        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
            <div class="step <?php echo $step == 3 ? 'active' : ''; ?>">3</div>
        </div>

        <?php if ($debug && isset($_GET['debug'])): ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-family: monospace; font-size: 0.8em; white-space: pre-line; border: 1px solid #dee2e6;">
                <strong>🔍 Debug Info:</strong><br>
                <?php echo htmlspecialchars($debug); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <div class="info-box">
                <strong>Passo 1:</strong> Configure a conexão com o banco de dados MySQL.<br>
                <small>O sistema criará automaticamente as tabelas necessárias.</small>
            </div>

            <div class="config-display">
                <strong>📋 Configurações Pré-definidas:</strong><br>
                <code>define('DB_HOST', 'localhost');</code>
                <code>define('DB_NAME', 'extremes_deputado_chamonzinho');</code>
                <code>define('DB_USER', 'extremes_chamonzinho');</code>
                <code>define('DB_PASS', 'fu7@tAq%UQyB#T;e');</code>
            </div>

            <form method="POST">
                <div class="grid-form">
                    <div class="form-group">
                        <label for="host">Host do Banco</label>
                        <input type="text" id="host" name="host" value="localhost" readonly>
                    </div>

                    <div class="form-group">
                        <label for="dbname">Nome do Banco</label>
                        <input type="text" id="dbname" name="dbname" value="extremes_deputado_chamonzinho" readonly>
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label for="username">Usuário do Banco</label>
                        <input type="text" id="username" name="username" value="extremes_chamonzinho" readonly>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha do Banco</label>
                        <input type="text" id="password" name="password" value="fu7@tAq%UQyB#T;e" readonly>
                    </div>
                </div>

                <div class="warning-box">
                    <strong>✅ Configuração Automática:</strong><br>
                    Os dados estão pré-configurados e bloqueados. Clique no botão abaixo para criar o banco de dados e tabelas automaticamente.
                </div>

                <button type="submit" class="btn">Configurar Banco de Dados</button>
            </form>

        <?php elseif ($step == 2): ?>
            <div class="info-box">
                <strong>Passo 2:</strong> Crie a conta do administrador do sistema.<br>
                <small>Esta conta será usada para acessar o painel administrativo.</small>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="2">
                
                <div class="grid-form">
                    <div class="form-group">
                        <label for="admin_user">Usuário Admin</label>
                        <input type="text" id="admin_user" name="admin_user" required placeholder="Ex: admin" maxlength="50" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_pass">Senha Admin</label>
                        <input type="password" id="admin_pass" name="admin_pass" required placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label for="admin_nome">Nome Completo</label>
                    <input type="text" id="admin_nome" name="admin_nome" required placeholder="Nome do administrador" maxlength="255" value="<?php echo htmlspecialchars($_POST['admin_nome'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="admin_email">E-mail (opcional)</label>
                    <input type="email" id="admin_email" name="admin_email" placeholder="admin@exemplo.com" maxlength="255" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                </div>

                <div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 0.9em;">
                    <strong>💡 Sugestão:</strong><br>
                    • Usuário: <code>admin</code><br>
                    • Senha: pelo menos 8 caracteres<br>
                    • Nome: seu nome completo
                </div>

                <button type="submit" class="btn">Criar Administrador</button>
                
                <div style="text-align: center; margin-top: 10px;">
                    <a href="?step=2&debug=1" style="color: #666; font-size: 0.8em; text-decoration: none;">🔍 Mostrar Debug</a>
                </div>
            </form>

        <?php elseif ($step == 3): ?>
            <div class="info-box">
                <strong>🎉 Instalação Concluída!</strong><br>
                O sistema está pronto para uso. Agora você pode acessar o formulário público e o painel administrativo.
            </div>

            <div class="success-actions">
                <a href="index.php">📝 Ir para Formulário</a>
                <a href="admin/login.php">⚙️ Área Administrativa</a>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante para Segurança:</strong><br>
                Por favor, <strong>delete o arquivo <code>install.php</code></strong> do servidor após a instalação para evitar problemas de segurança.
            </div>

            <div class="config-display">
                <strong>📋 Configurações Aplicadas:</strong><br>
                <code>Banco: extremes_deputado_chamonzinho</code>
                <code>Usuário: extremes_chamonzinho</code>
                <code>Host: localhost</code>
                <code>Sistema: Pronto para uso!</code>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Validações do formulário
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const step = <?php echo $step; ?>;
                    
                    if (step == 1) {
                        // Mostrar loading
                        const btn = form.querySelector('.btn');
                        btn.innerHTML = 'Configurando Banco... ⏳';
                        btn.disabled = true;
                        
                    } else if (step == 2) {
                        // Validar criação do admin
                        const usuario = document.getElementById('admin_user').value.trim();
                        const senha = document.getElementById('admin_pass').value;
                        const nome = document.getElementById('admin_nome').value.trim();
                        
                        if (!usuario || !senha || !nome) {
                            e.preventDefault();
                            alert('Por favor, preencha todos os campos obrigatórios.');
                            return false;
                        }
                        
                        if (senha.length < 6) {
                            e.preventDefault();
                            alert('A senha deve ter pelo menos 6 caracteres.');
                            return false;
                        }
                        
                        // Mostrar loading
                        const btn = form.querySelector('.btn');
                        btn.innerHTML = 'Criando Administrador... ⏳';
                        btn.disabled = true;
                    }
                });
            });
        });
    </script>
</body>
</html>