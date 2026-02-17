<?php
// admin/login.php — redireciona para login centralizado
// Este arquivo só existe para compatibilidade com acesso direto via URL
require_once __DIR__ . '/../config.php';
header('Location: ' . platformUrl(''));
exit;
