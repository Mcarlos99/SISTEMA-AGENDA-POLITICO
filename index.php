<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  index.php  — Dispatcher Central
//   Detecta o subdiretório automaticamente e roteia as URLs.
//   Não precisa mexer no .htaccess ao mudar de pasta.
//   v3.4  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================

// Detecta o subdiretório onde este arquivo está instalado
// Ex: instalado em /polix/ → $scriptDir = /polix
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove o subdiretório da URI → obtém caminho relativo
if ($scriptDir && strpos($requestUri, $scriptDir) === 0) {
    $path = substr($requestUri, strlen($scriptDir));
} else {
    $path = $requestUri;
}
$path = trim($path, '/'); // "lima-dantas/admin/calendario"

require_once __DIR__ . '/config.php';

// Segurança
if (preg_match('/\.(sql|env|log|bak)$/i', $path)) {
    http_response_code(403); die('Acesso negado.');
}

// --- ROTEAMENTO ------------------------------------------------------

// Raiz ou /login → login centralizado
if ($path === '' || $path === 'login') {
    require __DIR__ . '/login.php';
    exit;
}

// superadmin/*
if (preg_match('#^superadmin(?:/(.*))?$#', $path, $m)) {
    $page = trim($m[1] ?? '', '/') ?: 'dashboard';
    $allowed = ['login','logout','dashboard','verificar_disponibilidade'];
    if (!in_array($page, $allowed)) { http_response_code(404); die('Não encontrado.'); }
    $file = __DIR__ . '/superadmin/' . $page . '.php';
    if (!file_exists($file)) { http_response_code(404); die('Não encontrado.'); }
    require $file;
    exit;
}

// {slug}/admin/logout
if (preg_match('#^([a-z0-9\-]+)/admin/logout/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/admin/logout.php';
    exit;
}

// {slug}/admin/{page}
if (preg_match('#^([a-z0-9\-]+)/admin(?:/(.*))?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    $_GET['path'] = trim($m[2] ?? '', '/');
    require __DIR__ . '/admin/index.php';
    exit;
}

// {slug}/verificar_duplicata.php
if (preg_match('#^([a-z0-9\-]+)/verificar_duplicata\.php$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/verificar_duplicata.php';
    exit;
}

// {slug}/ → formulário público
if (preg_match('#^([a-z0-9\-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/cadastro.php';
    exit;
}

// 404
http_response_code(404);
echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Não encontrado</title></head>
<body style="font-family:Arial;text-align:center;padding:80px;color:#333">
<h2 style="color:#003366">Página não encontrada</h2>
<p>O endereço acessado não existe.</p>
</body></html>';
