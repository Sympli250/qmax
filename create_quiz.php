<?php
// Fichier de confort (si vous y accédez directement)
// Redirige vers l'écran de création inclus dans index.php
header('Location: ' . (function(){
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $proto = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($basePath === '' || $basePath === '.') $basePath = '';
    return $proto . $host . $basePath . '/index.php?page=create-quiz';
})());
exit;