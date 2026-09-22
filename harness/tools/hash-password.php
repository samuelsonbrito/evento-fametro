<?php
// Gera um hash bcrypt (custo 12) válido para admin/login.php, que verifica com
// password_verify() e não aceita mais MD5/texto puro (ver ISSUES.md item 2).
//
// Rodar direto no servidor (via SSH, já que ele tem PHP) ou dentro do container do
// harness:
//   php harness/tools/hash-password.php "minhaSenha"
//   docker compose exec app php /var/www/html/evento-fametro/harness/tools/hash-password.php "minhaSenha"
//
// Depois, no phpMyAdmin (aba SQL):
//   UPDATE administradores SET senha = '<hash gerado>' WHERE usuario = 'admin';
//
// Nunca compartilhe a senha em texto puro nem o comando com ela visível em lugar
// nenhum além do seu próprio terminal.

if ($argc < 2) {
    fwrite(STDERR, "Uso: php hash-password.php \"senha\"\n");
    exit(1);
}

echo password_hash($argv[1], PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;
