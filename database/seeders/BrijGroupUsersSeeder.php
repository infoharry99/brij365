<?php

/**
 * ══════════════════════════════════════════════════════════════════════
 * USER CREATION — Builder360 Production Users
 * FILE: database/seeders/BrijGroupUsersSeeder.php
 *
 * Run: php artisan db:seed --class=BrijGroupUsersSeeder
 * ══════════════════════════════════════════════════════════════════════
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BrijGroupUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Get company_id = 1 (Brij Group)
        $companyId = 1;

        // Role IDs from your roles table
        $roles = [
            'director'   => 1,
            'hr_manager' => 5,
            'sales_head' => 2,
            'employee'   => 7,
        ];

        $users = [
            // ── Directors ────────────────────────────────────────────
            [
                'role_id'    => $roles['director'],
                'company_id' => $companyId,
                'name'       => 'Harshvardhan',
                'email'      => 'harshvardhan@brijgroup.in',
                'password'   => 'dhanDirectorharshvar@Brij2026',
            ],
            [
                'role_id'    => $roles['director'],
                'company_id' => $companyId,
                'name'       => 'Krunal',
                'email'      => 'krunal@brijgroup.in',
                'password'   => 'DirectorkrunalDD@Brij2026',
            ],
            [
                'role_id'    => $roles['director'],
                'company_id' => $companyId,
                'name'       => 'Arpan',
                'email'      => 'arpan@brijgroup.in',
                'password'   => 'Directorarpanhr@Brij2026',
            ],

            // ── HR ───────────────────────────────────────────────────
            [
                'role_id'    => $roles['hr_manager'],
                'company_id' => $companyId,
                'name'       => 'Akshay',
                'email'      => 'akshay@brijgroup.in',
                'password'   => 'akshayhHr@Brij2026',
            ],

            // ── Sales Heads ──────────────────────────────────────────
            [
                'role_id'    => $roles['sales_head'],
                'company_id' => $companyId,
                'name'       => 'Mohit',
                'email'      => 'mohit@brijgroup.in',
                'password'   => 'SalesSales@Brij2026',
            ],
            [
                'role_id'    => $roles['sales_head'],
                'company_id' => $companyId,
                'name'       => 'Romil',
                'email'      => 'romil@brijgroup.in',
                'password'   => 'Sales@romilBrij2026',
            ],
            [
                'role_id'    => $roles['sales_head'],
                'company_id' => $companyId,
                'name'       => 'Ks',
                'email'      => 'ks@brijgroup.in',
                'password'   => 'ksSales@Brij2026',
            ],

            // ── Employees ────────────────────────────────────────────
            [
                'role_id'    => $roles['employee'],
                'company_id' => $companyId,
                'name'       => 'Jagrut',
                'email'      => 'jagrut@brijgroup.in',
                'password'   => 'Employeejagrut@Brij2026',
            ],
            [
                'role_id'    => $roles['employee'],
                'company_id' => $companyId,
                'name'       => 'Info',
                'email'      => 'info@brijgroup.in',
                'password'   => 'infoEmployee@Brij2026',
            ],
        ];

        foreach ($users as $userData) {
            $password = $userData['password'];
            unset($userData['password']);

            DB::table('users')->updateOrInsert(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password'          => Hash::make($password),
                    'status'            => 'active',
                    'email_verified_at' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])
            );

            $this->command->info("✓ Created: {$userData['email']}");
        }

        $this->command->newLine();
        $this->command->info('All users created successfully.');
    }
}