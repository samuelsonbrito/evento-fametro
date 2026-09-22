# Harness — evento-fametro

Ambiente e ferramentas para testar mudanças neste projeto sem depender do banco de
produção da InfinityFree e sem precisar adivinhar se algo quebrou. Nada aqui altera o
código do site (`config/database.php` e demais arquivos na raiz continuam intocados) —
o harness é só uma camada por cima.

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
| `smoke-tests.sh`                | Testa via curl o caminho feliz de cada página/endpoint |
| `tools/hash-password.php`       | Gera hash bcrypt pra senha de admin (em vez de texto puro/MD5) |

## Por que isso existe

O projeto não tinha nenhuma forma de testar mudanças: sem PHP/Composer/Docker
instalados localmente, `config/database.php` aponta direto pra produção com a senha
gravada no arquivo, e não há testes nem histórico de git. Qualquer alteração hoje só
pode ser validada publicando direto e torcendo. Ver `ANALYSIS.md` para o levantamento
completo e `ISSUES.md` para os riscos concretos (o mais grave: credencial de produção em
texto puro no repositório).

## Pré-requisito

Docker Desktop instalado (nada disso funciona sem ele — esta máquina não tinha PHP,
Composer nem Docker no PATH no momento da análise).

## Como usar

```bash
cd harness
docker compose up -d --build
```

Isso sobe:
- **app** — `http://localhost:8080/evento-fametro/index.php`
- **phpMyAdmin** — `http://localhost:8081` (usuário `fametro`, senha `fametro_local_pw`)
- **db** — MySQL exposto em `localhost:33061` se quiser conectar com outro cliente

Login de admin: o usuário `admin` vem com um hash placeholder no `db/schema.sql`
versionado (o hash MD5 real de produção foi redigido antes de subir pro GitHub — ver
`GIT_WORKFLOW.md` e `ISSUES.md` item 2), então não dá pra logar como `admin` no harness.
Pra testar o painel, use a conta de QA do seed:
usuário `harness_qa`, senha `harness123`. Depois desse primeiro login, confira no
phpMyAdmin que a coluna `senha` dela virou um hash `$2y$...` (bcrypt) sozinha — é a
migração automática de `admin/login.php` funcionando (ver `ISSUES.md` item 2).

Rodar os smoke tests depois que o `app` responder:

```bash
bash smoke-tests.sh
```

Resetar tudo (banco limpo, seed reaplicado do zero):

```bash
docker compose down -v
docker compose up -d
```

## Apontar pra outro ambiente (ex.: staging)

```bash
HARNESS_BASE_URL="https://staging.exemplo.com/evento-fametro" bash smoke-tests.sh
```

## Gerar uma senha de admin com hash de verdade

```bash
docker compose exec app php /var/www/html/evento-fametro/harness/tools/hash-password.php "senhaForte123"
```

Depois grave o hash retornado na coluna `senha` de `administradores` (via phpMyAdmin,
por exemplo) no lugar do texto puro do seed.

## Limitações conhecidas

- O leitor de QR Code por câmera (`admin/validar-qrcode.php`) precisa de HTTPS ou
  `localhost` pro navegador liberar a câmera — `http://localhost:8080` funciona, mas um
  domínio remoto sem TLS não vai pedir permissão de câmera. Use `CHECKLIST.md` pra isso.
- `smoke-tests.sh` cobre só HTTP/JSON; nada de renderização visual, upload de imagem ou
  impressão — isso está no `CHECKLIST.md`.
- A grade real de palestras (1-9) não tem horários sobrepostos entre si, só adjacentes
  — não dá pra testar `verificarConflitoHorario()` num conflito de verdade sem inserir
  manualmente uma palestra extra sobreposta no banco local do harness.
