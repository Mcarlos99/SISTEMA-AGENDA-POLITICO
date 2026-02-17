<?php
require_once __DIR__ . '/../config.php';
logActivity(0, 'superadmin_logout', $_SESSION[SESSION_SUPER_ADMIN]['usuario'] ?? '');
unset($_SESSION[SESSION_SUPER_ADMIN]);
session_regenerate_id(true);
header('Location: login.php?logout=1');
exit;
