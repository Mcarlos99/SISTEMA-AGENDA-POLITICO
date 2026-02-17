<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  verificar_duplicata.php
//   API JSON - verifica duplicata dentro do tenant
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$tenant = resolveTenant();
if (!$tenant) {
    echo json_encode(['duplicado' => false, 'erro' => 'tenant_invalido']);
    exit;
}

$tenantId = (int)$tenant['id'];
$db       = Database::getInstance();

$telefone = sanitizeInput($_GET['telefone'] ?? $_POST['telefone'] ?? '');
$email    = sanitizeInput($_GET['email']    ?? $_POST['email']    ?? '');
$telefone = preg_replace('/\D/', '', $telefone);

$resultado = ['duplicado' => false];

try {
    if ($telefone && strlen($telefone) >= 10) {
        $stmt = $db->prepare(
            'SELECT id, nome, cidade FROM cadastros
              WHERE tenant_id = ? AND telefone = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $telefone]);
        $row = $stmt->fetch();
        if ($row) {
            $resultado['duplicado'] = true;
            $resultado['campo']     = 'telefone';
            $resultado['info']      = htmlspecialchars($row['nome']) . ' — ' . htmlspecialchars($row['cidade']);
            logActivity($tenantId, 'duplicata_telefone', "Tel: $telefone | Existente: {$row['nome']}");
        }
    }

    if (!$resultado['duplicado'] && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $db->prepare(
            'SELECT id, nome, cidade FROM cadastros
              WHERE tenant_id = ? AND LOWER(email) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$tenantId, $email]);
        $row = $stmt->fetch();
        if ($row) {
            $resultado['duplicado'] = true;
            $resultado['campo']     = 'email';
            $resultado['info']      = htmlspecialchars($row['nome']) . ' — ' . htmlspecialchars($row['cidade']);
            logActivity($tenantId, 'duplicata_email', "Email: $email | Existente: {$row['nome']}");
        }
    }
} catch (Exception $e) {
    error_log('PoLiX duplicata: ' . $e->getMessage());
    $resultado['erro'] = 'Erro interno';
}

echo json_encode($resultado);
