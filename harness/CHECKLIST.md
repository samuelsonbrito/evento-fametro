# Checklist manual de QA

Para o que `smoke-tests.sh` não cobre (upload de arquivo, leitura de câmera, layout de
impressão, e-mail). Rode isso antes de qualquer deploy pra produção. Marque com o
ambiente usado: `[ local ]` (harness Docker) ou `[ prod ]` (InfinityFree).

## Inscrição (aluno)

- [ ] Abrir `index.php`, ver as palestras na ordem correta de horário.
- [ ] Clicar em "Inscrever-se" numa palestra, escolher "Aluno FAMETRO", preencher
      matrícula + nome + e-mail, confirmar.
- [ ] Ver o comprovante com QR Code, nome, matrícula corretos.
- [ ] Tentar se inscrever de novo com o mesmo e-mail na mesma palestra → deve levar
      direto ao comprovante existente, sem duplicar registro.
- [ ] Tentar se inscrever em duas palestras com horário sobreposto, mesma matrícula →
      deve bloquear com mensagem de conflito de horário.

## Inscrição (público externo)

- [ ] Escolher "Público Externo" no formulário → campo de matrícula deve sumir e não
      ser obrigatório.
- [ ] Confirmar inscrição e ver o comprovante mostrando o badge "Público Externo" sem
      linha de matrícula.

## Credenciamento (QR Code)

- [ ] Logar como admin, abrir "Ler QR Code".
- [ ] Permitir acesso à câmera do navegador e escanear um QR Code de um comprovante
      gerado no passo acima → deve mostrar "AUTENTICADA" com nome do participante.
- [ ] Escanear o mesmo QR Code de novo → deve avisar que já foi confirmado, sem travar.
- [ ] Testar a validação manual (campo de texto) colando o código do comprovante.
- [ ] Testar a validação manual com um código inválido → deve mostrar "NÃO AUTENTICADA".

## Painel administrativo

- [ ] Login com usuário/senha corretos → entra no painel.
- [ ] Login com senha errada → mensagem de erro, sem detalhe técnico vazando.
- [ ] Ver contadores do painel (palestras, inscrições, presenças) batendo com o banco.
- [ ] Cadastrar nova palestra com upload de foto do palestrante → foto aparece
      corretamente no card da home.
- [ ] Cadastrar palestra sem foto → home mostra o ícone de avatar padrão, sem quebrar.
- [ ] Ver lista de inscritos, filtrar por palestra.
- [ ] Emitir declaração individual de um inscrito com presença confirmada.
- [ ] Tentar emitir declaração de alguém SEM presença confirmada → deve bloquear.
- [ ] Emitir declarações em lote de uma palestra com vários confirmados → uma
      declaração por página ao imprimir/exportar PDF.
- [ ] Logout → sessão encerra e acesso a `/admin/index.php` redireciona pro login.

## Layout / impressão

- [ ] Testar o comprovante e a declaração em `Ctrl+P` (preview de impressão) — layout
      não deve cortar conteúdo nem duplicar página em branco.
- [ ] Testar em mobile (largura ~375px): cards da home, formulário de cadastro e leitor
      de QR Code continuam usáveis.

## Depois de rodar contra o ambiente local do harness

- [ ] Resetar o banco local (`docker compose down -v && docker compose up -d` dentro de
      `harness/`) e reaplicar `db/seed.sql` antes de repetir os testes, para não
      arrastar estado de uma rodada pra outra.
