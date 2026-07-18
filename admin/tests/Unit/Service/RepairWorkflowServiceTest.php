<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\RepairWorkflowService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepairWorkflowServiceTest extends TestCase
{
    private RepairWorkflowService $service;

    protected function setUp(): void
    {
        $this->service = (new \ReflectionClass(RepairWorkflowService::class))->newInstanceWithoutConstructor();
    }

    /**
     * @return array<string,array{0:list<array{status:string}>,1:int,2:int,3:bool}>
     */
    public static function workflowProvider(): array
    {
        return [
            'next pending step is selected' => [
                [['status' => 'done'], ['status' => 'pending']],
                0,
                1,
                false,
            ],
            'completed and skipped steps are bypassed' => [
                [
                    ['status' => 'done'],
                    ['status' => 'skipped'],
                    ['status' => 'done'],
                    ['status' => 'pending'],
                ],
                0,
                3,
                false,
            ],
            'workflow completes when no pending step remains' => [
                [['status' => 'done'], ['status' => 'skipped'], ['status' => 'done']],
                0,
                0,
                true,
            ],
            'last processed step completes workflow' => [
                [['status' => 'done'], ['status' => 'skipped']],
                1,
                1,
                true,
            ],
        ];
    }

    /**
     * @param list<array{status:string}> $steps
     */
    #[DataProvider('workflowProvider')]
    public function testAdvanceToNextPendingStep(
        array $steps,
        int $currentIndex,
        int $expectedIndex,
        bool $expectedCompleted
    ): void {
        $workflow = $this->service->advanceToNextPendingStep(
            [
                'current_step' => $currentIndex,
                'completed' => false,
                'steps' => $steps,
            ],
            $currentIndex
        );

        self::assertSame($expectedIndex, $workflow['current_step']);
        self::assertSame($expectedCompleted, $workflow['completed']);
    }
}
