<?php

namespace Tests\Feature\Api;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private School $school;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant       = Tenant::create(['name' => 'T', 'plan' => 'standard', 'is_active' => true]);
        $this->school = School::create(['tenant_id' => $tenant->id, 'name' => 'École']);
        $user         = User::create([
            'tenant_id' => $tenant->id, 'school_id' => $this->school->id,
            'firstname' => 'Dir', 'lastname' => 'Test', 'email' => 'dir@t.bj',
            'password'  => Hash::make('pass'), 'role' => 'director', 'is_active' => true,
        ]);
        $this->token = $user->createToken('test')->plainTextToken;
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'CE2', 'level' => 'Primaire']);
    }

    public function test_can_create_student_with_parent(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/students', [
            'firstname' => 'Kofi',
            'lastname'  => 'ADJOVI',
            'class_id'  => $this->class->id,
            'gender'    => 'M',
            'parent'    => [
                'firstname'      => 'Pierre',
                'lastname'       => 'ADJOVI',
                'phone_whatsapp' => '97111000',
                'relationship'   => 'father',
            ],
        ])->assertStatus(201)
          ->assertJsonPath('data.firstname', 'Kofi')
          ->assertJsonStructure(['data' => ['id', 'matricule', 'class', 'primaryParent']]);

        $this->assertDatabaseHas('students', ['firstname' => 'Kofi', 'lastname' => 'ADJOVI']);
        $this->assertDatabaseHas('parents', ['phone_whatsapp' => '97111000', 'is_primary' => 1]);
    }

    public function test_student_list_is_paginated(): void
    {
        Student::factory()->count(30)->create([
            'school_id' => $this->school->id,
            'class_id'  => $this->class->id,
            'status'    => 'active',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/students?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'last_page'])
            ->assertJsonPath('per_page', 10);
    }

    public function test_can_search_students(): void
    {
        Student::create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'matricule' => 'EPP-001', 'firstname' => 'Unique', 'lastname' => 'NOM', 'status' => 'active',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/students?search=Unique')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_student_balance(): void
    {
        $student = Student::create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'matricule' => 'EPP-002', 'firstname' => 'A', 'lastname' => 'B', 'status' => 'active',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/students/{$student->id}/balance")
            ->assertOk()
            ->assertJsonStructure(['data' => ['balance', 'overdue']]);
    }

    public function test_cannot_access_other_school_student(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'plan' => 'standard', 'is_active' => true]);
        $otherSchool = School::create(['tenant_id' => $otherTenant->id, 'name' => 'Autre']);
        $otherClass  = SchoolClass::create(['school_id' => $otherSchool->id, 'name' => 'CM1', 'level' => 'Primaire']);
        $otherStudent = Student::create([
            'school_id' => $otherSchool->id, 'class_id' => $otherClass->id,
            'matricule' => 'OTH-001', 'firstname' => 'X', 'lastname' => 'Y', 'status' => 'active',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/students/{$otherStudent->id}")
            ->assertStatus(403);
    }
}
