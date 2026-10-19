-- =====================================================================
-- 164_add_equipe_role.sql
-- ---------------------------------------------------------------------
-- Novo papel "equipe": acesso enxuto a Dashboard, Agenda, Gravações,
-- Enviar E-mail (prospecção), Caixa de Entrada, Notificações e Minha Conta.
--
-- Estende o enum de users.role mantendo todos os papéis já existentes.
-- Idempotente na prática (reexecutar apenas reafirma o mesmo enum).
--
-- Execute manualmente no MySQL do ambiente alvo (ex.: USE helpdesk_brooks;).
-- =====================================================================

ALTER TABLE users
    MODIFY COLUMN role ENUM(
        'super_admin',
        'attendant',
        'client',
        'whatsapp_agent',
        'developer',
        'analyst',
        'comercial',
        'marketing',
        'equipe'
    ) NOT NULL DEFAULT 'client';
