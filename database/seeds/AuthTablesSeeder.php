<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Users table
        DB::table('users')->truncate();
        DB::table('users')->delete();

        \App\User::query()->create([
            'name' => 'Default Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'activation_status' => 1,
            'created_at' => Carbon\Carbon::now(),
            'updated_at' => Carbon\Carbon::now()
        ]);

        echo "\033[32m Users successfully inserted. \033[0m".PHP_EOL;

        // Roles table
        DB::table('roles')->truncate();
        DB::table('roles')->delete();

        \App\Role::query()->create([
            'role_name' => 'Administrator',
            'role_slug' => 'administrator',
            'role_permissions' => [
                'admin-access' => true,
            ]
        ]);

        \App\Role::query()->create([
            'role_name' => 'Subscriber',
            'role_slug' => 'subscriber',
            'role_permissions' => [
                'subscriber-access' => true,
            ]
        ]);

        echo "\033[32m Roles successfully inserted. \033[0m".PHP_EOL;

        // Attach Role to Users
        DB::table('role_user')->truncate();
        DB::table('role_user')->delete();

        DB::table('role_user')->insert([
            [
                'role_id' => 1,
                'user_id' => 1
            ]
        ]);

        echo "\033[32m Roles attached to users successfully. \033[0m".PHP_EOL;
    }
}
