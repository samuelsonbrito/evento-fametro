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

   A correção original (mesmo dia) fez `admin/login.php` migrar a senha de forma
   transparente no próximo login válido (detecta MD5/texto puro, confere, e já
   regrava com `password_hash()`). Essa versão intermediária ficou obsoleta ainda no
   mesmo dia por causa do incidente de produção descrito na seção "Incidentes" no fim
   deste arquivo: a tabela `administradores` acabou sendo recriada do zero, então não
   havia mais nenhuma conta com MD5 real pra migrar — só fazia sentido zerar a senha
   via script e remover o fallback de vez.

   **Estado final:** `admin/login.php` não tem mais fallback nenhum pra MD5/texto
   puro — só `password_verify()`, com `PASSWORD_BCRYPT` custo 12, mais
   `password_needs_rehash()` pra regravar automaticamente se o custo for aumentado no
   futuro. A senha de produção foi redefinida via
   `harness/db/historico/incidente-2026-09-22-saneamento-producao.sql` +
   `harness/tools/hash-password.php`.

2b. **[CORRIGIDO em 2026-09-22] IDOR em `comprovante.php`/`ticket.php` — dados de
   qualquer inscrito expostos por ID sequencial.**
   As duas páginas aceitavam `?id=N` sem nenhuma checagem de posse e devolviam nome,
   e-mail, matrícula e o `codigo_qrcode` completo de quem quer que fosse o dono daquele
   ID. Bastava trocar o número na URL (`?id=1`, `?id=2`, `?id=3`...) pra enumerar todos
   os inscritos do evento. Corrigido: as duas páginas passaram a buscar por
   `?codigo=` usando a coluna `codigo_qrcode` (já `UNIQUE` no banco), que não é
   sequencial nem adivinhável. Os três pontos que geravam esses links
   (`cadastro.php` x2, `api/cadastrar_aluno.php`) foram atualizados pra redirecionar
   por `codigo` em vez de `id`.

2c. **[CORRIGIDO em 2026-09-22] `api/validar_presenca.php` e
   `admin/confirmar-presenca.php` confirmavam presença sem nenhuma autenticação.**
   A tela `admin/validar-qrcode.php` é protegida por `checarAutenticacaoAdmin()`, mas
   a API que ela chama por trás (`api/validar_presenca.php`) não checava sessão
   nenhuma — qualquer requisição POST direta com um `codigo_qrcode` válido confirmava
   presença. Como esse código aparece em texto puro na própria página do comprovante
   do participante, isso permitia a um aluno confirmar a própria presença remotamente,
   sem estar fisicamente no evento — quebrando o propósito do credenciamento por QR
   Code. `admin/confirmar-presenca.php` (ver item 16) tinha o mesmo problema, ainda
   mais grave por estar dentro da pasta `admin/` sem seguir o padrão de todo o resto
   dela. Corrigido: `api/validar_presenca.php` agora retorna `401` em JSON se
   `$_SESSION['admin_logged']` não estiver definido, e `admin/confirmar-presenca.php`
   ganhou a chamada `checarAutenticacaoAdmin()` que faltava.

3. **[CORRIGIDO em 2026-09-23] Mensagens de exceção do banco expostas ao usuário
   final.** `cadastro.php`, `admin/cadastrar-palestra.php`, `api/cadastrar_aluno.php`,
   `api/validar_presenca.php` e `config/database.php` (erro de conexão) faziam
   `"Erro ao processar: " . $e->getMessage()` direto na tela — vazava estrutura de
   tabela, nomes de coluna, às vezes fragmento da query. Corrigido: mensagem genérica
   pro usuário em todos, com `error_log($e->getMessage())` registrando o erro real
   só no servidor. Confirmado no harness disparando um erro de verdade (duplicidade
   de `uk_aluno_palestra`) — tela mostra mensagem genérica, log do container mostra o
   `SQLSTATE` completo.

## Alto

4. **Duas colunas de presença gravadas de forma inconsistente.**
   `admin/confirmar-presenca.php:28` grava só `presenca_confirmada`.
   `api/validar_presenca.php:76` grava `presenca_confirmada` **e** `presente` **e**
   `data_presenca`. As leituras (`admin/inscritos.php:100`,
   `admin/imprimir-comprovante.php:26`, `admin/imprimir-comprovantes-lote.php:25`)
   verificam `presenca_confirmada OR presente`, então hoje não quebra — mas é fácil
   introduzir um bug futuro esquecendo de atualizar as duas colunas em um novo fluxo.
   Vale unificar em uma coluna só.

5. **[CORRIGIDO em 2026-09-23] Sem proteção CSRF em nenhum formulário.**
   `cadastro.php`, `admin/login.php`, `admin/cadastrar-palestra.php` — formulários POST
   sem token, um site malicioso poderia submeter em nome de uma sessão de admin já
   autenticada. Corrigido: `gerarTokenCSRF()`/`validarTokenCSRF()` em
   `includes/functions.php` (token de 32 bytes aleatórios por sessão, comparado com
   `hash_equals()`); os 3 formulários ganharam um campo oculto `csrf_token`, validado
   no início do bloco POST de cada um — rejeitando com mensagem clara antes de tocar
   em qualquer dado (inclusive antes de processar o upload de foto, em
   `admin/cadastrar-palestra.php`). Testado no harness: os 3 formulários funcionam
   normalmente com token certo, e são rejeitados sem token ou com token forjado.
   Combinado com o `SameSite=Lax` do item 6b, cobre tanto CSRF cross-site quanto
   same-site-mas-forjado.

6. **[CORRIGIDO em 2026-09-23] Sem rate limiting no login admin.** `admin/login.php`
   não tinha limite de tentativas — força bruta de senha era viável. Corrigido: nova
   tabela `tentativas_login` (identificador = IP do cliente, priorizando
   `X-Forwarded-For` sobre `REMOTE_ADDR` — o site passa pelo proxy da Umbler);
   `estaLimitadoPorTentativas()`/`registrarTentativaFalha()`/`limparTentativas()` em
   `includes/functions.php`. Bloqueia depois de 5 tentativas erradas em 15 minutos,
   mesmo que a próxima tentativa use a senha certa (só destrava depois que a janela
   passa, ou fica valendo de novo a partir da tentativa seguinte). Login bem-sucedido
   limpa o contador. Testado no harness: 6ª tentativa errada bloqueia; janela
   simulada como expirada libera de novo e reseta a tabela no login certo.
   `api/validar_presenca.php` já exige sessão de admin desde o item 2c, então fica
   coberto indiretamente pelo mesmo rate limiting do login — não precisou de limite
   próprio.

   **Ressalva**: limitar por IP depende de identificar corretamente o IP real do
   visitante atrás do proxy da Umbler. Vale conferir depois do deploy que tentativas
   de pessoas diferentes não estão sendo agrupadas por engano (o que bloquearia
   gente de verdade junto com um possível atacante) — checar se
   `X-Forwarded-For` chega populado de verdade em produção.

6b. **[CORRIGIDO em 2026-09-23] Cookie de sessão sem `Secure`/`HttpOnly`/`SameSite`.**
   Adicionada `iniciarSessaoSegura()` em `includes/functions.php`, chamada antes de
   qualquer `session_start()` (substituindo as 3 chamadas cruas que existiam em
   `includes/functions.php`, `includes/header.php` e `admin/logout.php`). Define
   `HttpOnly` (sempre), `SameSite=Lax` (sempre) e `Secure` quando a conexão é HTTPS —
   detectado via `$_SERVER['HTTPS']` **ou** `X-Forwarded-Proto: https` **ou** porta
   443, porque o site passa pelo proxy da Umbler em produção e `$_SERVER['HTTPS']`
   sozinho pode não refletir o que o navegador do visitante usou de verdade.
   Confirmado no harness simulando o header do proxy.

6c. **[CORRIGIDO em 2026-09-23] Nenhum header de segurança.** `includes/functions.php`
   agora emite `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
   `Referrer-Policy: strict-origin-when-cross-origin` e uma
   `Content-Security-Policy-Report-Only` em toda página (inclusive as APIs). A CSP
   está deliberadamente em **report-only**, não enforced: o app carrega recursos de
   `cdn.jsdelivr.net`, `unpkg.com`, `quickchart.io`, `actions.google.com`, e usa
   bastante estilo/script inline — enforcar sem calibrar direito quebraria o site
   silenciosamente. Trocar pra `Content-Security-Policy` de verdade depois de um
   tempo monitorando violações é um passo manual futuro, não feito aqui.

7. **[CORRIGIDO em 2026-09-22] `api/validar_presenca.php` aceitava `i.id` como código
   válido.** A query casava `codigo_qrcode` OU `i.id`, então digitar um ID pequeno
   (`1`, `2`, `3`...) no campo "código manual" do validador confirmava presença de
   qualquer inscrição, sem precisar do QR Code real. Corrigido junto com o item 2b —
   a query só casa mais `codigo_qrcode` (exato ou case-insensitive), nunca `id`.

7d. **[CORRIGIDO em 2026-09-23] Duas cópias do site respondendo simultaneamente em
   produção** (`https://eventofametro.com.br/` e
   `https://eventofametro.com.br/evento-fametro/`), com código diferente em cada uma.
   Descoberto durante o trabalho de SEO ao testar `curl` contra o domínio real.
   Investigado via SSH (servidor Umbler): a pasta `/evento-fametro/` era uma cópia
   antiga sobrando de um deploy manual anterior — removida pelo usuário diretamente no
   servidor. A cópia real e única passou a ser a da raiz (`/home/defaultwebsite/public`,
   servindo `https://eventofametro.com.br/`).

7e. **[CORRIGIDO em 2026-09-23] Consequência do item 7d: todo link interno do app é
   absoluto com prefixo fixo `/evento-fametro/...`, mas a produção real serve da raiz
   do domínio.** Depois de resolver a duplicação (7d) e conectar o servidor ao Git (ver
   abaixo), ficou evidente que **isso quebrava a navegação de verdade**: CSS
   retornando 404, botão "Inscrever-se" de cada palestra levando a 404, login
   administrativo inacessível — confirmado ao vivo em `eventofametro.com.br` antes da
   correção. Causa: o app inteiro (51 ocorrências em 17 arquivos — `header()`,
   `href`, `src`, `action`, `fetch()`) hardcodava `/evento-fametro/` como prefixo de
   caminho, assumindo que o app rodava numa subpasta. Corrigido removendo o prefixo de
   todos os caminhos absolutos, pra bater com o deploy real (raiz do domínio). O
   harness Docker também foi ajustado (`harness/docker-compose.yml`): o volume do app
   agora monta direto em `/var/www/html` (raiz do Apache no container) em vez de
   `/var/www/html/evento-fametro`, pra local e produção terem a mesma estrutura de
   caminho e esse tipo de bug não passar despercebido de novo pelos smoke tests.

   **Conectando o servidor de produção ao Git**, nesta mesma sessão: o servidor Umbler
   (`/home/defaultwebsite/public`) tinha os arquivos do projeto mas nenhum histórico de
   Git. Adotado com `git init` + `git remote add origin` + `git fetch` +
   `git diff HEAD origin/main` (nunca `checkout -f`/`reset --hard` direto) pra revisar
   exatamente o que mudaria antes de aplicar — método seguro pra "adotar" uma pasta
   existente sem arriscar apagar dado de produção (`.env`, fotos de palestrante já
   enviadas). Um cuidado que vale registrar: o primeiro `git add -A` de captura do
   estado atual acabou incluindo o `.env` real (sem `.gitignore` na pasta ainda) —
   corrigido na hora (`git rm --cached .env` + `.gitignore` enviado) antes de qualquer
   commit ganhar um remote de verdade. Sempre confirmar que segredos não entraram no
   commit antes de considerar "pronto", mesmo em repositório que nunca vai receber
   push.

20. **[CORRIGIDO em 2026-09-23] XSS armazenado via URL `javascript:` no campo
    `pagina_url` do botão "Reportar problema".** `api/reportar_erro.php` grava `pagina_url` só com `sanitize()`
    (`htmlspecialchars`), que escapa `<`, `>`, `"`, `'` mas não bloqueia o
    esquema da URL. `admin/relatos-erro.php:115` usa esse valor direto num
    atributo `href` (`<a href="<?= htmlspecialchars($r['pagina_url']) ?>"
    target="_blank">`). Um atacante não precisa do site: o endpoint é público,
    só exige um `csrf_token` válido — obtido visitando qualquer página, sem
    login. Um POST direto pra `api/reportar_erro.php` com
    `pagina_url=javascript:alert(document.domain)` grava normalmente (nenhum
    caractere ali é escapado por `htmlspecialchars`), e o link malicioso fica
    esperando um administrador clicar em "Página" na lista de relatos — o que é
    um comportamento esperado dessa tela (conferir a página que a pessoa
    reportou). Como o cookie de sessão é `HttpOnly`, não dá pra ler
    `document.cookie` direto, mas o script roda com a origem do painel admin já
    autenticado — dá pra fazer requisições em nome do admin (inclusive extrair
    o `csrf_token` da página e disparar as próprias ações do painel, como
    excluir relatos ou marcar como resolvido).

    **Correção:** nova função `urlEhSegura()` em `includes/functions.php` (usa
    `parse_url()` e só aceita esquema `http`/`https`). Aplicada em dois pontos
    (defesa em profundidade): `api/reportar_erro.php` descarta (grava `NULL`)
    qualquer `pagina_url` que não passe na checagem, e `admin/relatos-erro.php`
    só renderiza como link clicável se a URL já salva passar na mesma checagem
    — senão mostra como texto simples. Testado no harness: POST com
    `pagina_url=javascript:alert(1)` é aceito (mensagem grava normalmente) mas
    a URL não vira link; POST com URL `https://` normal continua virando link.

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

21. **[CORRIGIDO em 2026-09-23] Sem rate limiting/anti-spam em
    `api/reportar_erro.php`.** O endpoint do botão "Reportar problema" não
    tinha nenhum limite de tentativas por IP/sessão — diferente do
    `admin/login.php` (item 6). O `csrf_token` é reaproveitado durante toda a
    sessão (por desenho, ver item 5), então um script com a mesma sessão
    podia enviar milhares de relatos em loop, sem precisar buscar um token
    novo a cada envio. Consequência: bloat da tabela `relatos_erro` e a tela
    `admin/relatos-erro.php` ficando inutilizável (relato de verdade se
    perdendo no meio de spam). **Correção:** reaproveitada a mesma
    infraestrutura do rate limiting do login (`estaLimitadoPorTentativas()`/
    `registrarTentativaFalha()`, tabela `tentativas_login`), com um
    identificador prefixado (`relato:<IP>`) pra não colidir com as tentativas
    de login do mesmo IP. Mesmo limite: 5 envios por IP a cada 15 minutos,
    contando toda submissão (não só falhas, já que o objetivo aqui é limitar
    volume). Testado no harness: 6º envio seguido retorna `429` com mensagem
    de limite.

## Baixo / cosmético

12. **[CORRIGIDO em 2026-09-23]** `display_errors`/`display_startup_errors` ligados
    incondicionalmente em `config/database.php` — mesmo arquivo usado em produção,
    então qualquer erro/warning não tratado mostrava caminho de arquivo, linha e
    stack trace pra qualquer visitante do site real. Corrigido: agora só liga se
    `APP_DEBUG=true` no `.env` (ausente por padrão — produção nunca define isso). O
    harness Docker continua mostrando erro normalmente pra desenvolvimento, porque
    usa `harness/config.local.php`, um arquivo totalmente separado que nunca passa
    por essa checagem.
13. **[CORRIGIDO/DESATUALIZADO]** Este item dizia "não há `.gitignore` nem
    repositório git inicializado" — hoje ambos existem e estão em uso normal desde
    2026-09-22 (ver `harness/GIT_WORKFLOW.md`). Mantido só por histórico.
14. **[CORRIGIDO em 2026-09-22]** Não havia `README.md` na raiz do projeto explicando
    como rodar/implantar. Adicionado `README.md` cobrindo funcionalidades, stack,
    modelo de dados, fluxo da aplicação, como rodar o harness local, configuração via
    `.env`, testes, segurança e deploy.
15. `inscricoes.palestra_id` é `ON DELETE CASCADE` (confirmado no dump real) — apagar
    uma palestra apaga silenciosamente todas as inscrições dela, sem aviso. Hoje não há
    nenhuma tela de admin para apagar palestra, então é inofensivo por enquanto, mas
    vale lembrar disso se essa funcionalidade for adicionada no futuro (pedir
    confirmação explícita, ou trocar para `ON DELETE RESTRICT`/soft delete).
16. **[CORRIGIDO/RECLASSIFICADO em 2026-09-22]** `admin/confirmar-presenca.php` era
    descrito aqui como "possível código morto" por não ser referenciada por nenhum
    outro arquivo — mas isso subestimava o risco: é um `.php` publicamente acessível
    num repositório público, referenciada ou não. Era, na prática, o único arquivo em
    `admin/` sem `checarAutenticacaoAdmin()`, permitindo confirmar presença de
    qualquer inscrição sem login (ver item 2c, onde foi corrigida). Mantida no
    projeto — não é código morto, é uma rota alternativa por link (`?code=`) agora
    devidamente protegida.

17. **[CORRIGIDO em 2026-09-22] Linha em branco antes de `<?php` quebrava
    `header()`/redirects em 12 arquivos.** `index.php`, `cadastro.php`,
    `comprovante.php`, `ticket.php`, `api/validar_presenca.php` e mais 7 em `admin/`
    tinham uma linha em branco antes da tag de abertura. Com `output_buffering`
    desligado (como no harness Docker), isso gera saída antes de qualquer
    `header()`, que passa a falhar silenciosamente ("headers already sent") — o
    `exit;` logo depois ainda impedia vazamento de dados, mas o redirect de verdade
    nunca saía, só um warning feio. Não achamos evidência de que isso afete a
    produção (hosts compartilhados costumam ligar `output_buffering` por padrão,
    o que mascararia o problema), mas foi corrigido em todos os 12 arquivos por
    segurança e para os redirects funcionarem de forma confiável em qualquer
    ambiente.

18. **Mojibake (encoding duplo) no texto de `titulo`/`descricao` das palestras.**
    Confirmado durante o trabalho de SEO (2026-09-22): títulos com acento aparecem
    como `"IA AgÃªntica"` em vez de `"IA Agêntica"` — no card da home (`index.php`,
    código não relacionado ao SEO) e em todo lugar que exibe esses campos. Os dados
    parecem já estar salvos assim no banco (não é um bug de exibição pontual). Causa
    provável: o texto foi inserido em algum momento com um mismatch de charset entre
    o cliente (formulário/import) e a conexão MySQL — `config/database.php` já usa
    `charset=utf8mb4` na conexão PDO, então não é isso. Precisa investigar a origem
    real dos dados (import manual? cadastro pelo formulário com charset errado?) antes
    de decidir a correção (reinserir os dados certos vs. escrever um script de
    conversão UTF-8→Latin1→UTF-8 nos valores já salvos).

19. **`admin/confirmar-presenca.php` muda estado via GET (`?code=`), CSRF-ável.**
    Identificado durante o plano de correção do item 5 (CSRF nos formulários POST).
    Como essa rota confirma presença só com um `GET`, um `<img src="...">` ou link
    externo já dispara a ação com a sessão de um admin logado, sem precisar de
    formulário nenhum — token CSRF em campo de formulário não resolve isso (o
    ataque nem passa por um form). Corrigir direito significa trocar de GET pra
    POST, o que muda como essa rota é usada hoje (link direto). Não resolvido ainda
    — ver plano de correção dos itens de risco Alto.

22. **[CORRIGIDO em 2026-09-23] `json_encode()` sem `JSON_HEX_TAG` ao injetar
    dados dentro de `<script>` em `admin/estatisticas.php:186`.** Os títulos de
    palestra (`palestras.titulo`, só editável por um admin autenticado via
    `admin/cadastrar-palestra.php`) são jogados dentro de uma tag `<script>`
    via `json_encode(..., JSON_UNESCAPED_UNICODE)`. Por padrão, `json_encode()`
    não escapa `<`/`>`, então um título contendo `</script><script>...`
    quebraria pra fora da tag e executaria o que vier depois. Como só um admin
    já autenticado consegue cadastrar palestra, era self-XSS de baixo impacto
    (exigiria outro admin malicioso ou uma conta admin já comprometida por
    outro meio) — mas era uma lacuna de defesa em profundidade barata de
    fechar. **Correção:** adicionadas as flags `JSON_HEX_TAG | JSON_HEX_AMP` na
    chamada. **Retestado no harness** cadastrando uma palestra com título
    `</script><script>alert(1)</script>`: na prática o cenário descrito nunca
    era explorável, porque `admin/cadastrar-palestra.php:14` já passa `titulo`
    por `sanitize()` (`htmlspecialchars`) **antes** de gravar no banco — o
    valor salvo já vem como `&lt;/script&gt;...`, nunca com `<`/`>` crus. A
    correção com `JSON_HEX_TAG`/`JSON_HEX_AMP` continua valendo como defesa em
    profundidade (protege qualquer inserção futura que não passe por
    `sanitize()`, como um import em lote direto no banco), mas não havia
    exploit real hoje dado o comportamento atual de `cadastrar-palestra.php`.

23. **[CORRIGIDO em 2026-09-23] Link de `pagina_url` em
    `admin/relatos-erro.php:115` usava `target="_blank"` sem
    `rel="noopener noreferrer"`.** Mesmo corrigindo o item 20 (esquema
    `javascript:`), um link `http(s)://` legítimo aberto assim ainda dava à
    página de destino acesso a `window.opener`, permitindo redirecionar a aba
    original do admin (reverse tabnabbing). Corrigido junto com o item 20 —
    `rel="noopener noreferrer"` adicionado no mesmo `<a>`.

## Incidentes

### 2026-09-22 — perda de dados de produção por rodar `schema.sql`/`seed.sql` no banco real

**O que aconteceu:** `harness/db/schema.sql` (que começa com `DROP TABLE` nas três
tabelas) e `harness/db/seed.sql` foram executados no phpMyAdmin do banco de produção,
em vez de só no MySQL descartável do harness (Docker). Consequência:

- As inscrições reais de alunos feitas antes desse momento foram apagadas. Não havia
  backup — não foi possível recuperar.
- A tabela `administradores` foi recriada com o hash placeholder (inválido) do
  `admin` e com a conta de teste `harness_qa`/`harness123` em texto puro — essa
  segunda, documentada publicamente neste repositório, virou um login de admin de
  verdade acessível por qualquer pessoa até ser removida.
- A tabela `palestras` foi recriada com o conteúdo real (isso não foi perda, é dado
  público que já estava correto no `schema.sql`).

**Correção aplicada:**
`harness/db/historico/incidente-2026-09-22-saneamento-producao.sql` — remove `harness_qa`,
redefine a senha do `admin` com um hash gerado na hora, remove as inscrições de teste
(`QR-SEED%`) sem tocar em nenhuma inscrição real. Também aproveitado pra remover de vez
o fallback de MD5/texto puro em `admin/login.php` (ver item 2) — sem mais motivo pra
manter compatibilidade com hash antigo, já que a senha foi redefinida do zero.

**Causa raiz:** nada no próprio `schema.sql`/`seed.sql` deixava óbvio, só de olhar o
arquivo, que rodá-lo em produção seria destrutivo — a única pista era um comentário no
topo do arquivo, fácil de não notar antes de colar um SQL grande no phpMyAdmin.

**O que mudou pra não repetir:**
- `schema.sql` e `seed.sql` agora abrem com um bloco de aviso bem grande e visível,
  antes de qualquer outra coisa no arquivo.
- `db/historico/incidente-2026-09-22-saneamento-producao.sql` fica como modelo de como um script
  de correção de produção deve ser: sem `DROP TABLE`, com `DELETE`/`UPDATE` bem
  específicos (por `codigo_qrcode LIKE 'QR-SEED%'`, nunca por tabela inteira), e com
  consultas de verificação no final.
- **Ainda em aberto:** nenhuma automação impede fisicamente alguém de colar
  `schema.sql` no SQL do banco errado de novo — a defesa hoje é só o aviso no
  comentário. Se isso for repetido, vale considerar manter o banco de produção e o do
  harness em instâncias MySQL com usuários/credenciais completamente diferentes (o que
  já é o caso hoje, então o risco real é confusão de aba/janela do phpMyAdmin, não
  credencial compartilhada).

### 2026-09-23 — harness quebrava do zero por ordem alfabética do `docker-entrypoint-initdb.d`

**O que aconteceu:** `harness/db/incidente-2026-09-22-saneamento-producao.sql`
morava na mesma pasta (`harness/db/`) montada como
`/docker-entrypoint-initdb.d` no container do MySQL. O MySQL roda todo `.sql`
dessa pasta em ordem alfabética — `incidente...` vem antes de `schema.sql` — então,
num harness recriado do zero (`docker compose down -v` + `up`), o script de
saneamento tentava rodar `UPDATE administradores` antes de `schema.sql` criar a
tabela, o que dava erro e abortava a inicialização inteira do banco (nenhuma tabela
chegava a ser criada). Só não tinha aparecido antes porque os testes anteriores
reaproveitaram um volume de banco já inicializado.

**Correção aplicada:** o script de incidente foi movido pra
`harness/db/historico/`, fora da pasta auto-executada pelo MySQL — ele é de uso
único contra produção mesmo, nunca fazia sentido rodar num harness que já começa
vazio.
