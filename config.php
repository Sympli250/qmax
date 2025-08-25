<?php
/**
 * Gestion de la configuration globale de l'application
 */

function getConfig(string $key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT value FROM app_config WHERE key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    } catch (Throwable $e) {
        error_log('getConfig: ' . $e->getMessage());
        return $default;
    }
}

function setConfig(string $key, string $value): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO app_config (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value"
        );
        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        error_log('setConfig: ' . $e->getMessage());
        return false;
    }
}

function getAllConfig(): array {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT key,value FROM app_config ORDER BY key");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        error_log('getAllConfig: ' . $e->getMessage());
        return [];
    }
}
