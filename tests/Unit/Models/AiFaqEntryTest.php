<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiFaqEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiFaqEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_is_cast_to_boolean_and_defaults_to_true(): void
    {
        $entry = AiFaqEntry::create(['question' => 'Q', 'answer' => 'A']);

        $this->assertTrue($entry->fresh()?->enabled);
    }
}
