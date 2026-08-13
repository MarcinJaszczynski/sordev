<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentScheduleTemplate;
use App\Models\PaymentScheduleTemplateInstallment;
use App\Services\PaymentScheduleTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentScheduleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payment_schedule_templates')) {
            return;
        }

        $service = app(PaymentScheduleTemplateService::class);

        $this->seedNamed($service, 'Standard szkolny 10/90', [
            PaymentScheduleTemplate::APPLIES_GROUP,
            PaymentScheduleTemplate::APPLIES_INDIVIDUAL,
        ], [
            [
                'label' => 'Zaliczka',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
                'due_offset_from_days' => -30,
                'due_offset_to_days' => -30,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Dopłata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 90,
                'due_offset_days' => -14,
                'due_offset_from_days' => -14,
                'due_offset_to_days' => -14,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
        ]);

        $this->seedNamed($service, 'Standard szkolny 10/90 + EUR u pilota', [
            PaymentScheduleTemplate::APPLIES_GROUP,
            PaymentScheduleTemplate::APPLIES_INDIVIDUAL,
        ], [
            [
                'label' => 'Zaliczka',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
                'due_offset_from_days' => -30,
                'due_offset_to_days' => -30,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Dopłata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 90,
                'due_offset_days' => -14,
                'due_offset_from_days' => -14,
                'due_offset_to_days' => -14,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Waluta u pilota',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_FOREIGN,
                'amount_foreign' => 50,
                'currency_code' => 'EUR',
                'due_offset_days' => 0,
                'due_offset_from_days' => 0,
                'due_offset_to_days' => 0,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_PILOT,
                'notes' => 'Płatność w dniu rozpoczęcia imprezy (gotówka u pilota)',
            ],
        ]);

        $this->seedNamed($service, 'Trzy raty 30/40/30', [
            PaymentScheduleTemplate::APPLIES_GROUP,
            PaymentScheduleTemplate::APPLIES_INDIVIDUAL,
        ], [
            [
                'label' => 'I rata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 30,
                'due_offset_days' => -60,
                'due_offset_from_days' => -60,
                'due_offset_to_days' => -60,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'II rata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 40,
                'due_offset_days' => -30,
                'due_offset_from_days' => -30,
                'due_offset_to_days' => -30,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'III rata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 30,
                'due_offset_days' => -14,
                'due_offset_from_days' => -14,
                'due_offset_to_days' => -14,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
        ]);
    }

    /**
     * @param  list<string>|null  $appliesTo
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedNamed(
        PaymentScheduleTemplateService $service,
        string $name,
        ?array $appliesTo,
        array $rows,
    ): void {
        $template = PaymentScheduleTemplate::query()->firstOrCreate(
            ['name' => $name],
            [
                'applies_to' => $appliesTo,
                'version' => 1,
                'is_active' => true,
                'version_notes' => 'Preset systemowy',
            ],
        );

        // Nie nadpisuj ręcznie zmienionych presetów, jeśli mają już transze.
        if ($template->installments()->exists()) {
            return;
        }

        $template->forceFill([
            'applies_to' => $appliesTo,
            'is_active' => true,
        ])->save();

        $service->syncInstallments($template, $rows);
    }
}
