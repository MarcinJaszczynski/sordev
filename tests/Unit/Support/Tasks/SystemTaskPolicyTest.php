<?php

namespace Tests\Unit\Support\Tasks;

use App\Enums\TaskSource;
use App\Models\Task;
use App\Support\Tasks\SystemTaskPolicy;
use Tests\TestCase;

class SystemTaskPolicyTest extends TestCase
{
    public function test_allows_only_inquiry_participant_count_and_confirmed_status(): void
    {
        $this->assertTrue(SystemTaskPolicy::allowsFingerprint('event-inquiry:12'));
        $this->assertTrue(SystemTaskPolicy::allowsFingerprint('event-participant-count:12:10:11'));
        $this->assertTrue(SystemTaskPolicy::allowsFingerprint('event-status:12:confirmed'));

        $this->assertFalse(SystemTaskPolicy::allowsFingerprint('event-status:12:offer'));
        $this->assertFalse(SystemTaskPolicy::allowsFingerprint('event-status:12:to_settle'));
        $this->assertFalse(SystemTaskPolicy::allowsFingerprint('[reservation-task:1:confirm]'));
        $this->assertFalse(SystemTaskPolicy::allowsFingerprint('[payment-reminder:settlement_cost:1:advance]'));
    }

    public function test_description_is_allowed_matches_fingerprints_in_body(): void
    {
        $this->assertTrue(SystemTaskPolicy::descriptionIsAllowed("Opis\n\nevent-status:9:confirmed\n\nLink: x"));
        $this->assertFalse(SystemTaskPolicy::descriptionIsAllowed("Opis\n\nevent-status:9:offer"));
    }

    public function test_task_is_allowed_passes_non_system(): void
    {
        $task = new Task([
            'source' => TaskSource::Office,
            'description' => 'cokolwiek',
        ]);

        $this->assertTrue(SystemTaskPolicy::taskIsAllowed($task));
    }
}
