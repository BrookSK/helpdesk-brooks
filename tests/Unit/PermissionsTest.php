<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Permissions;

/**
 * Testes unitários da matriz de permissões (Permissions) — sem banco.
 * Garante que super_admin/developer têm acesso total, que o WhatsApp Chat é
 * liberado a todos, que cada papel segmentado só vê seus módulos e que o
 * client fica restrito à área do cliente.
 */
final class PermissionsTest extends TestCase
{
    // ---- Acesso total ----
    //
    // NOTA (helpdesk-brooks): esta instalação DESATIVA um subconjunto de
    // módulos (Permissions::DISABLED_MODULES) para toda a base, inclusive para
    // super_admin/developer. Por isso "acesso total" agora significa "todos os
    // módulos NÃO desativados". Os testes abaixo refletem essa regra atual.

    public function testSuperAdminAcessaTodosOsModulosNaoDesativados(): void
    {
        foreach (Permissions::MODULES as $mod) {
            if (Permissions::isModuleDisabled($mod)) {
                $this->assertFalse(Permissions::canAccess('super_admin', $mod), "módulo desativado {$mod} não deve ser acessível nem ao super_admin");
                continue;
            }
            $this->assertTrue(Permissions::canAccess('super_admin', $mod), "super_admin deveria acessar {$mod}");
        }
        $this->assertTrue(Permissions::hasFullAccess('super_admin'));
    }

    public function testDeveloperAcessaTodosOsModulosNaoDesativados(): void
    {
        foreach (Permissions::MODULES as $mod) {
            if (Permissions::isModuleDisabled($mod)) {
                $this->assertFalse(Permissions::canAccess('developer', $mod), "módulo desativado {$mod} não deve ser acessível nem ao developer");
                continue;
            }
            $this->assertTrue(Permissions::canAccess('developer', $mod), "developer deveria acessar {$mod}");
        }
        $this->assertTrue(Permissions::hasFullAccess('developer'));
    }

    // ---- Módulos desativados nesta instalação ----

    public function testModulosDesativadosNaoSaoAcessiveisPorNinguem(): void
    {
        foreach (Permissions::DISABLED_MODULES as $mod) {
            foreach (Permissions::ROLES as $role) {
                $this->assertFalse(
                    Permissions::canAccess($role, $mod),
                    "módulo desativado {$mod} não deveria ser acessível por {$role}"
                );
            }
        }
    }

    public function testModulosMantidosContinuamAcessiveisAoSuperAdmin(): void
    {
        // Núcleo que DEVE permanecer visível nesta instalação.
        foreach (['dashboard', 'account', 'notifications', 'agenda', 'videocall',
                  'prospection', 'companies', 'users', 'settings'] as $mod) {
            $this->assertFalse(Permissions::isModuleDisabled($mod), "{$mod} não deveria estar na lista de desativados");
            $this->assertTrue(Permissions::canAccess('super_admin', $mod), "super_admin deveria acessar {$mod}");
        }
    }

    public function testAllowedModulesNaoIncluiDesativados(): void
    {
        $mods = Permissions::allowedModules('super_admin');
        foreach (Permissions::DISABLED_MODULES as $disabled) {
            $this->assertNotContains($disabled, $mods, "allowedModules não deveria conter o módulo desativado {$disabled}");
        }
    }

    // ---- WhatsApp Chat ----
    // WhatsApp está desativado nesta instalação; garante que ninguém o acessa.

    public function testWhatsappDesativadoParaTodosOsPapeis(): void
    {
        $this->assertTrue(Permissions::isModuleDisabled('whatsapp'));
        foreach (Permissions::ROLES as $role) {
            $this->assertFalse(Permissions::canAccess($role, 'whatsapp'), "{$role} NÃO deveria acessar whatsapp (desativado)");
        }
    }

    // ---- RDO (desativado) ----

    public function testRdoDesativadoParaTodos(): void
    {
        $this->assertTrue(Permissions::isModuleDisabled('rdo'));
        foreach (Permissions::ROLES as $role) {
            $this->assertFalse(Permissions::canAccess($role, 'rdo'), "{$role} NÃO deveria acessar rdo (desativado)");
        }
    }

    // ---- Marketing segmentado ----
    // Com marketing/social/crm desativados, o papel 'marketing' fica só com o
    // que sobra de sua lista (prospection permanece ativo).

    public function testMarketingNaoVeModulosDesativados(): void
    {
        $this->assertTrue(Permissions::canAccess('marketing', 'prospection'));
        // Desativados nesta instalação
        $this->assertFalse(Permissions::canAccess('marketing', 'marketing'));
        $this->assertFalse(Permissions::canAccess('marketing', 'social'));
        $this->assertFalse(Permissions::canAccess('marketing', 'crm'));
        // Nunca esteve na lista do papel
        $this->assertFalse(Permissions::canAccess('marketing', 'users'));
        $this->assertFalse(Permissions::canAccess('marketing', 'companies'));
        $this->assertFalse(Permissions::hasFullAccess('marketing'));
    }

    // ---- Comercial segmentado ----
    // Agenda permanece ativa; CRM/performance_comercial foram desativados.

    public function testComercialVeAgendaMasNaoModulosDesativados(): void
    {
        $this->assertTrue(Permissions::canAccess('comercial', 'agenda'));
        $this->assertTrue(Permissions::canAccess('comercial', 'prospection'));
        $this->assertFalse(Permissions::canAccess('comercial', 'crm'));
        $this->assertFalse(Permissions::canAccess('comercial', 'performance_comercial'));
        $this->assertFalse(Permissions::canAccess('comercial', 'marketing'));
        $this->assertFalse(Permissions::canAccess('comercial', 'users'));
        $this->assertFalse(Permissions::canAccess('comercial', 'settings'));
    }

    // ---- Papel "equipe" ----

    public function testEquipeVeApenasSeuEscopo(): void
    {
        // Deve ver: dashboard, account, notifications, agenda, videocall, prospection.
        foreach (['dashboard', 'account', 'notifications', 'agenda', 'videocall', 'prospection'] as $mod) {
            $this->assertTrue(Permissions::canAccess('equipe', $mod), "equipe deveria acessar {$mod}");
        }
        // Não deve ver módulos internos/admin nem a área do cliente.
        foreach (['tickets', 'crm', 'marketing', 'social', 'planning', 'documents',
                  'users', 'companies', 'settings', 'acessos', 'client_tickets'] as $mod) {
            $this->assertFalse(Permissions::canAccess('equipe', $mod), "equipe NÃO deveria acessar {$mod}");
        }
        $this->assertFalse(Permissions::hasFullAccess('equipe'));
    }

    public function testEquipeEhPapelValido(): void
    {
        $this->assertTrue(Permissions::isValidRole('equipe'));
        $this->assertContains('equipe', Permissions::ROLES);
    }

    // ---- Client restrito ----

    public function testClientRestritoAoNucleoMantido(): void
    {
        // Núcleo do painel do cliente que permanece nesta instalação.
        $this->assertTrue(Permissions::canAccess('client', 'dashboard'));
        $this->assertTrue(Permissions::canAccess('client', 'account'));
        $this->assertTrue(Permissions::canAccess('client', 'notifications'));
        // Demandas/cronograma/documentos do cliente foram desativados no rebrand.
        $this->assertFalse(Permissions::canAccess('client', 'client_tickets'));
        $this->assertFalse(Permissions::canAccess('client', 'client_schedule'));
        $this->assertFalse(Permissions::canAccess('client', 'documents'));
        // Nada de módulos internos.
        $this->assertFalse(Permissions::canAccess('client', 'crm'));
        $this->assertFalse(Permissions::canAccess('client', 'marketing'));
        $this->assertFalse(Permissions::canAccess('client', 'tickets'));
        $this->assertFalse(Permissions::canAccess('client', 'users'));
        $this->assertFalse(Permissions::hasFullAccess('client'));
    }

    // ---- Administração só para acesso total ----

    public function testAdministracaoRestritaASuperAdminEDeveloper(): void
    {
        foreach (['users', 'companies', 'settings'] as $mod) {
            $this->assertSame(
                ['super_admin', 'developer'],
                Permissions::rolesForModule($mod),
                "somente super_admin e developer deveriam acessar {$mod}"
            );
        }
    }

    // ---- Robustez ----

    public function testPapelNuloOuDesconhecidoNaoAcessaNada(): void
    {
        $this->assertFalse(Permissions::canAccess(null, 'dashboard'));
        $this->assertFalse(Permissions::canAccess('inexistente', 'dashboard'));
        $this->assertSame([], Permissions::allowedModules('inexistente'));
    }

    public function testCanAccessNaoRetornaTrueParaModuloInexistente(): void
    {
        // Mesmo super_admin não "acessa" um módulo que não existe no catálogo.
        $this->assertFalse(Permissions::canAccess('super_admin', 'modulo_que_nao_existe'));
    }

    public function testRolesForModuleDesativadoRetornaVazio(): void
    {
        // whatsapp está desativado nesta instalação: nenhum papel o acessa,
        // então rolesForModule deve retornar lista vazia.
        $this->assertSame([], Permissions::rolesForModule('whatsapp'));
    }

    public function testRolesForModuleAtivoIncluiSuperAdmin(): void
    {
        // Um módulo mantido (agenda) deve listar ao menos super_admin/developer.
        $roles = Permissions::rolesForModule('agenda');
        $this->assertContains('super_admin', $roles);
        $this->assertContains('developer', $roles);
    }

    // ---- isValidRole ----

    public function testIsValidRoleAceitaTodosOsPapeisConhecidos(): void
    {
        foreach (Permissions::ROLES as $role) {
            $this->assertTrue(Permissions::isValidRole($role), "{$role} deveria ser válido");
        }
    }

    public function testIsValidRoleRejeitaLixo(): void
    {
        $this->assertFalse(Permissions::isValidRole('root'));
        $this->assertFalse(Permissions::isValidRole('admin'));
        $this->assertFalse(Permissions::isValidRole(''));
        $this->assertFalse(Permissions::isValidRole(null));
        $this->assertFalse(Permissions::isValidRole(123));
    }
}
