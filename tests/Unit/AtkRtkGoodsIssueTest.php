<?php

namespace Tests\Unit;

use App\Models\Master\Ticket;
use App\Models\User;
use App\Services\LrtjSpacePortalSignatureService;
use App\Support\AtkRtkGoodsIssue;
use PHPUnit\Framework\TestCase;

class AtkRtkGoodsIssueTest extends TestCase
{
    public function test_goods_issue_signature_rejects_unknown_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LrtjSpacePortalSignatureService)->createGoodsIssueSignature(
            new Ticket,
            new User,
            'unknown'
        );
    }

    public function test_request_below_large_uom_is_not_bulk(): void
    {
        $this->assertFalse(AtkRtkGoodsIssue::isBulk([
            'items' => [[
                'item_id' => 10,
                'quantity' => 11,
                'conversion_qty' => 12,
            ]],
        ]));
    }

    public function test_request_equal_to_large_uom_is_bulk(): void
    {
        $this->assertTrue(AtkRtkGoodsIssue::isBulk([
            'items' => [[
                'item_id' => 10,
                'quantity' => 12,
                'conversion_qty' => 12,
            ]],
        ]));
    }

    public function test_request_above_large_uom_is_bulk(): void
    {
        $this->assertTrue(AtkRtkGoodsIssue::isBulk([
            'items' => [[
                'item_id' => 10,
                'quantity' => 13,
                'conversion_qty' => 12,
            ]],
        ]));
    }

    public function test_mixed_request_is_bulk_when_one_item_reaches_large_uom(): void
    {
        $this->assertTrue(AtkRtkGoodsIssue::isBulk([
            'items' => [
                ['item_id' => 10, 'quantity' => 2, 'conversion_qty' => 12],
                ['item_id' => 20, 'quantity' => 100, 'conversion_qty' => 100],
            ],
        ]));
    }
}
