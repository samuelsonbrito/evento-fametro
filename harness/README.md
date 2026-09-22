# Harness — evento-fametro

Ambiente e ferramentas para testar mudanças neste projeto sem depender do banco de
produção da InfinityFree e sem precisar adivinhar se algo quebrou. A maior parte é só
uma camada por cima, sem alterar o código do site — as únicas exceções, feitas junto com
o harness por serem correções de segurança diretamente relacionadas, foram
`admin/login.php` (ver `ISSUES.md` item 2) e `config/database.php` + `includes/env.php`
(ver `ISSUES.md` item 1, credenciais via `.env`).

## Conteúdo

| Arquivo/pasta              | Para quê |
|-----------------------------|----------|
| `ANALYSIS.md`                | Visão geral da aplicação e como as peças se encaixam |
| `ISSUES.md`                  | Problemas e inconsistências encontrados, priorizados |
| `CHECKLIST.md`                | QA manual para o que não dá pra automatizar (câmera, impressão) |
| `db/schema.sql`               | Dump **real** de produção (InfinityFree), como fornecido — fonte da verdade |
| `db/seed.sql`                  | Inscrições de exemplo (aluno, externo, presença confirmada/pendente) somadas às 9 palestras e ao admin reais que já vêm no schema.sql |
| `docker-compose.yml`          | PHP+Apache, MySQL e phpMyAdmin locais e descartáveis |
| `Dockerfile.php`               | Imagem PHP com `pdo_mysql` (a imagem oficial não vem com essa extensão) |
| `config.local.php`             | Substitui `config/database.php` **só dentro do container**, apontando pro MySQL local |
| `start.sh` / `stop.sh`          | Sobe/para o ambiente local (ver "Como usar" abaixo) |
| `smoke-tests.sh`                | Testa via curl o caminho feliz de cada página/endpoint |
| `tools/hash-password.php`       | Gera hash bcrypt pra senha de admin (em vez de texto puro/MD5) |

## Por que isso existe

O projeto não tinha nenhuma forma de testar mudanças: sem PHP/Composer/Docker
instalados localmente, `config/database.php` apontava direto pra produção com a senha
gravada no arquivo (corrigido — ver `ISSUES.md` item 1), e não havia testes nem
histórico de git. Ver `ANALYSIS.md` para o levantamento completo e `ISSUES.md` para os
riscos ainda em aberto.

## Pré-requisito

Docker Desktop instalado (nada disso funciona sem ele — esta máquina não tinha PHP,
Composer nem Docker no PATH no momento da análise).

## Como usar

Subir o ambiente (roda `docker compose up -d --build` por baixo):

```bash
bash harness/start.sh
```

Isso sobe:
- **app** — `http://localhost:8080/evento-fametro/index.php`
- **phpMyAdmin** — `http://localhost:8081` (usuário `fametro`, senha `fametro_local_pw`)
- **db** — MySQL exposto em `localhost:33061` se quiser conectar com outro cliente

Parar quando terminar de testar (mantém o banco local pra próxima vez):

```bash
bash harness/stop.sh
```

Parar E apagar o banco local, pra começar do zero na próxima vez:

```bash
bash harness/stop.sh --limpar
```

Login de admin: `admin/login.php` só aceita `password_verify()` — não existe mais
fallback pra MD5/texto puro (ver `ISSUES.md` item 2), então nenhuma conta do
`db/schema.sql`/`db/seed.sql` tem uma senha "de fábrica" que funcione (os dois vêm com
placeholders inválidos de propósito, pra nunca ter um hash real e utilizável dentro de
um arquivo versionado). Pra logar no harness, gere um hash primeiro:

```bash
docker compose exec app php /var/www/html/evento-fametro/harness/tools/hash-password.php "sua-senha-de-teste"
```

E aplique na conta de QA do seed via phpMyAdmin (`localhost:8081`):

```sql
UPDATE administradores SET senha = '<hash gerado>' WHERE usuario = 'harness_qa';
```

Depois disso, `harness_qa` / a senha que você escolheu já loga normalmente.

Rodar os smoke tests depois que o `app` responder:

```bash
bash smoke-tests.sh
```

Resetar tudo (banco limpo, seed reaplicado do zero):

```bash
bash harness/stop.sh --limpar
bash harness/start.sh
```

## Apontar pra outro ambiente (ex.: staging)

```bash
HARNESS_BASE_URL="https://staging.exemplo.com/evento-fametro" bash smoke-tests.sh
```

## Incidente de 2026-09-22

`harness/db/schema.sql`/`seed.sql` foram rodados por engano contra produção e apagaram
inscrições reais (sem backup). Ver `harness/db/incidente-2026-09-22-saneamento-producao.sql`
(script de correção pontual, sem `DROP TABLE`) e `ISSUES.md`. Os dois arquivos de
schema/seed agora têm avisos bem visíveis no topo — **nunca rodar contra um banco com
dados reais**.

## Limitações conhecidas

- O leitor de QR Code por câmera (`admin/validar-qrcode.php`) precisa de HTTPS ou
  `localhost` pro navegador liberar a câmera — `http://localhost:8080` funciona, mas um
  domínio remoto sem TLS não vai pedir permissão de câmera. Use `CHECKLIST.md` pra isso.
- `smoke-tests.sh` cobre só HTTP/JSON; nada de renderização visual, upload de imagem ou
  impressão — isso está no `CHECKLIST.md`.
- A grade real de palestras (1-9) não tem horários sobrepostos entre si, só adjacentes
  — não dá pra testar `verificarConflitoHorario()` num conflito de verdade sem inserir
  manualmente uma palestra extra sobreposta no banco local do harness.
