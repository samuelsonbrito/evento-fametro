<?php
// Carregador mínimo de variáveis de ambiente a partir de um arquivo .env, sem
// depender do Composer (o projeto não usa nenhuma biblioteca externa hoje).
//
// Uso: carregarEnv(__DIR__ . '/../.env'); antes de chamar getenv('DB_HOST') etc.
// Variáveis já definidas de verdade no ambiente (painel do host, Docker, etc.) nunca
// são sobrescritas pelo .env — o arquivo é só um fallback para desenvolvimento local.

function carregarEnv($caminhoEnv)
{
    if (!is_file($caminhoEnv) || !is_readable($caminhoEnv)) {
        return;
    }

    $linhas = file($caminhoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
            continue;
        }

        list($chave, $valor) = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        $tamanho = strlen($valor);
        if ($tamanho >= 2) {
            $primeiro = $valor[0];
            $ultimo = $valor[$tamanho - 1];
            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        if (getenv($chave) === false) {
            putenv($chave . '=' . $valor);
            $_ENV[$chave] = $valor;
        }
    }
}
