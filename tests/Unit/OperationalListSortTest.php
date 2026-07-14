<?php

namespace Tests\Unit;

use App\Support\OperationalListSort;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Tests\TestCase;

class OperationalListSortTest extends TestCase
{
    public function test_apply_to_query_orders_by_updated_and_created_at(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('orderByDesc')->once()->with('updated_at')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->once()->with('created_at')->andReturnSelf();

        $result = OperationalListSort::applyToQuery($query);

        $this->assertSame($query, $result);
    }
}
