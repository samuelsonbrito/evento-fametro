#!/usr/bin/env bash
# Sobe o ambiente local do harness (app PHP + MySQL descartável + phpMyAdmin),
# isolado da produção. Idempotente: pode rodar de novo com o ambiente já no ar.
set -e
cd "$(dirname "$0")"

echo "Subindo o harness (app, banco de dados e phpMyAdmin)..."
docker compose up -d --build

echo
echo "Harness no ar:"
echo "  Site:       http://localhost:8080/evento-fametro/index.php"
echo "  phpMyAdmin: http://localhost:8081  (usuário: fametro / senha: fametro_local_pw)"
echo "  MySQL:      localhost:33061"
echo
echo "Rodar os smoke tests:  bash harness/smoke-tests.sh"
echo "Parar quando terminar: bash harness/stop.sh"
