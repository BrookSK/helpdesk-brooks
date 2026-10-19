<?php $pageTitle = 'Caixa de Entrada - E-mail'; $currentPage = 'prospection_inbox'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-inbox"></i> Caixa de Entrada</h5>
            <small class="text-muted">E-mails recebidos na sua caixa</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('prospection') ?>" class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Enviar E-mail</a>
            <a href="<?= baseUrl('prospection/history') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history"></i> Enviados</a>
        </div>
    </div>

    <?php if (empty($accounts)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <?php if (!empty($needsEmailSetup)): ?>
        Conecte seu e-mail para começar a usar a caixa de entrada.
        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="openEmailSetup()"><i class="bi bi-plug"></i> Conectar e-mail</button>
        <?php elseif (empty($serverConfigured) && $user['role'] === 'super_admin'): ?>
        O servidor de e-mail padrão ainda não foi configurado.
        <a href="<?= baseUrl('settings/emailAccounts') ?>">Configurar servidor padrão</a>
        <?php elseif (empty($serverConfigured)): ?>
        O servidor de e-mail padrão ainda não foi configurado. Fale com o administrador.
        <?php else: ?>
        Nenhuma conta com IMAP configurado vinculada ao seu perfil.
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small mb-0">Conta</label>
                    <select name="account_id" class="form-select form-select-sm" style="min-width:200px;">
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= $selectedAccountId == $acc['id'] ? 'selected' : '' ?>><?= escape($acc['display_name'] ?: $acc['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-0">Buscar</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= escape($search ?? '') ?>" placeholder="Assunto ou remetente...">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Buscar</button>
                    <?php if ($search): ?>
                    <a href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId) ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    <?php endif; ?>
                </div>
                <div class="ms-auto">
                    <small class="text-muted"><?= $total ?> e-mail<?= $total !== 1 ? 's' : '' ?></small>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= escape($error) ?></div>
    <?php endif; ?>

    <!-- Lista de e-mails -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($messages) && !$error): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2 mb-0">Nenhum e-mail encontrado.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush" id="email-list">
                <?php foreach ($messages as $msg): ?>
                <div class="list-group-item email-row <?= $msg['seen'] ? '' : 'fw-bold' ?>" data-uid="<?= $msg['uid'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1" style="min-width:0;cursor:pointer;" onclick="openEmail(<?= $msg['uid'] ?>)">
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!$msg['seen']): ?>
                                <span class="badge bg-primary rounded-circle" style="width:8px;height:8px;padding:0;"></span>
                                <?php endif; ?>
                                <span class="text-truncate" style="max-width:200px;font-size:0.82rem;"><?= escape($msg['from']) ?></span>
                            </div>
                            <div class="text-truncate" style="font-size:0.82rem;"><?= escape($msg['subject']) ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-3">
                            <div class="text-end text-nowrap" style="font-size:0.72rem;">
                                <span class="text-muted"><?= $msg['date'] ? date('d/m H:i', strtotime($msg['date'])) : '' ?></span>
                                <?php if ($msg['has_attachments']): ?>
                                <br><i class="bi bi-paperclip text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="email-row-actions d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Arquivar" onclick="event.stopPropagation(); archiveFromList(<?= $msg['uid'] ?>, this)"><i class="bi bi-archive"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir" onclick="event.stopPropagation(); deleteFromList(<?= $msg['uid'] ?>, this)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-center">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : '')) ?>">&laquo;</a></li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . $p . ($search ? '&search=' . urlencode($search) : '')) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : '')) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Modal de leitura -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h6 class="modal-title text-truncate me-auto" id="email-modal-subject" style="max-width:50%;"><i class="bi bi-envelope-open"></i> E-mail</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-reply" onclick="toggleReply()"><i class="bi bi-reply"></i> Responder</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-archive" onclick="archiveCurrent()"><i class="bi bi-archive"></i> Arquivar</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete" onclick="deleteCurrent()"><i class="bi bi-trash"></i> Excluir</button>
                    <button type="button" class="btn-close ms-1" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" id="email-modal-body">
                <div class="text-center py-5"><span class="spinner-border"></span></div>
            </div>
            <!-- Histórico de conversa (enviados + recebidos) com o contato -->
            <div class="px-3 pb-2" id="email-thread-wrap" style="display:none;">
                <hr class="my-2">
                <h6 class="small fw-semibold mb-2"><i class="bi bi-chat-left-text"></i> Histórico com <span id="thread-party" class="text-muted"></span></h6>
                <div id="email-thread" style="max-height:240px;overflow-y:auto;"></div>
            </div>
            <!-- Área de resposta (oculta por padrão) -->
            <div class="modal-footer flex-column align-items-stretch" id="reply-area" style="display:none;">
                <div class="w-100">
                    <label class="form-label small fw-medium mb-1">Responder para <span id="reply-to-label" class="text-muted"></span></label>
                    <textarea id="reply-body" class="form-control form-control-sm mb-2" rows="5" placeholder="Escreva sua resposta..."></textarea>
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleReply()">Cancelar</button>
                        <button class="btn btn-sm btn-primary" id="reply-send-btn" onclick="sendReply()"><i class="bi bi-send"></i> Enviar resposta</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const ACCOUNT_ID = <?= (int)$selectedAccountId ?>;
let emailModal = null;
let currentEmail = null; // { uid, from_email, from, subject }

function getEmailModal() {
    if (!emailModal) emailModal = new bootstrap.Modal(document.getElementById('emailModal'));
    return emailModal;
}

function openEmail(uid) {
    const body = document.getElementById('email-modal-body');
    const title = document.getElementById('email-modal-subject');
    body.innerHTML = '<div class="text-center py-5"><span class="spinner-border"></span></div>';
    title.innerHTML = '<i class="bi bi-envelope-open"></i> Carregando...';
    // Reseta a área de resposta e o histórico
    document.getElementById('reply-area').style.display = 'none';
    document.getElementById('reply-body').value = '';
    document.getElementById('email-thread-wrap').style.display = 'none';
    document.getElementById('email-thread').innerHTML = '';
    getEmailModal().show();

    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', uid);

    fetch(`${BASE}prospection/readEmail`, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            body.innerHTML = `<div class="alert alert-danger">${d.error}</div>`;
            title.innerHTML = '<i class="bi bi-envelope-open"></i> Erro';
            return;
        }

        const msg = d.message;
        currentEmail = { uid: uid, from_email: msg.from_email, from: msg.from, subject: msg.subject };
        title.innerHTML = '<i class="bi bi-envelope-open"></i> ' + escapeHtml(msg.subject);
        // Carrega o histórico de conversa com este remetente
        loadThread(msg.from_email);

        let html = `
            <div class="mb-3" style="font-size:0.82rem;">
                <div class="row g-1">
                    <div class="col-12"><strong>De:</strong> ${escapeHtml(msg.from)}</div>
                    <div class="col-12"><strong>Para:</strong> ${escapeHtml(msg.to)}</div>
                    ${msg.cc ? `<div class="col-12"><strong>CC:</strong> ${escapeHtml(msg.cc)}</div>` : ''}
                    <div class="col-12"><strong>Data:</strong> ${msg.date ? new Date(msg.date).toLocaleString('pt-BR') : '—'}</div>
                </div>
            </div>
            <hr>
        `;

        // Corpo do e-mail
        if (msg.body_html) {
            html += `<div class="email-body-frame border rounded p-3 bg-white" style="max-height:500px;overflow-y:auto;">${msg.body_html}</div>`;
        } else if (msg.body_text) {
            html += `<pre class="border rounded p-3 bg-light" style="max-height:500px;overflow-y:auto;white-space:pre-wrap;font-size:0.82rem;">${escapeHtml(msg.body_text)}</pre>`;
        } else {
            html += '<p class="text-muted">Sem conteúdo.</p>';
        }

        // Anexos
        if (msg.attachments && msg.attachments.length) {
            html += '<hr><h6 class="small fw-semibold"><i class="bi bi-paperclip"></i> Anexos (' + msg.attachments.length + ')</h6><ul class="small">';
            msg.attachments.forEach(a => {
                const size = a.size > 1024 ? (a.size / 1024).toFixed(1) + ' KB' : a.size + ' B';
                html += `<li>${escapeHtml(a.filename)} <span class="text-muted">(${size})</span></li>`;
            });
            html += '</ul>';
        }

        body.innerHTML = html;

        // Marca como lido visualmente na lista
        const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
        if (row) row.classList.remove('fw-bold');
    })
    .catch(() => {
        body.innerHTML = '<div class="alert alert-danger">Erro ao carregar o e-mail.</div>';
    });
}

// Remove a linha da lista e fecha o modal após excluir/arquivar
function removeRowAndClose(uid) {
    const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
    if (row) row.remove();
    getEmailModal().hide();
}

// Carrega e renderiza o histórico de conversa (enviados + recebidos) com o contato
function loadThread(email) {
    if (!email) return;
    const wrap = document.getElementById('email-thread-wrap');
    const box = document.getElementById('email-thread');
    document.getElementById('thread-party').textContent = email;
    box.innerHTML = '<div class="text-muted small py-2"><span class="spinner-border spinner-border-sm"></span> Carregando histórico...</div>';
    wrap.style.display = '';

    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('email', email);
    fetch(`${BASE}prospection/emailThread`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error || !d.thread || !d.thread.length) {
                box.innerHTML = '<div class="text-muted small py-2">Nenhuma mensagem anterior com este contato.</div>';
                return;
            }
            box.innerHTML = d.thread.map(renderThreadItem).join('');
        })
        .catch(() => { box.innerHTML = '<div class="text-muted small py-2">Não foi possível carregar o histórico.</div>'; });
}

function renderThreadItem(it) {
    const isSent = it.direction === 'sent';
    const icon = isSent ? 'bi-arrow-up-right-circle text-primary' : 'bi-arrow-down-left-circle text-success';
    const label = isSent ? 'Enviado' : 'Recebido';
    const badge = isSent
        ? (it.status === 'failed' ? '<span class="badge bg-danger">falhou</span>' : '<span class="badge bg-primary">enviado</span>')
        : '<span class="badge bg-success">recebido</span>';
    const dt = it.date ? new Date(it.date.replace(' ', 'T')).toLocaleString('pt-BR') : '—';
    const snippet = it.snippet ? `<div class="text-muted text-truncate" style="font-size:0.75rem;">${escapeHtml(it.snippet)}</div>` : '';
    const openable = (it.direction === 'received' && it.uid)
        ? ` style="cursor:pointer;" onclick="openEmail(${it.uid})"` : '';
    return `<div class="border-bottom py-2"${openable}>
        <div class="d-flex justify-content-between align-items-center">
            <span style="font-size:0.8rem;"><i class="bi ${icon}"></i> <strong>${label}</strong> — ${escapeHtml(it.subject || '(sem assunto)')}</span>
            <span class="text-nowrap ms-2" style="font-size:0.7rem;">${badge} <span class="text-muted">${dt}</span></span>
        </div>
        ${snippet}
    </div>`;
}

// ===== Ações direto na listagem (sem abrir o e-mail) =====
function archiveFromList(uid, btn) {
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', uid);
    btn.disabled = true;
    fetch(`${BASE}prospection/archiveEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); btn.disabled = false; return; }
            const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
            if (row) row.remove();
        })
        .catch(() => { alert('Erro ao arquivar o e-mail.'); btn.disabled = false; });
}

function deleteFromList(uid, btn) {
    if (!confirm('Excluir este e-mail? Ele será movido para a lixeira da conta.')) return;
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', uid);
    btn.disabled = true;
    fetch(`${BASE}prospection/deleteEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); btn.disabled = false; return; }
            const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
            if (row) row.remove();
        })
        .catch(() => { alert('Erro ao excluir o e-mail.'); btn.disabled = false; });
}

function deleteCurrent() {
    if (!currentEmail) return;
    if (!confirm('Excluir este e-mail? Ele será movido para a lixeira da conta.')) return;
    const btn = document.getElementById('btn-delete');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', currentEmail.uid);
    fetch(`${BASE}prospection/deleteEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.error) { alert(d.error); return; }
            removeRowAndClose(currentEmail.uid);
        })
        .catch(() => { btn.disabled = false; alert('Erro ao excluir o e-mail.'); });
}

function archiveCurrent() {
    if (!currentEmail) return;
    const btn = document.getElementById('btn-archive');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', currentEmail.uid);
    fetch(`${BASE}prospection/archiveEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.error) { alert(d.error); return; }
            removeRowAndClose(currentEmail.uid);
        })
        .catch(() => { btn.disabled = false; alert('Erro ao arquivar o e-mail.'); });
}

function toggleReply() {
    const area = document.getElementById('reply-area');
    const showing = area.style.display !== 'none';
    if (showing) { area.style.display = 'none'; return; }
    if (!currentEmail) return;
    document.getElementById('reply-to-label').textContent = currentEmail.from_email || currentEmail.from;
    area.style.display = '';
    document.getElementById('reply-body').focus();
}

function sendReply() {
    if (!currentEmail) return;
    const bodyText = document.getElementById('reply-body').value.trim();
    if (!bodyText) { alert('Escreva a resposta.'); return; }

    const subject = /^re:/i.test(currentEmail.subject) ? currentEmail.subject : ('Re: ' + currentEmail.subject);
    // Converte quebras de linha em <br> para o corpo HTML
    const htmlBody = escapeHtml(bodyText).replace(/\n/g, '<br>');

    const btn = document.getElementById('reply-send-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('to', currentEmail.from_email);
    fd.append('subject', subject);
    fd.append('body', htmlBody);
    fetch(`${BASE}prospection/replyEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Enviar resposta';
            if (d.error) { alert(d.error); return; }
            alert(d.message || 'Resposta enviada!');
            document.getElementById('reply-area').style.display = 'none';
            document.getElementById('reply-body').value = '';
            // Atualiza o histórico para incluir a resposta recém-enviada
            if (currentEmail) loadThread(currentEmail.from_email);
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Enviar resposta'; alert('Erro ao enviar a resposta.'); });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
.email-row { border-left: 3px solid transparent; transition: border-color .15s, background .15s; }
.email-row:hover { border-left-color: var(--bs-primary); background: #f8f9fa; }
.email-row.fw-bold { border-left-color: #1976d2; background: #eef5ff; }
.email-body-frame img { max-width: 100%; height: auto; }
/* Ações da linha: discretas, aparecem ao passar o mouse (mantém visível em telas touch) */
.email-row-actions { opacity: 0; transition: opacity .15s; }
.email-row:hover .email-row-actions { opacity: 1; }
@media (hover: none) { .email-row-actions { opacity: 1; } }
</style>

<!-- Modal: configurar e-mail do usuário (aparece ao abrir a caixa sem conta conectada) -->
<div class="modal fade" id="emailSetupModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-envelope-at"></i> Conecte seu e-mail</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="emailSetupForm" onsubmit="return submitEmailSetup(event)">
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Para usar a caixa de entrada e enviar e-mails, conecte seu e-mail institucional.
                        O servidor já está pré-configurado — basta informar seu <strong>e-mail</strong> e sua <strong>senha</strong>.
                    </p>
                    <div id="email-setup-error" class="alert alert-danger py-2 small" style="display:none;"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">E-mail institucional</label>
                        <input type="email" name="institutional_email" id="setup-email" class="form-control" placeholder="seu.nome@suaempresa.com.br" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome de exibição (opcional)</label>
                        <input type="text" name="institutional_display_name" id="setup-display-name" class="form-control" placeholder="Ex: João Silva">
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-medium">Senha do e-mail</label>
                        <input type="password" name="institutional_password" id="setup-password" class="form-control" placeholder="Senha do seu e-mail" autocomplete="new-password" required>
                        <small class="text-muted">A senha é criptografada e usada apenas para conectar sua caixa.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Agora não</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="setup-submit-btn"><i class="bi bi-plug"></i> Conectar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let emailSetupModal = null;
function getEmailSetupModal() {
    if (!emailSetupModal) emailSetupModal = new bootstrap.Modal(document.getElementById('emailSetupModal'));
    return emailSetupModal;
}
function openEmailSetup() { getEmailSetupModal().show(); }

function submitEmailSetup(ev) {
    ev.preventDefault();
    const btn = document.getElementById('setup-submit-btn');
    const err = document.getElementById('email-setup-error');
    err.style.display = 'none';
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Conectando...';

    const fd = new FormData(document.getElementById('emailSetupForm'));
    fetch('<?= baseUrl("account/saveEmailAjax") ?>', {
        method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.error) {
            err.textContent = d.error; err.style.display = 'block';
            btn.disabled = false; btn.innerHTML = orig;
            return;
        }
        // Conectou: recarrega a caixa de entrada já com a conta ativa.
        location.reload();
    })
    .catch(() => {
        err.textContent = 'Erro ao conectar. Tente novamente.'; err.style.display = 'block';
        btn.disabled = false; btn.innerHTML = orig;
    });
    return false;
}

<?php if (!empty($needsEmailSetup)): ?>
// Usuário sem e-mail conectado: abre o pop-up automaticamente ao entrar.
document.addEventListener('DOMContentLoaded', function() { openEmailSetup(); });
<?php endif; ?>
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
