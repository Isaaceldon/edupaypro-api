<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\FeeConfig;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private School $school;
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant      = Tenant::create(['name' => 'T', 'plan' => 'standard', 'is_active' => true]);
        $this->school = School::create(['tenant_id' => $tenant->id, 'name' => 'École', 'receipt_prefix' => 'TST']);
        $this->year  = AcademicYear::create([
            'school_id' => $this->school->id, 'label' => '2024-2025',
            'start_date' => '2024-10-01', 'end_date' => '2025-07-31', 'is_active' => true,
        ]);
        $user = User::create([
            'tenant_id' => $tenant->id, 'school_id' => $this->school->id,
            'firstname' => 'D', 'lastname' => 'T', 'email' => 'dir@t.bj',
            'password'  => Hash::make('pass'), 'role' => 'director', 'is_active' => true,
        ]);
        $this->token = $user->createToken('test')->plainTextToken;
    }

    public function test_dashboard_returns_expected_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'academic_year',
                    'students' => ['total', 'classes', 'overdue'],
                    'financial' => ['total_due', 'total_collected', 'remaining', 'recovery_rate'],
                    'reminders' => ['sent', 'read', 'open_rate'],
                    'recent_payments',
                ],
            ]);
    }

    public function test_monthly_evolution_returns_12_months(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/monthly')
            ->assertOk()
            ->assertJsonCount(12, 'data');
    }

    public function test_forecast_returns_upcoming_installments(): void
    {
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'CM2', 'level' => 'Primaire']);
        $fee   = FeeConfig::create(['class_id' => $class->id, 'academic_year_id' => $this->year->id, 'label' => 'Sc', 'type' => 'tuition', 'total_amount' => 60000]);
        Installment::create(['fee_config_id' => $fee->id, 'order' => 1, 'label' => 'T1', 'amount' => 20000, 'due_date' => now()->addDays(10)]);
        Installment::create(['fee_config_id' => $fee->id, 'order' => 2, 'label' => 'T2', 'amount' => 20000, 'due_date' => now()->addDays(45)]);
        Installment::create(['fee_config_id' => $fee->id, 'order' => 3, 'label' => 'T3', 'amount' => 20000, 'due_date' => now()->addDays(75)]);

        $this->withToken($this->token)
            ->getJson('/api/v1/dashboard/forecast')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_recovery_rate_computed_correctly(): void
    {
        $class   = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'CE1', 'level' => 'Primaire']);
        $student = Student::create(['school_id' => $this->school->id, 'class_id' => $class->id, 'matricule' => 'E001', 'firstname' => 'A', 'lastname' => 'B', 'status' => 'active']);
        $user    = User::first();
        $fee     = FeeConfig::create(['class_id' => $class->id, 'academic_year_id' => $this->year->id, 'label' => 'Sc', 'type' => 'tuition', 'total_amount' => 100000]);
        $inst    = Installment::create(['fee_config_id' => $fee->id, 'order' => 1, 'label' => 'T1', 'amount' => 100000, 'due_date' => now()->addDays(5)]);

        Payment::create([
            'student_id' => $student->id, 'installment_id' => $inst->id, 'recorded_by' => $user->id,
            'amount' => 50000, 'mode' => 'cash', 'status' => 'confirmed', 'paid_at' => now(),
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk();
        // 50000 collecté sur 100000 dû = 50% de taux
        $rate = $response->json('data.financial.recovery_rate');
        $this->assertEquals(50.0, $rate);
    }
}
