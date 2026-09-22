<?php
// Modelo de config/database.php — copie este arquivo para config/database.php e
// preencha com as credenciais reais. config/database.php é ignorado pelo git
// (ver .gitignore) porque guarda a senha do MySQL de produção; nunca comitar esse
// arquivo com valores reais.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_banco');
define('DB_USER', 'usuario_do_banco');
define('DB_PASS', 'senha_do_banco');

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
            <h2>Erro de Conexão com o Banco de Dados:</h2>" . $e->getMessage() .
         "</div>");
}
