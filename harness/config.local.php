<?php
// Substituto de config/database.php usado SOMENTE dentro do container do harness
// (docker-compose.yml faz o bind-mount deste arquivo por cima do config/database.php
// real, apenas dentro do container — o arquivo original em config/ nunca é alterado).
//
// Aponta para o MySQL local descartável do harness, nunca para a InfinityFree.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'evento_fametro');
define('DB_USER', getenv('DB_USER') ?: 'fametro');
define('DB_PASS', getenv('DB_PASS') ?: 'fametro_local_pw');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>
            <h2>Erro de Conexão com o Banco de Dados (harness local):</h2>" . $e->getMessage() .
         "</div>");
}
