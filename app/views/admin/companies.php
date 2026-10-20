<?php $pageTitle = 'Empresas - Brooks Construtora Helpdesk'; $currentPage = 'companies'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Empresas</h5>
            <small class="text-muted">Gerencie as empresas clientes</small>
        </div>
        <a href="<?= baseUrl('companies/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-building-add"></i> Nova Empresa
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="filter-company-name" class="form-control" placeholder="Filtrar por nome, CNPJ/CPF ou email...">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <!-- Desktop: tabela -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CNPJ/CPF</th>
                            <th>Email</th>
                            <th>Usuários</th>
                            <th>Demandas</th>
                            <th>Documentos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="companies-tbody">
                        <?php foreach ($companies as $c): ?>
                        <tr class="company-row" data-search="<?= escape(mb_strtolower($c['name'] . ' ' . ($c['document'] ?? '') . ' ' . ($c['email'] ?? ''))) ?>" style="cursor:pointer" onclick="window.location='<?= baseUrl('companies/details/' . $c['id']) ?>'">
                            <td><?= $c['id'] ?></td>
                            <td class="fw-medium">
                                <i class="bi bi-building text-primary me-1"></i>
                                <?= escape($c['name']) ?>
                            </td>
                            <td style="font-size:0.85rem"><?= escape($c['document'] ?? '-') ?></td>
                            <td style="font-size:0.85rem"><?= escape($c['email'] ?? '-') ?></td>
                            <td><span class="badge bg-primary"><?= $c['users_count'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $c['tickets_count'] ?? 0 ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= $c['documents_count'] ?? 0 ?></span></td>
                            <td onclick="event.stopPropagation()">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= baseUrl('companies/details/' . $c['id']) ?>" class="btn btn-outline-secondary" title="Abrir"><i class="bi bi-box-arrow-in-right"></i></a>
                                    <a href="<?= baseUrl('companies/edit/' . $c['id']) ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="<?= baseUrl('companies/delete/' . $c['id']) ?>" class="btn btn-outline-danger" title="Remover" onclick="return confirm('Remover esta empresa?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($companies)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma empresa cadastrada.</td></tr>
                        <?php endif; ?>
                        <tr id="no-companies-msg" style="display:none"><td colspan="8" class="text-center text-muted py-4">Nenhuma empresa encontrada.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="d-md-none p-2">
                <?php foreach ($companies as $c): ?>
                <div class="border rounded-3 p-3 mb-2 company-row" data-search="<?= escape(mb_strtolower($c['name'] . ' ' . ($c['document'] ?? '') . ' ' . ($c['email'] ?? ''))) ?>">
                    <a href="<?= baseUrl('companies/details/' . $c['id']) ?>" class="text-decoration-none text-dark d-block mb-2">
                        <span class="fw-medium"><i class="bi bi-building text-primary me-1"></i><?= escape($c['name']) ?></span>
                    </a>
                    <div class="text-muted mb-2" style="font-size:0.8rem;word-break:break-word;">
                        <?php if (!empty($c['document'])): ?><div><i class="bi bi-card-text"></i> <?= escape($c['document']) ?></div><?php endif; ?>
                        <?php if (!empty($c['email'])): ?><div><i class="bi bi-envelope"></i> <?= escape($c['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2" style="font-size:0.72rem;">
                        <span class="badge bg-primary"><i class="bi bi-people"></i> <?= $c['users_count'] ?> usuários</span>
                        <span class="badge bg-secondary"><?= $c['tickets_count'] ?? 0 ?> demandas</span>
                        <span class="badge bg-info text-dark"><?= $c['documents_count'] ?? 0 ?> docs</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= baseUrl('companies/details/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-in-right"></i> Abrir</a>
                        <a href="<?= baseUrl('companies/edit/' . $c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                        <a href="<?= baseUrl('companies/delete/' . $c['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover esta empresa?')"><i class="bi bi-trash"></i> Remover</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const input = document.getElementById('filter-company-name');
    const rows = document.querySelectorAll('.company-row');
    const noMsg = document.getElementById('no-companies-msg');
    if (!input) return;
    input.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function(row) {
            const hay = row.getAttribute('data-search') || '';
            const show = term === '' || hay.indexOf(term) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noMsg) noMsg.style.display = visible === 0 ? '' : 'none';
    });
})();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
