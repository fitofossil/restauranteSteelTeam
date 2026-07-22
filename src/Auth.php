<?php
// =============================================================
// AUTH — Classe de Autenticação
// =============================================================
// Centraliza autenticação, validação, sanitização e sessões.
// =============================================================

class Auth
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    // --- SESSÃO ---

    public static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false,
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    private function regenerarId(): void
    {
        session_regenerate_id(true);
    }

    // --- LOGIN ---

    public function login(string $email, string $senha): array
    {
        $email = $this->sanitizarEmail($email);

        if (!$this->validarEmail($email)) {
            return ['sucesso' => false, 'mensagem' => 'E-mail inválido.'];
        }

        if (empty($senha)) {
            return ['sucesso' => false, 'mensagem' => 'Informe sua senha.'];
        }

        $stmt = $this->conn->prepare(
            'SELECT id, username, email, password_hash, role, is_active
             FROM users_login WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.'];
        }

        if (!$user['is_active']) {
            return ['sucesso' => false, 'mensagem' => 'Conta desativada. Contacte o administrador.'];
        }

        if (!password_verify($senha, $user['password_hash'])) {
            return ['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.'];
        }

        $this->regenerarId();

        $_SESSION['usuario_id']    = (int) $user['id'];
        $_SESSION['usuario']       = $user['username'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_role']  = (int) $user['role'];

        return ['sucesso' => true, 'mensagem' => 'Login realizado com sucesso.'];
    }

    // --- LOGOUT ---

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
        header('Location: ' . BASE_URL . '/templates/login.php');
        exit();
    }

    // --- VERIFICAÇÕES DE ACESSO ---

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    public static function requireLogin(): void
    {
        self::iniciarSessao();
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/templates/login.php');
            exit();
        }
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['usuario_role']) && (int) $_SESSION['usuario_role'] === 1;
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . BASE_URL . '/templates/painel.php');
            exit();
        }
    }

    // --- GETTERS ---

    public static function getId(): int    { return (int) ($_SESSION['usuario_id'] ?? 0); }
    public static function getNome(): string { return $_SESSION['usuario'] ?? ''; }
    public static function getEmail(): string { return $_SESSION['usuario_email'] ?? ''; }
    public static function getRole(): int  { return (int) ($_SESSION['usuario_role'] ?? 0); }

    // --- VALIDAÇÃO E SANITIZAÇÃO ---

    public function sanitizarEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function validarSenha(string $senha): bool
    {
        return strlen($senha) >= 6;
    }

    public static function sanitizarTexto(string $texto): string
    {
        return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
    }

    public function validarRole($role): bool
    {
        return in_array((int) $role, [1, 2, 3], true);
    }

    public static function hashSenha(string $senha): string
    {
        return password_hash($senha, PASSWORD_DEFAULT);
    }
}
