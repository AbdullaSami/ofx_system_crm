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
        // Create roles
        $AdminRole = Role::create(['name' => 'Admin']);
        $EmployeeRole = Role::create(['name' => 'Employee']);
        $TeamLeadRole = Role::create(['name' => 'Team Lead']);
        $SalesRole = Role::create(['name' => 'Sales']);
        $TechnicalRole = Role::create(['name' => 'Technical']);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ofx.com',
            'password' => bcrypt('password')
        ])->assignRole('Admin');
    }
}
