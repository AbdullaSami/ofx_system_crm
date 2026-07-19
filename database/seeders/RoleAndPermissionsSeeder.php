<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()["cache"]->forget("spatie.permission.cache");

        // Reset and create roles
        Role::truncate();
        Permission::truncate();

        // Create Permissions
        $models = [
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
            'reports',
        ];

        $actions = [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ];

        foreach ($models as $model) {
            foreach ($actions as $action) {
                Permission::create([
                    'name' => $model . '.' . $action
                ]);
            }
        }
        // Create roles
        $adminRole = Role::create(['name' => 'Admin']);
        $employeeRole = Role::create(['name' => 'Employee']);
        $teamLeadRole = Role::create(['name' => 'Team Lead']);
        $salesRole = Role::create(['name' => 'Sales']);
        $technicalRole = Role::create(['name' => 'Technical']);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ofx.com',
            'password' => bcrypt('password')
        ])->assignRole($adminRole);
    }
}
