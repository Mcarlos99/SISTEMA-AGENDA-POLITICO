<?php
require_once '../config.php';

// Verificar se está logado
if (isset($_SESSION[ADMIN_SESSION_NAME])) {
    $admin = $_SESSION[ADMIN_SESSION_NAME];
    
    // Log do logout
    logActivity('admin_login', "Logout realizado por: " . $admin['usuario']);
    
    // Destruir sessão
    unset($_SESSION[ADMIN_SESSION_NAME]);
}

session_destroy();

// Redirecionar para login
header('Location: login.php?logout=1');
exit;
?>