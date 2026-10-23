<?php

namespace Tests\Unit;

use App\Support\TicketWorkflowPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketWorkflowPresenterTest extends TestCase
{
    #[DataProvider('workflowProvider')]
    public function test_it_presents_the_current_workflow_stage(array $payload, string $expectedLabel, int $expectedStep, int $expectedTotal): void
    {
        $result = TicketWorkflowPresenter::make($payload, 'Open');

        $this->assertSame($expectedLabel, $result['label']);
        $this->assertSame($expectedStep, $result['step']);
        $this->assertSame($expectedTotal, $result['total']);
        $this->assertGreaterThanOrEqual(0, $result['percentage']);
        $this->assertLessThanOrEqual(100, $result['percentage']);
    }

    public static function workflowProvider(): array
    {
        return [
            'consumption waiting manager' => [
                ['request_type' => 'consumption', 'workflow_status' => 'WAITING_MANAGER_APPROVAL'],
                'Menunggu approval atasan', 1, 7,
            ],
            'consumption accountability' => [
                ['request_type' => 'consumption', 'workflow_status' => 'WAITING_ACCOUNTABILITY'],
                'Menunggu pertanggungjawaban', 6, 7,
            ],
            'consumption reopened' => [
                ['request_type' => 'consumption', 'workflow_status' => 'REOPENED'],
                'Dibuka kembali', 5, 7,
            ],
            'atk procurement' => [
                ['request_type' => 'atk_rtk', 'workflow_status' => 'WAITING_PROCUREMENT', 'total_estimated_amount' => 150000, 'approval_threshold' => 100000],
                'Dalam proses pengadaan', 3, 5,
            ],
            'atk ready' => [
                ['request_type' => 'atk_rtk', 'workflow_status' => 'READY_TO_HANDOVER', 'total_estimated_amount' => 150000, 'approval_threshold' => 100000],
                'Siap diserahkan', 4, 5,
            ],
            'atk without manager approval' => [
                ['request_type' => 'atk_rtk', 'workflow_status' => 'READY_TO_HANDOVER', 'total_estimated_amount' => 50000, 'approval_threshold' => 100000],
                'Siap diserahkan', 3, 4,
            ],
            'ga in progress' => [
                ['request_type' => 'ga_request_finding', 'workflow_status' => 'IN_PROGRESS'],
                'Sedang ditindaklanjuti', 2, 3,
            ],
            'ga closed' => [
                ['request_type' => 'ga_request_finding', 'workflow_status' => 'CLOSED'],
                'Selesai ditindaklanjuti', 3, 3,
            ],
        ];
    }
}
