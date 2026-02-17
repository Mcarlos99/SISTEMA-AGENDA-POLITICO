<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  admin/index.php  - Roteador
//   Roteia /{slug}/admin/{path} para o arquivo correto
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

$tenant = resolveTenant();
if (!$tenant) {
    http_response_code(404);
    die('Tenant não encontrado.');
}

$path  = trim($_GET['path'] ?? '', '/');
$pages = [
    ''             => 'dashboard.php',
    'dashboard'    => 'dashboard.php',
    'login'        => 'login.php',
    'logout'       => 'logout.php',
    'calendario'   => 'calendario.php',
];

// Fallback: checa se o arquivo existe diretamente
if (!isset($pages[$path])) {
    $file = __DIR__ . '/' . basename($path) . '.php';
    if (file_exists($file)) {
        $pages[$path] = basename($path) . '.php';
    }
}

$target = $pages[$path] ?? 'dashboard.php';
$file   = __DIR__ . '/' . $target;

if (!file_exists($file)) {
    http_response_code(404);
    die('Página não encontrada.');
}

require $file;
