<?php

namespace Tests\Feature;

use App\Enums\ContractorSettlementForm;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PilotSettlementFormAndBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_stores_bank_account_and_settlement_form(): void
    {
        if (! Schema::hasColumn('contractors', 'bank_account')
            || ! Schema::hasColumn('contractors', 'settlement_form')) {
            $this->markTestSkipped('Brak kolumn bank_account / settlement_form.');
        }

        $contractor = Contractor::query()->create([
            'name' => 'Pilot Test',
            'status' => 'active',
            'bank_account' => 'PL61109010140000071219812874',
            'settlement_form' => ContractorSettlementForm::Invoice,
        ]);

        $contractor->refresh();

        $this->assertSame('PL61109010140000071219812874', $contractor->bank_account);
        $this->assertSame(ContractorSettlementForm::Invoice, $contractor->settlement_form);
    }

    public function test_event_inherits_pilot_settlement_form_from_contractor(): void
    {
        if (! Schema::hasColumn('events', 'pilot_settlement_form')
            || ! Schema::hasColumn('contractors', 'settlement_form')) {
            $this->markTestSkipped('Brak kolumn pilot_settlement_form / settlement_form.');
        }

        $contractor = Contractor::query()->create([
            'name' => 'Pilot UoD',
            'status' => 'active',
            'settlement_form' => ContractorSettlementForm::ContractOfWork,
        ]);

        if (method_exists(ContractorType::class, 'idsForNames')) {
            $typeIds = ContractorType::idsForNames(['pilot']);
            if ($typeIds !== []) {
                $contractor->types()->syncWithoutDetaching($typeIds);
            }
        }

        $event = Event::factory()->create([
            'pilot_contractor_id' => $contractor->id,
            'pilot_settlement_form' => null,
        ]);

        $this->assertSame(ContractorSettlementForm::ContractOfWork, $event->resolvePilotSettlementForm());
        $this->assertSame('Umowa o dzieło', $event->resolvedPilotSettlementFormLabel());

        $event->update(['pilot_settlement_form' => ContractorSettlementForm::Invoice]);
        $event->refresh();

        $this->assertSame(ContractorSettlementForm::Invoice, $event->resolvePilotSettlementForm());
        $this->assertSame('Faktura', $event->resolvedPilotSettlementFormLabel());
    }

    public function test_contractor_contact_details_include_bank_account(): void
    {
        if (! Schema::hasColumn('contractors', 'bank_account')) {
            $this->markTestSkipped('Brak kolumny bank_account.');
        }

        $contractor = Contractor::query()->create([
            'name' => 'Firma Konto',
            'status' => 'active',
            'phone' => '500600700',
            'bank_account' => '12 3456 7890',
        ]);

        $meta = \App\Support\ContractorContactDetails::contractorMeta($contractor);
        $lines = \App\Support\ContractorContactDetails::displayLines($contractor);

        $this->assertSame('12 3456 7890', $meta['bank_account']);
        $this->assertTrue(collect($lines)->contains(fn (string $line): bool => str_contains($line, '12 3456 7890')));
    }
}
