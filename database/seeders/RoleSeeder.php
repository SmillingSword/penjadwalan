<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $ownerRole = Role::create(['name' => 'Owner']);
        $adminRole = Role::create(['name' => 'Admin']);
        $memberRole = Role::create(['name' => 'Member']);

        // Create permissions
        $permissions = [
            // Calendar permissions
            'calendar.view',
            'calendar.create',
            'calendar.update',
            'calendar.delete',
            
            // Event permissions
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'event.manage_participants',
            
            // Organization permissions
            'organization.manage',
            'organization.invite_users',
            'organization.remove_users',
            
            // User permissions
            'user.manage_roles',
            
            // Webhook permissions
            'webhook.manage',
            
            // Audit log permissions
            'audit.view',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        
        // Owner has all permissions
        $ownerRole->givePermissionTo(Permission::all());
        
        // Admin has most permissions except organization management and user role management
        $adminRole->givePermissionTo([
            'calendar.view',
            'calendar.create',
            'calendar.update',
            'calendar.delete',
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'event.manage_participants',
            'organization.invite_users',
            'webhook.manage',
            'audit.view',
        ]);
        
        // Member has basic permissions
        $memberRole->givePermissionTo([
            'calendar.view',
            'calendar.create',
            'event.view',
            'event.create',
            'event.update',
        ]);
    }
}
