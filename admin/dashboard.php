<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  admin/dashboard.php
//   Painel Administrativo do Tenant  v3.0
//   Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

$tenant = resolveTenant();
if (!$tenant) { http_response_code(404); die('Tenant não encontrado.'); }

$tenantId = (int)$tenant['id'];

// --- Autenticação --------------------------------------------------------------
if (empty($_SESSION[SESSION_TENANT_ADMIN]['tenant_id'])
    || $_SESSION[SESSION_TENANT_ADMIN]['tenant_id'] !== $tenantId) {
    header('Location: ' . tenantUrl($tenant, 'admin/login'));
    exit;
}
$adminNivel = $_SESSION[SESSION_TENANT_ADMIN]['nivel'] ?? 'operador';
$adminNome  = $_SESSION[SESSION_TENANT_ADMIN]['nome']  ?? 'Admin';
$adminId    = (int)($_SESSION[SESSION_TENANT_ADMIN]['id'] ?? 0);

$db  = Database::getInstance();
$msg = ''; $msgType = '';

// --- Ações POST ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');

    // Editar cadastro
    if ($action === 'edit' && in_array($adminNivel, ['master','admin','operador'])) {
        $id       = (int)($_POST['id'] ?? 0);
        $nome     = sanitizeInput($_POST['nome']       ?? '');
        $cidade   = sanitizeInput($_POST['cidade']     ?? '');
        $cargo    = sanitizeInput($_POST['cargo']      ?? '');
        $tel      = preg_replace('/\D/', '', sanitizeInput($_POST['telefone'] ?? ''));
        $email    = sanitizeInput($_POST['email']      ?? '');
        $nasc     = sanitizeInput($_POST['nascimento'] ?? '');
        $cat      = sanitizeInput($_POST['categoria']  ?? 'eleitor');
        $obs      = sanitizeInput($_POST['observacoes']       ?? '');
        $obsAdm   = sanitizeInput($_POST['observacoes_admin'] ?? '');
        $status   = ($_POST['status'] ?? 'ativo') === 'ativo' ? 'ativo' : 'inativo';
        $partido  = sanitizeInput($_POST['partido_vinculo'] ?? '');
        $nivel    = sanitizeInput($_POST['nivel_politico']  ?? '');

        // Converte data
        $dataNasc = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $nasc, $m)) {
            $dataNasc = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if ($id && $nome && $cidade && $tel && $dataNasc) {
            try {
                $stmt = $db->prepare(
                    'UPDATE cadastros SET nome=?,cidade=?,cargo=?,telefone=?,email=?,
                     data_nascimento=?,categoria=?,observacoes=?,observacoes_admin=?,
                     status=?,partido_vinculo=?,nivel_politico=?
                     WHERE id=? AND tenant_id=?'
                );
                $stmt->execute([
                    $nome, $cidade, $cargo, $tel, $email ?: null,
                    $dataNasc, $cat, $obs ?: null, $obsAdm ?: null,
                    $status, $partido ?: null, $nivel ?: null,
                    $id, $tenantId,
                ]);
                logActivity($tenantId, 'edicao_cadastro', "ID $id editado por $adminNome");
                $msg = 'Cadastro atualizado com sucesso!'; $msgType = 'success';
            } catch (Exception $e) {
                error_log('PoLiX edit: ' . $e->getMessage());
                $msg = 'Erro ao atualizar cadastro.'; $msgType = 'error';
            }
        } else {
            $msg = 'Preencha todos os campos obrigatórios.'; $msgType = 'error';
        }
    }

    // Alternar status
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare('SELECT status FROM cadastros WHERE id=? AND tenant_id=? LIMIT 1');
            $stmt->execute([$id, $tenantId]);
            $cur = $stmt->fetchColumn();
            $novo = ($cur === 'ativo') ? 'inativo' : 'ativo';
            $db->prepare('UPDATE cadastros SET status=? WHERE id=? AND tenant_id=?')
               ->execute([$novo, $id, $tenantId]);
            logActivity($tenantId, 'status_alterado', "ID $id → $novo por $adminNome");
            $msg = "Status alterado para $novo!"; $msgType = 'success';
        }
    }

    // Deletar
    if ($action === 'delete' && in_array($adminNivel, ['master','admin'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM cadastros WHERE id=? AND tenant_id=?')
               ->execute([$id, $tenantId]);
            logActivity($tenantId, 'exclusao_cadastro', "ID $id excluído por $adminNome");
            $msg = 'Cadastro excluído.'; $msgType = 'success';
        }
    }
}

// --- Filtros ------------------------------------------------------------------
$busca    = sanitizeInput($_GET['busca']    ?? '');
$filtStat = sanitizeInput($_GET['status']   ?? '');
$filtCid  = sanitizeInput($_GET['cidade']   ?? '');
$filtCat  = sanitizeInput($_GET['cat']      ?? '');
$pagAtual = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 15;

$where  = ['c.tenant_id = ?'];
$params = [$tenantId];

if ($busca) {
    $where[]  = '(c.nome LIKE ? OR c.cargo LIKE ? OR c.telefone LIKE ? OR c.email LIKE ?)';
    $b        = "%$busca%";
    array_push($params, $b, $b, $b, $b);
}
if ($filtStat) { $where[] = 'c.status = ?';    $params[] = $filtStat; }
if ($filtCid)  { $where[] = 'c.cidade = ?';    $params[] = $filtCid; }
if ($filtCat)  { $where[] = 'c.categoria = ?'; $params[] = $filtCat; }

$whereSQL = implode(' AND ', $where);

// Total
$stmtTot = $db->prepare("SELECT COUNT(*) FROM cadastros c WHERE $whereSQL");
$stmtTot->execute($params);
$total    = (int)$stmtTot->fetchColumn();
$totalPag = (int)ceil($total / $porPag);
$offset   = ($pagAtual - 1) * $porPag;

// Dados
$stmt = $db->prepare("SELECT c.* FROM cadastros c WHERE $whereSQL ORDER BY c.data_cadastro DESC LIMIT $porPag OFFSET $offset");
$stmt->execute($params);
$cadastros = $stmt->fetchAll();

// --- Estatísticas -------------------------------------------------------------
$stats = [];
// Total ativo
$r = $db->prepare('SELECT COUNT(*) FROM cadastros WHERE tenant_id=?');
$r->execute([$tenantId]); $stats['total'] = (int)$r->fetchColumn();

$r = $db->prepare('SELECT COUNT(*) FROM cadastros WHERE tenant_id=? AND status="ativo"');
$r->execute([$tenantId]); $stats['ativos'] = (int)$r->fetchColumn();

$r = $db->prepare('SELECT COUNT(*) FROM cadastros WHERE tenant_id=? AND DATE(data_cadastro)=CURDATE()');
$r->execute([$tenantId]); $stats['hoje'] = (int)$r->fetchColumn();

$r = $db->prepare('SELECT COUNT(*) FROM cadastros WHERE tenant_id=? AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$r->execute([$tenantId]); $stats['semana'] = (int)$r->fetchColumn();

// Agenda próximos 3 dias
$r = $db->prepare('SELECT COUNT(*) FROM agendamentos WHERE tenant_id=? AND data_agendamento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND status NOT IN ("realizado","cancelado")');
$r->execute([$tenantId]); $stats['agenda3'] = (int)$r->fetchColumn();

// Alerta hoje
$rHoje = $db->prepare('SELECT COUNT(*) FROM agendamentos WHERE tenant_id=? AND data_agendamento=CURDATE() AND status NOT IN ("realizado","cancelado")');
$rHoje->execute([$tenantId]); $agendaHoje = (int)$rHoje->fetchColumn();

// Dropdowns filtros
$cidades = $db->prepare('SELECT DISTINCT cidade FROM cadastros WHERE tenant_id=? ORDER BY cidade');
$cidades->execute([$tenantId]); $cidades = $cidades->fetchAll(PDO::FETCH_COLUMN);

$categorias = getCategorias();

// Embed JSON para JavaScript
$cadastrosJSON = json_encode(array_map(fn($c) => [
    'id'               => $c['id'],
    'nome'             => $c['nome'],
    'cidade'           => $c['cidade'],
    'cargo'            => $c['cargo'],
    'telefone'         => $c['telefone'],
    'email'            => $c['email'] ?? '',
    'data_nascimento'  => $c['data_nascimento'],
    'categoria'        => $c['categoria'],
    'observacoes'      => $c['observacoes'] ?? '',
    'observacoes_admin'=> $c['observacoes_admin'] ?? '',
    'partido_vinculo'  => $c['partido_vinculo'] ?? '',
    'nivel_politico'   => $c['nivel_politico'] ?? '',
    'status'           => $c['status'],
    'ip_address'       => $c['ip_address'] ?? '',
    'data_cadastro'    => $c['data_cadastro'],
    'data_ultima_alteracao' => $c['data_ultima_alteracao'] ?? '',
], $cadastros), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT);

// Cores
$c1 = htmlspecialchars($tenant['cor_primaria']   ?: '#003366');
$c2 = htmlspecialchars($tenant['cor_secundaria'] ?: '#0055aa');
$c3 = htmlspecialchars($tenant['cor_acento']     ?: '#0077cc');

// Helper query string
function buildQS(array $merge = [], array $remove = []): string {
    $p = $_GET;
    foreach ($merge  as $k => $v) $p[$k] = $v;
    foreach ($remove as $k)       unset($p[$k]);
    unset($p['pag']);
    return $p ? '?' . http_build_query($p) : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= htmlspecialchars($tenant['nome_politico']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f4f8;min-height:100vh}

/* HEADER */
.top-header{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.top-header .brand{font-size:.78rem;opacity:.75;letter-spacing:.5px;text-transform:uppercase}
.top-header .politico{font-size:1rem;font-weight:700}
.top-header .politico small{font-size:.78rem;opacity:.8;margin-left:8px}
.top-header .hactions{display:flex;gap:8px;align-items:center}
.hbtn{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:7px 14px;border-radius:6px;text-decoration:none;font-size:.82rem;cursor:pointer;white-space:nowrap;transition:background .2s}
.hbtn:hover{background:rgba(255,255,255,.25)}
.user-badge{background:rgba(255,255,255,.1);border-radius:6px;padding:7px 12px;font-size:.82rem}

/* CONTENT */
.content{max-width:1280px;margin:24px auto;padding:0 16px}

/* ALERTAS */
.alert{padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.9rem}
.alert-success{background:#d4edda;color:#155724;border-left:4px solid #28a745}
.alert-error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}
.alert-agenda{background:#fff3cd;color:#856404;border-left:4px solid #ffc107;padding:14px 18px;border-radius:8px;margin-bottom:20px;font-size:.9rem;display:flex;align-items:center;gap:12px}
.alert-agenda a{color:#856404;font-weight:700}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:10px;padding:20px 16px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.06);transition:transform .2s,box-shadow .2s;cursor:default}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1)}
.stat-card .num{font-size:2rem;font-weight:800;color:<?= $c1 ?>}
.stat-card .label{font-size:.78rem;color:#777;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
.stat-card.agenda-card .num{color:#e67e00}

/* FILTROS */
.filter-box{background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:18px 20px;margin-bottom:20px}
.filter-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:12px;align-items:end}
.filter-group{display:flex;flex-direction:column;gap:4px}
.filter-group label{font-size:.78rem;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.filter-group input,
.filter-group select{padding:9px 12px;border:2px solid #e0e8f0;border-radius:7px;font-size:.9rem;transition:border-color .2s}
.filter-group input:focus,
.filter-group select:focus{outline:none;border-color:<?= $c3 ?>}
.filter-actions{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.btn-filter{padding:9px 18px;border-radius:7px;border:none;cursor:pointer;font-size:.85rem;font-weight:700;transition:opacity .2s}
.btn-search{background:<?= $c1 ?>;color:#fff}
.btn-clear{background:#e0e8f0;color:#555}
.btn-excel{background:#1d6f42;color:#fff}
.btn-filter:hover{opacity:.85}

/* TABELA */
.table-wrap{background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden}
.table-header{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f0f4f8}
.table-header h3{color:<?= $c1 ?>;font-size:.95rem}
.table-header .count{font-size:.82rem;color:#888}
table{width:100%;border-collapse:collapse}
th{background:<?= $c1 ?>;color:#fff;padding:11px 12px;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
td{padding:11px 12px;font-size:.88rem;border-bottom:1px solid #f0f4f8;vertical-align:middle}
tr:hover td{background:#f7f9fc}
.badge-status{padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:700;text-transform:uppercase}
.badge-ativo{background:#d4edda;color:#155724}
.badge-inativo{background:#f8d7da;color:#721c24}
.badge-cat{background:#e3edff;color:<?= $c2 ?>;padding:2px 8px;border-radius:10px;font-size:.73rem}
.obs-badge{background:#fff3e0;color:#e65100;border-radius:6px;padding:2px 7px;font-size:.72rem;display:inline-block;margin-top:3px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.td-name{max-width:180px}
.td-name .name{font-weight:600;color:#1a1a1a}
.td-contact .tel{font-weight:600}
.td-contact .mail{font-size:.8rem;color:#888}
.td-actions{white-space:nowrap;text-align:right}
.act-btn{border:none;background:none;cursor:pointer;font-size:1.1rem;padding:4px 6px;border-radius:6px;transition:background .15s}
.act-btn:hover{background:#f0f4f8}

/* PAGINAÇÃO */
.pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #f0f4f8;flex-wrap:wrap;gap:8px}
.pagination .info{font-size:.82rem;color:#888}
.pag-btns{display:flex;gap:4px;flex-wrap:wrap}
.pag-btn{padding:6px 12px;border-radius:6px;border:2px solid #e0e8f0;background:#fff;color:#555;text-decoration:none;font-size:.82rem;font-weight:600;transition:all .2s}
.pag-btn.active{background:<?= $c1 ?>;color:#fff;border-color:<?= $c1 ?>}
.pag-btn:hover:not(.active){background:#f0f4f8;border-color:<?= $c3 ?>}
.pag-btn.disabled{opacity:.4;pointer-events:none}

/* MODAIS */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal{background:#fff;border-radius:14px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:popIn .2s ease}
@keyframes popIn{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.modal-header{padding:22px 24px 14px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;justify-content:space-between}
.modal-header h2{font-size:1rem;color:#fff}
.modal-header-view{background:linear-gradient(135deg,#5e35b1,#7c4dff)}
.modal-header-edit{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>)}
.modal-close{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:1.2rem;cursor:pointer;border-radius:6px;padding:4px 10px;line-height:1}
.modal-body{padding:24px}
.info-section{margin-bottom:20px}
.info-section h3{font-size:.78rem;color:<?= $c1 ?>;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #f0f4f8}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.info-item{background:#f7f9fc;border-radius:8px;padding:10px 12px}
.info-item .lbl{font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.info-item .val{font-size:.9rem;color:#1a1a1a;font-weight:600;word-break:break-word}
.info-item.full{grid-column:1/-1}
.obs-box{background:#f7f9fc;border-radius:8px;padding:12px;font-size:.88rem;color:#333;line-height:1.5;white-space:pre-wrap;word-break:break-word}
.obs-admin-box{background:#e8f0fe;border-radius:8px;padding:12px;font-size:.88rem;color:#1a237e;line-height:1.5;white-space:pre-wrap;word-break:break-word;border-left:3px solid <?= $c3 ?>}
.modal-footer{padding:14px 24px;border-top:1px solid #f0f4f8;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.mbtn{padding:9px 18px;border-radius:7px;border:none;cursor:pointer;font-size:.85rem;font-weight:700;transition:opacity .2s}
.mbtn:hover{opacity:.85}
.mbtn-primary{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff}
.mbtn-green{background:#1d6f42;color:#fff}
.mbtn-gray{background:#e0e8f0;color:#555}

/* FORM EDIÇÃO */
.edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.edit-group{display:flex;flex-direction:column;gap:5px}
.edit-group label{font-size:.78rem;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.edit-group input,
.edit-group select,
.edit-group textarea{padding:9px 12px;border:2px solid #e0e8f0;border-radius:7px;font-size:.9rem;transition:border-color .2s}
.edit-group input:focus,
.edit-group select:focus,
.edit-group textarea:focus{outline:none;border-color:<?= $c3 ?>}
.edit-group.full{grid-column:1/-1}
.edit-group textarea{resize:vertical;min-height:70px}
.edit-group .obs-admin-input{background:#e8f0fe;border-color:<?= $c3 ?>}

.empty-state{text-align:center;padding:50px 20px;color:#aaa}
.empty-state .icon{font-size:3rem;margin-bottom:12px}

@media(max-width:768px){
  .filter-grid{grid-template-columns:1fr 1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  th:nth-child(4),td:nth-child(4),
  th:nth-child(5),td:nth-child(5){display:none}
  .top-header{flex-direction:column;align-items:flex-start}
  .info-grid,.edit-grid{grid-template-columns:1fr}
}
@media(max-width:480px){
  .filter-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- HEADER -->
<div class="top-header">
  <div>
    <div class="brand"><?= PLATFORM_NAME ?> · Dashboard</div>
    <div class="politico">
      <?= htmlspecialchars($tenant['nome_politico']) ?>
      <small><?= htmlspecialchars($tenant['cargo']) ?>
      <?php if ($tenant['partido']): ?> · <?= htmlspecialchars($tenant['partido']) ?><?php endif ?>
      </small>
    </div>
  </div>
  <div class="hactions">
    <span class="user-badge">👤 <?= htmlspecialchars($adminNome) ?></span>
    <?php if ($tenant['tem_calendario'] ?? true): ?>
    <a class="hbtn" href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/calendario')) ?>">📅 Calendário</a>
    <?php endif ?>
    <a class="hbtn" href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/logout')) ?>">🚪 Sair</a>
  </div>
</div>

<div class="content">

  <!-- ALERTA DE AGENDA HOJE -->
  <?php if ($agendaHoje > 0): ?>
  <div class="alert-agenda">
    📅 <span>Você tem <strong><?= $agendaHoje ?> compromisso<?= $agendaHoje>1?'s':'' ?></strong> para hoje!
    <a href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/calendario')) ?>"> → Ver Calendário</a></span>
  </div>
  <?php endif ?>

  <!-- MENSAGEM DE AÇÃO -->
  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>" id="alertMsg"><?= htmlspecialchars($msg) ?></div>
  <?php endif ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card"><div class="num"><?= number_format($stats['total']) ?></div><div class="label">Total</div></div>
    <div class="stat-card"><div class="num"><?= number_format($stats['ativos']) ?></div><div class="label">Ativos</div></div>
    <div class="stat-card"><div class="num"><?= number_format($stats['hoje']) ?></div><div class="label">Hoje</div></div>
    <div class="stat-card"><div class="num"><?= number_format($stats['semana']) ?></div><div class="label">Esta Semana</div></div>
    <div class="stat-card agenda-card" title="Próximos 3 dias">
      <div class="num"><?= number_format($stats['agenda3']) ?></div>
      <div class="label">Agenda 3d</div>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="filter-box">
    <form method="GET" id="formFiltros">
      <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
      <div class="filter-grid">
        <div class="filter-group">
          <label>Busca</label>
          <input type="text" name="busca" placeholder="Nome, cargo, telefone, e-mail..."
                 value="<?= htmlspecialchars($busca) ?>">
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="">Todos</option>
            <option value="ativo"   <?= $filtStat==='ativo'  ?'selected':'' ?>>Ativo</option>
            <option value="inativo" <?= $filtStat==='inativo'?'selected':'' ?>>Inativo</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Cidade</label>
          <select name="cidade">
            <option value="">Todas</option>
            <?php foreach ($cidades as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $filtCid===$c?'selected':'' ?>>
              <?= htmlspecialchars($c) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Categoria</label>
          <select name="cat">
            <option value="">Todas</option>
            <?php foreach ($categorias as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $filtCat===$val?'selected':'' ?>>
              <?= htmlspecialchars($lbl) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-filter btn-search">🔍 Filtrar</button>
        <a href="?slug=<?= urlencode($tenant['slug']) ?>" class="btn-filter btn-clear">✖ Limpar</a>
        <button type="button" class="btn-filter btn-excel" onclick="copiarTudoExcel()">📋 Copiar Excel</button>
      </div>
    </form>
  </div>

  <!-- TABELA -->
  <div class="table-wrap">
    <div class="table-header">
      <h3>📋 Cadastros</h3>
      <div class="count">
        <?= number_format($total) ?> encontrado<?= $total!==1?'s':'' ?>
        <?php if ($total > 0): ?>
        · pág. <?= $pagAtual ?>/<?= max(1,$totalPag) ?>
        <?php endif ?>
      </div>
    </div>

    <?php if (empty($cadastros)): ?>
    <div class="empty-state">
      <div class="icon">👤</div>
      <p>Nenhum cadastro encontrado.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Cidade</th>
          <th>Contato</th>
          <th>Categoria</th>
          <th>Status</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($cadastros as $c): ?>
      <tr>
        <td style="color:#aaa;font-size:.8rem"><?= $c['id'] ?></td>
        <td class="td-name">
          <div class="name"><?= htmlspecialchars($c['nome']) ?></div>
          <div style="font-size:.78rem;color:#888"><?= htmlspecialchars($c['cargo']) ?></div>
          <?php if ($c['observacoes_admin']): ?>
          <div class="obs-badge" title="<?= htmlspecialchars($c['observacoes_admin']) ?>">
            📝 <?= htmlspecialchars(mb_substr($c['observacoes_admin'],0,30)) ?>…
          </div>
          <?php endif ?>
        </td>
        <td><?= htmlspecialchars($c['cidade']) ?></td>
        <td class="td-contact">
          <div class="tel">📱 <?= htmlspecialchars($c['telefone']) ?></div>
          <?php if ($c['email']): ?><div class="mail">✉ <?= htmlspecialchars($c['email']) ?></div><?php endif ?>
        </td>
        <td><span class="badge-cat"><?= htmlspecialchars($categorias[$c['categoria']] ?? $c['categoria']) ?></span></td>
        <td><span class="badge-status badge-<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
        <td style="font-size:.8rem;white-space:nowrap;color:#888"><?= formatDate($c['data_cadastro']) ?></td>
        <td class="td-actions">
          <button class="act-btn" title="Ver" onclick="openView(<?= $c['id'] ?>)">👁️</button>
          <button class="act-btn" title="Editar" onclick="openEdit(<?= $c['id'] ?>)">✏️</button>
          <form method="POST" style="display:inline" onsubmit="return true">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <?php $qs = buildQS(); ?>
            <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
            <button type="submit" class="act-btn" title="<?= $c['status']==='ativo'?'Desativar':'Ativar' ?>">
              <?= $c['status']==='ativo' ? '⏸️' : '▶️' ?>
            </button>
          </form>
          <?php if (in_array($adminNivel,['master','admin'])): ?>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Excluir este cadastro permanentemente?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
            <button type="submit" class="act-btn" title="Excluir">🗑️</button>
          </form>
          <?php endif ?>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    </div>

    <!-- PAGINAÇÃO -->
    <?php if ($totalPag > 1): ?>
    <div class="pagination">
      <div class="info">Mostrando <?= count($cadastros) ?> de <?= number_format($total) ?></div>
      <div class="pag-btns">
        <a class="pag-btn <?= $pagAtual<=1?'disabled':'' ?>"
           href="<?= buildQS() ?>&pag=<?= $pagAtual-1 ?>">‹</a>
        <?php
        $inicio = max(1, $pagAtual-2);
        $fim    = min($totalPag, $pagAtual+2);
        if ($inicio > 1): ?><a class="pag-btn" href="<?= buildQS() ?>&pag=1">1</a><?php endif ?>
        <?php for ($p=$inicio;$p<=$fim;$p++): ?>
        <a class="pag-btn <?= $p===$pagAtual?'active':'' ?>"
           href="<?= buildQS() ?>&pag=<?= $p ?>"><?= $p ?></a>
        <?php endfor ?>
        <?php if ($fim < $totalPag): ?><a class="pag-btn" href="<?= buildQS() ?>&pag=<?= $totalPag ?>"><?= $totalPag ?></a><?php endif ?>
        <a class="pag-btn <?= $pagAtual>=$totalPag?'disabled':'' ?>"
           href="<?= buildQS() ?>&pag=<?= $pagAtual+1 ?>">›</a>
      </div>
    </div>
    <?php endif ?>
    <?php endif ?>
  </div><!-- /table-wrap -->

</div><!-- /content -->

<!-- MODAL VISUALIZAÇÃO -->
<div class="modal-overlay" id="overlayView" onclick="if(event.target===this)closeView()">
  <div class="modal" id="modalView">
    <div class="modal-header modal-header-view">
      <h2>👁️ Detalhes do Cadastro</h2>
      <button class="modal-close" onclick="closeView()">✕</button>
    </div>
    <div class="modal-body" id="viewBody"></div>
    <div class="modal-footer">
      <button class="mbtn mbtn-green" onclick="copiarUmExcel(currentId)">📋 Copiar Excel</button>
      <button class="mbtn mbtn-primary" onclick="closeView();openEdit(currentId)">✏️ Editar</button>
      <button class="mbtn mbtn-gray" onclick="window.print()">🖨️ Imprimir</button>
      <button class="mbtn mbtn-gray" onclick="closeView()">Fechar</button>
    </div>
  </div>
</div>

<!-- MODAL EDIÇÃO -->
<div class="modal-overlay" id="overlayEdit" onclick="if(event.target===this)closeEdit()">
  <div class="modal" id="modalEdit">
    <div class="modal-header modal-header-edit">
      <h2>✏️ Editar Cadastro</h2>
      <button class="modal-close" onclick="closeEdit()">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="formEdit">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
        <div class="edit-grid">
          <div class="edit-group full">
            <label>Nome Completo *</label>
            <input type="text" name="nome" id="editNome" required>
          </div>
          <div class="edit-group">
            <label>Cidade *</label>
            <input type="text" name="cidade" id="editCidade" required>
          </div>
          <div class="edit-group">
            <label>Cargo / Ocupação</label>
            <input type="text" name="cargo" id="editCargo">
          </div>
          <div class="edit-group">
            <label>Telefone *</label>
            <input type="tel" name="telefone" id="editTelefone" maxlength="15" required>
          </div>
          <div class="edit-group">
            <label>E-mail</label>
            <input type="email" name="email" id="editEmail">
          </div>
          <div class="edit-group">
            <label>Nascimento (DD/MM/AAAA) *</label>
            <input type="text" name="nascimento" id="editNascimento" maxlength="10" required>
          </div>
          <div class="edit-group">
            <label>Categoria</label>
            <select name="categoria" id="editCategoria">
              <?php foreach ($categorias as $val => $lbl): ?>
              <option value="<?= $val ?>"><?= htmlspecialchars($lbl) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="edit-group">
            <label>Status</label>
            <select name="status" id="editStatus">
              <option value="ativo">Ativo</option>
              <option value="inativo">Inativo</option>
            </select>
          </div>
          <div class="edit-group">
            <label>Partido Vínculo</label>
            <input type="text" name="partido_vinculo" id="editPartido">
          </div>
          <div class="edit-group full">
            <label>Observações do Cidadão</label>
            <textarea name="observacoes" id="editObs"></textarea>
          </div>
          <div class="edit-group full">
            <label>⭐ Observações Administrativas (interno)</label>
            <textarea name="observacoes_admin" id="editObsAdm" class="obs-admin-input"></textarea>
          </div>
        </div>
        <div style="text-align:right;margin-top:16px;display:flex;gap:8px;justify-content:flex-end">
          <button type="button" class="mbtn mbtn-gray" onclick="closeEdit()">Cancelar</button>
          <button type="submit" class="mbtn mbtn-primary">💾 Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="footer" style="text-align:center;padding:20px;font-size:.75rem;color:#aaa">
  <?= PLATFORM_NAME ?> v<?= PLATFORM_VERSION ?> ·
  Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank" style="color:<?= $c3 ?>;text-decoration:none"><?= DEV_NOME ?></a>
</div>

<script>
const cadastrosData = <?= $cadastrosJSON ?>;
const categorias    = <?= json_encode($categorias, JSON_HEX_TAG) ?>;
let currentId = null;

function findById(id){ return cadastrosData.find(c=>c.id==id); }

// --- Modal View ---
function openView(id){
  currentId = id;
  const c = findById(id);
  if(!c) return;

  const nasc = c.data_nascimento;
  let idade = '';
  if(nasc){
    const d = new Date(nasc); const today = new Date();
    let a = today.getFullYear()-d.getFullYear();
    if(today.getMonth()<d.getMonth()||(today.getMonth()==d.getMonth()&&today.getDate()<d.getDate()))a--;
    idade = ` (${a} anos)`;
  }
  function fmt(d){ if(!d)return '-'; const p=d.split(/[-T ]/); return p[2]+'/'+p[1]+'/'+p[0]; }

  let html = `
  <div class="info-section">
    <h3>📋 Dados Pessoais</h3>
    <div class="info-grid">
      <div class="info-item full"><div class="lbl">Nome</div><div class="val">${esc(c.nome)}</div></div>
      <div class="info-item"><div class="lbl">Cidade</div><div class="val">${esc(c.cidade)}</div></div>
      <div class="info-item"><div class="lbl">Cargo</div><div class="val">${esc(c.cargo)||'-'}</div></div>
      <div class="info-item"><div class="lbl">Telefone</div><div class="val">📱 ${esc(c.telefone)}</div></div>
      <div class="info-item"><div class="lbl">E-mail</div><div class="val">${c.email?'✉ '+esc(c.email):'-'}</div></div>
      <div class="info-item"><div class="lbl">Nascimento</div><div class="val">${fmt(nasc)}${idade}</div></div>
      <div class="info-item"><div class="lbl">Categoria</div><div class="val">${esc(categorias[c.categoria]||c.categoria)}</div></div>
      <div class="info-item"><div class="lbl">Status</div><div class="val"><span class="badge-status badge-${c.status}">${c.status}</span></div></div>
      ${c.partido_vinculo?`<div class="info-item"><div class="lbl">Partido</div><div class="val">${esc(c.partido_vinculo)}</div></div>`:''}
    </div>
  </div>`;

  if(c.observacoes){
    html+=`<div class="info-section"><h3>💬 Observações do Cidadão</h3>
    <div class="obs-box">${esc(c.observacoes)}</div></div>`;
  }
  if(c.observacoes_admin){
    html+=`<div class="info-section"><h3>⭐ Observações Administrativas</h3>
    <div class="obs-admin-box">${esc(c.observacoes_admin)}</div></div>`;
  }

  html+=`<div class="info-section"><h3>⚙️ Sistema</h3>
    <div class="info-grid">
      <div class="info-item"><div class="lbl">ID</div><div class="val">#${c.id}</div></div>
      <div class="info-item"><div class="lbl">IP</div><div class="val">${esc(c.ip_address)||'-'}</div></div>
      <div class="info-item"><div class="lbl">Cadastrado em</div><div class="val">${fmt(c.data_cadastro)}</div></div>
      <div class="info-item"><div class="lbl">Última alteração</div><div class="val">${c.data_ultima_alteracao?fmt(c.data_ultima_alteracao):'-'}</div></div>
    </div>
  </div>`;

  document.getElementById('viewBody').innerHTML = html;
  document.getElementById('overlayView').classList.add('show');
}
function closeView(){ document.getElementById('overlayView').classList.remove('show'); }

// --- Modal Edit ---
function openEdit(id){
  closeView();
  currentId = id;
  const c = findById(id);
  if(!c) return;

  function dbToDisplay(d){
    if(!d) return '';
    const p=d.split('-');
    return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:'';
  }

  document.getElementById('editId').value        = c.id;
  document.getElementById('editNome').value       = c.nome;
  document.getElementById('editCidade').value     = c.cidade;
  document.getElementById('editCargo').value      = c.cargo||'';
  document.getElementById('editEmail').value      = c.email||'';
  document.getElementById('editObs').value        = c.observacoes||'';
  document.getElementById('editObsAdm').value     = c.observacoes_admin||'';
  document.getElementById('editPartido').value    = c.partido_vinculo||'';
  document.getElementById('editStatus').value     = c.status;
  document.getElementById('editCategoria').value  = c.categoria;
  document.getElementById('editNascimento').value = dbToDisplay(c.data_nascimento);

  // Formata telefone
  let t = c.telefone.replace(/\D/g,'');
  if(t.length===11) t=t.replace(/^(\d{2})(\d{5})(\d{4})$/,'($1) $2-$3');
  else if(t.length===10) t=t.replace(/^(\d{2})(\d{4})(\d{4})$/,'($1) $2-$3');
  document.getElementById('editTelefone').value = t;

  document.getElementById('overlayEdit').classList.add('show');
}
function closeEdit(){ document.getElementById('overlayEdit').classList.remove('show'); }

// --- ESC fecha modais ---
document.addEventListener('keydown', e=>{ if(e.key==='Escape'){closeView();closeEdit();} });

// --- Máscaras ---
document.getElementById('editTelefone').addEventListener('input', function(){
  let v=this.value.replace(/\D/g,'').substring(0,11);
  if(v.length>10) v=v.replace(/^(\d{2})(\d{5})(\d{4})$/,'($1) $2-$3');
  else if(v.length>6) v=v.replace(/^(\d{2})(\d{4})(\d*)$/,'($1) $2-$3');
  else if(v.length>2) v=v.replace(/^(\d{2})(\d*)$/,'($1) $2');
  this.value=v;
});
document.getElementById('editNascimento').addEventListener('input', function(){
  let v=this.value.replace(/\D/g,'').substring(0,8);
  if(v.length>4) v=v.replace(/^(\d{2})(\d{2})(\d*)$/,'$1/$2/$3');
  else if(v.length>2) v=v.replace(/^(\d{2})(\d*)$/,'$1/$2');
  this.value=v;
});

// --- Copiar Excel ---
function copiarExcel(linhas){
  const txt = linhas.join('\n');
  if(navigator.clipboard){
    navigator.clipboard.writeText(txt).then(()=>alert('✅ Copiado! Cole no Excel (Ctrl+V).'));
  } else {
    const ta=document.createElement('textarea');
    ta.value=txt; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    alert('✅ Copiado!');
  }
}

function copiarTudoExcel(){
  const header = 'Nome\tCidade\tTelefone\tE-mail\tCargo\tCategoria\tStatus\tData';
  const linhas  = [header].concat(cadastrosData.map(c=>
    [c.nome,c.cidade,c.telefone,c.email||'',c.cargo||'',categorias[c.categoria]||c.categoria,c.status,c.data_cadastro.split('T')[0]].join('\t')
  ));
  copiarExcel(linhas);
}

function copiarUmExcel(id){
  const c = findById(id);
  if(!c) return;
  copiarExcel(['Nome\tCidade\tTelefone\tE-mail',
    [c.nome,c.cidade,c.telefone,c.email||''].join('\t')]);
}

// --- Utilitário esc ---
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Auto-hide msg
<?php if ($msg): ?>
setTimeout(()=>{ const a=document.getElementById('alertMsg'); if(a) a.style.display='none'; }, 6000);
<?php endif ?>
</script>
</body>
</html>
