<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  superadmin/dashboard.php
//   Painel de Gestão de Tenants (Políticos)
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

if (empty($_SESSION[SESSION_SUPER_ADMIN])) {
    header('Location: login.php'); exit;
}

$adminNome = $_SESSION[SESSION_SUPER_ADMIN]['nome'];
$db        = Database::getInstance();
$msg = ''; $msgType = '';

// --- Ações POST ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');

    // Criar tenant
    if ($action === 'create_tenant') {
        $nome    = sanitizeInput($_POST['nome_politico'] ?? '');
        $cargo   = sanitizeInput($_POST['cargo']         ?? '');
        $partido = sanitizeInput($_POST['partido']       ?? '');
        $estado  = sanitizeInput($_POST['estado']        ?? '');
        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower(sanitizeInput($_POST['slug'] ?? '')));
        $plano   = (int)($_POST['plano_id'] ?? 2);
        $status  = sanitizeInput($_POST['status'] ?? 'trial');
        $trial   = sanitizeInput($_POST['trial_expira'] ?? '');
        $email   = sanitizeInput($_POST['email_contato'] ?? '');
        $tel     = sanitizeInput($_POST['telefone'] ?? '');
        $cor1    = sanitizeInput($_POST['cor_primaria'] ?? '#003366');
        // Admin inicial
        $aUsuario = sanitizeInput($_POST['admin_usuario'] ?? '');
        $aSenha   = $_POST['admin_senha'] ?? '';
        $aNome    = sanitizeInput($_POST['admin_nome']   ?? $nome);

        if ($nome && $cargo && $slug && strlen($slug) >= 3 && $aUsuario && strlen($aSenha) >= 6) {

            // 1. Verifica slug único
            $ckSlug = $db->prepare('SELECT id FROM tenants WHERE slug=? LIMIT 1');
            $ckSlug->execute([$slug]);
            $slugExiste = $ckSlug->fetch();

            // 2. Verifica se usuário já existe em qualquer tenant
            $ckUser = $db->prepare(
                'SELECT a.usuario, t.nome_politico FROM administradores a
                 INNER JOIN tenants t ON t.id = a.tenant_id
                 WHERE a.usuario = ? LIMIT 1'
            );
            $ckUser->execute([$aUsuario]);
            $userExiste = $ckUser->fetch();

            if ($slugExiste) {
                $msg = 'Slug <strong>' . htmlspecialchars($slug) . '</strong> já está em uso. Escolha outro.';
                $msgType = 'error';
            } elseif ($userExiste) {
                $msg = 'Usuário <strong>' . htmlspecialchars($aUsuario) . '</strong> já existe no sistema'
                     . ' (tenant: <em>' . htmlspecialchars($userExiste['nome_politico']) . '</em>).'
                     . ' Use um nome de usuário diferente.';
                $msgType = 'error';
            } else {
                try {
                    $db->beginTransaction();
                    // Insere tenant
                    $stmt = $db->prepare(
                        'INSERT INTO tenants (slug,plano_id,nome_politico,cargo,partido,estado,email_contato,telefone,cor_primaria,status,trial_expira)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$slug,$plano,$nome,$cargo,$partido,$estado,$email,$tel,$cor1,$status,$trial?:null]);
                    $tenantId = $db->lastInsertId();

                    // Insere admin
                    $db->prepare(
                        'INSERT INTO administradores (tenant_id,usuario,senha,nome,nivel)
                         VALUES (?,?,?,?,"master")'
                    )->execute([$tenantId, $aUsuario, password_hash($aSenha, PASSWORD_DEFAULT), $aNome]);

                    $db->commit();
                    logActivity(0, 'tenant_criado', "Tenant: $nome ($slug)");
                    $msg = "Tenant \"$nome\" criado com sucesso! Link: " . PLATFORM_URL . "/polix/$slug/admin/";
                    $msgType = 'success';
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log('PoLiX create_tenant: ' . $e->getMessage());
                    $msg = 'Erro ao criar tenant: ' . $e->getMessage(); $msgType = 'error';
                }
            }
        } else {
            $msg = 'Preencha todos os campos obrigatórios. Slug mínimo 3 chars. Senha mínimo 6 chars.';
            $msgType = 'error';
        }
    }

    // Alterar status do tenant
    if ($action === 'toggle_tenant') {
        $id    = (int)($_POST['id'] ?? 0);
        $novo  = sanitizeInput($_POST['novo_status'] ?? '');
        if ($id && in_array($novo, ['ativo','inativo','suspenso','trial'])) {
            $db->prepare('UPDATE tenants SET status=? WHERE id=?')->execute([$novo, $id]);
            $msg = "Status atualizado para $novo."; $msgType = 'success';
        }
    }

    // Deletar tenant
    if ($action === 'delete_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM tenants WHERE id=?')->execute([$id]);
            logActivity(0, 'tenant_excluido', "ID $id");
            $msg = 'Tenant excluído.'; $msgType = 'success';
        }
    }
}

// --- Listar tenants -----------------------------------------------------------
$tenants = $db->query(
    'SELECT t.*, p.nome AS plano_nome,
            (SELECT COUNT(*) FROM cadastros WHERE tenant_id=t.id) AS total_cadastros,
            (SELECT COUNT(*) FROM administradores WHERE tenant_id=t.id) AS total_admins
     FROM tenants t
     LEFT JOIN planos p ON p.id=t.plano_id
     ORDER BY t.data_criacao DESC'
)->fetchAll();

// Stats gerais
$stTotal  = $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
$stAtivos = $db->query('SELECT COUNT(*) FROM tenants WHERE status="ativo"')->fetchColumn();
$stCads   = $db->query('SELECT COUNT(*) FROM cadastros')->fetchColumn();
$planos   = $db->query('SELECT * FROM planos WHERE status="ativo" ORDER BY preco_mensal')->fetchAll();

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Super Admin — <?= PLATFORM_NAME ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#0d0d1a;color:#e0e0e0;min-height:100vh}
.top-header{background:linear-gradient(135deg,#1a1a2e,#0f3460);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.top-header .brand{font-size:.78rem;opacity:.6;letter-spacing:.5px;text-transform:uppercase;color:#aaa}
.top-header h1{font-size:1rem;color:#fff;font-weight:700}
.top-header .hactions{display:flex;gap:8px}
.hbtn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#ddd;padding:7px 14px;border-radius:6px;text-decoration:none;font-size:.82rem;cursor:pointer;transition:background .2s}
.hbtn:hover{background:rgba(255,255,255,.2)}
.content{max-width:1280px;margin:24px auto;padding:0 16px}
.alert{padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.9rem}
.alert-success{background:#d4edda;color:#155724}
.alert-error{background:#f8d7da;color:#721c24}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:#1e1e30;border-radius:10px;padding:20px 16px;text-align:center;border:1px solid #2a2a40}
.stat-card .num{font-size:2rem;font-weight:800;color:#7c9eff}
.stat-card .lbl{font-size:.78rem;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
.section{background:#1e1e30;border-radius:10px;border:1px solid #2a2a40;margin-bottom:24px;overflow:hidden}
.section-header{background:#16213e;padding:14px 20px;display:flex;align-items:center;justify-content:space-between}
.section-header h2{color:#7c9eff;font-size:.95rem}
.btn-new{background:#0f3460;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:.82rem;font-weight:700}
.btn-new:hover{background:#1557a0}
table{width:100%;border-collapse:collapse}
th{background:#16213e;color:#7c9eff;padding:11px 12px;text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.3px}
td{padding:11px 12px;font-size:.85rem;border-bottom:1px solid #2a2a40;color:#ccc}
tr:hover td{background:#252540}
.badge{padding:3px 10px;border-radius:12px;font-size:.73rem;font-weight:700;text-transform:uppercase}
.badge-ativo{background:#1b5e20;color:#a5d6a7}
.badge-trial{background:#1a237e;color:#90caf9}
.badge-suspenso{background:#b71c1c;color:#ef9a9a}
.badge-inativo{background:#424242;color:#bdbdbd}
.link-tenant{color:#7c9eff;text-decoration:none;font-size:.82rem}
.link-tenant:hover{text-decoration:underline}
.act-btn{border:none;background:none;cursor:pointer;font-size:1rem;padding:4px 6px;border-radius:6px;color:#aaa;transition:background .15s}
.act-btn:hover{background:#2a2a40}

/* FORM CRIAR TENANT */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:20px}
.fg{display:flex;flex-direction:column;gap:5px}
.fg label{font-size:.78rem;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.fg input,.fg select{padding:9px 12px;border:2px solid #2a2a40;border-radius:7px;font-size:.9rem;background:#0d0d1a;color:#e0e0e0;transition:border-color .2s}
.fg input:focus,.fg select:focus{outline:none;border-color:#7c9eff}
.fg.full{grid-column:1/-1}
.sep{grid-column:1/-1;border:none;border-top:1px solid #2a2a40;margin:4px 0}
.sep-label{grid-column:1/-1;font-size:.78rem;color:#7c9eff;text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.form-footer{padding:0 20px 20px;text-align:right;display:flex;gap:8px;justify-content:flex-end}
.btn-save{background:linear-gradient(135deg,#0f3460,#1557a0);color:#fff;border:none;padding:11px 24px;border-radius:7px;font-weight:700;cursor:pointer;font-size:.9rem}
.btn-save:hover{opacity:.9}
.btn-cancel{background:#2a2a40;color:#ccc;border:none;padding:11px 18px;border-radius:7px;cursor:pointer;font-size:.9rem}
.hidden{display:none}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}.fg.full{grid-column:1}}
</style>
</head>
<body>

<div class="top-header">
  <div>
    <div class="brand"><?= PLATFORM_NAME ?> · Super Admin</div>
    <h1>Gestão de Tenants</h1>
  </div>
  <div class="hactions">
    <span style="color:#aaa;font-size:.82rem;padding:7px 12px">👤 <?= htmlspecialchars($adminNome) ?></span>
    <a class="hbtn" href="logout.php">🚪 Sair</a>
  </div>
</div>

<div class="content">

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card"><div class="num"><?= $stTotal ?></div><div class="lbl">Tenants</div></div>
    <div class="stat-card"><div class="num"><?= $stAtivos ?></div><div class="lbl">Ativos</div></div>
    <div class="stat-card"><div class="num"><?= number_format($stCads) ?></div><div class="lbl">Cadastros Totais</div></div>
  </div>

  <!-- FORMULÁRIO NOVO TENANT -->
  <div class="section">
    <div class="section-header">
      <h2>➕ Novo Tenant (Político)</h2>
      <button class="btn-new" onclick="toggleForm()">+ Cadastrar</button>
    </div>
    <div id="formWrap" class="hidden">
      <form method="POST">
        <input type="hidden" name="action" value="create_tenant">
        <div class="form-grid">
          <div class="sep-label">Dados do Político</div>
          <div class="fg"><label>Nome do Político *</label>
            <input type="text" name="nome_politico" required placeholder="Ex: João Silva"></div>
          <div class="fg"><label>Cargo *</label>
            <input type="text" name="cargo" required placeholder="Ex: Deputado Estadual"></div>
          <div class="fg"><label>Slug da URL * <small style="color:#666">(apenas letras/números/hífen)</small></label>
            <input type="text" name="slug" id="slugInput" required placeholder="joao-silva" pattern="[a-z0-9\-]{3,60}"
                   oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'');verificarSlug(this.value)">
            <span id="slugFeedback" style="font-size:.75rem;margin-top:3px;display:none"></span>
          </div>
          <div class="fg"><label>Partido</label>
            <input type="text" name="partido" placeholder="MDB, PT, PSD..."></div>
          <div class="fg"><label>Estado</label>
            <select name="estado">
              <option value="">—</option>
              <?php foreach ($estados as $uf): ?><option value="<?= $uf ?>"><?= $uf ?></option><?php endforeach ?>
            </select></div>
          <div class="fg"><label>Plano</label>
            <select name="plano_id">
              <?php foreach ($planos as $pl): ?>
              <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['nome']) ?> — R$ <?= number_format($pl['preco_mensal'],2,',','.') ?>/mês</option>
              <?php endforeach ?>
            </select></div>
          <div class="fg"><label>Status</label>
            <select name="status">
              <option value="trial">Trial</option>
              <option value="ativo">Ativo</option>
            </select></div>
          <div class="fg"><label>Trial expira</label>
            <input type="date" name="trial_expira"></div>
          <div class="fg"><label>E-mail Contato</label>
            <input type="email" name="email_contato"></div>
          <div class="fg"><label>Telefone</label>
            <input type="text" name="telefone"></div>
          <div class="fg"><label>Cor Primária</label>
            <input type="color" name="cor_primaria" value="#003366"></div>

          <hr class="sep">
          <div class="sep-label">Administrador Inicial</div>
          <div class="fg"><label>Usuário Admin *</label>
            <input type="text" name="admin_usuario" id="adminUsuario" required placeholder="admin"
                   autocomplete="off" oninput="verificarUsuario(this.value)">
            <span id="usuarioFeedback" style="font-size:.75rem;margin-top:3px;display:none"></span>
          </div>
          <div class="fg"><label>Senha Admin * <small style="color:#666">(mín. 6 chars)</small></label>
            <input type="password" name="admin_senha" required minlength="6"></div>
          <div class="fg"><label>Nome do Admin</label>
            <input type="text" name="admin_nome" placeholder="Administrador"></div>
        </div>
        <div class="form-footer">
          <button type="button" class="btn-cancel" onclick="toggleForm()">Cancelar</button>
          <button type="submit" class="btn-save">💾 Criar Tenant</button>
        </div>
      </form>
    </div>
  </div>

  <!-- LISTA DE TENANTS -->
  <div class="section">
    <div class="section-header"><h2>🏢 Tenants Cadastrados (<?= count($tenants) ?>)</h2></div>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Político / Slug</th>
          <th>Plano</th>
          <th>Status</th>
          <th>Cadastros</th>
          <th>Admins</th>
          <th>Trial / Assinatura</th>
          <th>Criado em</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tenants as $t): ?>
      <tr>
        <td style="color:#555;font-size:.8rem"><?= $t['id'] ?></td>
        <td>
          <div style="font-weight:700;color:#e0e0e0"><?= htmlspecialchars($t['nome_politico']) ?></div>
          <div style="font-size:.78rem;color:#888"><?= htmlspecialchars($t['cargo']) ?><?php if($t['partido']): ?> · <?= htmlspecialchars($t['partido']) ?><?php endif ?></div>
          <a class="link-tenant" href="<?= htmlspecialchars(PLATFORM_URL.PLATFORM_SUBDIR.'/'.$t['slug'].'/') ?>" target="_blank">
            🔗 /<?= htmlspecialchars($t['slug']) ?>/
          </a>
        </td>
        <td style="font-size:.82rem"><?= htmlspecialchars($t['plano_nome'] ?? '-') ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
        <td style="text-align:center;font-weight:700;color:#7c9eff"><?= number_format($t['total_cadastros']) ?></td>
        <td style="text-align:center"><?= $t['total_admins'] ?></td>
        <td style="font-size:.8rem;color:#888">
          <?php if ($t['trial_expira']): ?>T: <?= formatDate($t['trial_expira']) ?><br><?php endif ?>
          <?php if ($t['assinatura_ate']): ?>A: <?= formatDate($t['assinatura_ate']) ?><?php endif ?>
        </td>
        <td style="font-size:.78rem;color:#888;white-space:nowrap"><?= formatDate($t['data_criacao']) ?></td>
        <td style="white-space:nowrap">
          <a class="act-btn" href="<?= htmlspecialchars(PLATFORM_URL.PLATFORM_SUBDIR.'/'.$t['slug'].'/admin/') ?>" target="_blank" title="Abrir Admin">🔐</a>
          <!-- Toggle status -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_tenant">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <input type="hidden" name="novo_status" value="<?= $t['status']==='ativo'?'suspenso':'ativo' ?>">
            <button type="submit" class="act-btn" title="<?= $t['status']==='ativo'?'Suspender':'Ativar' ?>">
              <?= $t['status']==='ativo' ? '⏸️' : '▶️' ?>
            </button>
          </form>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('ATENÇÃO: Excluir o tenant \"<?= htmlspecialchars($t['nome_politico']) ?>\" e TODOS os seus dados permanentemente?')">
            <input type="hidden" name="action" value="delete_tenant">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button type="submit" class="act-btn" title="Excluir">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
      <?php if (empty($tenants)): ?>
      <tr><td colspan="9" style="text-align:center;padding:40px;color:#555">Nenhum tenant cadastrado ainda.</td></tr>
      <?php endif ?>
      </tbody>
    </table>
    </div>
  </div>

</div>

<script>
function toggleForm(){
  const w = document.getElementById('formWrap');
  w.classList.toggle('hidden');
  if(!w.classList.contains('hidden')){
    document.getElementById('adminUsuario') && document.getElementById('adminUsuario').focus();
  }
}

// --- Verificação de usuário em tempo real ------------------------------------
let timerUser;
function verificarUsuario(val){
  val = val.trim();
  const fb = document.getElementById('usuarioFeedback');
  clearTimeout(timerUser);
  if(val.length < 3){ fb.style.display='none'; return; }

  fb.style.display = 'block';
  fb.style.color   = '#888';
  fb.textContent   = '⏳ Verificando...';

  timerUser = setTimeout(()=>{
    fetch('verificar_disponibilidade.php?tipo=usuario&valor=' + encodeURIComponent(val))
      .then(r=>r.json())
      .then(d=>{
        if(d.disponivel){
          fb.style.color   = '#2e7d32';
          fb.textContent   = '✅ Usuário disponível';
        } else {
          fb.style.color   = '#b71c1c';
          fb.textContent   = '❌ Usuário já existe em: ' + (d.tenant || 'outro cliente');
        }
      })
      .catch(()=>{ fb.style.display='none'; });
  }, 500);
}

// --- Verificação de slug em tempo real ----------------------------------------
let timerSlug;
function verificarSlug(val){
  val = val.trim();
  const fb = document.getElementById('slugFeedback');
  clearTimeout(timerSlug);
  if(val.length < 3){ fb.style.display='none'; return; }

  fb.style.display = 'block';
  fb.style.color   = '#888';
  fb.textContent   = '⏳ Verificando...';

  timerSlug = setTimeout(()=>{
    fetch('verificar_disponibilidade.php?tipo=slug&valor=' + encodeURIComponent(val))
      .then(r=>r.json())
      .then(d=>{
        if(d.disponivel){
          fb.style.color   = '#2e7d32';
          fb.textContent   = '✅ Slug disponível — link: <?= PLATFORM_URL ?>/polix/' + val + '/admin/';
        } else {
          fb.style.color   = '#b71c1c';
          fb.textContent   = '❌ Slug já em uso. Escolha outro.';
        }
      })
      .catch(()=>{ fb.style.display='none'; });
  }, 500);
}
</script>
</body>
</html>
