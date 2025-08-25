<?php
/**
 * Auth de base via session + table users
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur connecté est superadmin
 */
function isSuperAdmin(): bool {
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

/**
 * login(email, password) -> bool
 * Utilise la table users (password hashé)
 */
function login(string $email, string $password): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u) return false;
        if (!password_verify($password, $u['password'])) return false;

        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['username'] = (string)$u['username'];
        $_SESSION['role'] = (string)$u['role'];
        return true;
    } catch (Throwable $e) {
        error_log('login: ' . $e->getMessage());
        return false;
    }
}

/**
 * Crée un nouvel utilisateur
 */
function createUser(string $username, string $email, string $password, string $role = 'admin'): bool {
    global $pdo;
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,?)");
        return $stmt->execute([trim($username), trim($email), $hash, $role]);
    } catch (Throwable $e) {
        error_log('createUser: ' . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour le rôle d'un utilisateur
 */
function updateUserRole(int $user_id, string $role): bool {
    global $pdo;
    $valid = ['admin','superadmin'];
    if (!in_array($role, $valid, true)) return false;
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = datetime('now') WHERE id = ?");
        return $stmt->execute([$role, $user_id]);
    } catch (Throwable $e) {
        error_log('updateUserRole: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les utilisateurs
 */
function getAllUsers(): array {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getAllUsers: ' . $e->getMessage());
        return [];
    }
}