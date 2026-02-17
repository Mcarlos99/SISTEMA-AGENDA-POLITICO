<?php
// =============================================================
//   Extreme - PoLiX SaaS  |  Agenda Política Profissional
//   config.php  -  Configurações da Plataforma  v3.0
//   Desenvolvido por: Mauro Carlos (94) 98170-9809
// =============================================================

// ============================================================
//   BANCO DE DADOS
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'polix_saas');
define('DB_USER',    'polix_user');
define('DB_PASS',    'ALTERE_ESTA_SENHA');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
//   PLATAFORMA
// ============================================================
define('PLATFORM_NAME',    'Extreme - PoLiX');
define('PLATFORM_SUBTITLE','Agenda Política Profissional');
define('PLATFORM_VERSION', '3.0.0');
define('PLATFORM_URL',     'https://seudominio.com.br'); // sem barra final
define('TIMEZONE',         'America/Sao_Paulo');

// ============================================================
//   SESSÕES
// ============================================================
define('SESSION_TENANT_ADMIN', 'polix_tenant_admin');
define('SESSION_SUPER_ADMIN',  'polix_super_admin');

// ============================================================
//   DESENVOLVEDOR
// ============================================================
define('DEV_NOME',     'Mauro Carlos');
define('DEV_WHATSAPP', '5594981709809');
define('DEV_TELEFONE', '(94) 98170-9809');

// ============================================================
//   CATEGORIAS DE CADASTRO
// ============================================================
define('CATEGORIAS_CADASTRO', serialize([
    'eleitor'      => 'Eleitor',
    'apoiador'     => 'Apoiador',
    'lideranca'    => 'Liderança Comunitária',
    'empresario'   => 'Empresário',
    'servidor'     => 'Servidor Público',
    'politico'     => 'Vereador / Político',
    'assessor'     => 'Assessor',
    'imprensa'     => 'Imprensa / Comunicação',
    'parceiro'     => 'Parceiro Institucional',
    'sindicalista' => 'Sindicalista',
    'religioso'    => 'Liderança Religiosa',
    'educacao'     => 'Educação',
    'outro'        => 'Outro',
]));

// ============================================================
//   NÃO ALTERE ABAIXO DESTA LINHA
// ============================================================
date_default_timezone_set(TIMEZONE);

// ============================================================
//   DATABASE SINGLETON
// ============================================================
class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $opts = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $opts);
            } catch (PDOException $e) {
                error_log('PoLiX DB: ' . $e->getMessage());
                die('Erro de conexão com o banco de dados.');
            }
        }
        return self::$instance;
    }
}

// ============================================================
//   RESOLUÇÃO DE TENANT
//   Detecta o slug da URL e retorna os dados do tenant.
//   Suporta:
//     - /joao-silva/          → $_GET['slug'] via .htaccess
//     - subdomínio.dominio    → joao-silva.seudominio.com
// ============================================================
function resolveTenant(?string $slugForce = null): ?array {
    static $tenant = null;
    if ($tenant !== null) return $tenant;

    $slug = $slugForce ?? $_GET['slug'] ?? null;

    // Fallback: tenta extrair do subdomínio
    if (!$slug && isset($_SERVER['HTTP_HOST'])) {
        $parts = explode('.', $_SERVER['HTTP_HOST']);
        if (count($parts) >= 3) {
            $slug = strtolower($parts[0]);
        }
    }

    if (!$slug) return null;

    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

    try {
        $stmt = Database::getInstance()->prepare(
            'SELECT t.*, p.nome AS plano_nome, p.max_cadastros, p.max_usuarios,
                    p.tem_calendario, p.tem_relatorios, p.tem_exportacao
             FROM tenants t
             LEFT JOIN planos p ON p.id = t.plano_id
             WHERE t.slug = ? AND t.status IN ("ativo","trial")
             LIMIT 1'
        );
        $stmt->execute([$slug]);
        $tenant = $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log('PoLiX resolveTenant: ' . $e->getMessage());
    }

    return $tenant;
}

// ============================================================
//   HELPERS GERAIS
// ============================================================
function getCategorias(): array {
    return unserialize(CATEGORIAS_CADASTRO);
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)));
}

function getClientIP(): string {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logActivity(int $tenantId = 0, string $tipo = '', string $descricao = ''): void {
    try {
        $stmt = Database::getInstance()->prepare(
            'INSERT INTO logs_acesso (tenant_id, tipo, descricao, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId ?: null,
            $tipo,
            $descricao,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    } catch (Exception $e) {
        error_log('PoLiX Log: ' . $e->getMessage());
    }
}

function formatDate(string $date, string $fmt = 'd/m/Y'): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    return date($fmt, strtotime($date));
}

function calcularIdade(string $dob): int {
    if (empty($dob)) return 0;
    return (int) date_diff(date_create($dob), date_create('today'))->y;
}

function tenantUrl(array $tenant, string $path = ''): string {
    return PLATFORM_URL . '/' . $tenant['slug'] . '/' . ltrim($path, '/');
}

// ============================================================
//   SESSÃO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
