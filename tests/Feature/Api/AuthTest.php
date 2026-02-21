<?php

namespace Tests\Feature\Api;

use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Register ─────────────────────────────────────────────────────────

    public function test_school_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstname'             => 'Jean',
            'lastname'              => 'KOSSOU',
            'email'                 => 'directeur@test.bj',
            'password'              => 'Secret@2025!',
            'password_confirmation' => 'Secret@2025!',
            'school_name'           => 'École Test Cotonou',
            'city'                  => 'Cotonou',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => ['user', 'school', 'token', 'trial_ends_at'],
                 ]);

        $this->assertDatabaseHas('tenants', ['name' => 'École Test Cotonou']);
        $this->assertDatabaseHas('schools', ['name' => 'École Test Cotonou']);
        $this->assertDatabaseHas('users',   ['email' => 'directeur@test.bj', 'role' => 'director']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $tenant = Tenant::create(['name' => 'Exist', 'plan' => 'standard', 'is_active' => true]);
        $school = School::create(['tenant_id' => $tenant->id, 'name' => 'Exist']);
        User::create([
            'tenant_id' => $tenant->id, 'school_id' => $school->id,
            'firstname' => 'X', 'lastname' => 'Y',
            'email' => 'dup@test.bj', 'password' => Hash::make('pass'), 'role' => 'director',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'firstname' => 'A', 'lastname' => 'B',
            'email' => 'dup@test.bj',
            'password' => 'Secret@2025!', 'password_confirmation' => 'Secret@2025!',
            'school_name' => 'Autre École',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // ── Login ─────────────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        [$user] = $this->createUserWithSchool();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Secret@2025!',
        ])->assertOk()
          ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        [$user] = $this->createUserWithSchool();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'mauvais',
        ])->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        [$user] = $this->createUserWithSchool(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Secret@2025!',
        ])->assertStatus(403);
    }

    // ── Me ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_get_profile(): void
    {
        [$user, $token] = $this->createUserWithSchool();

        $this->withToken($token)
             ->getJson('/api/v1/auth/me')
             ->assertOk()
             ->assertJsonPath('data.email', $user->email)
             ->assertJsonPath('data.role', 'director');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        [, $token] = $this->createUserWithSchool();

        $this->withToken($token)
             ->postJson('/api/v1/auth/logout')
             ->assertOk();

        // Le token ne fonctionne plus
        $this->withToken($token)
             ->getJson('/api/v1/auth/me')
             ->assertStatus(401);
    }

    // ── Academic Years (Module A) ─────────────────────────────────────────

    public function test_director_can_create_academic_year(): void
    {
        [, $token] = $this->createUserWithSchool();

        $this->withToken($token)
             ->postJson('/api/v1/academic-years', [
                 'label'      => '2024-2025',
                 'start_date' => '2024-10-01',
                 'end_date'   => '2025-07-31',
             ])->assertStatus(201)
               ->assertJsonPath('data.label', '2024-2025');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createUserWithSchool(array $overrides = []): array
    {
        $tenant = Tenant::create(['name' => 'Test', 'plan' => 'standard', 'is_active' => true]);
        $school = School::create(['tenant_id' => $tenant->id, 'name' => 'École Test']);
        $user   = User::create(array_merge([
            'tenant_id' => $tenant->id,
            'school_id' => $school->id,
            'firstname' => 'Test',
            'lastname'  => 'User',
            'email'     => 'test@example.bj',
            'password'  => Hash::make('Secret@2025!'),
            'role'      => 'director',
            'is_active' => true,
        ], $overrides));

        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token, $school, $tenant];
    }
}
