-- ============================================================================
-- SANEAMENTO DE PRODUÇÃO — incidente de 2026-09-22
-- ============================================================================
-- O que aconteceu: harness/db/schema.sql (que começa com DROP TABLE) e
-- harness/db/seed.sql foram executados por engano contra o banco de PRODUÇÃO,
-- que já tinha inscrições reais de alunos. As tabelas foram recriadas vazias
-- (as inscrições reais anteriores foram perdidas — sem backup disponível) e
-- ficaram: (1) a conta administradores.admin com um hash placeholder inválido
-- (ninguém consegue logar), e (2) a conta de teste harness_qa/harness123, que
-- está documentada publicamente no GitHub — ou seja, um login de admin real
-- exposto publicamente até este script rodar.
--
-- Este arquivo é de USO ÚNICO para consertar esse estado específico. Diferente
-- de schema.sql, ele NÃO tem nenhum DROP TABLE — só ajusta linhas pontuais.
-- Depois de rodado com sucesso, pode ficar aqui só como registro histórico do
-- incidente; não precisa (e não deve) ser rodado de novo.
--
-- COMO USAR:
--   1. Gere um hash bcrypt de uma senha nova e forte (você já tem PHP no
--      servidor via SSH):
--        php harness/tools/hash-password.php "sua-senha-forte-aqui"
--   2. Copie o hash impresso (começa com "$2y$12$...") e cole no lugar de
--      SUBSTITUA_PELO_HASH_GERADO logo abaixo, substituindo o texto inteiro
--      entre aspas.
--   3. Cole o arquivo inteiro (já editado) na aba SQL do phpMyAdmin de
--      produção e execute.
--   4. Confira o resultado com as consultas de verificação no final.

-- 1. Remove a conta de teste que nunca deveria ter ido pra produção.
DELETE FROM administradores WHERE usuario = 'harness_qa';

-- 2. Define uma senha de administrador de verdade (o hash atual, todo em zeros,
--    é um placeholder que não corresponde a nenhuma senha real).
--    >>> TROQUE o valor abaixo pelo hash gerado no passo 1 do "COMO USAR". <<<
UPDATE administradores
SET senha = 'SUBSTITUA_PELO_HASH_GERADO'
WHERE usuario = 'admin';

-- 3. Remove as inscrições de teste que vieram do seed.sql do harness.
--    Identificadas pelo prefixo QR-SEED, que o sistema real nunca gera (os
--    códigos de verdade são "QR-" + uniqid() em hexadecimal, ou um hash MD5 em
--    hexadecimal — nunca contêm as letras S/E/D juntas nesse padrão). Isso não
--    apaga nenhuma inscrição real feita antes ou depois do incidente.
DELETE FROM inscricoes WHERE codigo_qrcode LIKE 'QR-SEED%';

-- ============================================================================
-- Verificação — rode isso depois e confira o resultado manualmente
-- ============================================================================

-- Deve retornar só a linha do 'admin', com um hash começando em "$2y$12$..."
-- (nunca mais "harness_qa" e nunca mais o hash zerado).
SELECT id, usuario, senha, criado_em FROM administradores;

-- Deve retornar 0 linhas (nenhuma inscrição de teste sobrando).
SELECT COUNT(*) AS inscricoes_de_teste_restantes
FROM inscricoes WHERE codigo_qrcode LIKE 'QR-SEED%';

-- Conferir quantas inscrições reais existem agora (esperado: só as que
-- aconteceram depois do incidente, já que as anteriores foram perdidas).
SELECT COUNT(*) AS total_inscricoes_atual FROM inscricoes;
