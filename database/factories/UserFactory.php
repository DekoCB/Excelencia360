<?php

namespace Database\Factories;

use App\Models\User;
use App\Shared\Enums\EstadoUsuarioEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // La columna ya tiene default 'activo' en la BD, pero sin
            // fijarlo acá el objeto Eloquent en memoria que devuelve
            // create() queda con estado=null (nunca se refresca solo para
            // enterarse del default) -- eso rompe cualquier código que lea
            // ->estado sobre esa misma instancia sin volver a consultarla.
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
