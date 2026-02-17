<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  index.php  -  Cadastro Público
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/config.php';

// --- Resolve o tenant -----------------------------------------------------------
$tenant = resolveTenant();
if (!$tenant) {
    http_response_code(404);
    die('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
    <title>Não Encontrado</title></head><body style="font-family:Arial;text-align:center;padding:80px">
    <h2 style="color:#003366">Página não encontrada</h2>
    <p>O link que você acessou é inválido ou expirou.</p></body></html>');
}

$tenantId  = (int)$tenant['id'];
$slug      = $tenant['slug'];
$categorias = getCategorias();
$db         = Database::getInstance();

// --- Verifica limite de cadastros do plano -------------------------------------
if ((int)$tenant['max_cadastros'] > 0) {
    $stmtCount = $db->prepare('SELECT COUNT(*) FROM cadastros WHERE tenant_id = ?');
    $stmtCount->execute([$tenantId]);
    if ((int)$stmtCount->fetchColumn() >= (int)$tenant['max_cadastros']) {
        die('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
        <title>Cadastros encerrados</title></head><body style="font-family:Arial;text-align:center;padding:80px">
        <h2 style="color:#003366">Cadastros temporariamente encerrados</h2>
        <p>Capacidade máxima atingida. Entre em contato com o gabinete.</p></body></html>');
    }
}

// --- Processa formulário -------------------------------------------------------
$msg     = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = sanitizeInput($_POST['nome']      ?? '');
    $cidade    = sanitizeInput($_POST['cidade']    ?? '');
    $cargo     = sanitizeInput($_POST['cargo']     ?? '');
    $telefone  = sanitizeInput($_POST['telefone']  ?? '');
    $email     = sanitizeInput($_POST['email']     ?? '');
    $nascimento = sanitizeInput($_POST['nascimento'] ?? '');
    $categoria = sanitizeInput($_POST['categoria'] ?? 'eleitor');
    $obs       = sanitizeInput($_POST['observacoes'] ?? '');

    // Converte data DD/MM/AAAA → AAAA-MM-DD
    $dataNasc = '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $nascimento, $m)) {
        $dataNasc = "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    // Normaliza telefone (somente dígitos)
    $telLimpo = preg_replace('/\D/', '', $telefone);

    // Validações básicas
    $erros = [];
    if (strlen($nome) < 3)      $erros[] = 'Nome deve ter ao menos 3 caracteres.';
    if (empty($cidade))         $erros[] = 'Cidade é obrigatória.';
    if (empty($cargo))          $erros[] = 'Cargo / Ocupação é obrigatório.';
    if (strlen($telLimpo) < 10) $erros[] = 'Telefone inválido.';
    if (empty($dataNasc))       $erros[] = 'Data de nascimento inválida (use DD/MM/AAAA).';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
    if (!array_key_exists($categoria, $categorias))           $erros[] = 'Categoria inválida.';

    if (empty($erros)) {
        // Verifica duplicata (telefone) dentro do mesmo tenant
        $stmtDup = $db->prepare('SELECT id FROM cadastros WHERE tenant_id = ? AND telefone = ? LIMIT 1');
        $stmtDup->execute([$tenantId, $telLimpo]);
        if ($stmtDup->fetch()) {
            $erros[] = 'Este telefone já está cadastrado. Obrigado pelo seu apoio!';
        }
    }

    if (empty($erros)) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO cadastros
                    (tenant_id, nome, cidade, cargo, telefone, email, data_nascimento,
                     categoria, observacoes, ip_address)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenantId, $nome, $cidade, $cargo, $telLimpo,
                $email ?: null, $dataNasc, $categoria, $obs ?: null, getClientIP(),
            ]);
            logActivity($tenantId, 'novo_cadastro', "Cadastro: $nome | $cidade | $telLimpo");
            $msg     = "Cadastro realizado com sucesso! Obrigado, $nome!";
            $msgType = 'success';
        } catch (Exception $e) {
            error_log('PoLiX cadastro: ' . $e->getMessage());
            $msg     = 'Erro ao salvar cadastro. Tente novamente.';
            $msgType = 'error';
        }
    } else {
        $msg     = implode('<br>', $erros);
        $msgType = 'error';
    }
}

// --- Cores do tenant -----------------------------------------------------------
$cor1 = htmlspecialchars($tenant['cor_primaria']   ?: '#003366');
$cor2 = htmlspecialchars($tenant['cor_secundaria'] ?: '#0055aa');
$cor3 = htmlspecialchars($tenant['cor_acento']     ?: '#0077cc');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro — <?= htmlspecialchars($tenant['nome_politico']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f4f8;min-height:100vh}
.header{background:linear-gradient(135deg,<?= $cor1 ?>,<?= $cor2 ?>);color:#fff;padding:30px 20px;text-align:center;position:relative}
.header .badge{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:4px 14px;font-size:11px;letter-spacing:1px;text-transform:uppercase;display:inline-block;margin-bottom:12px}
.header h1{font-size:clamp(1.3rem,4vw,2rem);margin-bottom:6px}
.header .subtitle{font-size:.9rem;opacity:.85}
.header .admin-link{position:absolute;top:12px;right:16px;color:rgba(255,255,255,.6);text-decoration:none;font-size:18px}
.header .admin-link:hover{color:#fff}
.container{max-width:640px;margin:30px auto 20px;padding:0 16px}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:32px;margin-bottom:20px}
.card h2{color:<?= $cor1 ?>;font-size:1rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid <?= $cor3 ?>}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.82rem;color:#555;font-weight:600;margin-bottom:5px}
.form-group input,
.form-group select,
.form-group textarea{width:100%;padding:11px 14px;border:2px solid #e0e8f0;border-radius:8px;font-size:.95rem;transition:border-color .2s}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus{outline:none;border-color:<?= $cor3 ?>}
.form-group textarea{resize:vertical;min-height:90px}
.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,<?= $cor1 ?>,<?= $cor2 ?>);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s;letter-spacing:.5px}
.btn-submit:hover{opacity:.9}
.alert{padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.9rem;line-height:1.6}
.alert-success{background:#d4edda;color:#155724;border-left:4px solid #28a745}
.alert-error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}
.footer{text-align:center;padding:20px;font-size:.78rem;color:#aaa}
.footer a{color:<?= $cor3 ?>;text-decoration:none}
@media(max-width:500px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="header">
  <a class="admin-link" href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/')) ?>" title="Área Administrativa">⚙️</a>
  <div class="badge"><?= PLATFORM_NAME ?></div>
  <h1><?= htmlspecialchars($tenant['nome_politico']) ?></h1>
  <div class="subtitle">
    <?= htmlspecialchars($tenant['cargo']) ?>
    <?php if ($tenant['partido']): ?> · <?= htmlspecialchars($tenant['partido']) ?><?php endif ?>
    <?php if ($tenant['estado']):  ?> – <?= htmlspecialchars($tenant['estado'])  ?><?php endif ?>
  </div>
</div>

<div class="container">

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>" id="alertMsg"><?= $msg ?></div>
  <?php endif ?>

  <div class="card">
    <h2>📋 Identificação</h2>
    <form method="POST" id="formCadastro" novalidate>

      <div class="form-row">
        <div class="form-group">
          <label for="nome">Nome Completo *</label>
          <input type="text" id="nome" name="nome" required placeholder="Seu nome completo"
                 value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="nascimento">Data de Nascimento *</label>
          <input type="text" id="nascimento" name="nascimento" placeholder="DD/MM/AAAA"
                 maxlength="10" required value="<?= htmlspecialchars($_POST['nascimento'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cargo">Cargo / Ocupação *</label>
          <input type="text" id="cargo" name="cargo" required placeholder="Ex: Professor, Comerciante..."
                 value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="categoria">Categoria *</label>
          <select id="categoria" name="categoria">
            <?php foreach ($categorias as $val => $label): ?>
            <option value="<?= $val ?>" <?= (($_POST['categoria'] ?? 'eleitor') === $val) ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <h2 style="margin-top:8px">📍 Localização</h2>

      <div class="form-group">
        <label for="cidade">Cidade *</label>
        <input type="text" id="cidade" name="cidade" required placeholder="Sua cidade"
               value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
      </div>

      <h2>📞 Contato</h2>

      <div class="form-row">
        <div class="form-group">
          <label for="telefone">Telefone / WhatsApp *</label>
          <input type="tel" id="telefone" name="telefone" required placeholder="(00) 00000-0000"
                 maxlength="15" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="email">E-mail <small>(opcional)</small></label>
          <input type="email" id="email" name="email" placeholder="seu@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <h2>💬 Mensagem</h2>

      <div class="form-group">
        <label for="observacoes">Deixe uma mensagem <small>(opcional)</small></label>
        <textarea id="observacoes" name="observacoes"
                  placeholder="Escreva aqui seu recado, pedido ou sugestão..."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn-submit">✅ ENVIAR CADASTRO</button>
    </form>
  </div>
</div>

<div class="footer">
  Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank"><?= DEV_NOME ?></a>
  · <?= DEV_TELEFONE ?>
</div>

<script>
// Máscara telefone
document.getElementById('telefone').addEventListener('input', function(){
  let v = this.value.replace(/\D/g,'').substring(0,11);
  if(v.length>10) v = v.replace(/^(\d{2})(\d{5})(\d{4})$/,'($1) $2-$3');
  else if(v.length>6) v = v.replace(/^(\d{2})(\d{4})(\d*)$/,'($1) $2-$3');
  else if(v.length>2) v = v.replace(/^(\d{2})(\d*)$/,'($1) $2');
  this.value = v;
});

// Máscara data
document.getElementById('nascimento').addEventListener('input', function(){
  let v = this.value.replace(/\D/g,'').substring(0,8);
  if(v.length>4) v = v.replace(/^(\d{2})(\d{2})(\d*)$/,'$1/$2/$3');
  else if(v.length>2) v = v.replace(/^(\d{2})(\d*)$/,'$1/$2');
  this.value = v;
});

// Verificação de duplicata em tempo real
let dupTimer;
document.getElementById('telefone').addEventListener('blur', function(){
  const tel = this.value.replace(/\D/g,'');
  if(tel.length < 10) return;
  clearTimeout(dupTimer);
  dupTimer = setTimeout(()=>{
    fetch('<?= htmlspecialchars(tenantUrl($tenant, 'verificar_duplicata.php')) ?>?telefone='+encodeURIComponent(tel))
      .then(r=>r.json()).then(d=>{
        if(d.duplicado){
          alert('⚠️ Este telefone já está cadastrado!\n\nObrigado pelo seu apoio!');
        }
      }).catch(()=>{});
  }, 500);
});

// Auto-hide mensagem de sucesso
<?php if ($msgType === 'success'): ?>
setTimeout(()=>{ const a=document.getElementById('alertMsg'); if(a) a.style.display='none'; }, 6000);
<?php endif ?>
</script>
</body>
</html>
