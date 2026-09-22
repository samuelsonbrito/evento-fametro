# Problemas encontrados na análise

Lista bruta, do mais crítico pro mais cosmético. Cada item tem o(s) arquivo(s)
envolvido(s). Acompanhamento no GitHub:
[issue #2](https://github.com/samuelsonbrito/evento-fametro/issues/2) — os itens já
corrigidos estão marcados abaixo, o resto é backlog.

## Crítico

1. **[CORRIGIDO em 2026-09-22] Senha do banco de produção em texto puro no arquivo.**
   `config/database.php` tinha `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` literais no
   código. Corrigido: `config/database.php` agora lê essas quatro variáveis via
   `getenv()`, carregadas de um arquivo `.env` na raiz do projeto (por
   `includes/env.php`, um parser mínimo escrito na mão — o projeto não usa Composer).
   `.env` está no `.gitignore` e nunca foi commitado; `.env.example` é o modelo
   versionado. Como `config/database.php` não guarda mais nenhum segredo, ele mesmo
   passou a ser versionado (antes era ignorado inteiro).

   **Ação manual pendente, fora do alcance do harness:** o `config/database.php` que já
   está no servidor InfinityFree ainda tem os valores antigos escritos direto no código
   — isso é outro arquivo físico (deploy é manual, não tem CI/CD nem FTP automatizado
   aqui). Antes de subir esta versão pro servidor, é preciso criar um arquivo `.env` lá
   (mesma pasta do `config/database.php` real, com as credenciais de produção reais —
   ver `.env.example`) **e só depois** substituir o `config/database.php` do servidor
   por este daqui. Subir só o código novo sem o `.env` correspondente derruba o site.

2. **[CORRIGIDO em 2026-09-22] Login admin aceitava senha em texto puro ou MD5.**
   Confirmado no dump real de produção: o usuário `admin` tinha a senha guardada como
   MD5 puro, não `password_hash()` (o valor exato do hash foi redigido antes de
   versionar este documento — MD5 é quebrável e este é um repositório público, ver
   `GIT_WORKFLOW.md`). Não era um cenário hipotético — a conta de admin do evento estava
   protegida só por MD5.

   Como ninguém tem a senha em texto puro que gerou esse hash (só o dump do banco),
   trocar a senha "na mão" exigiria comunicar uma senha nova pra quem usa o painel, no
   meio do evento. Em vez disso, `admin/login.php` agora faz migração transparente: no
   próximo login válido (com a senha que a pessoa já usa), o código detecta que o hash
   salvo não é bcrypt, confere a senha via MD5/texto puro como antes, mas imediatamente
   gera um `password_hash()` novo e sobrescreve a coluna `senha` — sem exigir nenhuma
   ação de quem loga. Depois desse primeiro login pós-deploy, o MD5 nunca mais existe no
   banco para essa conta, e todo login seguinte passa só pelo `password_verify()`.

   Testável no harness com a conta `harness_qa` (ver `db/seed.sql`), que simula o mesmo
   estado legado com uma senha de teste conhecida.

   **Follow-up recomendado, não feito ainda:** depois de confirmar (via
   `SELECT senha FROM administradores`) que toda conta de admin em produção já foi
   migrada — hash começando com `$2y$` — remover de vez o fallback de MD5/texto puro em
   `admin/login.php`, deixando só `password_verify()`. Enquanto isso não acontece, o
   fallback continua sendo a única forma de destravar uma conta ainda não migrada.

3. **Mensagens de exceção do banco expostas ao usuário final.**
   `cadastro.php:58`, `admin/login.php:29`, `admin/cadastrar-palestra.php:52` — todos
   fazem `"Erro ao processar: " . $e->getMessage()` e imprimem isso na tela. Vaza
   estrutura de tabela, nomes de coluna, às vezes até fragmento da query.

## Alto

4. **Duas colunas de presença gravadas de forma inconsistente.**
   `admin/confirmar-presenca.php:28` grava só `presenca_confirmada`.
   `api/validar_presenca.php:76` grava `presenca_confirmada` **e** `presente` **e**
   `data_presenca`. As leituras (`admin/inscritos.php:100`,
   `admin/imprimir-comprovante.php:26`, `admin/imprimir-comprovantes-lote.php:25`)
   verificam `presenca_confirmada OR presente`, então hoje não quebra — mas é fácil
   introduzir um bug futuro esquecendo de atualizar as duas colunas em um novo fluxo.
   Vale unificar em uma coluna só.

5. **Sem proteção CSRF em nenhum formulário.**
   `cadastro.php`, `admin/login.php`, `admin/cadastrar-palestra.php` — formulários POST
   sem token. Como o login aceita GET indiretamente via redirecionamento e não há
   `SameSite`/CSRF configurado, um site malicioso poderia submeter esses formulários em
   nome de uma sessão de admin já autenticada.

6. **Sem rate limiting.**
   `admin/login.php` (força bruta de senha) e `api/validar_presenca.php` (poderia ser
   martelado para tentar descobrir códigos de QR válidos por tentativa e erro, já que
   aceita `i.id` numérico como alternativa ao código).

7. **`api/validar_presenca.php` aceita `i.id` como código válido.**
   Linha 46-47: a query casa `codigo_qrcode` OU `i.id`. Isso significa que digitar
   manualmente um ID pequeno (`1`, `2`, `3`...) no campo "código manual" do validador
   confirma presença de qualquer inscrição, sem precisar do QR Code real.

## Médio-Alto

7b. **Checagem de duplicidade não bate com a constraint real do banco.**
   `cadastro.php:35` verifica se já existe inscrição pelo par `(palestra_id, email)`.
   O banco real, porém, tem `UNIQUE KEY uk_aluno_palestra (matricula, palestra_id)`
   (`harness/db/schema.sql`) — a restrição de verdade é por matrícula, não e-mail.
   `api/cadastrar_aluno.php:23` já checa corretamente por `(matricula, palestra_id)`,
   então os dois pontos de entrada divergem. Consequência prática: um aluno pode passar
   pelo check de `cadastro.php` (e-mail diferente, mesma matrícula) e cair num erro de
   chave duplicada do MySQL na hora do `INSERT`, que vira uma `PDOException` — e o
   item 3 desta lista já mostra que essa exceção é exibida crua pro usuário.

7c. **Limite de vagas não é aplicado em lugar nenhum.**
   `palestras.vagas` existe e é mostrado no painel (`admin/index.php:145`,
   `.../vagas`), mas nem `cadastro.php` nem `api/cadastrar_aluno.php` contam quantas
   inscrições já existem antes de inserir uma nova. Não trava a inscrição em nenhum
   momento, mesmo que a palestra "encha".

## Médio

8. **`comprovante.php` e `ticket.php` são arquivos idênticos.**
   Mesmo código byte a byte. `cadastro.php` redireciona para `comprovante.php`,
   `api/cadastrar_aluno.php` redireciona para `ticket.php`. Dois pontos de manutenção
   para a mesma tela — uma correção em um pode não ser replicada no outro.

9. **Dois fluxos de cadastro paralelos e divergentes.**
   `cadastro.php` (formulário direto na página, cria/atualiza sessão) e
   `api/cadastrar_aluno.php` (POST separado, usado por algum formulário não encontrado
   nos arquivos lidos — possivelmente vestígio de uma versão anterior da UI). O segundo
   não seta `tipo_participante` no INSERT (depende do `DEFAULT 'aluno'` do banco) e exige
   matrícula sempre, sem suportar público externo.

10. **`foto` vs `imagem`.**
    `index.php:118` — `$palestra['foto'] ?? $palestra['imagem'] ?? ''`. Não existe
    coluna `imagem` em nenhum INSERT/CREATE; é um fallback morto que nunca será usado.

11. **Upload de imagem valida só a extensão do nome do arquivo.**
    `admin/cadastrar-palestra.php:27-30` — checa `pathinfo(...)['extension']` mas não o
    tipo MIME real nem o conteúdo. Um arquivo `.php` renomeado para `.jpg` seria aceito
    (embora não seja executável como PHP dentro de `uploads/` a menos que o servidor
    esteja mal configurado para isso — ainda assim, vale validar o conteúdo).

## Baixo / cosmético

12. `display_errors`/`display_startup_errors` ligados em `config/database.php`, com o
    comentário "durante os testes" — mas é o arquivo usado em produção também.
13. Não há `.gitignore` nem repositório git inicializado — nenhum histórico de mudanças.
14. Não há `README.md` na raiz do projeto explicando como rodar/implantar.
15. `inscricoes.palestra_id` é `ON DELETE CASCADE` (confirmado no dump real) — apagar
    uma palestra apaga silenciosamente todas as inscrições dela, sem aviso. Hoje não há
    nenhuma tela de admin para apagar palestra, então é inofensivo por enquanto, mas
    vale lembrar disso se essa funcionalidade for adicionada no futuro (pedir
    confirmação explícita, ou trocar para `ON DELETE RESTRICT`/soft delete).
16. `admin/confirmar-presenca.php` parece ser uma rota antiga (fluxo por link/GET com
    `?code=`) não referenciada por nenhum outro arquivo lido — possível código morto,
    mas mantenha até confirmar que nenhum e-mail/QR antigo ainda aponta pra ela.
