# Jornada Acadêmica Imersão FAMETRO

Sistema de inscrição e credenciamento para a **Jornada Acadêmica Imersão FAMETRO**
(2 de outubro) — um evento acadêmico presencial com múltiplas palestras. Alunos e
público externo se inscrevem pela web, recebem um comprovante com QR Code, e a
presença é confirmada no dia do evento por um leitor de QR Code operado pela equipe
organizadora. Depois de confirmada a presença, o sistema emite declarações de
participação (individuais ou em lote).

## Sumário

- [Funcionalidades](#funcionalidades)
- [Stack e arquitetura](#stack-e-arquitetura)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Modelo de dados](#modelo-de-dados)
- [Fluxo da aplicação](#fluxo-da-aplicação)
- [Como rodar localmente](#como-rodar-localmente)
- [Configuração (.env)](#configuração-env)
- [Testes](#testes)
- [Segurança](#segurança)
- [Deploy em produção](#deploy-em-produção)
- [Workflow de git / contribuição](#workflow-de-git--contribuição)
- [Problemas conhecidos](#problemas-conhecidos)

## Funcionalidades

**Público (sem login):**
- Listagem das palestras do evento, ordenadas por horário (`index.php`).
- Inscrição em uma palestra como aluno FAMETRO (com matrícula) ou público externo
  (`cadastro.php`).
- Bloqueio automático de conflito de horário: um aluno não consegue se inscrever em
  duas palestras que aconteçam ao mesmo tempo.
- Comprovante de inscrição com QR Code gerado na hora (`comprovante.php`/`ticket.php`),
  usando a API pública do [QuickChart](https://quickchart.io/) pra renderizar a imagem.

**Administração (login obrigatório):**
- Painel com estatísticas gerais: total de palestras, inscrições e presenças
  confirmadas (`admin/index.php`).
- Cadastro de novas palestras, com upload de foto do palestrante
  (`admin/cadastrar-palestra.php`).
- Listagem de inscritos, com filtro por palestra (`admin/inscritos.php`).
- Leitor de QR Code pela câmera do navegador (biblioteca `html5-qrcode`), com
  validação manual por código digitado como alternativa (`admin/validar-qrcode.php`).
- Emissão de declaração de participação individual ou em lote por palestra, só para
  quem teve presença confirmada (`admin/imprimir-comprovante.php`,
  `admin/imprimir-comprovantes-lote.php`).

## Stack e arquitetura

- **PHP procedural puro** — sem framework (Laravel, Symfony etc.) e sem Composer.
  Cada página é um script PHP que mistura lógica de banco e HTML/Bootstrap na mesma
  requisição.
- **MySQL/MariaDB** via PDO com prepared statements em todas as queries.
- **Sessão nativa do PHP** (`session_start()`) para autenticação de admin — sem JWT,
  sem OAuth.
- **Front-end**: Bootstrap 5 + [Tabler](https://tabler.io/) (CSS/ícones) via CDN,
  JavaScript vanilla (sem build step, sem bundler).
- **QR Code**: gerado como imagem via API externa (QuickChart) e lido no navegador
  via [`html5-qrcode`](https://github.com/mebjas/html5-qrcode) (CDN).
- **Hospedagem de produção**: [InfinityFree](https://www.infinityfree.com/) (hosting
  compartilhado), com o banco em `sql102.infinityfree.com`. Deploy é manual (FTP/painel
  do host) — não há CI/CD.

## Estrutura de pastas

```
.
├── index.php                  # Home pública — lista as palestras
├── cadastro.php                # Formulário de inscrição
├── comprovante.php             # Comprovante com QR Code (busca por ?codigo=)
├── ticket.php                  # Idêntico a comprovante.php (ver "Problemas conhecidos")
├── config/
│   └── database.php             # Conexão PDO, lê credenciais do .env
├── includes/
│   ├── env.php                   # Parser mínimo de .env (sem Composer)
│   ├── functions.php             # sanitize(), autenticação admin, geração de QR, etc.
│   ├── header.php                # <head> + navbar, comum a todas as páginas
│   └── footer.php                # Rodapé, comum a todas as páginas
├── admin/                        # Área administrativa — todas as páginas exigem login
│   ├── login.php / logout.php
│   ├── index.php                  # Painel com estatísticas
│   ├── cadastrar-palestra.php     # Criar palestra + upload de foto
│   ├── inscritos.php              # Listagem de inscritos, com filtro
│   ├── validar-qrcode.php         # Leitor de câmera + validação manual
│   ├── confirmar-presenca.php     # Rota alternativa de confirmação por link (?code=)
│   ├── imprimir-comprovante.php   # Declaração individual
│   └── imprimir-comprovantes-lote.php  # Declarações em lote por palestra
├── api/
│   ├── validar_presenca.php      # Endpoint JSON chamado pelo leitor de QR Code
│   └── cadastrar_aluno.php       # Endpoint alternativo de inscrição (ver issue #9)
├── assets/
│   ├── css/style.css
│   └── img/                       # Logo e imagens institucionais
├── uploads/palestrantes/          # Fotos enviadas no cadastro de palestras
├── .env.example                   # Modelo de configuração (copiar para .env)
└── harness/                       # Ambiente de desenvolvimento/QA local — ver abaixo
```

## Modelo de dados

Três tabelas (schema completo e sempre atualizado em `harness/db/schema.sql`, que é a
fonte da verdade — reflete o dump real de produção):

- **`palestras`** — título, palestrante, foto, descrição, horário de início/fim,
  limite de vagas (`vagas`, hoje só informativo — não é aplicado no cadastro).
- **`inscricoes`** — nome, matrícula (opcional, `NULL` pra público externo), e-mail,
  `palestra_id` (FK `ON DELETE CASCADE`), `codigo_qrcode` (**`UNIQUE`**, é a chave
  usada pra localizar o comprovante e confirmar presença), `tipo_participante`
  (`aluno`/`externo`), e duas colunas de presença (`presenca_confirmada` e `presente`
  — mantidas em paralelo, ver "Problemas conhecidos"). Tem também
  `UNIQUE KEY uk_aluno_palestra (matricula, palestra_id)` — um aluno não pode se
  inscrever duas vezes na mesma palestra.
- **`administradores`** — usuário e senha (hash bcrypt via `password_hash()`,
  `PASSWORD_BCRYPT` custo 12).

## Fluxo da aplicação

```
Aluno/público            index.php → cadastro.php?palestra_id=N → INSERT em inscricoes
                          → redireciona pra comprovante.php?codigo=QR-XXXX (mostra QR)

Dia do evento (staff)    admin/login.php → admin/validar-qrcode.php
                          → câmera lê o QR (ou digita o código manualmente)
                          → POST pra api/validar_presenca.php
                          → UPDATE inscricoes SET presenca_confirmada=1, presente=1

Depois do evento (staff) admin/inscritos.php → botão "Declaração" (só se presença
                          confirmada) → admin/imprimir-comprovante.php (individual)
                          ou admin/imprimir-comprovantes-lote.php (todos de uma palestra)
```

## Como rodar localmente

Este projeto **não tem PHP/MySQL configurados fora do Docker** — a forma suportada de
rodar localmente é o harness em `harness/`, que sobe um ambiente completo e descartável
(app PHP+Apache, MySQL, phpMyAdmin), isolado do banco de produção.

```bash
bash harness/start.sh
```

Isso sobe:
- **App**: http://localhost:8080/index.php
- **phpMyAdmin**: http://localhost:8081 (usuário `fametro`, senha `fametro_local_pw`)
- **MySQL**: `localhost:33061`, se quiser conectar com outro cliente

Parar quando terminar (mantém o banco local pra próxima vez):

```bash
bash harness/stop.sh
```

Detalhes completos (gerar senha de admin pra login local, resetar o banco, apontar os
testes pra um ambiente remoto/staging) estão em **[`harness/README.md`](harness/README.md)**.

## Configuração (.env)

`config/database.php` não guarda nenhuma credencial no código — ele lê
`DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` de variáveis de ambiente, carregadas de um
arquivo `.env` na raiz do projeto (via `includes/env.php`, um parser mínimo escrito à
mão, já que o projeto não usa Composer).

```bash
cp .env.example .env
# edite .env com as credenciais do seu MySQL
```

`.env` está no `.gitignore` e nunca deve ser commitado — `.env.example` é o único
modelo versionado. O harness Docker **não usa** esse `.env`: ele injeta as variáveis
direto no container (ver `harness/docker-compose.yml`).

## Testes

Não há framework de testes automatizado (sem PHPUnit) — a cobertura é feita por:

- **`harness/smoke-tests.sh`** — smoke tests via `curl` do caminho feliz de cada
  página/endpoint (fluxo público, painel sem login redirecionando corretamente, API de
  validação de presença). Rodar depois de subir o harness:

  ```bash
  bash harness/smoke-tests.sh
  ```

- **`harness/CHECKLIST.md`** — checklist manual de QA pro que não dá pra automatizar
  fácil (upload de imagem, leitura de câmera, layout de impressão).

## Segurança

O projeto já passou por rodadas de correção de segurança — histórico completo em
**[`harness/ISSUES.md`](harness/ISSUES.md)**. Resumo do que já foi corrigido:

- Credenciais de banco fora do código-fonte (via `.env`).
- Login de admin só aceita `password_verify()`/bcrypt — sem fallback pra MD5/texto puro.
- IDOR corrigido em `comprovante.php`/`ticket.php`: busca por `codigo_qrcode` (não
  sequencial) em vez de `id` numérico.
- `api/validar_presenca.php` e `admin/confirmar-presenca.php` agora exigem sessão de
  admin autenticada antes de confirmar qualquer presença.

Itens ainda em aberto (CSRF, rate limiting, mensagens de erro expostas ao usuário,
etc.) estão documentados e priorizados em `harness/ISSUES.md`.

## Deploy em produção

Não há CI/CD — o deploy no InfinityFree é manual (FTP/painel do host). Dois pontos de
atenção:

1. O `.env` de produção precisa existir **antes** de enviar um `config/database.php`
   atualizado, senão o site cai (não acha as variáveis de ambiente).
2. `harness/db/schema.sql`/`seed.sql` começam com `DROP TABLE` — **nunca rodar contra
   o banco de produção**. Eles servem só pro MySQL descartável do harness. Qualquer
   ajuste em produção deve ser um script pontual (`ALTER`/`UPDATE`/`DELETE`
   específicos), nunca esses arquivos.

## Workflow de git / contribuição

- `main` só recebe merge via Pull Request.
- Branches de trabalho: `<tipo>/<descrição-curta>` — tipos usados: `security`, `fix`,
  `feat`, `chore`, `docs`.
- Detalhes completos em **[`harness/GIT_WORKFLOW.md`](harness/GIT_WORKFLOW.md)**.

## Problemas conhecidos

Lista completa, priorizada, com arquivo/linha de cada item: **[`harness/ISSUES.md`](harness/ISSUES.md)**.
Alguns destaques que ainda não foram corrigidos:

- `comprovante.php` e `ticket.php` são arquivos idênticos (dois pontos de manutenção
  pra mesma tela).
- Duas colunas de presença (`presenca_confirmada` e `presente`) gravadas em paralelo.
- Sem proteção CSRF nos formulários (login, cadastro, cadastro de palestra).
- Sem rate limiting no login de admin.
- Limite de vagas (`palestras.vagas`) não é aplicado no momento da inscrição.
