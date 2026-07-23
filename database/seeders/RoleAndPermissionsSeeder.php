<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RoleAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Permission naming convention:
     *   module.view           → list all / show any record
     *   module.view.own       → list own / show own record only
     *   module.create         → create new records
     *   module.update         → update any record
     *   module.update.own     → update only own records
     *   module.delete         → delete any record
     *   module.delete.own     → delete only own records
     *
     * Special permissions:
     *   employees.pay_salary        → pay an employee's salary
     *   employees.pay_commission    → pay an employee's commission
     *   contracts.cancel            → cancel a contract or single service
     */
    public function run(): void
    {
        // Clear Spatie permission cache
        app()['cache']->forget('spatie.permission.cache');

        // Reset all permission/role data
        Schema::disableForeignKeyConstraints();
        Role::truncate();
        Permission::truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('role_has_permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        // ---------------------------------------------------------------
        // 1. Create permissions
        // ---------------------------------------------------------------

        // Modules that support full CRUD + own-scoped variants
        $modules = [
            'users',
            'departments',
            'services',
            'teams',
            'employees',
            'leads',
            'follow-ups',
            'contracts',
            'clients',
            'collections',
            'treasury',
            'expenses',
        ];

        // Standard actions and their .own counterparts
        $standardActions = ['view', 'create', 'update', 'delete'];
        $ownActions      = ['view.own', 'update.own', 'delete.own'];

        foreach ($modules as $module) {
            foreach ($standardActions as $action) {
                Permission::create(['name' => "{$module}.{$action}"]);
            }
            foreach ($ownActions as $action) {
                Permission::create(['name' => "{$module}.{$action}"]);
            }
        }

        // Reports module — view only (read-only dashboard)
        Permission::create(['name' => 'reports.view']);
        Permission::create(['name' => 'reports.view.own']);

        // Special financial / operational permissions
        Permission::create(['name' => 'employees.pay_salary']);
        Permission::create(['name' => 'employees.pay_commission']);
        Permission::create(['name' => 'contracts.cancel']);

        // ---------------------------------------------------------------
        // 2. Create roles and assign permissions
        // ---------------------------------------------------------------

        /** ADMIN — full access to everything */
        $adminRole = Role::create(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        /** TEAM LEAD — broad view rights, own-record mutations */
        $teamLeadRole = Role::create(['name' => 'Team Lead']);
        $teamLeadRole->givePermissionTo([
            // Users — view only
            'users.view',
            // Departments / Services / Teams — view only
            'departments.view',
            'services.view',
            'teams.view',
            // Employees — view
            'employees.view',
            // Leads — full management
            'leads.view',
            'leads.create',
            'leads.update',
            'leads.delete',
            // Follow-ups — full management
            'follow-ups.view',
            'follow-ups.create',
            'follow-ups.update',
            'follow-ups.delete',
            // Clients — view all, own-scoped mutations
            'clients.view',
            'clients.create',
            'clients.update.own',
            'clients.delete.own',
            // Contracts — view all, own-scoped mutations
            'contracts.view',
            'contracts.create',
            'contracts.update.own',
            'contracts.delete.own',
            'contracts.cancel',
            // Collections — view all, own-scoped
            'collections.view',
            'collections.create',
            'collections.update.own',
            'collections.delete.own',
            // Reports — full view
            'reports.view',
            // Expenses — own
            'expenses.view.own',
            'expenses.create',
            'expenses.update.own',
            'expenses.delete.own',
        ]);

        /** SALES — own-scoped access to sales pipeline */
        $salesRole = Role::create(['name' => 'Sales']);
        $salesRole->givePermissionTo([
            // Departments / Services — view only (needed for forms)
            'departments.view',
            'services.view',
            'teams.view',
            // Leads — own
            'leads.view.own',
            'leads.create',
            'leads.update.own',
            'leads.delete.own',
            // Follow-ups — own
            'follow-ups.view.own',
            'follow-ups.create',
            'follow-ups.update.own',
            'follow-ups.delete.own',
            // Clients — own
            'clients.view.own',
            'clients.create',
            'clients.update.own',
            'clients.delete.own',
            // Contracts — own
            'contracts.view.own',
            'contracts.create',
            'contracts.update.own',
            'contracts.cancel',
            // Collections — own
            'collections.view.own',
            'collections.create',
            'collections.update.own',
            // Reports — own only
            'reports.view.own',
        ]);

        /** TECHNICAL — service / contract viewer */
        $technicalRole = Role::create(['name' => 'Technical']);
        $technicalRole->givePermissionTo([
            'departments.view',
            'services.view',
            'teams.view',
            'contracts.view.own',
            'clients.view.own',
            'collections.view.own',
            'reports.view.own',
        ]);

        /** EMPLOYEE — minimal, own-data only */
        $employeeRole = Role::create(['name' => 'Employee']);
        $employeeRole->givePermissionTo([
            'departments.view',
            'services.view',
            'leads.view.own',
            'clients.view.own',
            'contracts.view.own',
            'collections.view.own',
            'follow-ups.view.own',
            'follow-ups.create',
            'reports.view.own',
        ]);

        // ---------------------------------------------------------------
        // 3. Create / update the Admin superuser
        // ---------------------------------------------------------------
        $adminUser = User::firstOrCreate(
            ['email' => 'test_admin@ofx.com'],
            [
                'name'     => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );
        $adminUser->syncRoles([$adminRole]);
        $adminUser->givePermissionTo(Permission::all());
    }
}
