<?php
require_once __DIR__ . '/../config.php';
$tenant = resolveTenant();
if ($tenant && !empty($_SESSION[SESSION_TENANT_ADMIN])) {
    logActivity((int)$tenant['id'], 'logout', 'Admin: ' . ($_SESSION[SESSION_TENANT_ADMIN]['usuario'] ?? ''));
}
unset($_SESSION[SESSION_TENANT_ADMIN]);
session_regenerate_id(true);
// Volta para o login CENTRALIZADO
header('Location: ' . PLATFORM_URL . PLATFORM_SUBDIR . '/?logout=1');
exit;
