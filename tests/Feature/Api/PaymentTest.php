<?php

namespace Tests\Feature\Api;

use App\Models\FeeConfig;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MobileMoneyService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Student $student;
    private Installment $installment;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock des services externes
        $this->mock(ReceiptService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn(new Receipt([
                'id' => \Illuminate\Support\Str::uuid(),
                'number' => 'REC-2025-000001',
                'qr_code' => 'ABC123-250101',
            ]));
            $mock->shouldReceive('sendViaWhatsApp')->andReturn(true);
        });

        $this->mock(MobileMoneyService::class, function ($mock) {
            $mock->shouldReceive('initiateMTN')->andReturn([
                'success' => true, 'reference' => 'MTN-REF-001', 'message' => 'OK'
            ]);
            $mock->shouldReceive('checkStatusMTN')->andReturn([
                'success' => true, 'status' => 'successful', 'data' => []
            ]);
        });

        [$this->user, $this->token, $school] = $this->createContext();
        [$this->student, $this->installment] = $this->createStudentWithInstallment($school);
    }

    public function test_can_create_cash_payment(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/payments', [
            'student_id'     => $this->student->id,
            'installment_id' => $this->installment->id,
            'amount'         => 50000,
            'mode'           => 'cash',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.payment.status', 'confirmed')
                 ->assertJsonStructure(['data' => ['payment', 'receipt']]);

        $this->assertDatabaseHas('payments', [
            'student_id' => $this->student->id,
            'amount'     => 50000,
            'mode'       => 'cash',
            'status'     => 'confirmed',
        ]);
    }

    public function test_mtn_momo_payment_stays_pending(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/payments', [
            'student_id'     => $this->student->id,
            'installment_id' => $this->installment->id,
            'amount'         => 50000,
            'mode'           => 'mtn_momo',
            'phone'          => '97000001',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.mobile_money_pending', true);

        $this->assertDatabaseHas('payments', ['status' => 'pending', 'mode' => 'mtn_momo']);
    }

    public function test_can_cancel_payment_with_reason(): void
    {
        $payment = Payment::create([
            'student_id'    => $this->student->id,
            'installment_id'=> $this->installment->id,
            'recorded_by'   => $this->user->id,
            'amount'        => 50000,
            'mode'          => 'cash',
            'status'        => 'confirmed',
            'paid_at'       => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/payments/{$payment->id}/cancel", [
                'reason' => 'Erreur de saisie du montant.',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_already_cancelled_payment(): void
    {
        $payment = Payment::create([
            'student_id'    => $this->student->id,
            'installment_id'=> $this->installment->id,
            'recorded_by'   => $this->user->id,
            'amount'        => 50000,
            'mode'          => 'cash',
            'status'        => 'cancelled',
            'paid_at'       => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/payments/{$payment->id}/cancel", ['reason' => 'Test'])
            ->assertStatus(422);
    }

    public function test_list_payments_with_filters(): void
    {
        Payment::create([
            'student_id' => $this->student->id, 'installment_id' => $this->installment->id,
            'recorded_by' => $this->user->id, 'amount' => 30000, 'mode' => 'cash',
            'status' => 'confirmed', 'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/payments?student_id={$this->student->id}&mode=cash")
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createContext(): array
    {
        $tenant = Tenant::create(['name' => 'Test', 'plan' => 'standard', 'is_active' => true]);
        $school = School::create(['tenant_id' => $tenant->id, 'name' => 'École Test', 'receipt_prefix' => 'TST']);
        $user   = User::create([
            'tenant_id' => $tenant->id, 'school_id' => $school->id,
            'firstname' => 'Test', 'lastname' => 'User', 'email' => 'test@test.bj',
            'password'  => Hash::make('pass'), 'role' => 'accountant', 'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token, $school];
    }

    private function createStudentWithInstallment(School $school): array
    {
        $year   = $school->academicYears()->create(['label' => '2024-2025', 'start_date' => '2024-10-01', 'end_date' => '2025-07-31', 'is_active' => true]);
        $class  = SchoolClass::create(['school_id' => $school->id, 'name' => 'CM2', 'level' => 'Primaire']);
        $student = Student::create([
            'school_id' => $school->id, 'class_id' => $class->id, 'matricule' => 'EPP-001',
            'firstname' => 'Jean', 'lastname' => 'Test', 'status' => 'active',
        ]);
        $student->parents()->create([
            'firstname' => 'Papa', 'lastname' => 'Test', 'relationship' => 'father',
            'phone_whatsapp' => '97000001', 'is_primary' => true, 'whatsapp_opted_in' => true,
        ]);
        $fee  = FeeConfig::create(['class_id' => $class->id, 'academic_year_id' => $year->id, 'label' => 'Scolarité', 'type' => 'tuition', 'total_amount' => 150000]);
        $inst = Installment::create(['fee_config_id' => $fee->id, 'order' => 1, 'label' => '1ère tranche', 'amount' => 50000, 'due_date' => now()->addDays(10)]);
        return [$student, $inst];
    }
}
