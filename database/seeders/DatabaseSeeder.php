<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\ParentContact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Super Admin (hors tenant) ─────────────────────────────────
        $superTenant = Tenant::create([
            'name'      => 'EDU PAY PRO — Admin',
            'plan'      => 'enterprise',
            'is_active' => true,
        ]);

        $superSchool = School::create([
            'tenant_id' => $superTenant->id,
            'name'      => 'Admin Système',
        ]);

        User::create([
            'tenant_id' => $superTenant->id,
            'school_id' => $superSchool->id,
            'firstname' => 'Super',
            'lastname'  => 'Admin',
            'email'     => 'admin@edupaypro.bj',
            'password'  => Hash::make('Admin@2025!'),
            'role'      => 'super_admin',
            'is_active' => true,
        ]);

        // ── 2. École démo — Groupe Scolaire Excellence Cotonou ────────────
        $tenant = Tenant::create([
            'name'          => 'Groupe Scolaire Excellence',
            'subdomain'     => 'excellence',
            'plan'          => 'standard',
            'is_active'     => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $school = School::create([
            'tenant_id'     => $tenant->id,
            'name'          => 'Groupe Scolaire Excellence Cotonou',
            'address'       => 'Quartier Cadjehoun, Cotonou',
            'city'          => 'Cotonou',
            'country'       => 'BJ',
            'phone'         => '+22997000001',
            'email'         => 'contact@excellence.bj',
            'rccm'          => 'RCCM-BJ-2010-001234',
            'ifu'           => '1234567890123',
            'director_name' => 'Jean-Pierre KOSSOU',
            'receipt_prefix'=> 'GSE',
        ]);

        // ── 3. Utilisateurs de l'école ────────────────────────────────────
        $director = User::create([
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'firstname' => 'Jean-Pierre',
            'lastname'  => 'KOSSOU',
            'email'     => 'directeur@excellence.bj',
            'password'  => Hash::make('Demo@2025!'),
            'role'      => 'director',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'firstname' => 'Marie',
            'lastname'  => 'AZONKPONON',
            'email'     => 'comptable@excellence.bj',
            'password'  => Hash::make('Demo@2025!'),
            'role'      => 'accountant',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'firstname' => 'Kofi',
            'lastname'  => 'ADJOVI',
            'email'     => 'enseignant@excellence.bj',
            'password'  => Hash::make('Demo@2025!'),
            'role'      => 'teacher',
        ]);

        // ── 4. Année scolaire active ──────────────────────────────────────
        $year = AcademicYear::create([
            'school_id'  => $school->id,
            'label'      => '2024-2025',
            'start_date' => '2024-10-01',
            'end_date'   => '2025-07-31',
            'is_active'  => true,
        ]);

        // ── 5. Classes ────────────────────────────────────────────────────
        $classes = [];
        $classData = [
            ['CP',  'Primaire', 30],
            ['CE1', 'Primaire', 35],
            ['CE2', 'Primaire', 35],
            ['CM1', 'Primaire', 38],
            ['CM2', 'Primaire', 40],
            ['6ème','Secondaire',42],
            ['5ème','Secondaire',42],
            ['4ème','Secondaire',40],
            ['3ème','Secondaire',38],
        ];

        foreach ($classData as $i => [$name, $level, $cap]) {
            $classes[] = SchoolClass::create([
                'school_id'  => $school->id,
                'name'       => $name,
                'level'      => $level,
                'capacity'   => $cap,
                'sort_order' => $i,
            ]);
        }

        // ── 6. Quelques élèves de démo ────────────────────────────────────
        $studentsData = [
            ['Adjaï',    'HOUNKPATIN', 'M', '2015-03-12', '+22997111001', 'Koffi HOUNKPATIN'],
            ['Prisca',   'DOSSOU',     'F', '2014-08-22', '+22996222002', 'Adjoa DOSSOU'],
            ['Rodrigue', 'GANSOU',     'M', '2016-01-05', '+22995333003', 'Pierre GANSOU'],
            ['Sandrine', 'AHOUANMENOU','F', '2015-11-18', '+22994444004', 'Elise AHOUANMENOU'],
            ['Kevin',    'AMOUSSOU',   'M', '2014-06-30', '+22993555005', 'René AMOUSSOU'],
        ];

        foreach ($studentsData as $i => [$fname, $lname, $gender, $dob, $parentPhone, $parentName]) {
            $classIndex = $i % count($classes);
            $seq = $i + 1;

            $student = Student::create([
                'school_id'       => $school->id,
                'class_id'        => $classes[$classIndex]->id,
                'matricule'       => sprintf('EPP-2025-%s-%05d', strtoupper(substr($classes[$classIndex]->name, 0, 3)), $seq),
                'firstname'       => $fname,
                'lastname'        => $lname,
                'gender'          => $gender,
                'birth_date'      => $dob,
                'status'          => 'active',
                'enrollment_date' => '2024-10-01',
            ]);

            [$parentFirst, $parentLast] = explode(' ', $parentName, 2);

            ParentContact::create([
                'student_id'        => $student->id,
                'firstname'         => $parentFirst,
                'lastname'          => $parentLast,
                'relationship'      => $gender === 'M' ? 'father' : 'mother',
                'phone_whatsapp'    => $parentPhone,
                'is_primary'        => true,
                'whatsapp_opted_in' => true,
            ]);
        }

        $this->command->info('✅  Seeder terminé !');
        $this->command->info('');
        $this->command->info('  Super Admin  : admin@edupaypro.bj       / Admin@2025!');
        $this->command->info('  Directeur    : directeur@excellence.bj  / Demo@2025!');
        $this->command->info('  Comptable    : comptable@excellence.bj  / Demo@2025!');
        $this->command->info('  Enseignant   : enseignant@excellence.bj / Demo@2025!');
    }
}
