<?php

namespace App\Services\Tfg;

interface TfgFeedClientInterface
{
    /**
     * @return array{feed_identifier: string, sync_status: string, sync_errors: array}
     */
    public function submitFeed(string $jsonPath): array;

    /**
     * @return array{async_status: string, async_errors: array}
     */
    public function getFeedStatus(string $feedIdentifier): array;
}
