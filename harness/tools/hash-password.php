<?php
// Gera um hash bcrypt válido para admin/login.php (que já sabe verificar com
// password_verify()). Use isto em vez de gravar senha em texto puro ou md5().
//
// Rodar dentro do container do harness:
//   docker compose exec app php /var/www/html/evento-fametro/harness/tools/hash-password.php "minhaSenha"
//
// Depois:
//   UPDATE administradores SET senha = '<hash gerado>' WHERE usuario = 'admin';

if ($argc < 2) {
    fwrite(STDERR, "Uso: php hash-password.php \"senha\"\n");
    exit(1);
}

echo password_hash($argv[1], PASSWORD_DEFAULT) . PHP_EOL;
