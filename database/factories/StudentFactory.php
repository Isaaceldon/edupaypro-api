<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $gender    = $this->faker->randomElement(['M', 'F']);
        $firstname = $gender === 'M'
            ? $this->faker->randomElement(['Kofi', 'Koffi', 'Pierre', 'Jean', 'Marc', 'André', 'Rodrigue'])
            : $this->faker->randomElement(['Afi', 'Akosua', 'Marie', 'Sandrine', 'Prisca', 'Carine']);

        $lastnames = ['ADJOVI', 'HOUNKPATIN', 'DOSSOU', 'GANSOU', 'AMOUSSOU', 'AZONKPONON', 'KOSSOU', 'AGOSSOU'];

        return [
            'matricule'       => 'EPP-' . now()->year . '-' . strtoupper($this->faker->lexify('???')) . '-' . $this->faker->unique()->numberBetween(1, 99999),
            'firstname'       => $firstname,
            'lastname'        => $this->faker->randomElement($lastnames),
            'gender'          => $gender,
            'birth_date'      => $this->faker->dateTimeBetween('-16 years', '-5 years'),
            'birth_place'     => $this->faker->randomElement(['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey']),
            'status'          => 'active',
            'enrollment_date' => now()->startOfYear(),
            'nationality'     => 'BJ',
        ];
    }
}
