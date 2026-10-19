<?php

/**
 * Regras puras (sem banco/HTTP) da conta de e-mail institucional por usuário.
 *
 * Nesta instalação (helpdesk-brooks) o super admin configura UMA VEZ o servidor
 * padrão de envio/recebimento (SMTP/IMAP: host, porta, criptografia) em
 * Configurações → Contas de E-mail → "Servidor Padrão". Cada usuário, na sua
 * própria tela "Minha Conta", informa apenas o e-mail institucional e a senha.
 *
 * Esta classe concentra, de forma testável:
 *  - validação do e-mail e da senha informados pelo usuário;
 *  - verificação de que o servidor padrão está configurado;
 *  - a MONTAGEM do payload de email_accounts, combinando os defaults do
 *    servidor (host/porta/criptografia) com o e-mail/senha do usuário.
 *
 * A persistência (criar/atualizar a linha em email_accounts e vincular o
 * usuário via email_account_users) fica no AccountController/EmailAccount; aqui
 * é só a decisão/montagem, que é a fonte única de verdade do formato da conta.
 */
class UserEmailRules
{
    public const VALID_SMTP_ENCRYPTION = ['tls', 'ssl', 'none'];
    public const VALID_IMAP_ENCRYPTION = ['ssl', 'tls', 'none'];

    /** E-mail informado é válido? */
    public static function isValidEmail(?string $email): bool
    {
        $e = trim((string) $email);
        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * A senha informada é aceitável? Exige pelo menos 1 caractere não-espaço.
     * (Senha de e-mail institucional pode ser qualquer coisa; só barramos vazio.)
     */
    public static function isValidPassword(?string $password): bool
    {
        return is_string($password) && trim($password) !== '';
    }

    /**
     * O servidor padrão de SMTP está configurado? Sem host de SMTP não há como
     * montar a conta do usuário — o super admin precisa preencher o "Servidor
     * Padrão" antes. (Porta/criptografia têm fallback; o host é obrigatório.)
     *
     * @param array $defaults settings prospection_* (host/port/encryption)
     */
    public static function isServerConfigured(array $defaults): bool
    {
        return trim((string) ($defaults['prospection_smtp_host'] ?? '')) !== '';
    }

    /**
     * Mensagem de erro para a primeira regra violada, ou null se tudo ok.
     * Centraliza as mensagens para controller e testes não divergirem.
     */
    public static function validationError(?string $email, ?string $password, array $defaults, bool $isNew): ?string
    {
        if (!self::isServerConfigured($defaults)) {
            return 'O servidor de e-mail padrão ainda não foi configurado. Fale com o administrador.';
        }
        if (!self::isValidEmail($email)) {
            return 'Informe um e-mail institucional válido.';
        }
        // Na criação a senha é obrigatória; na atualização pode ficar em branco
        // para manter a senha atual.
        if ($isNew && !self::isValidPassword($password)) {
            return 'Informe a senha do e-mail.';
        }
        if (!$isNew && $password !== null && $password !== '' && !self::isValidPassword($password)) {
            return 'A senha informada é inválida.';
        }
        return null;
    }

    /** Normaliza a criptografia SMTP para um valor válido do enum. */
    public static function normalizeSmtpEncryption(?string $enc): string
    {
        $e = strtolower(trim((string) $enc));
        return in_array($e, self::VALID_SMTP_ENCRYPTION, true) ? $e : 'tls';
    }

    /** Normaliza a criptografia IMAP para um valor válido do enum. */
    public static function normalizeImapEncryption(?string $enc): string
    {
        $e = strtolower(trim((string) $enc));
        return in_array($e, self::VALID_IMAP_ENCRYPTION, true) ? $e : 'ssl';
    }

    /**
     * Monta o payload de email_accounts para a conta pessoal do usuário,
     * combinando o servidor padrão (host/porta/criptografia) com o e-mail/senha
     * informados. O e-mail é usado também como smtp_username (login = e-mail,
     * padrão da maioria dos provedores institucionais).
     *
     * A senha NÃO é incluída quando $password é vazio (caso de atualização sem
     * troca de senha) — quem persiste decide manter a atual. Quando incluída,
     * vai em texto puro; a criptografia é responsabilidade do model
     * (EmailAccount::create/update), mantendo uma só porta de criptografia.
     *
     * @param array $defaults settings do servidor padrão (prospection_smtp_ e prospection_imap_)
     * @return array payload pronto para EmailAccount::create()/update()
     */
    public static function buildAccountPayload(string $email, ?string $password, string $displayName, array $defaults): array
    {
        $email = trim($email);

        $payload = [
            'email' => $email,
            'display_name' => trim($displayName) !== '' ? trim($displayName) : null,
            'smtp_host' => trim((string) ($defaults['prospection_smtp_host'] ?? '')),
            'smtp_port' => (int) ($defaults['prospection_smtp_port'] ?? 587),
            'smtp_encryption' => self::normalizeSmtpEncryption($defaults['prospection_smtp_encryption'] ?? 'tls'),
            'smtp_username' => $email,
            'imap_host' => trim((string) ($defaults['prospection_imap_host'] ?? '')) ?: null,
            'imap_port' => (int) ($defaults['prospection_imap_port'] ?? 993),
            'imap_encryption' => self::normalizeImapEncryption($defaults['prospection_imap_encryption'] ?? 'ssl'),
            'is_active' => 1,
        ];

        if (self::isValidPassword($password)) {
            $payload['smtp_password'] = $password;
        }

        return $payload;
    }
}
