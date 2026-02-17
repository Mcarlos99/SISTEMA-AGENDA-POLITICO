<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  admin/calendario.php
//   Calendário de Agendamentos  v3.0
//   Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

$tenant = resolveTenant();
if (!$tenant) { http_response_code(404); die('Tenant não encontrado.'); }

$tenantId = (int)$tenant['id'];

// --- Autenticação -------------------------------------------------------------
if (empty($_SESSION[SESSION_TENANT_ADMIN]['tenant_id'])
    || $_SESSION[SESSION_TENANT_ADMIN]['tenant_id'] !== $tenantId) {
    header('Location: ' . platformUrl(''));
    exit;
}
$adminNome  = $_SESSION[SESSION_TENANT_ADMIN]['nome']  ?? 'Admin';
$adminId    = (int)($_SESSION[SESSION_TENANT_ADMIN]['id'] ?? 0);
$adminNivel = $_SESSION[SESSION_TENANT_ADMIN]['nivel'] ?? 'operador';

$db  = Database::getInstance();
$msg = ''; $msgType = '';

// --- Ações POST ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');

    // Criar agendamento
    if ($action === 'criar') {
        $titulo    = sanitizeInput($_POST['titulo']    ?? '');
        $descricao = sanitizeInput($_POST['descricao'] ?? '');
        $data      = sanitizeInput($_POST['data']      ?? '');
        $horaIni   = sanitizeInput($_POST['hora_inicio'] ?? '');
        $horaFim   = sanitizeInput($_POST['hora_fim']    ?? '');
        $tipo      = sanitizeInput($_POST['tipo']      ?? 'retorno');
        $prioridade= sanitizeInput($_POST['prioridade']?? 'media');
        $local     = sanitizeInput($_POST['local']     ?? '');
        $obs       = sanitizeInput($_POST['observacoes'] ?? '');
        $cadastroId= (int)($_POST['cadastro_id'] ?? 0);

        // Converte data DD/MM/AAAA → AAAA-MM-DD se necessário
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            $data = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if ($titulo && $data && $horaIni) {
            try {
                $stmt = $db->prepare(
                    'INSERT INTO agendamentos
                        (tenant_id, cadastro_id, titulo, descricao, data_agendamento,
                         hora_inicio, hora_fim, tipo, status, prioridade, local,
                         observacoes, criado_por)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $tenantId,
                    $cadastroId ?: null,
                    $titulo, $descricao ?: null, $data,
                    $horaIni, $horaFim ?: null,
                    $tipo, 'agendado', $prioridade,
                    $local ?: null, $obs ?: null,
                    $adminId,
                ]);
                logActivity($tenantId, 'agendamento_criado', "$titulo em $data por $adminNome");
                $msg = 'Agendamento criado com sucesso!'; $msgType = 'success';
            } catch (Exception $e) {
                error_log('PoLiX cal criar: ' . $e->getMessage());
                $msg = 'Erro ao criar agendamento.'; $msgType = 'error';
            }
        } else {
            $msg = 'Preencha Título, Data e Hora de Início.'; $msgType = 'error';
        }
    }

    // Editar agendamento
    if ($action === 'editar') {
        $id        = (int)($_POST['id'] ?? 0);
        $titulo    = sanitizeInput($_POST['titulo']    ?? '');
        $descricao = sanitizeInput($_POST['descricao'] ?? '');
        $data      = sanitizeInput($_POST['data']      ?? '');
        $horaIni   = sanitizeInput($_POST['hora_inicio'] ?? '');
        $horaFim   = sanitizeInput($_POST['hora_fim']    ?? '');
        $tipo      = sanitizeInput($_POST['tipo']      ?? 'retorno');
        $status    = sanitizeInput($_POST['status']    ?? 'agendado');
        $prioridade= sanitizeInput($_POST['prioridade']?? 'media');
        $local     = sanitizeInput($_POST['local']     ?? '');
        $obs       = sanitizeInput($_POST['observacoes'] ?? '');
        $cadastroId= (int)($_POST['cadastro_id'] ?? 0);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            $data = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if ($id && $titulo && $data && $horaIni) {
            try {
                $db->prepare(
                    'UPDATE agendamentos SET
                        cadastro_id=?, titulo=?, descricao=?, data_agendamento=?,
                        hora_inicio=?, hora_fim=?, tipo=?, status=?, prioridade=?,
                        local=?, observacoes=?
                     WHERE id=? AND tenant_id=?'
                )->execute([
                    $cadastroId ?: null,
                    $titulo, $descricao ?: null, $data,
                    $horaIni, $horaFim ?: null,
                    $tipo, $status, $prioridade,
                    $local ?: null, $obs ?: null,
                    $id, $tenantId,
                ]);
                logActivity($tenantId, 'agendamento_editado', "ID $id editado por $adminNome");
                $msg = 'Agendamento atualizado!'; $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Erro ao atualizar.'; $msgType = 'error';
            }
        } else {
            $msg = 'Preencha os campos obrigatórios.'; $msgType = 'error';
        }
    }

    // Deletar
    if ($action === 'deletar' && in_array($adminNivel, ['master','admin'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM agendamentos WHERE id=? AND tenant_id=?')
               ->execute([$id, $tenantId]);
            logActivity($tenantId, 'agendamento_excluido', "ID $id excluído por $adminNome");
            $msg = 'Agendamento excluído.'; $msgType = 'success';
        }
    }

    // Alterar status rápido
    if ($action === 'status') {
        $id     = (int)($_POST['id']     ?? 0);
        $status = sanitizeInput($_POST['novo_status'] ?? '');
        $validos = ['agendado','confirmado','realizado','cancelado'];
        if ($id && in_array($status, $validos)) {
            $db->prepare('UPDATE agendamentos SET status=? WHERE id=? AND tenant_id=?')
               ->execute([$status, $id, $tenantId]);
            $msg = "Status → $status"; $msgType = 'success';
        }
    }
}

// --- Mês/Ano atual ------------------------------------------------------------
$mesAtual = (int)($_GET['mes'] ?? date('n'));
$anoAtual = (int)($_GET['ano'] ?? date('Y'));
// Navegação segura
if ($mesAtual < 1)  { $mesAtual = 12; $anoAtual--; }
if ($mesAtual > 12) { $mesAtual = 1;  $anoAtual++; }

$mesPrev = $mesAtual - 1; $anoPrev = $anoAtual;
if ($mesPrev < 1)  { $mesPrev = 12; $anoPrev--; }
$mesNext = $mesAtual + 1; $anoNext = $anoAtual;
if ($mesNext > 12) { $mesNext = 1;  $anoNext++; }

$primeiroDia    = mktime(0,0,0,$mesAtual,1,$anoAtual);
$ultimoDia      = mktime(0,0,0,$mesAtual+1,0,$anoAtual);
$diasNoMes      = (int)date('t', $primeiroDia);
$diaSemanaInicio= (int)date('w', $primeiroDia); // 0=dom

$mesesNomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// --- Carrega agendamentos do mês ---------------------------------------------
$stmt = $db->prepare(
    'SELECT a.*, c.nome AS cadastro_nome
     FROM agendamentos a
     LEFT JOIN cadastros c ON c.id = a.cadastro_id
     WHERE a.tenant_id = ?
       AND a.data_agendamento BETWEEN ? AND ?
     ORDER BY a.data_agendamento, a.hora_inicio'
);
$stmt->execute([
    $tenantId,
    date('Y-m-d', $primeiroDia),
    date('Y-m-d', $ultimoDia),
]);
$agendamentos = $stmt->fetchAll();

// Indexa por dia
$porDia = [];
foreach ($agendamentos as $ag) {
    $dia = (int)date('j', strtotime($ag['data_agendamento']));
    $porDia[$dia][] = $ag;
}

// --- Lista próximos 30 dias para o painel lateral ----------------------------
$stmtLista = $db->prepare(
    'SELECT a.*, c.nome AS cadastro_nome
     FROM agendamentos a
     LEFT JOIN cadastros c ON c.id = a.cadastro_id
     WHERE a.tenant_id = ?
       AND a.data_agendamento >= CURDATE()
       AND a.status NOT IN ("realizado","cancelado")
     ORDER BY a.data_agendamento, a.hora_inicio
     LIMIT 20'
);
$stmtLista->execute([$tenantId]);
$proximos = $stmtLista->fetchAll();

// --- Lista de cadastros para o select (busca) --------------------------------
$stmtCads = $db->prepare(
    'SELECT id, nome, telefone FROM cadastros
     WHERE tenant_id=? AND status="ativo"
     ORDER BY nome LIMIT 500'
);
$stmtCads->execute([$tenantId]);
$cadsList = $stmtCads->fetchAll();

// Cores / tema
$c1 = htmlspecialchars($tenant['cor_primaria']   ?: '#003366');
$c2 = htmlspecialchars($tenant['cor_secundaria'] ?: '#0055aa');
$c3 = htmlspecialchars($tenant['cor_acento']     ?: '#0077cc');

// Cores por prioridade
$corPrioridade = [
    'baixa'   => '#43a047',
    'media'   => '#1e88e5',
    'alta'    => '#e65100',
    'urgente' => '#b71c1c',
];
$corStatus = [
    'agendado'   => '#1e88e5',
    'confirmado' => '#43a047',
    'realizado'  => '#757575',
    'cancelado'  => '#e53935',
];

// Embed JSON dos agendamentos para JS
$agJSON = json_encode(array_map(fn($a) => [
    'id'             => $a['id'],
    'titulo'         => $a['titulo'],
    'descricao'      => $a['descricao'] ?? '',
    'data'           => $a['data_agendamento'],
    'hora_inicio'    => substr($a['hora_inicio'],0,5),
    'hora_fim'       => $a['hora_fim'] ? substr($a['hora_fim'],0,5) : '',
    'tipo'           => $a['tipo'],
    'status'         => $a['status'],
    'prioridade'     => $a['prioridade'],
    'local'          => $a['local'] ?? '',
    'observacoes'    => $a['observacoes'] ?? '',
    'cadastro_id'    => $a['cadastro_id'] ?? 0,
    'cadastro_nome'  => $a['cadastro_nome'] ?? '',
], $agendamentos), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calendário — <?= htmlspecialchars($tenant['nome_politico']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f4f8;min-height:100vh}

/* HEADER */
.top-header{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.top-header .brand{font-size:.78rem;opacity:.75;letter-spacing:.5px;text-transform:uppercase}
.top-header .politico{font-size:1rem;font-weight:700}
.hactions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.hbtn{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:7px 14px;border-radius:6px;text-decoration:none;font-size:.82rem;cursor:pointer;transition:background .2s;white-space:nowrap}
.hbtn:hover{background:rgba(255,255,255,.25)}
.hbtn-destaque{background:rgba(255,255,255,.3);font-weight:700}

/* LAYOUT */
.content{max-width:1280px;margin:24px auto;padding:0 16px;display:grid;grid-template-columns:1fr 300px;gap:20px}
@media(max-width:900px){.content{grid-template-columns:1fr}}

/* ALERTAS */
.alert{padding:14px 18px;border-radius:8px;margin-bottom:16px;font-size:.9rem}
.alert-success{background:#d4edda;color:#155724;border-left:4px solid #28a745}
.alert-error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}

/* CALENDÁRIO */
.cal-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.cal-nav{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between}
.cal-nav h2{font-size:1.1rem;font-weight:700}
.nav-btn{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:.85rem;transition:background .2s}
.nav-btn:hover{background:rgba(255,255,255,.35)}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr)}
.cal-dow{background:#f7f9fc;padding:10px 4px;text-align:center;font-size:.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e8eef4}
.cal-day{min-height:90px;padding:6px;border-right:1px solid #f0f4f8;border-bottom:1px solid #f0f4f8;vertical-align:top;cursor:pointer;transition:background .15s;position:relative}
.cal-day:hover{background:#f7f9fc}
.cal-day.empty{background:#fafafa;cursor:default}
.cal-day.hoje{background:#e8f4fd}
.cal-day.hoje .day-num{background:<?= $c1 ?>;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center}
.day-num{font-size:.85rem;font-weight:700;color:#444;margin-bottom:4px;width:26px;height:26px;display:flex;align-items:center;justify-content:center}
.ev{border-radius:4px;padding:2px 6px;font-size:.7rem;margin-bottom:2px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#fff;font-weight:600;transition:opacity .15s}
.ev:hover{opacity:.85}
.ev-more{font-size:.68rem;color:#aaa;text-align:right;margin-top:1px}
.add-day-btn{position:absolute;top:4px;right:4px;background:none;border:none;color:#ccc;font-size:.9rem;cursor:pointer;opacity:0;transition:opacity .15s;padding:2px}
.cal-day:hover .add-day-btn{opacity:1}
.add-day-btn:hover{color:<?= $c3 ?>}

/* PAINEL LATERAL */
.side-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.side-header{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:14px 16px;display:flex;align-items:center;justify-content:space-between}
.side-header h3{font-size:.9rem;font-weight:700}
.btn-novo-ag{background:rgba(255,255,255,.25);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:.8rem;font-weight:700}
.btn-novo-ag:hover{background:rgba(255,255,255,.35)}
.prox-list{padding:12px}
.prox-item{border-left:3px solid #ddd;padding:8px 10px;margin-bottom:8px;border-radius:0 8px 8px 0;background:#f7f9fc;cursor:pointer;transition:background .15s}
.prox-item:hover{background:#eef2f8}
.prox-item .pi-data{font-size:.72rem;color:#888;margin-bottom:2px}
.prox-item .pi-titulo{font-size:.85rem;font-weight:700;color:#1a1a1a;margin-bottom:2px}
.prox-item .pi-info{font-size:.75rem;color:#888}
.prox-vazio{padding:20px;text-align:center;color:#bbb;font-size:.85rem}

/* BADGES */
.badge-tipo{padding:2px 8px;border-radius:10px;font-size:.72rem;background:#e3edff;color:#0055aa}
.badge-status{padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:700;text-transform:uppercase}
.badge-agendado{background:#e3f2fd;color:#1e88e5}
.badge-confirmado{background:#e8f5e9;color:#43a047}
.badge-realizado{background:#f5f5f5;color:#757575}
.badge-cancelado{background:#ffebee;color:#e53935}
.badge-prioridade{padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:700}
.badge-baixa{background:#e8f5e9;color:#2e7d32}
.badge-media{background:#e3f2fd;color:#1565c0}
.badge-alta{background:#fff3e0;color:#e65100}
.badge-urgente{background:#ffebee;color:#b71c1c}

/* MODAIS */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal{background:#fff;border-radius:14px;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:popIn .2s ease}
@keyframes popIn{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.mh{padding:20px 24px 14px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;justify-content:space-between;color:#fff}
.mh h2{font-size:.95rem}
.modal-close{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:1.1rem;cursor:pointer;border-radius:6px;padding:4px 10px}
.mb{padding:20px 24px}
.mf{padding:12px 24px 20px;border-top:1px solid #f0f4f8;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fg{display:flex;flex-direction:column;gap:5px}
.fg label{font-size:.76rem;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.fg input,.fg select,.fg textarea{padding:9px 12px;border:2px solid #e0e8f0;border-radius:7px;font-size:.9rem;transition:border-color .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:<?= $c3 ?>}
.fg textarea{resize:vertical;min-height:70px}
.fg.full{grid-column:1/-1}
.mbtn{padding:9px 18px;border-radius:7px;border:none;cursor:pointer;font-size:.85rem;font-weight:700;transition:opacity .2s}
.mbtn:hover{opacity:.85}
.mbtn-primary{background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff}
.mbtn-red{background:#e53935;color:#fff}
.mbtn-gray{background:#e0e8f0;color:#555}
.mbtn-green{background:#43a047;color:#fff}

/* INFO VIEW */
.info-row{display:flex;gap:16px;margin-bottom:12px;flex-wrap:wrap}
.info-item{flex:1;min-width:120px;background:#f7f9fc;border-radius:8px;padding:10px 12px}
.info-item .lbl{font-size:.7rem;color:#888;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.info-item .val{font-size:.88rem;font-weight:700;color:#1a1a1a}
.obs-box{background:#f7f9fc;border-radius:8px;padding:12px;font-size:.88rem;color:#333;line-height:1.6;white-space:pre-wrap}

/* Status rápido no view */
.status-btns{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.st-btn{padding:5px 12px;border-radius:14px;border:2px solid transparent;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s}
.st-btn.active{border-color:#333}
.st-btn-agendado{background:#e3f2fd;color:#1e88e5}
.st-btn-confirmado{background:#e8f5e9;color:#43a047}
.st-btn-realizado{background:#f5f5f5;color:#757575}
.st-btn-cancelado{background:#ffebee;color:#e53935}
</style>
</head>
<body>

<!-- HEADER -->
<div class="top-header">
  <div>
    <div class="brand"><?= PLATFORM_NAME ?> · Calendário</div>
    <div class="politico"><?= htmlspecialchars($tenant['nome_politico']) ?>
      <small style="opacity:.8;font-size:.8rem;margin-left:6px"><?= htmlspecialchars($tenant['cargo']) ?></small>
    </div>
  </div>
  <div class="hactions">
    <span style="font-size:.82rem;padding:7px 10px;opacity:.8">👤 <?= htmlspecialchars($adminNome) ?></span>
    <button class="hbtn hbtn-destaque" onclick="openNovo()">➕ Novo Agendamento</button>
    <a class="hbtn" href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/')) ?>">📋 Dashboard</a>
    <a class="hbtn" href="<?= htmlspecialchars(tenantUrl($tenant, 'admin/logout')) ?>">🚪 Sair</a>
  </div>
</div>

<div style="max-width:1280px;margin:0 auto;padding:16px 16px 0">
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>" id="alertMsg"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
</div>

<div class="content">

  <!-- CALENDÁRIO MENSAL -->
  <div class="cal-card">
    <div class="cal-nav">
      <a class="nav-btn" href="?slug=<?= urlencode($tenant['slug']) ?>&mes=<?= $mesPrev ?>&ano=<?= $anoPrev ?>">‹ Anterior</a>
      <h2><?= $mesesNomes[$mesAtual] ?> <?= $anoAtual ?></h2>
      <a class="nav-btn" href="?slug=<?= urlencode($tenant['slug']) ?>&mes=<?= $mesNext ?>&ano=<?= $anoNext ?>">Próximo ›</a>
    </div>
    <div class="cal-grid">
      <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $d): ?>
      <div class="cal-dow"><?= $d ?></div>
      <?php endforeach ?>

      <?php
      // Células vazias antes do dia 1
      for ($i = 0; $i < $diaSemanaInicio; $i++): ?>
      <div class="cal-day empty"></div>
      <?php endfor ?>

      <?php
      $hoje = (int)date('j');
      $mesHoje = (int)date('n');
      $anoHoje = (int)date('Y');
      for ($dia = 1; $dia <= $diasNoMes; $dia++):
        $isHoje = ($dia === $hoje && $mesAtual === $mesHoje && $anoAtual === $anoHoje);
        $dataStr = sprintf('%04d-%02d-%02d', $anoAtual, $mesAtual, $dia);
        $evsDia  = $porDia[$dia] ?? [];
        $mostrar = array_slice($evsDia, 0, 3);
        $extras  = count($evsDia) - count($mostrar);
      ?>
      <div class="cal-day <?= $isHoje ? 'hoje' : '' ?>" onclick="abrirDia('<?= $dataStr ?>')">
        <div class="day-num"><?= $dia ?></div>
        <button class="add-day-btn" onclick="event.stopPropagation();openNovoData('<?= $dataStr ?>')" title="Agendar neste dia">+</button>
        <?php foreach ($mostrar as $ev):
          $cor = $corPrioridade[$ev['prioridade']] ?? '#1e88e5';
          if ($ev['status'] === 'cancelado') $cor = '#bbb';
          if ($ev['status'] === 'realizado') $cor = '#9e9e9e';
        ?>
        <div class="ev" style="background:<?= $cor ?>"
             onclick="event.stopPropagation();openView(<?= $ev['id'] ?>)"
             title="<?= htmlspecialchars($ev['titulo']) ?> — <?= substr($ev['hora_inicio'],0,5) ?>">
          <?= substr($ev['hora_inicio'],0,5) ?> <?= htmlspecialchars(mb_substr($ev['titulo'],0,18)) ?>
        </div>
        <?php endforeach ?>
        <?php if ($extras > 0): ?>
        <div class="ev-more">+<?= $extras ?> mais</div>
        <?php endif ?>
      </div>
      <?php endfor ?>
    </div>
  </div>

  <!-- PAINEL LATERAL: PRÓXIMOS -->
  <div class="side-card">
    <div class="side-header">
      <h3>📅 Próximos Compromissos</h3>
      <button class="btn-novo-ag" onclick="openNovo()">+ Novo</button>
    </div>
    <div class="prox-list">
      <?php if (empty($proximos)): ?>
      <div class="prox-vazio">Nenhum compromisso futuro.</div>
      <?php else: ?>
      <?php foreach ($proximos as $ag):
        $cor = $corPrioridade[$ag['prioridade']] ?? '#1e88e5';
        $dataFmt = formatDate($ag['data_agendamento']);
        $isHojeLat = ($ag['data_agendamento'] === date('Y-m-d'));
      ?>
      <div class="prox-item" style="border-left-color:<?= $cor ?>"
           onclick="openView(<?= $ag['id'] ?>)">
        <div class="pi-data"><?= $isHojeLat ? '🔴 HOJE' : $dataFmt ?> · <?= substr($ag['hora_inicio'],0,5) ?></div>
        <div class="pi-titulo"><?= htmlspecialchars($ag['titulo']) ?></div>
        <div class="pi-info">
          <?= htmlspecialchars($ag['tipo']) ?>
          <?php if ($ag['local']): ?> · <?= htmlspecialchars(mb_substr($ag['local'],0,25)) ?><?php endif ?>
          <?php if ($ag['cadastro_nome']): ?><br>👤 <?= htmlspecialchars($ag['cadastro_nome']) ?><?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>

</div><!-- /content -->

<!-- MODAL VER/EDITAR RÁPIDO -->
<div class="modal-overlay" id="overlayView" onclick="if(event.target===this)closeView()">
  <div class="modal">
    <div class="mh" id="viewHeader" style="background:linear-gradient(135deg,#5e35b1,#7c4dff)">
      <h2>📅 Agendamento</h2>
      <button class="modal-close" onclick="closeView()">✕</button>
    </div>
    <div class="mb" id="viewBody"></div>
    <div class="mf">
      <button class="mbtn mbtn-red" onclick="deletarAg(currentId)">🗑️ Excluir</button>
      <button class="mbtn mbtn-primary" onclick="closeView();openEdit(currentId)">✏️ Editar</button>
      <button class="mbtn mbtn-gray" onclick="closeView()">Fechar</button>
    </div>
  </div>
</div>

<!-- MODAL DIA (lista de events do dia clicado) -->
<div class="modal-overlay" id="overlayDia" onclick="if(event.target===this)closeDia()">
  <div class="modal">
    <div class="mh" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>)">
      <h2 id="diaHeader">📅 Agendamentos</h2>
      <button class="modal-close" onclick="closeDia()">✕</button>
    </div>
    <div class="mb" id="diaBody"></div>
    <div class="mf">
      <button class="mbtn mbtn-primary" id="diaAddBtn" onclick="">➕ Agendar neste dia</button>
      <button class="mbtn mbtn-gray" onclick="closeDia()">Fechar</button>
    </div>
  </div>
</div>

<!-- MODAL NOVO / EDITAR AGENDAMENTO -->
<div class="modal-overlay" id="overlayForm" onclick="if(event.target===this)closeForm()">
  <div class="modal">
    <div class="mh" id="formHeader" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>)">
      <h2 id="formTitle">➕ Novo Agendamento</h2>
      <button class="modal-close" onclick="closeForm()">✕</button>
    </div>
    <div class="mb">
      <form method="POST" id="formAg">
        <input type="hidden" name="action" id="formAction" value="criar">
        <input type="hidden" name="id"   id="formId">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
        <div class="form-grid">
          <div class="fg full">
            <label>Título *</label>
            <input type="text" name="titulo" id="fTitulo" required placeholder="Ex: Reunião com liderança">
          </div>
          <div class="fg">
            <label>Data *</label>
            <input type="date" name="data" id="fData" required>
          </div>
          <div class="fg">
            <label>Hora Início *</label>
            <input type="time" name="hora_inicio" id="fHoraIni" required>
          </div>
          <div class="fg">
            <label>Hora Fim</label>
            <input type="time" name="hora_fim" id="fHoraFim">
          </div>
          <div class="fg">
            <label>Tipo</label>
            <select name="tipo" id="fTipo">
              <option value="retorno">Retorno</option>
              <option value="reuniao">Reunião</option>
              <option value="visita">Visita</option>
              <option value="evento">Evento</option>
              <option value="audiencia">Audiência</option>
              <option value="outro">Outro</option>
            </select>
          </div>
          <div class="fg">
            <label>Prioridade</label>
            <select name="prioridade" id="fPrioridade">
              <option value="baixa">Baixa</option>
              <option value="media" selected>Média</option>
              <option value="alta">Alta</option>
              <option value="urgente">Urgente</option>
            </select>
          </div>
          <div class="fg" id="fStatusWrap" style="display:none">
            <label>Status</label>
            <select name="status" id="fStatus">
              <option value="agendado">Agendado</option>
              <option value="confirmado">Confirmado</option>
              <option value="realizado">Realizado</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div class="fg full">
            <label>Local</label>
            <input type="text" name="local" id="fLocal" placeholder="Ex: Câmara Municipal, Zoom...">
          </div>
          <div class="fg full">
            <label>Vinculado ao Cadastro</label>
            <select name="cadastro_id" id="fCadastro">
              <option value="">— Nenhum —</option>
              <?php foreach ($cadsList as $cad): ?>
              <option value="<?= $cad['id'] ?>">
                <?= htmlspecialchars($cad['nome']) ?> (<?= htmlspecialchars($cad['telefone']) ?>)
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="fg full">
            <label>Descrição</label>
            <textarea name="descricao" id="fDescricao" placeholder="Detalhes do compromisso..."></textarea>
          </div>
          <div class="fg full">
            <label>Observações</label>
            <textarea name="observacoes" id="fObs" placeholder="Notas internas..."></textarea>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
          <button type="button" class="mbtn mbtn-gray" onclick="closeForm()">Cancelar</button>
          <button type="submit" class="mbtn mbtn-primary" id="fSubmitBtn">💾 Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div style="text-align:center;padding:20px;font-size:.75rem;color:#aaa">
  <?= PLATFORM_NAME ?> v<?= PLATFORM_VERSION ?> ·
  Desenvolvido por <a href="https://wa.me/<?= DEV_WHATSAPP ?>" target="_blank" style="color:<?= $c3 ?>;text-decoration:none"><?= DEV_NOME ?></a>
</div>

<script>
const agData = <?= $agJSON ?>;
let currentId  = null;
let currentData = null;

const corPrioridade = <?= json_encode($corPrioridade) ?>;
const corStatus     = <?= json_encode($corStatus) ?>;

function findAg(id){ return agData.find(a=>a.id==id); }
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(d){ if(!d)return '-'; const p=d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }

// --- Abrir dia no modal -------------------------------------------------------
function abrirDia(data){
  currentData = data;
  const evsDia = agData.filter(a=>a.data===data);
  const partes  = data.split('-');
  const label   = partes[2]+'/'+partes[1]+'/'+partes[0];
  document.getElementById('diaHeader').textContent = '📅 ' + label;

  let html = '';
  if(evsDia.length === 0){
    html = '<p style="text-align:center;color:#bbb;padding:20px">Nenhum agendamento neste dia.</p>';
  } else {
    evsDia.forEach(ag=>{
      const cor = ag.status==='cancelado'?'#bbb':(ag.status==='realizado'?'#9e9e9e':corPrioridade[ag.prioridade]);
      html += `<div style="border-left:4px solid ${cor};padding:10px 12px;margin-bottom:10px;background:#f7f9fc;border-radius:0 8px 8px 0;cursor:pointer"
                    onclick="closeDia();openView(${ag.id})">
        <div style="font-weight:700;color:#1a1a1a">${esc(ag.titulo)}</div>
        <div style="font-size:.8rem;color:#888;margin-top:3px">
          ⏰ ${ag.hora_inicio}${ag.hora_fim?' – '+ag.hora_fim:''}
          &nbsp;·&nbsp; <span class="badge-status badge-${ag.status}">${ag.status}</span>
          &nbsp;·&nbsp; ${ag.tipo}
          ${ag.local?'<br>📍 '+esc(ag.local):''}
          ${ag.cadastro_nome?'<br>👤 '+esc(ag.cadastro_nome):''}
        </div>
      </div>`;
    });
  }
  document.getElementById('diaBody').innerHTML = html;
  document.getElementById('diaAddBtn').onclick = ()=>{ closeDia(); openNovoData(data); };
  document.getElementById('overlayDia').classList.add('show');
}
function closeDia(){ document.getElementById('overlayDia').classList.remove('show'); }

// --- Modal Ver ---------------------------------------------------------------
function openView(id){
  currentId = id;
  const ag = findAg(id);
  if(!ag) return;
  const cor = corPrioridade[ag.prioridade] || '#1e88e5';

  let html = `
  <div class="info-row">
    <div class="info-item"><div class="lbl">Data</div><div class="val">${fmt(ag.data)}</div></div>
    <div class="info-item"><div class="lbl">Horário</div><div class="val">${ag.hora_inicio}${ag.hora_fim?' – '+ag.hora_fim:''}</div></div>
  </div>
  <div class="info-row">
    <div class="info-item"><div class="lbl">Tipo</div><div class="val">${ag.tipo}</div></div>
    <div class="info-item"><div class="lbl">Prioridade</div><div class="val"><span class="badge-prioridade badge-${ag.prioridade}">${ag.prioridade.toUpperCase()}</span></div></div>
  </div>`;
  if(ag.local) html+=`<div style="margin-bottom:12px"><span style="font-size:.8rem;color:#888">📍 Local:</span> <strong>${esc(ag.local)}</strong></div>`;
  if(ag.cadastro_nome) html+=`<div style="margin-bottom:12px"><span style="font-size:.8rem;color:#888">👤 Contato:</span> <strong>${esc(ag.cadastro_nome)}</strong></div>`;
  if(ag.descricao) html+=`<div style="margin-bottom:12px"><div style="font-size:.78rem;color:#888;margin-bottom:4px">DESCRIÇÃO</div><div class="obs-box">${esc(ag.descricao)}</div></div>`;
  if(ag.observacoes) html+=`<div style="margin-bottom:12px"><div style="font-size:.78rem;color:#888;margin-bottom:4px">OBSERVAÇÕES</div><div class="obs-box">${esc(ag.observacoes)}</div></div>`;

  // Status rápido
  html += `<div style="margin-top:8px"><div style="font-size:.78rem;color:#888;margin-bottom:6px">ALTERAR STATUS:</div>
  <form method="POST" id="formStatus">
    <input type="hidden" name="action" value="status">
    <input type="hidden" name="id" value="${ag.id}">
    <input type="hidden" name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">
    <div class="status-btns">
      ${['agendado','confirmado','realizado','cancelado'].map(s=>`
      <button type="submit" name="novo_status" value="${s}"
        class="st-btn st-btn-${s} ${ag.status===s?'active':''}">${s}</button>`).join('')}
    </div>
  </form></div>`;

  document.getElementById('viewBody').innerHTML = html;
  document.getElementById('viewHeader').style.background = `linear-gradient(135deg,${cor},${cor}cc)`;
  document.getElementById('overlayView').classList.add('show');
}
function closeView(){ document.getElementById('overlayView').classList.remove('show'); }

// --- Modal Form (criar/editar) ------------------------------------------------
function resetForm(){
  document.getElementById('formAg').reset();
  document.getElementById('formId').value    = '';
  document.getElementById('fStatusWrap').style.display = 'none';
  document.getElementById('fSubmitBtn').textContent    = '💾 Salvar';
  document.getElementById('formTitle').textContent     = '➕ Novo Agendamento';
  document.getElementById('formAction').value          = 'criar';
}

function openNovo(){
  resetForm();
  // Data padrão: hoje
  document.getElementById('fData').value = '<?= date('Y-m-d') ?>';
  document.getElementById('overlayForm').classList.add('show');
  document.getElementById('fTitulo').focus();
}

function openNovoData(data){
  resetForm();
  document.getElementById('fData').value = data;
  document.getElementById('overlayForm').classList.add('show');
  document.getElementById('fTitulo').focus();
}

function openEdit(id){
  closeView();
  const ag = findAg(id);
  if(!ag) return;
  resetForm();
  document.getElementById('formAction').value      = 'editar';
  document.getElementById('formTitle').textContent = '✏️ Editar Agendamento';
  document.getElementById('fSubmitBtn').textContent= '💾 Salvar Alterações';
  document.getElementById('formId').value          = ag.id;
  document.getElementById('fTitulo').value         = ag.titulo;
  document.getElementById('fData').value           = ag.data;
  document.getElementById('fHoraIni').value        = ag.hora_inicio;
  document.getElementById('fHoraFim').value        = ag.hora_fim||'';
  document.getElementById('fTipo').value           = ag.tipo;
  document.getElementById('fPrioridade').value     = ag.prioridade;
  document.getElementById('fStatus').value         = ag.status;
  document.getElementById('fLocal').value          = ag.local||'';
  document.getElementById('fDescricao').value      = ag.descricao||'';
  document.getElementById('fObs').value            = ag.observacoes||'';
  document.getElementById('fCadastro').value       = ag.cadastro_id||'';
  document.getElementById('fStatusWrap').style.display = 'block';
  document.getElementById('overlayForm').classList.add('show');
}

function closeForm(){ document.getElementById('overlayForm').classList.remove('show'); }

// --- Deletar -----------------------------------------------------------------
function deletarAg(id){
  if(!confirm('Excluir este agendamento permanentemente?')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.innerHTML = `<input name="action" value="deletar">
    <input name="id" value="${id}">
    <input name="slug" value="<?= htmlspecialchars($tenant['slug']) ?>">`;
  document.body.appendChild(f);
  f.submit();
}

// ESC fecha modais
document.addEventListener('keydown', e=>{
  if(e.key==='Escape'){ closeView(); closeForm(); closeDia(); }
});

// Auto-hide msg
<?php if ($msg): ?>
setTimeout(()=>{ const a=document.getElementById('alertMsg'); if(a) a.style.display='none'; }, 5000);
<?php endif ?>
</script>
</body>
</html>
