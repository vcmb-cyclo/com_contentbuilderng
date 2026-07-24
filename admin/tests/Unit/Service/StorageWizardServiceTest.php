<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\StorageWizardService;
use Joomla\CMS\Application\AdministratorApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/StorageWizardService.php';

final class StorageWizardServiceTest extends TestCase
{
    private AdministratorApplication $app;
    private StorageWizardService $service;

    protected function setUp(): void
    {
        $this->app = new AdministratorApplication();
        $this->service = new StorageWizardService($this->app);
    }

    public function testCreatesInitialState(): void
    {
        self::assertSame([
            'current_step' => StorageWizardService::STEP_STORAGE,
            'storage_id' => 0,
            'form_id' => 0,
            'menu_item_id' => 0,
            'started_at' => '2026-02-17 12:00:00',
        ], $this->service->createState());
    }

    public function testReturnsInitialStateForMissingOrInvalidSessionState(): void
    {
        self::assertSame(StorageWizardService::STEP_STORAGE, $this->service->getState()['current_step']);

        $this->app->setUserState(StorageWizardService::STATE_KEY, 'invalid');
        self::assertSame(StorageWizardService::STEP_STORAGE, $this->service->getState()['current_step']);

        $this->app->setUserState(StorageWizardService::STATE_KEY, ['storage_id' => 7]);
        self::assertSame(StorageWizardService::STEP_STORAGE, $this->service->getState()['current_step']);
    }

    public function testSavesReturnsAndResetsState(): void
    {
        $state = [
            'current_step' => StorageWizardService::STEP_FORM,
            'storage_id' => 7,
            'form_id' => 9,
        ];

        $this->service->saveState($state);
        self::assertSame($state, $this->service->getState());

        $this->service->reset();
        self::assertSame(StorageWizardService::STEP_STORAGE, $this->service->getState()['current_step']);
        self::assertSame(0, $this->service->getState()['storage_id']);
    }

    public function testAdvancesOnlyToKnownSteps(): void
    {
        self::assertSame(
            StorageWizardService::STEP_MENU,
            $this->service->advanceTo([], StorageWizardService::STEP_MENU)['current_step']
        );
        self::assertSame(
            StorageWizardService::STEP_STORAGE,
            $this->service->advanceTo([], 'unknown')['current_step']
        );
    }

    /**
     * @return array<string,array{0:string,1:int}>
     */
    public static function stepIndexProvider(): array
    {
        return [
            'storage' => [StorageWizardService::STEP_STORAGE, 0],
            'fields' => [StorageWizardService::STEP_FIELDS, 1],
            'form' => [StorageWizardService::STEP_FORM, 2],
            'menu' => [StorageWizardService::STEP_MENU, 3],
            'done' => [StorageWizardService::STEP_DONE, 4],
            'unknown' => ['unknown', 0],
        ];
    }

    #[DataProvider('stepIndexProvider')]
    public function testReturnsStepIndex(string $step, int $expected): void
    {
        self::assertSame($expected, $this->service->stepIndex($step));
    }
}
