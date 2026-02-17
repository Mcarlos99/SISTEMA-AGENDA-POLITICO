<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  superadmin/login.php
//   Autenticação do Super Administrador (gestão de tenants)
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION[SESSION_SUPER_ADMIN])) {
    header('Location: dashboard.php'); exit;
}

$erro = '';
$db   = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizeInput($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($usuario && $senha) {
        $stmt = $db->prepare(
            'SELECT * FROM super_admins WHERE usuario=? AND status="ativo" LIMIT 1'
        );
        $stmt->execute([$usuario]);
        $adm = $stmt->fetch();

        if ($adm && password_verify($senha, $adm['senha'])) {
            $db->prepare('UPDATE super_admins SET ultimo_acesso=NOW() WHERE id=?')
               ->execute([$adm['id']]);
            $_SESSION[SESSION_SUPER_ADMIN] = [
                'id'      => $adm['id'],
                'usuario' => $adm['usuario'],
                'nome'    => $adm['nome'],
            ];
            logActivity(0, 'superadmin_login', $adm['usuario']);
            header('Location: dashboard.php'); exit;
        } else {
            $erro = 'Usuário ou senha incorretos.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Super Admin — <?= PLATFORM_NAME ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);width:100%;max-width:380px;overflow:hidden}
.header{background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:30px;text-align:center}
.header .badge{background:rgba(255,255,255,.15);border-radius:20px;padding:3px 12px;font-size:10px;letter-spacing:1px;text-transform:uppercase;display:inline-block;margin-bottom:12px}
.header h1{font-size:1.2rem;margin-bottom:4px}
.header .sub{font-size:.8rem;opacity:.7}
.body{padding:30px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.82rem;color:#555;font-weight:600;margin-bottom:5px}
.form-group input{width:100%;padding:11px 14px;border:2px solid #e0e8f0;border-radius:8px;font-size:.95rem;transition:border-color .2s}
.form-group input:focus{outline:none;border-color:#0f3460}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
.btn:hover{opacity:.9}
.alert-error{background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:18px;font-size:.88rem;text-align:center}
.footer{text-align:center;padding:16px;font-size:.75rem;color:#aaa;border-top:1px solid #f0f4f8}
.footer a{color:#0f3460;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <div class="header">
    <div class="badge">🔐 Super Admin</div>
    <h1><?= PLATFORM_NAME ?></h1>
    <div class="sub">Gestão da Plataforma</div>
  </div>
  <div class="body">
    <?php if ($erro): ?><div class="alert-error"><?= htmlspecialchars($erro) ?></div><?php endif ?>
    <form method="POST">
      <div class="form-group">
        <label>Usuário</label>
        <input type="text" name="usuario" required autofocus value="<?= htmlspecialchars($_POST['usuario']??'') ?>">
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" required>
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
