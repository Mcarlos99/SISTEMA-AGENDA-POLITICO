<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  superadmin/verificar_disponibilidade.php
//   API AJAX — verifica se usuário ou slug já estão em uso
//   Acessível apenas por super admins autenticados
//   v3.0  |  Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Só super admin autenticado pode consultar
if (empty($_SESSION[SESSION_SUPER_ADMIN])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$tipo  = sanitizeInput($_GET['tipo']  ?? '');
$valor = sanitizeInput($_GET['valor'] ?? '');

if (!$valor || strlen($valor) < 3) {
    echo json_encode(['disponivel' => false, 'erro' => 'Valor muito curto']);
    exit;
}

$db = Database::getInstance();

try {
    if ($tipo === 'usuario') {
        // Verifica em todos os tenants
        $stmt = $db->prepare(
            'SELECT a.usuario, t.nome_politico
             FROM administradores a
             INNER JOIN tenants t ON t.id = a.tenant_id
             WHERE a.usuario = ?
             LIMIT 1'
        );
        $stmt->execute([$valor]);
        $row = $stmt->fetch();

        if ($row) {
            echo json_encode([
                'disponivel' => false,
                'tenant'     => $row['nome_politico'],
            ]);
        } else {
            echo json_encode(['disponivel' => true]);
        }

    } elseif ($tipo === 'slug') {
        $stmt = $db->prepare('SELECT slug FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$valor]);
        $row = $stmt->fetch();

        echo json_encode(['disponivel' => !$row]);

    } else {
        echo json_encode(['erro' => 'Tipo inválido']);
    }

} catch (Exception $e) {
    error_log('PoLiX verif_disp: ' . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno']);
}
