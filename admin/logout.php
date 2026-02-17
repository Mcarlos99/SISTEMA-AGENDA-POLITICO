<?php
require_once __DIR__ . '/../config.php';
$tenant = resolveTenant();
if ($tenant && !empty($_SESSION[SESSION_TENANT_ADMIN])) {
    logActivity((int)$tenant['id'], 'logout', 'Admin: ' . ($_SESSION[SESSION_TENANT_ADMIN]['usuario'] ?? ''));
}
unset($_SESSION[SESSION_TENANT_ADMIN]);
session_regenerate_id(true);
$slug = $tenant['slug'] ?? '';
header('Location: ' . ($slug ? tenantUrl($tenant, 'admin/login?logout=1') : '/'));
exit;
