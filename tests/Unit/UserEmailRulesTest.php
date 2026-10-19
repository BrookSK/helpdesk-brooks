<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use UserEmailRules;

/**
 * Testes unitários das regras puras da conta de e-mail institucional por
 * usuário. Cobre validação de e-mail/senha, verificação do servidor padrão,
 * normalização de criptografia e a montagem do payload de email_accounts a
 * partir dos defaults do servidor.
 */
final class UserEmailRulesTest extends TestCase
{
    /** Defaults válidos de servidor padrão (como salvos em settings). */
    private function defaults(array $overrides = []): array
    {
        return array_merge([
            'prospection_smtp_host' => 'smtp.empresa.com.br',
            'prospection_smtp_port' => '587',
            'prospection_smtp_encryption' => 'tls',
            'prospection_imap_host' => 'imap.empresa.com.br',
            'prospection_imap_port' => '993',
            'prospection_imap_encryption' => 'ssl',
        ], $overrides);
    }

    // ===== Validação de e-mail =====

    public function testIsValidEmail(): void
    {
        $this->assertTrue(UserEmailRules::isValidEmail('joao@empresa.com.br'));
        $this->assertFalse(UserEmailRules::isValidEmail('joao@'));
        $this->assertFalse(UserEmailRules::isValidEmail('sem-arroba'));
        $this->assertFalse(UserEmailRules::isValidEmail(''));
        $this->assertFalse(UserEmailRules::isValidEmail(null));
        $this->assertFalse(UserEmailRules::isValidEmail('   '));
    }

    // ===== Validação de senha =====

    public function testIsValidPassword(): void
    {
        $this->assertTrue(UserEmailRules::isValidPassword('x'));
        $this->assertTrue(UserEmailRules::isValidPassword('SenhaForte!123'));
        $this->assertFalse(UserEmailRules::isValidPassword(''));
        $this->assertFalse(UserEmailRules::isValidPassword('   '));
        $this->assertFalse(UserEmailRules::isValidPassword(null));
    }

    // ===== Servidor configurado =====

    public function testIsServerConfigured(): void
    {
        $this->assertTrue(UserEmailRules::isServerConfigured($this->defaults()));
        $this->assertFalse(UserEmailRules::isServerConfigured(['prospection_smtp_host' => '']));
        $this->assertFalse(UserEmailRules::isServerConfigured(['prospection_smtp_host' => '   ']));
        $this->assertFalse(UserEmailRules::isServerConfigured([]));
    }

    // ===== Regra de validação consolidada =====

    public function testValidationErrorServidorNaoConfigurado(): void
    {
        $err = UserEmailRules::validationError('joao@empresa.com.br', 'senha', ['prospection_smtp_host' => ''], true);
        $this->assertNotNull($err);
        $this->assertStringContainsString('servidor', mb_strtolower($err));
    }

    public function testValidationErrorEmailInvalido(): void
    {
        $err = UserEmailRules::validationError('invalido', 'senha', $this->defaults(), true);
        $this->assertNotNull($err);
        $this->assertStringContainsString('e-mail', mb_strtolower($err));
    }

    public function testValidationErrorSenhaObrigatoriaNaCriacao(): void
    {
        $err = UserEmailRules::validationError('joao@empresa.com.br', '', $this->defaults(), true);
        $this->assertNotNull($err);
        $this->assertStringContainsString('senha', mb_strtolower($err));
    }

    public function testValidationOkNaCriacao(): void
    {
        $this->assertNull(UserEmailRules::validationError('joao@empresa.com.br', 'senha', $this->defaults(), true));
    }

    public function testValidationSenhaOpcionalNaAtualizacao(): void
    {
        // Em atualização ($isNew = false) a senha em branco é aceita (mantém a atual).
        $this->assertNull(UserEmailRules::validationError('joao@empresa.com.br', '', $this->defaults(), false));
        $this->assertNull(UserEmailRules::validationError('joao@empresa.com.br', null, $this->defaults(), false));
    }

    public function testValidationAtualizacaoAindaExigeEmailValido(): void
    {
        $err = UserEmailRules::validationError('invalido', '', $this->defaults(), false);
        $this->assertNotNull($err);
    }

    // ===== Normalização de criptografia =====

    public function testNormalizeSmtpEncryption(): void
    {
        $this->assertSame('tls', UserEmailRules::normalizeSmtpEncryption('tls'));
        $this->assertSame('ssl', UserEmailRules::normalizeSmtpEncryption('SSL'));
        $this->assertSame('none', UserEmailRules::normalizeSmtpEncryption('none'));
        // Valor inválido cai no padrão tls.
        $this->assertSame('tls', UserEmailRules::normalizeSmtpEncryption('xpto'));
        $this->assertSame('tls', UserEmailRules::normalizeSmtpEncryption(null));
    }

    public function testNormalizeImapEncryption(): void
    {
        $this->assertSame('ssl', UserEmailRules::normalizeImapEncryption('ssl'));
        $this->assertSame('tls', UserEmailRules::normalizeImapEncryption('TLS'));
        $this->assertSame('none', UserEmailRules::normalizeImapEncryption('none'));
        // Valor inválido cai no padrão ssl.
        $this->assertSame('ssl', UserEmailRules::normalizeImapEncryption('xpto'));
        $this->assertSame('ssl', UserEmailRules::normalizeImapEncryption(null));
    }

    // ===== Montagem do payload =====

    public function testBuildAccountPayloadUsaServidorPadraoEEmailComoUsername(): void
    {
        $payload = UserEmailRules::buildAccountPayload('joao@empresa.com.br', 'segredo', 'João Comercial', $this->defaults());

        $this->assertSame('joao@empresa.com.br', $payload['email']);
        $this->assertSame('joao@empresa.com.br', $payload['smtp_username'], 'login deve ser o próprio e-mail');
        $this->assertSame('João Comercial', $payload['display_name']);
        $this->assertSame('smtp.empresa.com.br', $payload['smtp_host']);
        $this->assertSame(587, $payload['smtp_port']);
        $this->assertSame('tls', $payload['smtp_encryption']);
        $this->assertSame('imap.empresa.com.br', $payload['imap_host']);
        $this->assertSame(993, $payload['imap_port']);
        $this->assertSame('ssl', $payload['imap_encryption']);
        $this->assertSame(1, $payload['is_active']);
        // Senha vai em texto puro (model criptografa); presente quando informada.
        $this->assertArrayHasKey('smtp_password', $payload);
        $this->assertSame('segredo', $payload['smtp_password']);
    }

    public function testBuildAccountPayloadSemSenhaNaoIncluiChaveDeSenha(): void
    {
        // Atualização sem troca de senha: a chave smtp_password não entra no
        // payload, para o model manter a senha atual.
        $payload = UserEmailRules::buildAccountPayload('joao@empresa.com.br', '', '', $this->defaults());
        $this->assertArrayNotHasKey('smtp_password', $payload);
        // display_name vazio vira null (não string vazia).
        $this->assertNull($payload['display_name']);
    }

    public function testBuildAccountPayloadImapVazioViraNull(): void
    {
        $payload = UserEmailRules::buildAccountPayload(
            'joao@empresa.com.br',
            'segredo',
            '',
            $this->defaults(['prospection_imap_host' => ''])
        );
        $this->assertNull($payload['imap_host']);
    }
}
