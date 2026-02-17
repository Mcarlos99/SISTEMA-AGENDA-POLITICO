<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  login.php  -  Login Centralizado
//   Um único login para todos os admins de todos os tenants.
//   O sistema identifica o tenant pelo usuário digitado.
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/config.php';

// Se já tem sessão ativa → redireciona direto pro dashboard do tenant
if (!empty($_SESSION[SESSION_TENANT_ADMIN]['tenant_id'])) {
    $tid  = (int)$_SESSION[SESSION_TENANT_ADMIN]['tenant_id'];
    $stmt = Database::getInstance()->prepare(
        'SELECT slug FROM tenants WHERE id=? AND status IN ("ativo","trial") LIMIT 1'
    );
    $stmt->execute([$tid]);
    $row = $stmt->fetch();
    if ($row) {
        header('Location: ' . platformUrl($row['slug'] . '/admin/'));
        exit;
    }
    // Sessão inválida, limpa
    unset($_SESSION[SESSION_TENANT_ADMIN]);
}

$erro    = '';
$aviso   = '';
$db      = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizeInput($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($usuario && $senha) {
        // Busca o admin em QUALQUER tenant ativo — usuário é único na plataforma
        $stmt = $db->prepare(
            'SELECT a.*, t.slug, t.nome_politico, t.cargo, t.cor_primaria,
                    t.cor_secundaria, t.cor_acento, t.status AS tenant_status
             FROM administradores a
             INNER JOIN tenants t ON t.id = a.tenant_id
             WHERE a.usuario = ?
               AND a.status  = "ativo"
               AND t.status IN ("ativo","trial")
             LIMIT 1'
        );
        $stmt->execute([$usuario]);
        $adm = $stmt->fetch();

        if ($adm && password_verify($senha, $adm['senha'])) {
            // Atualiza último acesso
            $db->prepare('UPDATE administradores SET ultimo_acesso=NOW() WHERE id=?')
               ->execute([$adm['id']]);

            $_SESSION[SESSION_TENANT_ADMIN] = [
                'id'        => $adm['id'],
                'tenant_id' => $adm['tenant_id'],
                'usuario'   => $adm['usuario'],
                'nome'      => $adm['nome'],
                'nivel'     => $adm['nivel'],
            ];

            logActivity((int)$adm['tenant_id'], 'login_sucesso', "Login central: {$adm['usuario']}");

            // Redireciona para o dashboard do tenant correto
            header('Location: ' . platformUrl($adm['slug'] . '/admin/'));
            exit;

        } else {
            // Verifica se usuário existe mas tenant está suspenso
            $stmtChk = $db->prepare(
                'SELECT t.status FROM administradores a
                 INNER JOIN tenants t ON t.id = a.tenant_id
                 WHERE a.usuario = ? AND a.status = "ativo" LIMIT 1'
            );
            $stmtChk->execute([$usuario]);
            $chk = $stmtChk->fetch();

            if ($chk && in_array($chk['status'], ['suspenso','inativo'])) {
                $erro = 'Conta suspensa. Entre em contato com o suporte.';
            } else {
                $erro = 'Usuário ou senha incorretos.';
            }

            logActivity(0, 'login_central_falha', "Tentativa: $usuario | IP: " . getClientIP());
        }
    } else {
        $erro = 'Preencha usuário e senha.';
    }
}

$logout = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= PLATFORM_NAME ?> — Acesso ao Sistema</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:Arial,sans-serif;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#0d1117;
  position:relative;
  overflow:hidden;
}
/* Fundo animado */
.bg{
  position:fixed;inset:0;z-index:0;
  background:linear-gradient(135deg,#003366 0%,#0055aa 50%,#0f3460 100%);
}
.bg::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at 20% 50%,rgba(0,119,204,.4) 0%,transparent 60%),
             radial-gradient(ellipse at 80% 20%,rgba(0,85,170,.3) 0%,transparent 50%);
  animation:bgMove 8s ease-in-out infinite alternate;
}
@keyframes bgMove{
  from{transform:scale(1) rotate(0deg)}
  to{transform:scale(1.05) rotate(2deg)}
}

/* CARD */
.card{
  position:relative;z-index:1;
  background:#fff;
  border-radius:20px;
  box-shadow:0 30px 80px rgba(0,0,0,.5);
  width:100%;max-width:420px;
  overflow:hidden;
  animation:slideUp .4s ease;
}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}

/* HEADER DO CARD */
.card-header{
  background:linear-gradient(135deg,#003366,#0055aa);
  padding:36px 32px 28px;
  text-align:center;
  position:relative;
}
.logo-circle{
  width:72px;height:72px;
  background:rgba(255,255,255,.15);
  border:3px solid rgba(255,255,255,.3);
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;
  margin:0 auto 14px;
}
.card-header h1{
  color:#fff;font-size:1.4rem;font-weight:800;margin-bottom:4px;letter-spacing:-.5px;
}
.card-header .sub{
  color:rgba(255,255,255,.7);font-size:.82rem;
}
.card-header .badge{
  display:inline-block;
  background:rgba(255,255,255,.15);
  border:1px solid rgba(255,255,255,.25);
  color:rgba(255,255,255,.9);
  border-radius:20px;padding:3px 12px;
  font-size:.7rem;letter-spacing:1px;text-transform:uppercase;
  margin-bottom:12px;
}

/* CORPO */
.card-body{padding:32px}

.alert{
  padding:12px 16px;border-radius:8px;
  margin-bottom:20px;font-size:.88rem;
  display:flex;align-items:center;gap:8px;
}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}

.field{margin-bottom:18px}
.field label{
  display:block;font-size:.78rem;color:#64748b;
  font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  margin-bottom:6px;
}
.input-wrap{position:relative}
.input-wrap .icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  font-size:1rem;pointer-events:none;
}
.field input{
  width:100%;padding:12px 14px 12px 42px;
  border:2px solid #e2e8f0;border-radius:10px;
  font-size:.95rem;color:#1e293b;
  transition:border-color .2s,box-shadow .2s;
  background:#f8fafc;
}
.field input:focus{
  outline:none;border-color:#0055aa;
  box-shadow:0 0 0 3px rgba(0,85,170,.1);
  background:#fff;
}
.field input::placeholder{color:#94a3b8}

.btn-login{
  width:100%;padding:14px;
  background:linear-gradient(135deg,#003366,#0055aa);
  color:#fff;border:none;border-radius:10px;
  font-size:1rem;font-weight:800;letter-spacing:.5px;
  cursor:pointer;transition:transform .15s,box-shadow .2s;
  box-shadow:0 4px 15px rgba(0,85,170,.3);
}
.btn-login:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 20px rgba(0,85,170,.4);
}
.btn-login:active{transform:translateY(0)}

.card-footer{
  padding:16px 32px 24px;
  text-align:center;
  border-top:1px solid #f1f5f9;
}
.card-footer p{font-size:.75rem;color:#94a3b8}
.card-footer a{color:#0055aa;text-decoration:none;font-weight:600}
.card-footer a:hover{text-decoration:underline}

/* Loading no botão */
.btn-login.loading{opacity:.7;pointer-events:none}
.btn-login.loading::after{
  content:' ⏳';
}
</style>
</head>
<body>
<div class="bg"></div>

<div class="card">
  <div class="card-header">
    <div class="badge">🏛️ Sistema Político</div>
    <div class="logo-circle">⚡</div>
    <h1><?= PLATFORM_NAME ?></h1>
    <div class="sub"><?= PLATFORM_SUBTITLE ?></div>
  </div>

  <div class="card-body">

    <?php if ($logout): ?>
    <div class="alert alert-success">✅ Você saiu com segurança.</div>
    <?php endif ?>

    <?php if ($erro): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($erro) ?></div>
    <?php endif ?>

    <form method="POST" id="formLogin" onsubmit="submeter(this)">

      <div class="field">
        <label for="usuario">Usuário</label>
        <div class="input-wrap">
          <span class="icon">👤</span>
          <input type="text" id="usuario" name="usuario" required
                 autofocus autocomplete="username"
                 placeholder="Seu usuário"
                 value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label for="senha">Senha</label>
        <div class="input-wrap">
          <span class="icon">🔑</span>
          <input type="password" id="senha" name="senha" required
                 autocomplete="current-password"
                 placeholder="Sua senha">
        </div>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">🔐 ENTRAR NO SISTEMA</button>
    </form>
  </div>

  <div class="card-footer">
    <p>Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank"><?= DEV_NOME ?></a> · <?= DEV_TELEFONE ?></p>
  </div>
</div>

<script>
function submeter(f){
  const btn = document.getElementById('btnLogin');
  btn.classList.add('loading');
  btn.textContent = 'Verificando...';
}
// Remove loading se voltar (ex: erro de rede)
window.addEventListener('pageshow', ()=>{
  const btn = document.getElementById('btnLogin');
  if(btn){ btn.classList.remove('loading'); btn.textContent = '🔐 ENTRAR NO SISTEMA'; }
});
</script>
</body>
</html>
