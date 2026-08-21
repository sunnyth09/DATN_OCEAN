<?php

namespace Tests\Unit;

use App\Helpers\OceanTimestampHelper;
use Carbon\Carbon;
use Tests\TestCase;

class OceanTimestampHelperTest extends TestCase
{
    public function test_parses_iso_8601_utc_string(): void
    {
        $payload = ['timestamp' => '2026-08-21T03:09:10Z'];
        $carbon = OceanTimestampHelper::parseOceanTimestamp($payload);

        $this->assertSame('Asia/Ho_Chi_Minh', $carbon->getTimezone()->getName());
        $this->assertSame('2026-08-21 10:09:10', $carbon->format('Y-m-d H:i:s'));
    }

    public function test_parses_plain_utc_datetime_string(): void
    {
        $payload = ['timestamp' => '2026-08-21 04:41:00'];
        $carbon = OceanTimestampHelper::parseOceanTimestamp($payload);

        $this->assertSame('Asia/Ho_Chi_Minh', $carbon->getTimezone()->getName());
        $this->assertSame('2026-08-21 11:41:00', $carbon->format('Y-m-d H:i:s'));
    }

    public function test_parses_epoch_milliseconds(): void
    {
        // 1787285537000 = 2026-08-21T04:12:17Z -> 11:12:17 in GMT+7
        $payload = ['timestamp_epoch' => 1787285537000];
        $carbon = OceanTimestampHelper::parseOceanTimestamp($payload);

        $this->assertSame('Asia/Ho_Chi_Minh', $carbon->getTimezone()->getName());
        $this->assertSame('2026-08-21 11:12:17', $carbon->format('Y-m-d H:i:s'));
    }

    public function test_parses_created_at_and_happened_at_fallbacks(): void
    {
        $payload = ['happened_at' => '2026-08-21T01:00:00Z'];
        $carbon = OceanTimestampHelper::parseOceanTimestamp($payload);

        $this->assertSame('2026-08-21 08:00:00', $carbon->format('Y-m-d H:i:s'));
    }

    public function test_fallback_to_current_time_when_empty(): void
    {
        $before = Carbon::now('Asia/Ho_Chi_Minh');
        $carbon = OceanTimestampHelper::parseOceanTimestamp([]);
        $after = Carbon::now('Asia/Ho_Chi_Minh');

        $this->assertSame('Asia/Ho_Chi_Minh', $carbon->getTimezone()->getName());
        $this->assertTrue($carbon->betweenIncluded($before->subSecond(), $after->addSecond()));
    }
}
