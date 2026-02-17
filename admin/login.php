<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  admin/login.php
//   Autenticação do administrador de tenant
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

$tenant = resolveTenant();
if (!$tenant) { http_response_code(404); die('Tenant não encontrado.'); }

$tenantId = (int)$tenant['id'];
$slug     = $tenant['slug'];

// Já autenticado → redireciona
if (!empty($_SESSION[SESSION_TENANT_ADMIN]['tenant_id'])
    && $_SESSION[SESSION_TENANT_ADMIN]['tenant_id'] === $tenantId) {
    header('Location: ' . tenantUrl($tenant, 'admin/'));
    exit;
}

$erro = '';
$db   = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizeInput($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($usuario && $senha) {
        $stmt = $db->prepare(
            'SELECT * FROM administradores
              WHERE tenant_id = ? AND usuario = ? AND status = "ativo" LIMIT 1'
        );
        $stmt->execute([$tenantId, $usuario]);
        $adm = $stmt->fetch();

        if ($adm && password_verify($senha, $adm['senha'])) {
            // Atualiza último acesso
            $db->prepare('UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?')
               ->execute([$adm['id']]);

            $_SESSION[SESSION_TENANT_ADMIN] = [
                'id'        => $adm['id'],
                'tenant_id' => $tenantId,
                'usuario'   => $adm['usuario'],
                'nome'      => $adm['nome'],
                'nivel'     => $adm['nivel'],
            ];
            logActivity($tenantId, 'login_sucesso', "Admin: {$adm['usuario']}");
            header('Location: ' . tenantUrl($tenant, 'admin/'));
            exit;
        } else {
            $erro = 'Usuário ou senha incorretos.';
            logActivity($tenantId, 'login_falha', "Tentativa: $usuario");
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}

$logout  = isset($_GET['logout']);
$c1      = htmlspecialchars($tenant['cor_primaria']   ?: '#003366');
$c2      = htmlspecialchars($tenant['cor_secundaria'] ?: '#0055aa');
$c3      = htmlspecialchars($tenant['cor_acento']     ?: '#0077cc');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Admin — <?= htmlspecialchars($tenant['nome_politico']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:100%;max-width:400px;overflow:hidden}
.header{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:30px;text-align:center}
.header .badge{background:rgba(255,255,255,.2);border-radius:20px;padding:3px 12px;font-size:10px;letter-spacing:1px;text-transform:uppercase;display:inline-block;margin-bottom:10px}
.header h1{font-size:1.2rem;margin-bottom:4px}
.header .sub{font-size:.8rem;opacity:.8}
.body{padding:30px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.82rem;color:#555;font-weight:600;margin-bottom:5px}
.form-group input{width:100%;padding:11px 14px;border:2px solid #e0e8f0;border-radius:8px;font-size:.95rem;transition:border-color .2s}
.form-group input:focus{outline:none;border-color:<?= $c3 ?>}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s}
.btn:hover{opacity:.9}
.alert{padding:12px;border-radius:8px;margin-bottom:18px;font-size:.88rem;text-align:center}
.alert-error{background:#f8d7da;color:#721c24}
.alert-success{background:#d4edda;color:#155724}
.footer{text-align:center;padding:16px;font-size:.75rem;color:#aaa;border-top:1px solid #f0f4f8}
.footer a{color:<?= $c3 ?>;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <div class="header">
    <div class="badge"><?= PLATFORM_NAME ?> · Admin</div>
    <h1><?= htmlspecialchars($tenant['nome_politico']) ?></h1>
    <div class="sub"><?= htmlspecialchars($tenant['cargo']) ?></div>
  </div>
  <div class="body">
    <?php if ($logout): ?>
      <div class="alert alert-success">Você saiu com segurança.</div>
    <?php endif ?>
    <?php if ($erro): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif ?>
    <form method="POST">
      <div class="form-group">
        <label for="usuario">Usuário</label>
        <input type="text" id="usuario" name="usuario" required autofocus
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required>
      </div>
      <button type="submit" class="btn">🔐 ENTRAR</button>
    </form>
  </div>
  <div class="footer">
    Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank"><?= DEV_NOME ?></a>
  </div>
</div>
</body>
</html>
