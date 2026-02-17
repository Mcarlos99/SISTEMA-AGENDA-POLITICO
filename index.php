<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar e sanitizar dados
        $nome = sanitizeInput($_POST['nome']);
        $cidade = sanitizeInput($_POST['cidade']);
        $cargo = sanitizeInput($_POST['cargo']);
        $telefone = sanitizeInput($_POST['telefone']);
        $email = sanitizeInput($_POST['email']);
        $data_nascimento_input = sanitizeInput($_POST['nascimento']);
        $observacoes = sanitizeInput($_POST['observacoes']);
        
        // Converter data do formato DD/MM/AAAA para YYYY-MM-DD (se necessário)
        $data_nascimento = $data_nascimento_input;
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data_nascimento_input)) {
            $partes = explode('/', $data_nascimento_input);
            $data_nascimento = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        }
        
        // Validações básicas
        if (empty($nome) || empty($cidade) || empty($cargo) || empty($telefone) || empty($data_nascimento)) {
            throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
        }
        
        if (!preg_match('/^\(\d{2}\) \d{4,5}-\d{4}$/', $telefone)) {
            throw new Exception('Formato de telefone inválido.');
        }
        
        // Validar email se fornecido
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de email inválido.');
        }
        
        // VERIFICAÇÃO RIGOROSA DE DUPLICATA POR TELEFONE OU EMAIL
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Normalizar telefone
        $telefone_limpo = preg_replace('/\D/', '', $telefone);
        
        // 1. Verificar se telefone já existe
        $stmt = $pdo->prepare("
            SELECT id, nome, cidade, cargo, telefone, email, data_cadastro 
            FROM cadastros 
            WHERE (telefone = ? OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), ' ', ''), '-', '') = ?)
            AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$telefone, $telefone_limpo]);
        $cadastro_por_telefone = $stmt->fetch();
        
        if ($cadastro_por_telefone) {
            // TELEFONE JÁ EXISTE - BLOQUEAR CADASTRO
            $data_cadastro_existente = date('d/m/Y', strtotime($cadastro_por_telefone['data_cadastro']));
            
            logActivity('cadastro_duplicado_bloqueado', "TELEFONE DUPLICADO BLOQUEADO - Telefone: $telefone - Tentativa: $nome ($cidade) - Existente: {$cadastro_por_telefone['nome']} ({$cadastro_por_telefone['cidade']})");
            
            throw new Exception("⚠️ CADASTRO NÃO PERMITIDO ⚠️\n\nEste número de telefone ({$telefone}) já está cadastrado no sistema.\n\n📋 Cadastro existente:\n• Nome: {$cadastro_por_telefone['nome']}\n• Cidade: {$cadastro_por_telefone['cidade']}\n• Cargo: {$cadastro_por_telefone['cargo']}\n• Email: " . ($cadastro_por_telefone['email'] ?: 'Não informado') . "\n• Cadastrado em: {$data_cadastro_existente}\n\n💡 Se você precisa atualizar seus dados, entre em contato conosco.");
        }
        
        // 2. Verificar se email já existe (se fornecido)
        if (!empty($email)) {
            $email_limpo = strtolower(trim($email));
            
            $stmt = $pdo->prepare("
                SELECT id, nome, cidade, cargo, telefone, email, data_cadastro 
                FROM cadastros 
                WHERE LOWER(TRIM(email)) = ? AND email IS NOT NULL AND email != ''
                AND status = 'ativo'
                LIMIT 1
            ");
            $stmt->execute([$email_limpo]);
            $cadastro_por_email = $stmt->fetch();
            
            if ($cadastro_por_email) {
                // EMAIL JÁ EXISTE - BLOQUEAR CADASTRO
                $data_cadastro_existente = date('d/m/Y', strtotime($cadastro_por_email['data_cadastro']));
                
                logActivity('cadastro_duplicado_bloqueado', "EMAIL DUPLICADO BLOQUEADO - Email: $email - Telefone tentativa: $telefone - Nome tentativa: $nome ($cidade) - Existente: {$cadastro_por_email['nome']} ({$cadastro_por_email['telefone']})");
                
                throw new Exception("⚠️ CADASTRO NÃO PERMITIDO ⚠️\n\nEste endereço de email ({$email}) já está cadastrado no sistema.\n\n📋 Cadastro existente:\n• Nome: {$cadastro_por_email['nome']}\n• Cidade: {$cadastro_por_email['cidade']}\n• Cargo: {$cadastro_por_email['cargo']}\n• Telefone: {$cadastro_por_email['telefone']}\n• Cadastrado em: {$data_cadastro_existente}\n\n💡 Talvez você já se cadastrou antes, ou alguém da sua família usou este email.\n💡 Se você precisa atualizar seus dados, entre em contato conosco.");
            }
        }
        
        // Se chegou até aqui, pode prosseguir com o cadastro
        $stmt = $pdo->prepare("INSERT INTO cadastros (nome, cidade, cargo, telefone, email, data_nascimento, observacoes, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $nome,
            $cidade,
            $cargo,
            $telefone,
            $email,
            $data_nascimento,
            $observacoes,
            getClientIP()
        ]);
        
        if ($result) {
            $message = 'Cadastro realizado com sucesso!';
            $messageType = 'success';
            
            // Log da ação
            logActivity('cadastro', "Novo cadastro aprovado: $nome de $cidade - Telefone: $telefone");
        } else {
            throw new Exception('Erro ao salvar cadastro.');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
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

        .header {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.6; }
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .form-container {
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            white-space: pre-line;
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

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #003366;
            font-weight: bold;
            font-size: 1.1em;
            transition: color 0.3s ease;
        }

        .form-group label .optional {
            font-weight: normal;
            color: #666;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0066cc;
            background: white;
            box-shadow: 0 0 15px rgba(0, 102, 204, 0.2);
            transform: translateY(-2px);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #003366, #0066cc);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 102, 204, 0.4);
            background: linear-gradient(135deg, #0066cc, #0080ff);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        /* Alertas de duplicata */
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .warning-box.show {
            display: block;
            animation: slideDown 0.5s ease-out;
        }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .error-box.show {
            display: block;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            animation: pulse 1s ease-in-out infinite;
        }

        @media (max-width: 768px) {
            .form-row,
            .form-row-three {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .form-container {
                padding: 20px;
            }
        }

        .admin-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .has-icon {
            position: relative;
        }

        .has-icon input {
            padding-right: 50px;
        }

        .email-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1em;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Overlay de Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <h3>🔍 Verificando dados...</h3>
            <p>Aguarde enquanto verificamos se seus dados já estão cadastrados.</p>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h1>Chamonzinho</h1>
            <p>Deputado Estadual pelo Pará - MDB</p>
        </div>
        
        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Aviso de Telefone/Email Duplicado -->
            <div id="errorDuplicata" class="error-box">
                <h4 id="errorTitulo">❌ CADASTRO NÃO PERMITIDO</h4>
                <p id="errorTexto"></p>
                <p><strong>Não é possível fazer um novo cadastro com estes dados.</strong></p>
                <p>Se você precisa atualizar seus dados, entre em contato conosco.</p>
            </div>

            <!-- Avisos de Duplicata -->
            <div id="warningDuplicata" class="warning-box">
                <h4>⚠️ Atenção - Dados Similares Encontrados</h4>
                <div id="listaDuplicatas"></div>
                <p><strong>Você pode continuar o cadastro, mas verifique se seus dados estão corretos.</strong></p>
            </div>
            
            <form method="POST" action="" id="formCadastro">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" required maxlength="255" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cidade">Cidade *</label>
                        <input type="text" id="cidade" name="cidade" required maxlength="100" value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row-three">
                    <div class="form-group">
                        <label for="cargo">Cargo *</label>
                        <input type="text" id="cargo" name="cargo" required maxlength="100" value="<?php echo htmlspecialchars($_POST['cargo'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone *</label>
                        <input type="tel" id="telefone" name="telefone" required maxlength="15" placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>">
                    </div>

                    <div class="form-group has-icon">
                        <label for="email">Email <span class="optional">(opcional)</span></label>
                        <input type="email" id="email" name="email" maxlength="255" placeholder="seu@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <!-- <span class="email-icon">📧</span> -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nascimento">Data de Nascimento *</label>
                    <input type="text" id="nascimento" name="nascimento" required placeholder="DD/MM/AAAA" pattern="\d{2}/\d{2}/\d{4}" maxlength="10" value="<?php echo htmlspecialchars($_POST['nascimento'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" placeholder="Digite suas observações aqui..."><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group" style="text-align: center;">
                    <button type="submit" class="btn-submit" id="btnSubmit">Cadastrar</button>
                </div>
            </form>
            
            <div class="admin-link">
                <a href="admin/login.php" style="color: #666; text-decoration: none; font-size: 1.2em; padding: 10px; border-radius: 50%; transition: all 0.3s ease; display: inline-block;" onmouseover="this.style.color='#003366'; this.style.transform='scale(1.1)'" onmouseout="this.style.color='#666'; this.style.transform='scale(1)'">⚙️</a>
            </div>
        </div>
    </div>

    <!-- Rodapé do Desenvolvedor -->
    <footer style="background: #f8f9fa; border-top: 1px solid #dee2e6; padding: 20px 0; margin-top: 40px;">
        <div style="max-width: 800px; margin: 0 auto; text-align: center; padding: 0 20px;">
            <p style="margin: 0; color: #6c757d; font-size: 0.9em;">
                <strong>Sistema desenvolvido por:</strong><br>
                <a href="https://wa.me/5594981709809?text=Olá, vim através do sistema do Deputado Chamonzinho e gostaria de mais informações sobre desenvolvimento de sistemas." 
                   target="_blank" 
                   style="color: #25D366; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px;">
                    📱 Mauro Carlos - (94) 98170-9809
                </a>
            </p>
            <p style="margin: 8px 0 0 0; color: #adb5bd; font-size: 0.8em;">
                Desenvolvimento de sistemas web e aplicativos
            </p>
        </div>
    </footer>

    <script>
        let verificacaoRealizada = false;
        let telefonePermitido = false;

        // Função para verificar duplicatas via AJAX
        async function verificarDuplicatas() {
            const telefone = document.getElementById('telefone').value.trim();
            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!telefone || telefone.length < 14) {
                return false;
            }

            // Mostrar loading
            document.getElementById('loadingOverlay').classList.add('show');

            try {
                const response = await fetch('verificar_duplicata.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        nome: nome,
                        telefone: telefone,
                        email: email
                    })
                });

                const resultado = await response.json();

                // Esconder loading
                document.getElementById('loadingOverlay').classList.remove('show');

                if (resultado.bloqueado) {
                    // TELEFONE OU EMAIL BLOQUEADO
                    mostrarErroDuplicata(resultado);
                    telefonePermitido = false;
                    desabilitarFormulario();
                    return true;
                } else if (resultado.avisos && resultado.avisos.length > 0) {
                    // MOSTRAR AVISOS (mas permitir cadastro)
                    mostrarAvisos(resultado.avisos);
                    telefonePermitido = true;
                    return false;
                } else {
                    // TUDO OK
                    limparAvisos();
                    telefonePermitido = true;
                    return false;
                }
            } catch (error) {
                console.error('Erro ao verificar duplicatas:', error);
                // Esconder loading
                document.getElementById('loadingOverlay').classList.remove('show');
                telefonePermitido = true; // Em caso de erro, permitir cadastro
                return false;
            }
        }

        // Mostrar erro de duplicata (telefone ou email) - bloqueia cadastro
        function mostrarErroDuplicata(resultado) {
            const errorBox = document.getElementById('errorDuplicata');
            const errorTitulo = document.getElementById('errorTitulo');
            const errorTexto = document.getElementById('errorTexto');
            
            const cadastro = resultado.cadastro_existente;
            const campo = resultado.campo_duplicado;
            
            if (campo === 'telefone') {
                errorTitulo.textContent = '❌ TELEFONE JÁ CADASTRADO';
                errorTexto.innerHTML = `
                    Este número <strong>${cadastro.telefone}</strong> já está cadastrado para:<br>
                    <strong>• Nome:</strong> ${cadastro.nome}<br>
                    <strong>• Cidade:</strong> ${cadastro.cidade}<br>
                    <strong>• Cargo:</strong> ${cadastro.cargo}<br>
                    <strong>• Email:</strong> ${cadastro.email}<br>
                    <strong>• Cadastrado em:</strong> ${cadastro.data_cadastro}
                `;
            } else if (campo === 'email') {
                errorTitulo.textContent = '❌ EMAIL JÁ CADASTRADO';
                errorTexto.innerHTML = `
                    Este email <strong>${cadastro.email}</strong> já está cadastrado para:<br>
                    <strong>• Nome:</strong> ${cadastro.nome}<br>
                    <strong>• Cidade:</strong> ${cadastro.cidade}<br>
                    <strong>• Cargo:</strong> ${cadastro.cargo}<br>
                    <strong>• Telefone:</strong> ${cadastro.telefone}<br>
                    <strong>• Cadastrado em:</strong> ${cadastro.data_cadastro}<br>

                `;
            }
            
            errorBox.classList.add('show');
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Esconder avisos normais
            document.getElementById('warningDuplicata').classList.remove('show');
        }

        // Mostrar avisos de possíveis duplicatas (permite cadastro)
        function mostrarAvisos(avisos) {
            const warningBox = document.getElementById('warningDuplicata');
            const listaDuplicatas = document.getElementById('listaDuplicatas');
            
            let html = '<ul>';
            avisos.forEach(aviso => {
                html += `<li><strong>${aviso.tipo.replace('_', ' ').toUpperCase()}:</strong> ${aviso.mensagem}</li>`;
            });
            html += '</ul>';
            
            listaDuplicatas.innerHTML = html;
            warningBox.classList.add('show');
            
            // Esconder erro de telefone
            document.getElementById('errorTelefone').classList.remove('show');
        }

        // Limpar todos os avisos
        function limparAvisos() {
            document.getElementById('warningDuplicata').classList.remove('show');
            document.getElementById('errorDuplicata').classList.remove('show');
            habilitarFormulario();
        }

        // Desabilitar formulário
        function desabilitarFormulario() {
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = true;
            btnSubmit.textContent = '❌ Cadastro Não Permitido';
            btnSubmit.style.background = '#dc3545';
        }

        // Habilitar formulário
        function habilitarFormulario() {
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Cadastrar';
            btnSubmit.style.background = '';
        }

        // Verificação automática ao sair do campo telefone
        document.getElementById('telefone').addEventListener('blur', async function() {
            if (this.value.length >= 14) {
                verificacaoRealizada = true;
                await verificarDuplicatas();
            }
        });

        // Verificação automática ao sair do campo email
        document.getElementById('email').addEventListener('blur', async function() {
            const email = this.value.trim();
            const telefone = document.getElementById('telefone').value.trim();
            
            // Se tem email e telefone, verificar
            if (email.length > 5 && telefone.length >= 14 && validateEmail(email)) {
                verificacaoRealizada = true;
                await verificarDuplicatas();
            }
        });

        // Verificação ao digitar no telefone (para limpar erros quando muda número)
        document.getElementById('telefone').addEventListener('input', function() {
            // Se mudou o telefone, permitir nova verificação
            if (verificacaoRealizada) {
                verificacaoRealizada = false;
                telefonePermitido = false;
                limparAvisos();
                habilitarFormulario();
            }
        });

        // Verificação ao digitar no email (para limpar erros quando muda email)
        document.getElementById('email').addEventListener('input', function() {
            // Se mudou o email, permitir nova verificação
            if (verificacaoRealizada && this.value.length > 0) {
                verificacaoRealizada = false;
                telefonePermitido = false;
                limparAvisos();
                habilitarFormulario();
            }
        });

        // Interceptar envio do formulário
        document.getElementById('formCadastro').addEventListener('submit', async function(e) {
            // Prevenir envio até verificar
            e.preventDefault();

            // Validar email se fornecido
            const email = document.getElementById('email').value.trim();
            if (email && !validateEmail(email)) {
                alert('Por favor, digite um email válido.');
                document.getElementById('email').focus();
                return false;
            }

            // Se não verificou duplicata ainda, verificar agora
            if (!verificacaoRealizada) {
                const temProblema = await verificarDuplicatas();
                verificacaoRealizada = true;
                
                if (temProblema) {
                    return false; // Bloquear envio
                }
            }

            // Se telefone não é permitido, bloquear
            if (!telefonePermitido) {
                alert('❌ Não é possível cadastrar com estes dados. Verifique as informações acima.');
                return false;
            }

            // Se chegou até aqui, pode enviar
            this.submit();
        });

        // Função para validar email
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // Máscara para data de nascimento
        document.getElementById('nascimento').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '$1/$2');
            value = value.replace(/(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
            e.target.value = value;
        });

        // Validação em tempo real do email
        document.getElementById('email').addEventListener('input', function(e) {
            const email = e.target.value;
            const emailIcon = document.querySelector('.email-icon');
            
            if (email.length > 0) {
                if (validateEmail(email)) {
                    e.target.style.borderColor = '#28a745';
                    emailIcon.style.color = '#28a745';
                    emailIcon.textContent = '✅';
                } else {
                    e.target.style.borderColor = '#dc3545';
                    emailIcon.style.color = '#dc3545';
                    emailIcon.textContent = '❌';
                }
            } else {
                e.target.style.borderColor = '#e0e0e0';
                emailIcon.style.color = '#666';
                emailIcon.textContent = '📧';
            }
        });

        // Validação da data no envio do formulário
        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            const dataInput = document.getElementById('nascimento');
            const dataValue = dataInput.value;
            
            // Verificar formato DD/MM/AAAA
            const regex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
            const match = dataValue.match(regex);
            
            if (!match) {
                e.preventDefault();
                alert('Por favor, digite a data no formato DD/MM/AAAA');
                dataInput.focus();
                return false;
            }
            
            const dia = parseInt(match[1]);
            const mes = parseInt(match[2]);
            const ano = parseInt(match[3]);
            
            // Validações básicas
            if (mes < 1 || mes > 12) {
                e.preventDefault();
                alert('Mês inválido. Digite um valor entre 01 e 12.');
                dataInput.focus();
                return false;
            }
            
            if (dia < 1 || dia > 31) {
                e.preventDefault();
                alert('Dia inválido. Digite um valor entre 01 e 31.');
                dataInput.focus();
                return false;
            }
            
            if (ano < 1900 || ano > new Date().getFullYear()) {
                e.preventDefault();
                alert('Ano inválido. Digite um ano válido.');
                dataInput.focus();
                return false;
            }
            
            // Verificar se a data é válida
            const dataObj = new Date(ano, mes - 1, dia);
            if (dataObj.getDate() !== dia || dataObj.getMonth() !== mes - 1 || dataObj.getFullYear() !== ano) {
                e.preventDefault();
                alert('Data inválida. Verifique o dia, mês e ano.');
                dataInput.focus();
                return false;
            }
        });

        // Auto-hide mensagens de sucesso
        <?php if ($message && $messageType == 'success'): ?>
        setTimeout(function() {
            const messageDiv = document.querySelector('.message.success');
            if (messageDiv) {
                messageDiv.style.display = 'none';
            }
        }, 5000);
        <?php endif; ?>

        // Fechar loading overlay ao clicar fora
        document.getElementById('loadingOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        console.log('🚀 Sistema de verificação rigorosa carregado!');
        console.log('📞 BLOQUEIO POR TELEFONE: Ativado');
    </script>
</body>
</html>