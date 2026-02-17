<?php
require_once '../config.php';

$error = '';

// Verificar se já está logado
if (isset($_SESSION[ADMIN_SESSION_NAME])) {
    header('Location: dashboard.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $usuario = sanitizeInput($_POST['usuario']);
        $senha = $_POST['senha'];
        
        if (empty($usuario) || empty($senha)) {
            throw new Exception('Usuário e senha são obrigatórios.');
        }
        
        $db = new Database();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("SELECT id, usuario, senha, nome FROM administradores WHERE usuario = ? AND status = 'ativo'");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($senha, $admin['senha'])) {
            // Login bem-sucedido
            $_SESSION[ADMIN_SESSION_NAME] = [
                'id' => $admin['id'],
                'usuario' => $admin['usuario'],
                'nome' => $admin['nome'],
                'login_time' => time()
            ];
            
            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            // Log do login
            logActivity('admin_login', "Login realizado por: " . $admin['usuario']);
            
            header('Location: dashboard.php');
            exit;
        } else {
            throw new Exception('Usuário ou senha incorretos.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logActivity('admin_login', "Tentativa de login falhada: $usuario");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - <?php echo SITE_NAME; ?></title>
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

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
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

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #003366;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 1.1em;
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
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 10px rgba(0, 102, 204, 0.2);
        }

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #003366;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .security-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9em;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Área Administrativa</h1>
            <p>Deputado Chamonzinho - MDB</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="back-link">
            <a href="../index.php">← Voltar ao Formulário</a>
        </div>

        <div class="security-info">
            <strong>Acesso Restrito:</strong> Esta área é destinada apenas a administradores autorizados.
        </div>
    </div>

    <script>
        // Prevenir ataques de força bruta
        let tentativas = 0;
        const maxTentativas = 3;
        
        document.querySelector('form').addEventListener('submit', function(e) {
            if (tentativas >= maxTentativas) {
                e.preventDefault();
                alert('Muitas tentativas de login. Tente novamente em alguns minutos.');
                return false;
            }
            
            <?php if ($error): ?>
            tentativas++;
            <?php endif; ?>
        });
    </script>
</body>
</html>