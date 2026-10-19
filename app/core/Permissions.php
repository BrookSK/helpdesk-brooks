<?php

/**
 * Fonte ÚNICA de verdade para autorização por papel (role) no helpdeskON.
 *
 * Antes, a decisão de "quem vê o quê" estava espalhada e podia divergir entre
 * três lugares: o requireRole() de cada método de controller, os if() de papel
 * no sidebar e checagens inline. Esta classe centraliza o mapa papel -> módulos,
 * de forma pura (sem banco/HTTP) e testável por unidade.
 *
 * Regras de negócio (definidas com o cliente):
 *  - super_admin: acessa tudo.
 *  - developer:   acessa tudo que o super_admin acessa, EXCETO ver o RDO de
 *                 outras pessoas (no RDO ele só vê o próprio). O acesso ao
 *                 MÓDULO rdo é liberado; o escopo "só o meu" é aplicado no
 *                 model/controller via RdoRules::canViewReportOf().
 *  - marketing/comercial/analyst/attendant/whatsapp_agent: acesso segmentado.
 *  - client: apenas a área do cliente.
 *  - whatsapp (Chat) é liberado para TODOS os papéis (inclusive client).
 */
class Permissions
{
    /** Todos os papéis conhecidos do sistema (enum users.role). */
    public const ROLES = [
        'super_admin', 'developer', 'attendant', 'analyst',
        'comercial', 'marketing', 'whatsapp_agent', 'equipe', 'client',
    ];

    /**
     * Módulos (chaves de acesso) usados por controllers e sidebar.
     * Não confundir com "currentPage" da view — aqui é a unidade de permissão.
     */
    public const MODULES = [
        'dashboard',            // painel inicial (todo logado)
        'account',              // minha conta (todo logado)
        'notifications',        // notificações (todo logado)
        'whatsapp',             // WhatsApp Chat (todos)
        'rdo',                  // Relatório Diário (equipe interna)
        // Operacional / suporte
        'tickets',              // Demandas (equipe)
        'tickets_create',       // Nova Demanda (super_admin)
        'planning',             // Planejamento
        'documents',            // Documentos
        'performance_operacional',
        // Comercial / CRM
        'agenda',
        'performance_comercial',
        'crm',
        'crm_apollo',
        'crm_prospecting',
        'sequences',
        'linkedin',
        'leadcapture',
        'leadcapture_admin',
        'service_catalog',      // Catálogo de serviços (esteira comercial)
        'presentations',        // Apresentação comercial com link público (esteira comercial, antes da proposta)
        'proposals',            // Propostas/orçamentos (esteira comercial)
        'contracts',            // Contratos + assinatura (esteira comercial)
        'finance',              // Financeiro: parcelas, recorrência, Asaas (esteira comercial)
        'onboarding',           // Onboarding por etapas pós-entrada paga (esteira comercial)
        'credentials',          // Cofre de credenciais do cliente (restrito a super_admin/developer)
        'provisioning',         // Provisionamento de infra no LRV Cloud (esteira comercial)
        'providers',            // Contratação de prestadores (gestão interna, só super_admin/developer)
        'projects',             // Projetos entregues + garantia (esteira comercial)
        // Prospecção / marketing / social
        'prospection',
        'marketing',
        'social',
        'videocall',            // Gravações
        // Administração
        'companies',
        'users',
        'acessos',
        'settings',
        // Área do cliente
        'client_tickets',
        'client_schedule',
        'subusers',
    ];

    /**
     * Módulos liberados por papel (exceto super_admin/developer, que recebem
     * tudo via allowedModules()). 'whatsapp' aparece em todos de propósito.
     */
    private const ROLE_MODULES = [
        'marketing' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'marketing', 'prospection', 'social', 'videocall',
        ],
        'comercial' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'agenda', 'performance_comercial',
            'prospection', 'videocall',
            'crm', 'crm_apollo', 'crm_prospecting', 'sequences', 'linkedin', 'leadcapture',
            'service_catalog', 'presentations', 'proposals', 'contracts', 'onboarding', 'provisioning', 'projects',
        ],
        'attendant' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'documents', 'performance_operacional',
            'prospection', 'videocall',
            'crm', 'sequences', 'linkedin', 'leadcapture',
        ],
        'analyst' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'documents', 'videocall',
        ],
        'whatsapp_agent' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'videocall',
            'crm', 'sequences', 'linkedin', 'leadcapture',
        ],
        // Papel "equipe": acesso enxuto focado em agenda/reuniões e e-mail.
        // Dashboard, Agenda, Gravações (videocall), Enviar E-mail + Caixa de
        // Entrada (prospection), Notificações e Minha Conta.
        'equipe' => [
            'dashboard', 'account', 'notifications',
            'agenda', 'videocall', 'prospection',
        ],
        'client' => [
            'dashboard', 'account', 'notifications', 'whatsapp',
            'client_tickets', 'client_schedule', 'documents', 'subusers',
        ],
    ];

    /**
     * Papéis que enxergam TODOS os módulos. O developer é "quase super_admin":
     * acessa todos os módulos; a única diferença (RDO só o dele) é uma regra de
     * ESCOPO de dados, não de acesso ao módulo — aplicada em RdoRules.
     */
    private const FULL_ACCESS_ROLES = ['super_admin', 'developer'];

    /**
     * Módulos DESATIVADOS nesta instalação (helpdesk-brooks).
     *
     * Este projeto é uma cópia de um helpdesk mais completo, mas aqui só se usa
     * um subconjunto das funcionalidades. Em vez de remover o código das abas
     * (sidebar/controllers), ocultamos os módulos aqui: `canAccess()` devolve
     * `false` para qualquer módulo listado, INCLUSIVE para super_admin/developer.
     *
     * Para reexibir um módulo no futuro, basta removê-lo desta lista — nada no
     * sidebar ou nos controllers precisa mudar. Esvaziar a lista restaura o
     * comportamento original (tudo visível conforme a matriz de papéis).
     */
    public const DISABLED_MODULES = [
        'whatsapp',                 // WhatsApp (Chat)
        'rdo',                      // Relatório Diário
        'tickets',                  // Demandas
        'tickets_create',           // Nova Demanda
        'documents',                // Documentos
        'planning',                 // Planejamento
        // Esteira comercial (grupo inteiro)
        'service_catalog',
        'presentations',
        'proposals',
        'contracts',
        'finance',                  // Financeiro
        'onboarding',
        'credentials',
        'provisioning',
        'providers',
        'projects',
        // Performance (grupo inteiro)
        'performance_comercial',
        'performance_operacional',
        // Marketing / social
        'marketing',
        'social',                   // Métricas Sociais
        // CRM (grupo inteiro)
        'crm',
        'crm_apollo',
        'crm_prospecting',
        'sequences',
        'linkedin',
        'leadcapture',
        'leadcapture_admin',
        // Administração
        'acessos',                  // Acessos
        // Área do cliente (alinhada ao rebrand: o painel do cliente fica só com
        // Dashboard, Notificações e Minha Conta). 'documents' já está acima.
        'client_tickets',           // Minhas Demandas + Nova Demanda (cliente)
        'client_schedule',          // Cronograma (cliente)
    ];

    /** O módulo está desativado nesta instalação? */
    public static function isModuleDisabled(string $module): bool
    {
        return in_array($module, self::DISABLED_MODULES, true);
    }

    /** Lista de módulos que um papel pode acessar. */
    public static function allowedModules(?string $role): array
    {
        if ($role !== null && in_array($role, self::FULL_ACCESS_ROLES, true)) {
            return array_values(array_diff(self::MODULES, self::DISABLED_MODULES));
        }
        if ($role === null) {
            return [];
        }
        $modules = self::ROLE_MODULES[$role] ?? [];
        return array_values(array_diff($modules, self::DISABLED_MODULES));
    }

    /** O papel pode acessar o módulo? */
    public static function canAccess(?string $role, string $module): bool
    {
        // Módulos desativados nesta instalação não são acessíveis por ninguém,
        // nem por super_admin/developer. (Lista: DISABLED_MODULES.)
        if (self::isModuleDisabled($module)) {
            return false;
        }
        if ($role !== null && in_array($role, self::FULL_ACCESS_ROLES, true)) {
            // Acesso total, mas só a módulos reconhecidos (evita "true" para lixo).
            return in_array($module, self::MODULES, true);
        }
        return in_array($module, self::allowedModules($role), true);
    }

    /** Este papel tem acesso irrestrito a módulos (super_admin/developer)? */
    public static function hasFullAccess(?string $role): bool
    {
        return $role !== null && in_array($role, self::FULL_ACCESS_ROLES, true);
    }

    /** O papel é um valor válido do enum users.role? */
    public static function isValidRole($role): bool
    {
        return is_string($role) && in_array($role, self::ROLES, true);
    }

    /**
     * Retorna a lista de papéis que podem acessar um módulo. Útil para passar
     * ao requireRole() legado sem duplicar a matriz.
     */
    public static function rolesForModule(string $module): array
    {
        $roles = [];
        foreach (self::ROLES as $role) {
            if (self::canAccess($role, $module)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}
