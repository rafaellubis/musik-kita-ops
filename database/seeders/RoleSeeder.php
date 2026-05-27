<?php
 
namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
 
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'Owner']);
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Auditor']);
        Role::firstOrCreate(['name' => 'Guru']);
    }
}
