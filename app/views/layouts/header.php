<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= escape(csrf_token()) ?>">
    <title><?= escape($pageTitle ?? 'Brooks Construtora Helpdesk') ?></title>
    <?php
    $faviconUrl = Config::get('app_favicon');
    if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== Identidade visual Brooks Construtora =====
           Paleta oficial do site brooksconstrutora.com.br:
           navy #1a2332, azul #2c4a6e / #3d6b9f, azul accent #4a90d9,
           vermelho #c0392b (hover #e74c3c). Fontes: Inter (corpo) e
           Playfair Display (títulos de marca). */
        :root {
            --primary: #4a90d9;         /* brooks-blue-accent (destaque) */
            --primary-dark: #2c4a6e;    /* brooks-blue (hover/escuro) */
            --primary-light: #3d6b9f;   /* brooks-blue-light */
            --primary-50: #eaf2fb;      /* azul bem claro p/ fundos sutis */
            --brooks-navy: #1a2332;
            --brooks-red: #c0392b;
            --brooks-red-hover: #e74c3c;
            --sidebar-bg: #1a2332;      /* brooks-navy */
            --sidebar-width: 260px;
            --font-display: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #fff;
        }
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .bg-primary { background-color: var(--primary) !important; }
        .text-primary { color: var(--primary) !important; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1050;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 11px 18px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.2s;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(74, 144, 217, 0.18);
            color: var(--primary);
        }
        .sidebar .nav-link i {
            width: 22px;
            margin-right: 10px;
            font-size: 1rem;
        }
        .sidebar-header {
            padding: 18px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sidebar-footer {
            margin-top: auto;
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 8px 0;
            min-height: 0; /* necessário para flex overflow funcionar */
        }
        /* Scrollbar estilizada para sidebar */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(74, 144, 217, 0.4);
            border-radius: 10px;
        }
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 144, 217, 0.7);
        }
        /* Firefox */
        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(74, 144, 217, 0.4) rgba(255,255,255,0.05);
        }

        /* ===== SIDEBAR RECOLHIDA (somente ícones) ===== */
        @media (min-width: 992px) {
            body.sidebar-collapsed .sidebar { width: 70px; }
            body.sidebar-collapsed .main-content { margin-left: 70px; }

            /* Esconde textos, mantém ícones */
            body.sidebar-collapsed .sidebar .nav-text,
            body.sidebar-collapsed .sidebar .nav-link .crm-caret,
            body.sidebar-collapsed .sidebar .nav-link .companies-caret,
            body.sidebar-collapsed .sidebar-nav small,
            body.sidebar-collapsed .sidebar .notification-count-sidebar,
            body.sidebar-collapsed .sidebar-footer .flex-grow-1,
            body.sidebar-collapsed .sidebar-header > div,
            body.sidebar-collapsed .sidebar-header img,
            body.sidebar-collapsed .sidebar-header .logo-text,
            body.sidebar-collapsed .sidebar-header .fw-light {
                display: none !important;
            }
            /* Nav-link centralizado no modo ícone */
            body.sidebar-collapsed .sidebar .nav-link {
                text-align: center;
                margin: 2px 8px;
                padding: 11px 0;
                justify-content: center !important;
            }
            /* Texto solto (nós sem .nav-text) fica escondido, ícone permanece */
            body.sidebar-collapsed .sidebar .nav-link {
                font-size: 0;
            }
            body.sidebar-collapsed .sidebar .nav-link .nav-link-body {
                display: flex;
                justify-content: center;
                width: 100%;
            }
            body.sidebar-collapsed .sidebar .nav-link i {
                margin-right: 0 !important;
                font-size: 1.1rem;
            }
            /* No modo ícone, esconder as subabas por completo (evita texto vazando) */
            body.sidebar-collapsed #crm-subnav,
            body.sidebar-collapsed #companies-subnav {
                display: none !important;
            }
            /* Rodapé no modo recolhido: empilha avatar e logout, centralizados */
            body.sidebar-collapsed .sidebar-footer { padding: 10px 4px; }
            body.sidebar-collapsed .sidebar-footer > div {
                flex-direction: column;
                gap: 8px;
            }
            body.sidebar-collapsed .sidebar-footer .rounded-circle { margin: 0 auto; }
            body.sidebar-collapsed .sidebar-footer > div .btn {
                padding: 5px 8px;
                margin: 0 auto !important;
                width: 36px;
            }
            /* Botão "Voltar" (impersonação) permanece full-width */
            body.sidebar-collapsed .sidebar-footer > a.btn { width: auto; }
            /* Botão recolher centralizado quando o menu está recolhido */
            body.sidebar-collapsed .sidebar-header { padding: 14px 0; }
            body.sidebar-collapsed .sidebar-collapse-btn {
                position: static !important;
                margin: 0 auto;
            }
        }
        /* Botão de recolher (desktop) */
        .sidebar-collapse-btn {
            display: none;
        }
        @media (min-width: 992px) {
            .sidebar-collapse-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(255,255,255,0.08);
                color: rgba(255,255,255,0.7);
                border: none;
                border-radius: 8px;
                width: 30px;
                height: 30px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .sidebar-collapse-btn:hover { background: rgba(74,144,217,0.2); color: var(--primary); }
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 25px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .top-bar {
            background: #fff;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .top-bar h5 { font-size: 1.1rem; }

        /* ===== CARDS ===== */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card {
            border-left: 4px solid var(--primary);
            padding: 16px !important;
        }
        .stat-card .stat-label {
            font-size: 0.78rem;
            color: #666;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }

        /* ===== BADGES ===== */
        .badge-status {
            font-size: 0.72rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            white-space: nowrap;
        }
        .badge-open { background: #e3f2fd; color: #1565c0; }
        .badge-in_progress { background: #fff3e0; color: #e65100; }
        .badge-em_revisao_interna { background: #e8eaf6; color: #5c6bc0; }
        .badge-waiting_client { background: #fce4ec; color: #c62828; }
        .badge-em_homologacao { background: #e0f7fa; color: #0097a7; }
        .badge-aprovado_producao { background: #f1f8e9; color: #689f38; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        .badge-denied { background: #fbe9e7; color: #d84315; }
        .badge-archived { background: #eceff1; color: #546e7a; }

        .priority-low { color: #4caf50; font-weight: 500; }
        .priority-medium { color: #ff9800; font-weight: 500; }
        .priority-high { color: #f44336; font-weight: 500; }
        .priority-urgent { color: #9c27b0; font-weight: 500; }

        /* ===== CHAT ===== */
        .chat-container {
            height: 380px;
            overflow-y: auto;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            padding: 15px;
            background: #fafafa;
        }
        .chat-message { margin-bottom: 12px; display: flex; }
        .chat-message.mine { justify-content: flex-end; }
        .chat-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            word-wrap: break-word;
        }
        .chat-message.mine .chat-bubble {
            background: var(--primary);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chat-message.other .chat-bubble {
            background: #e8e8e8;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .chat-sender { font-size: 0.72rem; font-weight: 600; margin-bottom: 2px; }
        .chat-time { font-size: 0.68rem; opacity: 0.7; margin-top: 3px; }

        /* ===== KANBAN ===== */
        .kanban-column {
            min-height: 180px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
        }
        .kanban-card {
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kanban-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* ===== LOGO ===== */
        .logo-text {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* ===== TABLE ===== */
        .table { font-size: 0.85rem; }
        .table th { font-weight: 600; font-size: 0.78rem; text-transform: uppercase; color: #666; white-space: nowrap; }
        .table td { vertical-align: middle; }

        /* ===== MOBILE OVERLAY ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }
        .sidebar-overlay.show { display: block; }

        /* ===== MOBILE TOGGLE ===== */
        .mobile-topbar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 1030;
            background: #fff;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            align-items: center;
            gap: 12px;
        }
        .mobile-topbar .logo-text { font-size: 1.1rem; }
        /* Logo branca da Brooks sobre bloco navy, para não sumir no topbar branco */
        .mobile-topbar .mobile-logo-wrap {
            display: inline-flex;
            align-items: center;
            background: var(--brooks-navy, #1a2332);
            border-radius: 8px;
            padding: 5px 10px;
        }
        .mobile-topbar .mobile-logo-wrap img { max-height: 24px; max-width: 150px; display: block; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .mobile-topbar {
                display: flex;
            }
            .top-bar {
                padding: 12px 15px;
            }
            .top-bar h5 { font-size: 1rem; }
            .stat-card .stat-value { font-size: 1.4rem; }
            .table-responsive { font-size: 0.8rem; }
            .kanban-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 10px;
            }
            .kanban-scroll .row {
                flex-wrap: nowrap;
            }
            .kanban-scroll .col {
                min-width: 260px;
            }
        }
        @media (max-width: 575.98px) {
            .main-content { padding: 10px; }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .stat-card { padding: 12px !important; }
            .stat-card .stat-value { font-size: 1.2rem; }
            .stat-card .stat-label { font-size: 0.7rem; }
            .card-body { padding: 12px; }
            .chat-container { height: 300px; padding: 10px; }
            .chat-bubble { max-width: 85%; font-size: 0.82rem; }

            /* ===== Modais ocupam a tela toda no celular (global) =====
               Vale para qualquer modal do sistema (agenda, etc.), evitando
               margens apertadas e conteúdo espremido em telas pequenas. */
            .modal-dialog {
                margin: 0;
                max-width: 100%;
                min-height: 100%;
            }
            .modal-content { border-radius: 0; min-height: 100vh; }
            .modal-lg, .modal-xl { max-width: 100%; }
            .modal-body { min-height: 0 !important; }

            /* top-bar do sistema: botões ocupam a largura e não cortam texto */
            .top-bar .btn { white-space: normal; }

            /* Cabeçalhos de card com título + botões: quebram em vez de cortar */
            .card-header .d-flex { flex-wrap: wrap; gap: 8px; }

            /* Tabelas longas rolam com indicação de toque */
            .table-responsive { -webkit-overflow-scrolling: touch; }
        }
    </style>
</head>
<body>
