# Análise do projeto — evento-fametro

Levantamento feito lendo todos os arquivos do projeto (25 arquivos, sem contar imagens
enviadas em `uploads/`). Serve de base para o harness e para futuras mudanças.

## O que é

Sistema de inscrição e credenciamento para um evento acadêmico presencial (Jornada
Acadêmica Imersão FAMETRO, 2 de outubro). PHP procedural puro (sem framework, sem
Composer), MySQL/MariaDB via PDO, hospedado na InfinityFree (`sql102.infinityfree.com`).

Fluxo público:
1. `index.php` lista as palestras cadastradas.
2. `cadastro.php?palestra_id=N` — formulário de inscrição (aluno com matrícula ou
   público externo).
3. Submissão grava em `inscricoes` e redireciona para `comprovante.php?id=N`
   (ou `ticket.php`/`api/cadastrar_aluno.php`, ver duplicação abaixo), que mostra os
   dados e um QR Code gerado via `quickchart.io`.
4. No dia do evento, `admin/validar-qrcode.php` lê o QR pela câmera (lib `html5-qrcode`)
   e chama `api/validar_presenca.php`, que marca presença.
5. Depois de confirmada a presença, dá pra emitir declaração individual
   (`admin/imprimir-comprovante.php`) ou em lote por palestra
   (`admin/imprimir-comprovantes-lote.php`).

Área administrativa (`admin/*`) protegida por sessão (`checarAutenticacaoAdmin()` em
`includes/functions.php`), login contra a tabela `administradores`.

## Estrutura de dados

Não existe nenhum arquivo de schema/migração versionado no projeto — a pasta
`database/` está vazia. `harness/db/schema.sql` era, inicialmente, um schema
reconstruído lendo cada `SELECT`/`INSERT`/`UPDATE` do código; em 2026-09-22 foi
**substituído pelo dump real de produção** (InfinityFree) fornecido diretamente. A
partir de agora, `harness/db/schema.sql` é a fonte da verdade e deve ser atualizado
sempre que o schema real mudar — não o contrário.

Três tabelas: `palestras`, `inscricoes`, `administradores`. Duas coisas que o dump real
revelou e que a versão inferida não tinha:

- `inscricoes` tem `UNIQUE KEY uk_aluno_palestra (matricula, palestra_id)` — uma
  restrição de verdade no banco que parte do código não respeita (ver `ISSUES.md`).
- `administradores.senha` do usuário `admin` real era um hash **MD5 puro** (valor
  redigido antes de versionar este documento — ver `GIT_WORKFLOW.md`), não
  `password_hash()`/bcrypt. Confirmou que o
  fallback inseguro de `admin/login.php` não era só uma possibilidade teórica — era o
  modo como a conta de admin real estava protegida. **Corrigido em 2026-09-22:**
  `admin/login.php` agora faz upgrade automático pra bcrypt no primeiro login válido
  (ver `ISSUES.md` item 2) — não muda a senha que a pessoa usa, só troca o hash salvo.

## Por que não dá pra testar isso hoje

- Não há PHP, Composer, PHPUnit nem Docker instalados nesta máquina — não dá pra nem
  rodar o projeto localmente sem instalar algo primeiro.
- `config/database.php` aponta direto pro banco de produção da InfinityFree, com a
  senha em texto puro no arquivo. Ou seja: hoje, testar qualquer mudança localmente
  significa testar contra o banco real do evento.
- Não há `.git` inicializado — nenhum histórico, nenhum jeito de comparar "antes/depois"
  de uma mudança ou reverter algo que quebrou.
- Não há nenhum teste automatizado, script de smoke test ou checklist de QA.

O harness em `harness/` resolve isso com um MySQL local descartável (Docker), schema +
seed, smoke tests via curl e um checklist manual para o que não dá pra automatizar
fácil (câmera de QR Code, impressão).

## Inconsistências e riscos encontrados

Ver `harness/ISSUES.md` para a lista detalhada e priorizada. Resumo:

- ~~Credencial de produção em texto puro dentro do repositório (`config/database.php`).~~
  Corrigido: `config/database.php` agora lê de `.env` (não versionado) via
  `includes/env.php`. Falta replicar isso manualmente no servidor de produção.
- ~~Login admin aceita senha em texto puro ou MD5 como fallback de `password_verify()`.~~
  Corrigido: agora migra pra bcrypt sozinho no primeiro login válido.
- Duas colunas de presença (`presenca_confirmada` e `presente`) mantidas em paralelo,
  gravadas de forma inconsistente por arquivo diferente.
- `foto` (banco) vs `imagem` (fallback lido em `index.php`) — nunca existiu coluna
  `imagem`, é um resquício morto.
- `cadastro.php` verifica duplicidade por `email + palestra_id`, mas a constraint real
  do banco é `matricula + palestra_id` (`uk_aluno_palestra`) — os dois caminhos de
  cadastro não concordam sobre o que torna uma inscrição "duplicada".
- O campo `vagas` de cada palestra nunca é validado no momento da inscrição — é só
  informativo no painel admin.
- `comprovante.php` e `ticket.php` são exatamente o mesmo arquivo duplicado.
- Mensagens de erro de banco (`$e->getMessage()`) são exibidas direto pro usuário final
  em `cadastro.php`, `admin/login.php` e `admin/cadastrar-palestra.php`.
- Sem CSRF token em nenhum formulário (login, cadastro, cadastro de palestra).
- Sem rate limiting no login admin nem na API de validação de QR Code.
- Upload de foto do palestrante valida só a extensão do nome do arquivo, não o
  conteúdo/MIME real.
- `display_errors` ligado em produção (`config/database.php`), que também vaza detalhes
  internos.
