<?php
require_once __DIR__ . '/../includes/env.php';
carregarEnv(__DIR__ . '/../.env');

// Exibir erros do PHP no navegador durante os testes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function configEnvObrigatoria($chave)
{
    $valor = getenv($chave);
    if ($valor === false || $valor === '') {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'>"
            . "<h2>Configuração ausente</h2>"
            . "<p>Defina <code>{$chave}</code> no arquivo <code>.env</code> "
            . "na raiz do projeto (veja <code>.env.example</code>).</p>"
            . "</div>");
    }
    return $valor;
}

define('DB_HOST', configEnvObrigatoria('DB_HOST'));
define('DB_NAME', configEnvObrigatoria('DB_NAME'));
define('DB_USER', configEnvObrigatoria('DB_USER'));
define('DB_PASS', configEnvObrigatoria('DB_PASS'));

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
