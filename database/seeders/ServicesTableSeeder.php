<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesTableSeeder extends Seeder
{
    public function run()
    {
        // Sample services data
        $services = [
            [
                'name' => 'Renting',
                'is_active' => true,
            ],
            [
                'name' => 'Transportation',
                'is_active' => true,
            ],
            [
                'name' => 'Spa/Massage',
                'is_active' => true,
            ],
            [
                'name' => 'Barber',
                'is_active' => true,
            ],
        ];

        // Insert data into the services table
        DB::table('services')->insert($services);

    }
}
