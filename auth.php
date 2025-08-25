<?php
/**
 * Auth de base via session + table users
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
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