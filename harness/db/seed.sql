-- ============================================================================
-- ⚠️  NUNCA RODE ESTE ARQUIVO CONTRA UM BANCO DE PRODUÇÃO.  ⚠️
-- Isso já aconteceu por engano em 2026-09-22 e colocou uma conta de admin com
-- senha em texto puro conhecida publicamente (harness_qa/harness123) e
-- inscrições falsas no banco real — ver
-- harness/db/historico/incidente-2026-09-22-saneamento-producao.sql. Este arquivo é só
-- pro banco descartável do harness local (harness/docker-compose.yml).
-- ============================================================================
--
-- Dados de teste para o harness local.
-- `administradores` e `palestras` já vêm populados pelo schema.sql (é o dump real de
-- produção — id de admin=1 usuário 'admin', 9 palestras reais). Este arquivo só
-- acrescenta inscrições de exemplo, respeitando a UNIQUE KEY uk_aluno_palestra
-- (matricula, palestra_id) que existe de verdade no banco.
--
-- IMPORTANTE sobre login no harness: desde a correção do incidente de 2026-09-22,
-- admin/login.php só aceita password_verify() — não existe mais fallback pra MD5 nem
-- texto puro, nem aqui no harness nem em produção. Isso significa que nenhuma senha
-- "de fábrica" funciona sem gerar um hash de verdade primeiro. O hash do usuário
-- `admin` em schema.sql é um placeholder inválido de propósito (ver comentário lá).
--
-- Pra conseguir logar no harness local, depois de rodar este seed.sql:
--   1. php harness/tools/hash-password.php "sua-senha-de-teste"
--   2. UPDATE administradores SET senha = '<hash gerado>' WHERE usuario = 'harness_qa';
--      (rodar isso no phpMyAdmin do harness, localhost:8081)
INSERT INTO administradores (usuario, senha) VALUES
    -- Placeholder inválido de propósito — sem hash real, ninguém loga com isto.
    -- Gere um hash de verdade com harness/tools/hash-password.php (ver acima).
    ('harness_qa', 'SEM_SENHA_DEFINIDA_VER_INSTRUCOES_ACIMA');

INSERT INTO inscricoes (nome_aluno, matricula, email, palestra_id, codigo_qrcode, presenca_confirmada, tipo_participante, presente, data_presenca) VALUES
    -- Aluno com presença já confirmada (testa admin/imprimir-comprovante.php e o botão "Declaração")
    ('Ana Beatriz Lima', '202310123', 'ana.lima@exemplo.com', 1, 'QR-SEEDCONFIRMADO01', 1, 'aluno', 1, NOW()),
    -- Aluno pendente na MESMA palestra, matrícula diferente (testa admin/validar-qrcode.php e api/validar_presenca.php)
    ('Bruno Costa', '202310456', 'bruno.costa@exemplo.com', 1, 'QR-SEEDPENDENTE01', 0, 'aluno', 0, NULL),
    -- Mesma matrícula do Bruno, agora inscrito na palestra 2 (matricula+palestra_id
    -- diferente de (202310456,1), então a constraint não bloqueia — cobre o caso comum
    -- de um aluno se inscrever em várias palestras do evento).
    ('Bruno Costa', '202310456', 'bruno.costa@exemplo.com', 2, 'QR-SEEDPENDENTE02', 0, 'aluno', 0, NULL),
    -- Público externo, sem matrícula — vários externos podem ter matricula NULL na
    -- mesma palestra porque o MySQL não considera NULL=NULL em UNIQUE KEY.
    ('Carla Mendes', NULL, 'carla.mendes@exemplo.com', 2, 'QR-SEEDEXTERNO01', 0, 'externo', 0, NULL),
    -- Certificado com os 3 turnos (15h): presença em 2 palestras da manhã (1, 2), 1 da
    -- tarde (4) e 1 da noite (7) — testa que 2 palestras no mesmo turno não somam 10h.
    ('Diego Ramos', '202310789', 'diego.ramos@exemplo.com', 1, 'QR-SEEDCERT15H01', 1, 'aluno', 1, NOW()),
    ('Diego Ramos', '202310789', 'diego.ramos@exemplo.com', 2, 'QR-SEEDCERT15H02', 1, 'aluno', 1, NOW()),
    ('Diego Ramos', '202310789', 'diego.ramos@exemplo.com', 4, 'QR-SEEDCERT15H04', 1, 'aluno', 1, NOW()),
    ('Diego Ramos', '202310789', 'diego.ramos@exemplo.com', 7, 'QR-SEEDCERT15H07', 1, 'aluno', 1, NOW()),
    -- Inscrito mas faltou numa delas: só a palestra 5 (tarde) conta — certificado de 5h.
    ('Eduarda Souza', NULL, 'eduarda.souza@exemplo.com', 5, 'QR-SEEDEXTPRES05', 1, 'externo', 1, NOW()),
    ('Eduarda Souza', NULL, 'eduarda.souza@exemplo.com', 8, 'QR-SEEDEXTFALT08', 0, 'externo', 0, NULL);

-- Diego já abriu o certificado 3 vezes (estatísticas: 1 emitido de 3 com direito).
INSERT INTO certificados_emitidos (chave_pessoa, codigo, primeira_emissao, ultima_visualizacao, visualizacoes) VALUES
    ('M:202310789', 'IMIA-SEED', NOW() - INTERVAL 1 DAY, NOW(), 3);

-- Certificados esperados (ver smoke-tests.sh):
--   ana.lima@exemplo.com / 202310123  -> 5h  (manhã)
--   202310789 / diego.ramos@...       -> 15h (manhã, tarde, noite)
--   eduarda.souza@exemplo.com         -> 5h  (tarde), público externo, sem matrícula
--   bruno.costa@... / carla.mendes@... -> sem certificado (nenhuma presença)

-- Nota sobre teste de conflito de horário (verificarConflitoHorario em
-- includes/functions.php): a grade real (palestras 1-9) não tem horários sobrepostos
-- entre si (é sequencial), só adjacentes — ex.: palestra 1 termina 09:35:00 e a 2 começa
-- exatamente 09:35:00, o que NÃO deve contar como conflito (checar isso é um bom teste
-- de regressão). Pra testar um conflito de verdade, insira temporariamente uma palestra
-- extra com horário sobreposto ao de outra já existente, só no banco local do harness.
