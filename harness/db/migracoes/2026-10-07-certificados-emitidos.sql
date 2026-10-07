-- Migração de PRODUÇÃO — seguro de rodar no banco real: só cria uma tabela nova,
-- não apaga nem altera nada que já existe (IF NOT EXISTS, sem DROP).
--
-- Rodar uma vez no phpMyAdmin da hospedagem (aba SQL), antes ou junto do deploy das
-- estatísticas de certificado. Sem esta tabela o site continua funcionando (o
-- certificado abre normalmente); só a contagem de emitidos fica zerada no admin.
--
-- Uma linha por pessoa (matrícula, ou e-mail para público externo): guarda quando ela
-- abriu o certificado pela primeira vez e quantas vezes abriu.

CREATE TABLE IF NOT EXISTS certificados_emitidos (
  id int(11) NOT NULL AUTO_INCREMENT,
  chave_pessoa varchar(200) NOT NULL,
  codigo varchar(40) NOT NULL,
  primeira_emissao timestamp NOT NULL DEFAULT current_timestamp(),
  ultima_visualizacao timestamp NOT NULL DEFAULT current_timestamp(),
  visualizacoes int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY chave_pessoa (chave_pessoa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
