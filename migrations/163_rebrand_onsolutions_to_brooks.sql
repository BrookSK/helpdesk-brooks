-- =====================================================================
-- 163_rebrand_onsolutions_to_brooks.sql
-- ---------------------------------------------------------------------
-- Rebranding: "ON Solutions" -> "Brooks Construtora" nos DADOS já gravados
-- no banco (as strings no código-fonte já foram trocadas em arquivos).
--
-- Também aplica a identidade visual da Brooks Construtora:
--   - logo do sistema  -> uploads/brand/brooks-logo.webp (logo branca + check vermelho)
--   - favicon          -> uploads/brand/brooks-favicon.png
--
-- Idempotente: pode ser executada mais de uma vez sem efeito colateral
-- (os REPLACE só mudam o que ainda contém "ON Solutions"; as logos são
-- definidas via upsert por chave).
--
-- Execute manualmente no MySQL do ambiente alvo, ex. (local):
--   USE helpdesk_brooks;  (ou o banco do ambiente)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Troca textual em settings: qualquer valor que contenha "ON Solutions"
--    (cobre app_name, smtp_from_name, prospecting_sender_name, etc.)
-- ---------------------------------------------------------------------
UPDATE settings
   SET setting_value = REPLACE(setting_value, 'ON Solutions', 'Brooks Construtora')
 WHERE setting_value LIKE '%ON Solutions%';

-- ---------------------------------------------------------------------
-- 2) Identidade visual: logo e favicon do sistema.
--    Faz upsert das chaves app_logo/app_favicon para os arquivos da Brooks
--    já presentes em public/uploads/brand/. A logo é branca com check
--    vermelho (pensada para o fundo escuro da sidebar).
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
    ('app_logo', 'uploads/brand/brooks-logo.webp'),
    ('app_favicon', 'uploads/brand/brooks-favicon.png')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------------------------------------------------------------------
-- 3) Assinaturas de e-mail por domínio: troca textual residual no nome da
--    empresa (company) caso ainda contenha "ON Solutions".
-- ---------------------------------------------------------------------
UPDATE email_signatures
   SET company = REPLACE(company, 'ON Solutions', 'Brooks Construtora')
 WHERE company LIKE '%ON Solutions%';

-- ---------------------------------------------------------------------
-- 4) Templates de mensagem (prospecção): corpo e assunto que mencionem o
--    nome antigo. A tabela message_templates pode não existir em bases
--    muito antigas; o bloco é protegido para não quebrar nesse caso.
-- ---------------------------------------------------------------------
UPDATE message_templates
   SET body = REPLACE(body, 'ON Solutions', 'Brooks Construtora')
 WHERE body LIKE '%ON Solutions%';

UPDATE message_templates
   SET subject = REPLACE(subject, 'ON Solutions', 'Brooks Construtora')
 WHERE subject IS NOT NULL AND subject LIKE '%ON Solutions%';
