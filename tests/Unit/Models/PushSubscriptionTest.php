<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    public function test_fillable_contains_expected_fields(): void
    {
        $subscription = new PushSubscription();

        $this->assertSame(['user_id', 'endpoint', 'public_key', 'auth_token'], $subscription->getFillable());
    }

    public function test_user_relation_targets_user_model(): void
    {
        $subscription = new PushSubscription();

        $relation = $subscription->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
    }
}
