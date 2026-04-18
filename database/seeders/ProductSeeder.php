<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Organic White Rice',
                'unit' => 'kg',
                'price' => 58.00,
                'description' => 'Premium milled white rice from local farms.',
                'image' => null,
            ],
            [
                'name' => 'Brown Rice',
                'unit' => 'kg',
                'price' => 65.00,
                'description' => 'Whole grain brown rice rich in fiber.',
                'image' => null,
            ],
            [
                'name' => 'Yellow Corn Grain',
                'unit' => 'kg',
                'price' => 32.50,
                'description' => 'Dried yellow corn suitable for feed and milling.',
                'image' => null,
            ],
            [
                'name' => 'Hybrid Corn Seeds',
                'unit' => 'pack',
                'price' => 480.00,
                'description' => 'High-yield hybrid corn seed pack.',
                'image' => null,
            ],
            [
                'name' => 'Tomato Seeds',
                'unit' => 'pack',
                'price' => 95.00,
                'description' => 'Open-pollinated tomato seed variety.',
                'image' => null,
            ],
            [
                'name' => 'Eggplant Seeds',
                'unit' => 'pack',
                'price' => 88.00,
                'description' => 'Fast-germinating eggplant seeds.',
                'image' => null,
            ],
            [
                'name' => 'Chili Pepper Seeds',
                'unit' => 'pack',
                'price' => 110.00,
                'description' => 'Hot chili seed pack for tropical climate.',
                'image' => null,
            ],
            [
                'name' => 'Cabbage Seeds',
                'unit' => 'pack',
                'price' => 102.00,
                'description' => 'Crisp cabbage seed variety for cool zones.',
                'image' => null,
            ],
            [
                'name' => 'Urea Fertilizer 46-0-0',
                'unit' => 'bag',
                'price' => 1250.00,
                'description' => 'Nitrogen fertilizer for vegetative growth.',
                'image' => null,
            ],
            [
                'name' => 'Complete Fertilizer 14-14-14',
                'unit' => 'bag',
                'price' => 1375.00,
                'description' => 'Balanced fertilizer for general crop nutrition.',
                'image' => null,
            ],
            [
                'name' => 'Ammonium Sulfate 21-0-0',
                'unit' => 'bag',
                'price' => 1190.00,
                'description' => 'Sulfur-containing nitrogen fertilizer.',
                'image' => null,
            ],
            [
                'name' => 'Potash 0-0-60',
                'unit' => 'bag',
                'price' => 1460.00,
                'description' => 'Potassium fertilizer for fruiting and root crops.',
                'image' => null,
            ],
            [
                'name' => 'Vermicast Organic Fertilizer',
                'unit' => 'bag',
                'price' => 420.00,
                'description' => 'Organic soil conditioner from earthworm castings.',
                'image' => null,
            ],
            [
                'name' => 'Glyphosate Herbicide',
                'unit' => 'liter',
                'price' => 560.00,
                'description' => 'Broad-spectrum post-emergent herbicide.',
                'image' => null,
            ],
            [
                'name' => 'Insecticide Spray Concentrate',
                'unit' => 'liter',
                'price' => 690.00,
                'description' => 'Concentrated insecticide for common field pests.',
                'image' => null,
            ],
            [
                'name' => 'Fungicide Wettable Powder',
                'unit' => 'kg',
                'price' => 780.00,
                'description' => 'Protective fungicide for leaf and fruit diseases.',
                'image' => null,
            ],
            [
                'name' => 'Mulching Plastic Roll',
                'unit' => 'roll',
                'price' => 950.00,
                'description' => 'UV-resistant mulch plastic for weed control.',
                'image' => null,
            ],
            [
                'name' => 'Drip Irrigation Hose',
                'unit' => 'roll',
                'price' => 1350.00,
                'description' => 'Water-efficient hose for drip irrigation lines.',
                'image' => null,
            ],
            [
                'name' => 'Seedling Tray 200 Holes',
                'unit' => 'piece',
                'price' => 75.00,
                'description' => 'Reusable tray for nursery seedling production.',
                'image' => null,
            ],
            [
                'name' => 'Compost Activator',
                'unit' => 'kg',
                'price' => 230.00,
                'description' => 'Microbial activator to speed up composting.',
                'image' => null,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                $product,
            );
        }
    }
}
