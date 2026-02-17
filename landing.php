<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  landing.php  - Página Inicial
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= PLATFORM_NAME ?> — <?= PLATFORM_SUBTITLE ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#0d0d1a;color:#e0e0e0;min-height:100vh}
.hero{background:linear-gradient(135deg,#1a1a2e,#0f3460,#1557a0);padding:80px 20px;text-align:center}
.hero .badge{background:rgba(255,255,255,.15);border-radius:20px;padding:5px 16px;font-size:.8rem;letter-spacing:1px;text-transform:uppercase;display:inline-block;margin-bottom:20px;color:#aad4ff}
.hero h1{font-size:clamp(2rem,6vw,3.5rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:12px}
.hero h1 span{color:#4fc3f7}
.hero p{font-size:1.1rem;opacity:.8;max-width:600px;margin:0 auto 32px;line-height:1.7}
.cta-btn{background:linear-gradient(135deg,#4fc3f7,#0288d1);color:#fff;padding:14px 32px;border-radius:30px;text-decoration:none;font-weight:700;font-size:1rem;display:inline-block;transition:transform .2s,box-shadow .2s;box-shadow:0 4px 20px rgba(79,195,247,.3)}
.cta-btn:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(79,195,247,.4)}
.features{max-width:900px;margin:60px auto;padding:0 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px}
.feat{background:#1e1e30;border-radius:12px;padding:24px;border:1px solid #2a2a40;text-align:center}
.feat .icon{font-size:2.5rem;margin-bottom:12px}
.feat h3{color:#7c9eff;margin-bottom:8px;font-size:.95rem}
.feat p{font-size:.85rem;color:#888;line-height:1.6}
.footer{text-align:center;padding:40px 20px;color:#555;font-size:.8rem}
.footer a{color:#7c9eff;text-decoration:none}
</style>
</head>
<body>
<div class="hero">
  <div class="badge">🏛️ <?= PLATFORM_NAME ?></div>
  <h1>Gestão Política <span>Profissional</span></h1>
  <p><?= PLATFORM_SUBTITLE ?> — Sistema multi-tenant para campanhas e gabinetes políticos de todo o Brasil.</p>
  <a class="cta-btn" href="/superadmin/login.php">🔐 Área Administrativa</a>
</div>

<div class="features">
  <div class="feat"><div class="icon">👥</div><h3>Multi-Tenant</h3><p>Cada político tem seu ambiente isolado, sua URL personalizada e seu próprio banco de dados.</p></div>
  <div class="feat"><div class="icon">📋</div><h3>Cadastro de Apoiadores</h3><p>Formulário público responsivo com verificação de duplicatas em tempo real.</p></div>
  <div class="feat"><div class="icon">📅</div><h3>Agenda Política</h3><p>Calendário de compromissos com prioridade, alertas e controle de status.</p></div>
  <div class="feat"><div class="icon">📊</div><h3>Dashboard Completo</h3><p>Estatísticas, filtros avançados, exportação para Excel e gestão completa dos contatos.</p></div>
  <div class="feat"><div class="icon">🎨</div><h3>Identidade Visual</h3><p>Cores personalizadas para cada político, mantendo a marca do gabinete.</p></div>
  <div class="feat"><div class="icon">🔒</div><h3>Segurança</h3><p>Sessões isoladas por tenant, senhas com hash bcrypt e log completo de acessos.</p></div>
</div>

<div class="footer">
  <?= PLATFORM_NAME ?> v<?= PLATFORM_VERSION ?> ·
  Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank"><?= DEV_NOME ?></a>
  · <?= DEV_TELEFONE ?>
</div>
</body>
</html>
