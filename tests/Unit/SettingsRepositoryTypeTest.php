<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SettingsRepositoryTypeTest extends TestCase
{
    /** Documents the expected JSON representation used by the settings repository. */
    public function test_json_round_trip_contract_is_utf8_safe(): void
    {
        $payload = ['site_name' => 'اتوکار', 'maintenance' => false];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        self::assertSame($payload, json_decode($encoded, true, flags: JSON_THROW_ON_ERROR));
    }
}
