# Workflow de Git / GitHub

O projeto não tinha `git` nem repositório remoto até 2026-09-22. A partir de agora:

- **Repositório:** https://github.com/samuelsonbrito/evento-fametro
- **Branch padrão:** `main` — reflete o estado que pode ir pra produção (InfinityFree).
- **Remote local:** `origin`, via SSH (`git@github.com:samuelsonbrito/evento-fametro.git`).

## Por que `config/database.php` não está no repositório

`config/database.php` guarda a senha real do MySQL de produção em texto puro. Está no
`.gitignore` de propósito — versionar esse arquivo colocaria a credencial de produção no
histórico do Git pra sempre, mesmo que fosse removida depois. Em vez disso:

- `config/database.example.php` é o modelo commitado, com placeholders.
- Pra rodar localmente (fora do harness Docker): copiar `config/database.example.php`
  para `config/database.php` e preencher com credenciais reais/locais.
- O harness Docker (`harness/docker-compose.yml`) nem usa esse arquivo — ele sobrepõe
  `config/database.php` só dentro do container com `harness/config.local.php`, que
  aponta pro MySQL descartável local (ver `harness/README.md`).
- Se em algum momento a senha atual de produção (a que já está no servidor InfinityFree)
  precisar ser trocada por ter sido exposta antes deste ponto, isso é uma ação manual no
  painel do InfinityFree — não tem como "reverter" um `git commit` que nunca aconteceu
  aqui, mas vale confirmar que a senha antiga nunca foi parar em nenhum outro lugar
  (print, mensagem, etc.) antes deste harness existir.

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
- PR aberto desse branch contra `main`.
- Issue de acompanhamento pro restante do backlog de segurança em `ISSUES.md`.
