<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use EmailAccount;
use UserEmailRules;
use Database;

/**
 * Testes de integração do fluxo "e-mail institucional por usuário" contra o
 * banco helpdesk_on_test.
 *
 * Reproduz o que o AccountController::updateEmail orquestra: a partir do
 * servidor padrão (defaults) + e-mail/senha do usuário, monta o payload
 * (UserEmailRules), cria/atualiza a conta em email_accounts e a vincula ao
 * usuário (email_account_users). Valida que:
 *  - a conta é criada com os dados do servidor padrão e o e-mail como username;
 *  - a senha é criptografada e recuperável;
 *  - getSingleByUser devolve a conta pessoal do usuário;
 *  - getByUser (usado por Prospecção/Inbox) enxerga a conta;
 *  - atualizar sem senha mantém a senha anterior; atualizar com senha troca.
 */
final class UserEmailAccountTest extends TestCase
{
    private Database $db;
    private EmailAccount $accountModel;
    private int $userId;
    /** @var int[] */
    private array $accountIds = [];

    /** Servidor padrão fictício (o que o super admin salva em Configurações). */
    private function defaults(): array
    {
        return [
            'prospection_smtp_host' => 'smtp.empresa.test',
            'prospection_smtp_port' => '587',
            'prospection_smtp_encryption' => 'tls',
            'prospection_imap_host' => 'imap.empresa.test',
            'prospection_imap_port' => '993',
            'prospection_imap_encryption' => 'ssl',
        ];
    }

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->accountModel = new EmailAccount();

        $u = uniqid();
        $this->userId = (int) $this->db->insert('users', [
            'name' => "User Email {$u}",
            'email' => "ue_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'role' => 'comercial',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->accountIds as $id) {
            try { $this->db->delete('email_account_users', 'email_account_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('email_accounts', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->userId]); } catch (\Throwable $e) {}
    }

    /**
     * Simula o AccountController::updateEmail: valida, monta o payload e
     * persiste (cria ou atualiza) a conta pessoal do usuário.
     *
     * @return int id da conta
     */
    private function salvarEmailDoUsuario(string $email, ?string $password, string $displayName = ''): int
    {
        $defaults = $this->defaults();
        $existing = $this->accountModel->getSingleByUser($this->userId);
        $isNew = empty($existing);

        $error = UserEmailRules::validationError($email, $password, $defaults, $isNew);
        $this->assertNull($error, "validação não deveria falhar: " . (string) $error);

        $payload = UserEmailRules::buildAccountPayload($email, $password, $displayName, $defaults);

        if ($isNew) {
            $payload['created_by'] = $this->userId;
            $id = (int) $this->accountModel->create($payload);
            $this->accountModel->setLinkedUsers($id, [$this->userId]);
            $this->accountIds[] = $id;
            return $id;
        }

        $this->accountModel->update((int) $existing['id'], $payload);
        $this->accountModel->setLinkedUsers((int) $existing['id'], [$this->userId]);
        return (int) $existing['id'];
    }

    public function testCriaContaComServidorPadraoEVinculaUsuario(): void
    {
        $id = $this->salvarEmailDoUsuario('joao@empresa.test', 'SenhaInicial1', 'João Comercial');

        $acc = $this->accountModel->findById($id);
        $this->assertNotEmpty($acc);
        $this->assertSame('joao@empresa.test', $acc['email']);
        $this->assertSame('joao@empresa.test', $acc['smtp_username'], 'login deve ser o próprio e-mail');
        $this->assertSame('João Comercial', $acc['display_name']);
        $this->assertSame('smtp.empresa.test', $acc['smtp_host']);
        $this->assertSame(587, (int) $acc['smtp_port']);
        $this->assertSame('tls', $acc['smtp_encryption']);
        $this->assertSame('imap.empresa.test', $acc['imap_host']);
        $this->assertSame(993, (int) $acc['imap_port']);
        $this->assertSame('ssl', $acc['imap_encryption']);
        $this->assertSame(1, (int) $acc['is_active']);

        // Senha criptografada no banco, mas recuperável.
        $this->assertNotSame('SenhaInicial1', $acc['smtp_password']);
        $this->assertSame('SenhaInicial1', EmailAccount::decryptPassword($acc['smtp_password']));

        // Vínculo criado.
        $ids = array_map('intval', $this->accountModel->getLinkedUserIds($id));
        $this->assertContains($this->userId, $ids);
    }

    public function testGetSingleByUserDevolveAContaPessoal(): void
    {
        $id = $this->salvarEmailDoUsuario('maria@empresa.test', 'Senha1');
        $single = $this->accountModel->getSingleByUser($this->userId);
        $this->assertNotEmpty($single);
        $this->assertSame($id, (int) $single['id']);
        $this->assertSame('maria@empresa.test', $single['email']);
    }

    public function testContaApareceEmGetByUserParaProspeccaoEInbox(): void
    {
        $id = $this->salvarEmailDoUsuario('vendas@empresa.test', 'Senha1');
        $contas = $this->accountModel->getByUser($this->userId);
        $accIds = array_map(fn($c) => (int) $c['id'], $contas);
        $this->assertContains($id, $accIds, 'a conta pessoal deve estar disponível para o envio/inbox');
    }

    public function testAtualizarSemSenhaMantemSenhaAnterior(): void
    {
        $id = $this->salvarEmailDoUsuario('ana@empresa.test', 'SenhaOriginal1', 'Ana');

        // Atualiza só o nome de exibição, senha em branco.
        $this->salvarEmailDoUsuario('ana@empresa.test', '', 'Ana Silva');

        $acc = $this->accountModel->findById($id);
        $this->assertSame('Ana Silva', $acc['display_name']);
        // Senha preservada.
        $this->assertSame('SenhaOriginal1', EmailAccount::decryptPassword($acc['smtp_password']));
        // Continua sendo a mesma conta (não criou outra).
        $this->assertSame($id, (int) $this->accountModel->getSingleByUser($this->userId)['id']);
    }

    public function testAtualizarComSenhaTrocaSenha(): void
    {
        $id = $this->salvarEmailDoUsuario('carlos@empresa.test', 'SenhaOriginal1');
        $this->salvarEmailDoUsuario('carlos@empresa.test', 'NovaSenha2');

        $acc = $this->accountModel->findById($id);
        $this->assertSame('NovaSenha2', EmailAccount::decryptPassword($acc['smtp_password']));
    }

    public function testNaoCriaSegundaContaAoAtualizar(): void
    {
        $this->salvarEmailDoUsuario('pedro@empresa.test', 'Senha1');
        $this->salvarEmailDoUsuario('pedro2@empresa.test', 'Senha2', 'Pedro');

        // Só existe UMA conta vinculada ao usuário.
        $contas = $this->accountModel->getByUser($this->userId);
        $this->assertCount(1, $contas);
        $this->assertSame('pedro2@empresa.test', $contas[0]['email']);
    }
}
