<?php

class AccountController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $userData = $this->userModel->findById($user['id']);

        // E-mail institucional do usuário + estado do servidor padrão.
        $emailAccount = (new EmailAccount())->getSingleByUser($user['id']);
        $emailServerConfigured = UserEmailRules::isServerConfigured([
            'prospection_smtp_host' => Config::get('prospection_smtp_host', ''),
        ]);

        $this->view('account/index', [
            'user' => $user,
            'userData' => $userData,
            'emailAccount' => $emailAccount,
            'emailServerConfigured' => $emailServerConfigured,
        ]);
    }

    public function update()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('account');
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $user['id']) {
            flash('error', 'Este email já está em uso.');
            $this->redirect('account');
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];

        $this->userModel->update($user['id'], $data);

        // Atualizar sessão
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        flash('success', 'Dados atualizados com sucesso!');
        $this->redirect('account');
    }

    public function changePassword()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            flash('error', 'Preencha todos os campos de senha.');
            $this->redirect('account');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'A nova senha e a confirmação não coincidem.');
            $this->redirect('account');
        }

        if (strlen($newPassword) < 6) {
            flash('error', 'A nova senha deve ter no mínimo 6 caracteres.');
            $this->redirect('account');
        }

        $userData = $this->userModel->findById($user['id']);
        if (!password_verify($currentPassword, $userData['password'])) {
            flash('error', 'Senha atual incorreta.');
            $this->redirect('account');
        }

        $this->userModel->update($user['id'], ['password' => $newPassword]);
        flash('success', 'Senha alterada com sucesso!');
        $this->redirect('account');
    }

    /**
     * E-mail institucional do próprio usuário.
     *
     * O super admin configura o servidor padrão (SMTP/IMAP) uma única vez em
     * Configurações. Aqui o usuário informa apenas o e-mail e a senha; o sistema
     * monta/atualiza a conta em email_accounts combinando o servidor padrão com
     * essas credenciais e a vincula ao próprio usuário (email_account_users).
     * Assim a Prospecção e a Caixa de Entrada passam a usar a conta pessoal.
     */
    public function updateEmail()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();
        $result = $this->persistUserEmailAccount(
            (int) $user['id'],
            trim($_POST['institutional_email'] ?? ''),
            $_POST['institutional_password'] ?? '',
            trim($_POST['institutional_display_name'] ?? '')
        );

        if (!$result['ok']) {
            flash('error', $result['error']);
        } else {
            flash('success', 'E-mail institucional salvo com sucesso!');
        }
        $this->redirect('account');
    }

    /**
     * Versão AJAX de updateEmail, usada pelo pop-up de configuração que aparece
     * na Caixa de Entrada quando o usuário ainda não conectou seu e-mail.
     * Retorna JSON {success|error}.
     */
    public function saveEmailAjax()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido.'], 405);
        }

        $user = $this->currentUser();
        $result = $this->persistUserEmailAccount(
            (int) $user['id'],
            trim($_POST['institutional_email'] ?? ''),
            $_POST['institutional_password'] ?? '',
            trim($_POST['institutional_display_name'] ?? '')
        );

        if (!$result['ok']) {
            $this->json(['error' => $result['error']], 422);
        }
        $this->json(['success' => true, 'message' => 'E-mail conectado com sucesso!']);
    }

    /**
     * Valida, monta e persiste a conta de e-mail pessoal do usuário a partir do
     * servidor padrão + e-mail/senha informados. Fonte única usada tanto pelo
     * fluxo de "Minha Conta" (updateEmail) quanto pelo pop-up da Caixa de
     * Entrada (saveEmailAjax).
     *
     * @return array{ok:bool,error:?string}
     */
    private function persistUserEmailAccount(int $userId, string $email, string $password, string $displayName): array
    {
        $accountModel = new EmailAccount();
        $existing = $accountModel->getSingleByUser($userId);
        $isNew = empty($existing);

        // Defaults do servidor padrão (definidos pelo super admin).
        $defaults = [
            'prospection_smtp_host' => Config::get('prospection_smtp_host', ''),
            'prospection_smtp_port' => Config::get('prospection_smtp_port', '587'),
            'prospection_smtp_encryption' => Config::get('prospection_smtp_encryption', 'tls'),
            'prospection_imap_host' => Config::get('prospection_imap_host', ''),
            'prospection_imap_port' => Config::get('prospection_imap_port', '993'),
            'prospection_imap_encryption' => Config::get('prospection_imap_encryption', 'ssl'),
        ];

        $error = UserEmailRules::validationError($email, $password, $defaults, $isNew);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $payload = UserEmailRules::buildAccountPayload($email, $password, $displayName, $defaults);

        if ($isNew) {
            $payload['created_by'] = $userId;
            $accountId = $accountModel->create($payload);
            $accountModel->setLinkedUsers($accountId, [$userId]);
        } else {
            // buildAccountPayload só inclui smtp_password quando o usuário digitou
            // uma nova senha; o model mantém a atual se ausente.
            $accountModel->update($existing['id'], $payload);
            $accountModel->setLinkedUsers($existing['id'], [$userId]); // idempotente
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Permite ao próprio usuário (qualquer papel) definir/alterar seu PIN de
     * acesso de 4 dígitos, usado para entrar pela opção "Entrar com PIN".
     */
    public function updateClientPin()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();

        $pin = trim($_POST['client_pin'] ?? '');
        if (!ClientPinRules::isValidFormat($pin)) {
            flash('error', 'O PIN deve ter exatamente 4 dígitos numéricos.');
            $this->redirect('account');
        }
        if ($this->userModel->clientPinExists($pin, $user['id'])) {
            flash('error', 'Este PIN já está em uso. Escolha outro.');
            $this->redirect('account');
        }

        $saved = $this->userModel->setClientPin($user['id'], $pin);
        if ($saved === null) {
            flash('error', 'Não foi possível salvar o PIN. Tente outro.');
            $this->redirect('account');
        }

        flash('success', 'PIN de acesso atualizado com sucesso!');
        $this->redirect('account');
    }
}
