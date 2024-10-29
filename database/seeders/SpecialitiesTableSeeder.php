<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpecialitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialities = [
            // Specialities for Renting
            ['service_id' => 1, 'name' => 'House Renting', 'is_active' => true],
            ['service_id' => 1, 'name' => 'Apartments Rentals', 'is_active' => true],
            ['service_id' => 1, 'name' => 'Office Spaces', 'is_active' => true],
        
            // Specialities for Transportation
            ['service_id' => 2, 'name' => 'Airport Transfers', 'is_active' => true],
            ['service_id' => 2, 'name' => 'City Tours', 'is_active' => true],
            ['service_id' => 2, 'name' => 'Car Rentals', 'is_active' => true],
            ['service_id' => 2, 'name' => 'Bike Rentals', 'is_active' => true],
            ['service_id' => 2, 'name' => 'Luxury Car Rentals', 'is_active' => true],
            ['service_id' => 2, 'name' => 'Moving Truck Rentals', 'is_active' => true],
            ['service_id' => 2, 'name' => 'Boat Charters', 'is_active' => true],
            ['service_id' => 2, 'name' => 'RV and Camper Rentals', 'is_active' => true],
        
            // Specialities for Spa
            ['service_id' => 3, 'name' => 'Massage Therapy', 'is_active' => true],
            ['service_id' => 3, 'name' => 'Facial Treatments', 'is_active' => true],
        
            // Specialities for Barber
            ['service_id' => 4, 'name' => 'Haircuts', 'is_active' => true],
            ['service_id' => 4, 'name' => 'Shaving', 'is_active' => true],
        
        ];
        

        // Insert data into the specialities table
        \DB::table('specialities')->insert($specialities);
    }
}
