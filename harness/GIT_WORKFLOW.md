# Workflow de Git / GitHub

O projeto não tinha `git` nem repositório remoto até 2026-09-22. A partir de agora:

- **Repositório:** https://github.com/samuelsonbrito/evento-fametro
- **Branch padrão:** `main` — reflete o estado que pode ir pra produção (InfinityFree).
- **Remote local:** `origin`, via SSH (`git@github.com:samuelsonbrito/evento-fametro.git`).

## Como as credenciais de banco são versionadas

`config/database.php` **é** versionado normalmente — desde a correção do item 1 em
`ISSUES.md`, ele não guarda mais nenhum valor real, só lê `DB_HOST`/`DB_NAME`/`DB_USER`/
`DB_PASS` via `getenv()` (carregados por `includes/env.php` a partir de um `.env` na
raiz do projeto). Quem guarda segredo é o `.env`, e esse sim está no `.gitignore` e
nunca deve ser commitado. `.env.example` é o modelo versionado, com placeholders.

- Pra rodar localmente (fora do harness Docker): copiar `.env.example` para `.env` na
  raiz do projeto e preencher com credenciais reais/locais.
- O harness Docker (`harness/docker-compose.yml`) nem usa esse `.env` — ele injeta as
  variáveis de ambiente direto no container e sobrepõe `config/database.php` só lá
  dentro com `harness/config.local.php`, que aponta pro MySQL descartável local (ver
  `harness/README.md`).
- **Deploy em produção (InfinityFree) exige um passo manual:** o servidor ainda tem uma
  versão antiga de `config/database.php` com os valores escritos direto no código. Antes
  de substituir esse arquivo pela versão nova, é preciso criar um `.env` no servidor
  (mesma pasta, com as credenciais reais de produção) — do contrário o site cai assim
  que o `config/database.php` novo for enviado, porque não vai achar nenhuma variável
  definida. Esse deploy não é automatizado por este harness; é FTP/painel manual.
- Se a senha atual de produção precisar ser trocada por ter sido exposta antes deste
  ponto, isso é uma ação manual no painel do InfinityFree — não tem como "reverter" um
  `git commit` que nunca aconteceu aqui, mas vale confirmar que a senha antiga nunca foi
  parar em nenhum outro lugar (print, mensagem, etc.) antes deste harness existir.

## Branches

- `main` — só recebe merge via Pull Request.
- Branches de trabalho: `<tipo>/<descricao-curta>`, ex.:
  `security/admin-password-migration-and-harness`, `fix/duplicidade-inscricao`,
  `feat/dashboard-exportar-csv`.
- Tipos usados: `security`, `fix`, `feat`, `chore`, `docs`.

## Commits

Mensagem curta no imperativo explicando o *porquê*, corpo opcional com detalhes. Sem
regra rígida de Conventional Commits — só clareza.

## Pull Requests

- Toda mudança de código (fora ajustes triviais de doc) vira PR contra `main`, mesmo
  trabalhando sozinho — dá histórico de revisão e um ponto de reverter fácil
  (`git revert` de um merge commit).
- Descrever no PR: o que mudou, por quê, e como foi testado (rodar
  `harness/smoke-tests.sh` e/ou o checklist manual em `harness/CHECKLIST.md` antes de
  abrir).
- Usar `gh pr create` / `gh pr view` / `gh pr merge` pela CLI, já autenticada nesta
  máquina.

## Issues

- Cada item não resolvido em `harness/ISSUES.md` que for virar trabalho de verdade deve
  ter uma issue correspondente no GitHub (linkar o número da issue de volta no
  `ISSUES.md`, se fizer sentido).
- `gh issue create`, `gh issue list`, `gh issue close` pela CLI.

## Nunca versionar hashes de senha reais

`harness/db/schema.sql` começou como o dump literal de produção, incluindo o hash MD5
real da senha do admin. Antes do primeiro push pro GitHub (repositório público), esse
valor foi **redigido e trocado por um placeholder** — MD5 é quebrável por força
bruta/rainbow table, e publicar o hash real teria dado a qualquer pessoa uma forma
prática de tentar descobrir a senha do admin. A mesma regra vale pra qualquer segredo
real (hash de senha, token, chave de API): nunca entra em um arquivo que vai pra este
repositório, nem redigido "depois" — verificar antes de dar `git add`.

## O que já foi automatizado nesta sessão

- `git init`, primeiro commit (`main`) com o estado original do projeto (sem
  `config/database.php`).
- Branch `security/admin-password-migration-and-harness` com a correção da migração de
  senha de admin (ver `ISSUES.md` item 2) + toda a pasta `harness/`.
- [PR #1](https://github.com/samuelsonbrito/evento-fametro/pull/1) aberto desse branch
  contra `main`.
- [Issue #2](https://github.com/samuelsonbrito/evento-fametro/issues/2) de
  acompanhamento pro restante do backlog de segurança em `ISSUES.md`.
