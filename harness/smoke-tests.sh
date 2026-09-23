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
# O seed em db/seed.sql precisa já estar carregado (QR-SEEDCONFIRMADO01 = Ana Beatriz,
# confirmada; QR-SEEDPENDENTE01 = Bruno Costa, pendente).
#
# api/validar_presenca.php agora exige sessão de admin (ver ISSUES.md — presença não
# podia mais ser confirmada remotamente por qualquer um com o código). Os testes de
# rejeição sem login rodam sempre; o caminho feliz autenticado só roda se você definir
# HARNESS_ADMIN_USER e HARNESS_ADMIN_PASS (gere o hash com harness/tools/hash-password.php
# e grave em administradores.senha antes de rodar):
#   HARNESS_ADMIN_USER=admin HARNESS_ADMIN_PASS=sua-senha-de-teste bash harness/smoke-tests.sh

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
check_body_contains "Comprovante (seed) exibe QR"        "Comprovante de Inscrição" "$BASE_URL/comprovante.php?codigo=QR-SEEDCONFIRMADO01"
check_body_contains "Ticket (seed) exibe QR"             "Comprovante de Inscrição" "$BASE_URL/ticket.php?codigo=QR-SEEDCONFIRMADO01"

echo
echo "-- SEO --"
check_body_contains "Home tem meta description"          'name="description"' "$BASE_URL/index.php"
check_body_contains "Home é indexável"                    'name="robots" content="index, follow"' "$BASE_URL/index.php"
check_body_contains "Home tem dados estruturados (Event)" 'application/ld+json' "$BASE_URL/index.php"
check_body_contains "Comprovante é noindex (dados pessoais)" 'name="robots" content="noindex, nofollow"' "$BASE_URL/comprovante.php?codigo=QR-SEEDCONFIRMADO01"
check_body_contains "Admin login é noindex"               'name="robots" content="noindex, nofollow"' "$BASE_URL/admin/login.php"
check_status        "robots.txt responde 200"             200 "$BASE_URL/robots.txt"
check_body_contains "robots.txt bloqueia /admin/"         'Disallow: /evento-fametro/admin/' "$BASE_URL/robots.txt"
check_status        "sitemap.php responde 200"             200 "$BASE_URL/sitemap.php"
check_body_contains "sitemap.php é XML válido"             '<urlset' "$BASE_URL/sitemap.php"
check_status        "Comprovante por ID sequencial não funciona mais (ex-IDOR)" 302 "$BASE_URL/comprovante.php?id=1"
check_status        "Comprovante inexistente redireciona" 302 "$BASE_URL/comprovante.php?codigo=QR-CODIGO-INEXISTENTE"

echo
echo "-- Área administrativa (sem login) --"
check_body_contains "Login ADM carrega"                  "Painel ADM" "$BASE_URL/admin/login.php"
check_status        "Painel sem sessão redireciona"      302 "$BASE_URL/admin/index.php"
check_status        "Inscritos sem sessão redireciona"   302 "$BASE_URL/admin/inscritos.php"
check_status        "Confirmar presença (rota antiga) sem sessão redireciona" 302 "$BASE_URL/admin/confirmar-presenca.php?code=QR-SEEDPENDENTE01"

echo
echo "-- API de validação de presença: acesso direto sem sessão deve ser rejeitado --"
check_status "API sem sessão retorna 401 (ex-bypass de presença remota)" 401 \
    -X POST -d 'codigo_qrcode=QR-SEEDPENDENTE01' "$BASE_URL/api/validar_presenca.php"

resp_unauth="$(curl -s -X POST -d 'codigo_qrcode=QR-SEEDPENDENTE01' "$BASE_URL/api/validar_presenca.php")"
if printf '%s' "$resp_unauth" | grep -q '"sucesso":false'; then
    echo "PASS  API sem sessão não confirma presença"
    PASS=$((PASS+1))
else
    echo "FAIL  API sem sessão não deveria confirmar presença — veio: $resp_unauth"
    FAIL=$((FAIL+1))
fi

echo
echo "-- API de validação de presença autenticada (opcional) --"
if [ -n "${HARNESS_ADMIN_USER:-}" ] && [ -n "${HARNESS_ADMIN_PASS:-}" ]; then
    COOKIE_JAR="$(mktemp)"
    curl -s -c "$COOKIE_JAR" -o /dev/null \
        --data-urlencode "usuario=$HARNESS_ADMIN_USER" \
        --data-urlencode "senha=$HARNESS_ADMIN_PASS" \
        "$BASE_URL/admin/login.php"

    resp="$(curl -s -b "$COOKIE_JAR" -X POST -d 'codigo_qrcode=QR-SEEDPENDENTE01' "$BASE_URL/api/validar_presenca.php")"
    if printf '%s' "$resp" | grep -q '"sucesso":true'; then
        echo "PASS  Admin autenticado confirma presença"
        PASS=$((PASS+1))
    elif printf '%s' "$resp" | grep -q 'FOI CONFIRMADA'; then
        echo "PASS  Presença já confirmada em execução anterior (idempotência ok)"
        PASS=$((PASS+1))
    else
        echo "FAIL  Resposta inesperada da API autenticada: $resp"
        FAIL=$((FAIL+1))
    fi

    resp2="$(curl -s -b "$COOKIE_JAR" -X POST -d 'codigo_qrcode=QR-CODIGO-INEXISTENTE' "$BASE_URL/api/validar_presenca.php")"
    if printf '%s' "$resp2" | grep -q '"sucesso":false'; then
        echo "PASS  Código inexistente é rejeitado"
        PASS=$((PASS+1))
    else
        echo "FAIL  Código inexistente deveria retornar sucesso:false — veio: $resp2"
        FAIL=$((FAIL+1))
    fi

    resp3="$(curl -s -b "$COOKIE_JAR" -X POST -d 'codigo_qrcode=3' "$BASE_URL/api/validar_presenca.php")"
    if printf '%s' "$resp3" | grep -q '"sucesso":false'; then
        echo "PASS  ID numérico sequencial não é aceito como código (ex-IDOR)"
        PASS=$((PASS+1))
    else
        echo "FAIL  ID numérico não deveria confirmar presença — veio: $resp3"
        FAIL=$((FAIL+1))
    fi

    rm -f "$COOKIE_JAR"
else
    echo "SKIP  Defina HARNESS_ADMIN_USER e HARNESS_ADMIN_PASS pra rodar o caminho feliz autenticado"
fi

echo
echo "== Resultado: $PASS passou, $FAIL falhou =="
[ "$FAIL" -eq 0 ]
