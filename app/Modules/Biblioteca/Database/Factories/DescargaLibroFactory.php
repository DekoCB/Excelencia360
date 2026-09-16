<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Database\Factories;

use App\Models\User;
use App\Modules\Biblioteca\Models\DescargaLibro;
use App\Modules\Biblioteca\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DescargaLibro>
 */
class DescargaLibroFactory extends Factory
{
    protected $model = DescargaLibro::class;

    public function definition(): array
    {
        return [
            'libro_id' => Libro::factory(),
            'user_id' => User::factory(),
            'descargado_en' => now(),
        ];
    }
}
