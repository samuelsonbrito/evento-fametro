#!/usr/bin/env bash
# Smoke tests do "caminho feliz" do evento-fametro.
#
# Não existe nenhum framework de teste no projeto (sem PHPUnit, sem Composer), então
# este script cobre as rotas principais via HTTP/curl. Rode depois de subir o
# ambiente local (`docker compose up -d` dentro de harness/) e aplicar db/seed.sql.
#
# Uso:
#   bash harness/smoke-tests.sh
#   HARNESS_BASE_URL=https://staging.exemplo.com/evento-fametro bash harness/smoke-tests.sh
#
# O seed em db/seed.sql precisa já estar carregado (id 1 = Ana Beatriz, confirmada;
# QR-SEEDPENDENTE01 = Bruno Costa, pendente).

set -u

BASE_URL="${HARNESS_BASE_URL:-http://localhost:8080/evento-fametro}"
PASS=0
FAIL=0

status_of() {
    curl -s -o /dev/null -w "%{http_code}" "$@"
}

check_status() {
    local desc="$1" expected="$2"; shift 2
    local actual
    actual="$(status_of "$@")"
    if [ "$actual" = "$expected" ]; then
        echo "PASS  $desc (HTTP $actual)"
        PASS=$((PASS+1))
    else
        echo "FAIL  $desc (esperado HTTP $expected, veio $actual)"
        FAIL=$((FAIL+1))
    fi
}

check_body_contains() {
    local desc="$1" needle="$2"; shift 2
    local body
    body="$(curl -s "$@")"
    if printf '%s' "$body" | grep -qF "$needle"; then
        echo "PASS  $desc"
        PASS=$((PASS+1))
    else
        echo "FAIL  $desc (não encontrou: \"$needle\")"
        FAIL=$((FAIL+1))
    fi
}

echo "== Alvo: $BASE_URL =="
echo

echo "-- Fluxo público --"
check_body_contains "Home lista a programação"          "Programação das Palestras" "$BASE_URL/index.php"
check_body_contains "CSS carrega"                        "" "$BASE_URL/assets/css/style.css"
check_status        "CSS responde 200"                   200 "$BASE_URL/assets/css/style.css"
check_body_contains "Cadastro abre para palestra válida" "Inscrição de Participante" "$BASE_URL/cadastro.php?palestra_id=1"
check_status        "Cadastro sem palestra_id redireciona" 302 "$BASE_URL/cadastro.php"
check_body_contains "Comprovante (seed id=1) exibe QR"   "Comprovante de Inscrição" "$BASE_URL/comprovante.php?id=1"
check_body_contains "Ticket (seed id=1) exibe QR"        "Comprovante de Inscrição" "$BASE_URL/ticket.php?id=1"
check_status        "Comprovante inexistente redireciona" 302 "$BASE_URL/comprovante.php?id=999999"

echo
echo "-- Área administrativa (sem login) --"
check_body_contains "Login ADM carrega"                  "Painel ADM" "$BASE_URL/admin/login.php"
check_status        "Painel sem sessão redireciona"      302 "$BASE_URL/admin/index.php"
check_status        "Inscritos sem sessão redireciona"   302 "$BASE_URL/admin/inscritos.php"

echo
echo "-- API de validação de presença (usa o seed QR-SEEDPENDENTE01) --"
resp="$(curl -s -X POST -d 'codigo_qrcode=QR-SEEDPENDENTE01' "$BASE_URL/api/validar_presenca.php")"
if printf '%s' "$resp" | grep -q '"sucesso":true'; then
    echo "PASS  Primeira validação confirma presença"
    PASS=$((PASS+1))
elif printf '%s' "$resp" | grep -q 'JÁ FOI CONFIRMADA'; then
    echo "PASS  Presença já confirmada em execução anterior (idempotência ok)"
    PASS=$((PASS+1))
else
    echo "FAIL  Resposta inesperada da API: $resp"
    FAIL=$((FAIL+1))
fi

resp2="$(curl -s -X POST -d 'codigo_qrcode=QR-CODIGO-INEXISTENTE' "$BASE_URL/api/validar_presenca.php")"
if printf '%s' "$resp2" | grep -q '"sucesso":false'; then
    echo "PASS  Código inexistente é rejeitado"
    PASS=$((PASS+1))
else
    echo "FAIL  Código inexistente deveria retornar sucesso:false — veio: $resp2"
    FAIL=$((FAIL+1))
fi

echo
echo "== Resultado: $PASS passou, $FAIL falhou =="
[ "$FAIL" -eq 0 ]
