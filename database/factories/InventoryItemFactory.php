<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
class InventoryItemFactory extends Factory {
    public function definition(): array {
        return ['inventory'=>'inventario','category'=>'chemical_materials','name'=>fake()->words(3,true),'quantity'=>10,'unit'=>'piezas','details'=>[],'version'=>1];
    }
}

