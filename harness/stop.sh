#!/usr/bin/env bash
# Para o ambiente local do harness.
#
# Por padrão preserva o banco de dados local (o volume Docker continua existindo),
# então na próxima vez que rodar start.sh os dados de teste continuam lá.
#
# Uso:
#   bash harness/stop.sh            # para os containers, mantém os dados
#   bash harness/stop.sh --limpar   # para e APAGA o banco local, próximo start.sh começa do zero
set -e
cd "$(dirname "$0")"

if [ "${1:-}" = "--limpar" ]; then
    echo "Parando o harness e apagando o banco de dados local (--limpar)..."
    docker compose down -v
else
    echo "Parando o harness (dados do banco local preservados)..."
    docker compose down
fi

echo "Harness parado."
