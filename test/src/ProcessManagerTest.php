<?php

declare(strict_types=1);

namespace BluePsyduckTest\SymfonyProcessManager;

use BluePsyduck\Common\Test\ReflectionTrait;
use BluePsyduck\SymfonyProcessManager\ProcessManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Symfony\Component\Process\Process;

/**
 * The PHPUnit test of the ProcessManager class.
 *
 * @author BluePsyduck <bluepsyduck@gmx.com>
 * @license http://opensource.org/licenses/GPL-3.0 GPL v3
 * @coversDefaultClass \BluePsyduck\SymfonyProcessManager\ProcessManager
 */
class ProcessManagerTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Tests the constructing.
     * @covers ::__construct
     * @throws ReflectionException
     */
    public function testConstruct(): void
    {
        $numberOfParallelProcesses = 42;
        $pollInterval = 1337;

        $manager = new ProcessManager($numberOfParallelProcesses, $pollInterval);
        $this->assertSame($numberOfParallelProcesses, $this->extractProperty($manager, 'numberOfParallelProcesses'));
        $this->assertSame($pollInterval, $this->extractProperty($manager, 'pollInterval'));
    }

    /**
     * Tests the addProcess method.
     * @covers ::addProcess
     * @throws ReflectionException
     */
    public function testAddProcess(): void
    {
        /* @var Process $process */
        $process = $this->createMock(Process::class);
        $callback = 'strval';
        $env = ['abc' => 'def'];

        $pendingProcessData = [
            ['foo', 'bar'],
        ];
        $expectedPendingProcessData = [
            ['foo', 'bar'],
            [$process, $callback, $env],
        ];

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['executeNextPendingProcess', 'checkRunningProcesses'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->once())
                ->method('executeNextPendingProcess');
        $manager->expects($this->once())
                ->method('checkRunningProcesses');
        $this->injectProperty($manager, 'pendingProcessData', $pendingProcessData);

        $manager->addProcess($process, $callback, $env);
        $this->assertEquals($expectedPendingProcessData, $this->extractProperty($manager, 'pendingProcessData'));
    }

    /**
     * Tests the executeNextPendingProcess method.
     * @covers ::executeNextPendingProcess
     * @throws ReflectionException
     */
    public function testExecuteNextPendingProcess(): void
    {
        $callback = 'strval';
        $env = ['foo' => 'bar'];
        $pid = 42;

        /* @var Process|MockObject $process */
        $process = $this->getMockBuilder(Process::class)
                        ->setMethods(['start', 'getPid'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $process->expects($this->once())
                ->method('start')
                ->with($callback, $env);
        $process->expects($this->once())
                ->method('getPid')
                ->willReturn($pid);

        $pendingProcessData = [
            [$process, $callback, $env],
            ['abc', 'def'],
        ];
        $expectedPendingProcessData = [
            ['abc', 'def'],
        ];

        $runningProcesses = [
            1337 => 'ghi',
        ];
        $expectedRunningProcesses = [
            1337 => 'ghi',
            42 => $process,
        ];

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['canExecuteNextPendingRequest'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->once())
                ->method('canExecuteNextPendingRequest')
                ->willReturn(true);

        $this->injectProperty($manager, 'pendingProcessData', $pendingProcessData);
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);

        $this->invokeMethod($manager, 'executeNextPendingProcess');

        $this->assertEquals($expectedPendingProcessData, $this->extractProperty($manager, 'pendingProcessData'));
        $this->assertEquals($expectedRunningProcesses, $this->extractProperty($manager, 'runningProcesses'));
    }

    /**
     * Provides the data for the canExecuteNextPendingRequest test.
     * @return array
     */
    public function provideCanExecuteNextPendingRequest(): array
    {
        return [
            [4, ['abc'], ['foo'], true],
            [4, ['abc', 'def', 'ghi', 'jkl'], ['foo'], false],
            [4, ['abc'], [], false],
        ];
    }

    /**
     * Tests the canExecuteNextPendingRequest method.
     * @param array $runningProcesses
     * @param int $numberOfParallelProcesses
     * @param array $pendingProcessData
     * @param bool $expectedResult
     * @throws ReflectionException
     * @covers ::canExecuteNextPendingRequest
     * @dataProvider provideCanExecuteNextPendingRequest
     */
    public function testCanExecuteNextPendingRequest(
        int $numberOfParallelProcesses,
        array $runningProcesses,
        array $pendingProcessData,
        bool $expectedResult
    ): void {
        $manager = new ProcessManager($numberOfParallelProcesses);
        $this->injectProperty($manager, 'pendingProcessData', $pendingProcessData);
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);

        $result = $this->invokeMethod($manager, 'canExecuteNextPendingRequest');
        $this->assertSame($expectedResult, $result);
    }

    /**
     * Tests the checkRunningProcesses method.
     * @covers ::checkRunningProcesses
     * @throws ReflectionException
     */
    public function testCheckRunningProcesses(): void
    {
        $isRunningResults = [
            42 => false,
            1337 => true,
            27 => false,
        ];
        $expectedNumberOfExecuteInvocations = 2;

        $processes = [];
        $expectedProcesses = [];
        foreach ($isRunningResults as $pid => $isRunningResult) {
            /* @var Process|MockObject $process */
            $process = $this->getMockBuilder(Process::class)
                            ->setMethods(['checkTimeout', 'isRunning'])
                            ->disableOriginalConstructor()
                            ->getMock();
            $process->expects($this->once())
                    ->method('checkTimeout');
            $process->expects($this->once())
                    ->method('isRunning')
                    ->willReturn($isRunningResult);

            $processes[$pid] = $process;
            if ($isRunningResult) {
                $expectedProcesses[$pid] = $process;
            }
        }

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['executeNextPendingProcess'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->exactly($expectedNumberOfExecuteInvocations))
                ->method('executeNextPendingProcess');
        $this->injectProperty($manager, 'runningProcesses', $processes);

        $this->invokeMethod($manager, 'checkRunningProcesses');
        $this->assertEquals($expectedProcesses, $this->extractProperty($manager, 'runningProcesses'));
    }

    /**
     * Tests the waitForAllProcesses method.
     * @covers ::waitForAllProcesses
     */
    public function testWaitForAllProcesses(): void
    {
        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['hasUnfinishedProcesses', 'sleep', 'checkRunningProcesses'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->exactly(3))
                ->method('hasUnfinishedProcesses')
                ->willReturnOnConsecutiveCalls(
                    true,
                    true,
                    false
                );
        $manager->expects($this->exactly(2))
                ->method('sleep');
        $manager->expects($this->exactly(2))
                ->method('checkRunningProcesses');

        $manager->waitForAllProcesses();
    }

    /**
     * Tests the sleep method.
     * @throws ReflectionException
     * @covers ::sleep
     */
    public function testSleep(): void
    {
        $pollInterval = 1000;
        $manager = new ProcessManager(42, $pollInterval);

        $startTime = microtime(true);
        $this->invokeMethod($manager, 'sleep');
        $endTime = microtime(true);
        $this->assertTrue($endTime >= $startTime + $pollInterval / 1000000);
    }

    /**
     * Provides the data for the hasUnfinishedProcesses test.
     * @return array
     */
    public function provideHasUnfinishedProcesses(): array
    {
        return [
            [[['abc' => 'def']], [], true],
            [[], [$this->createMock(Process::class)], true],
            [[], [], false],
        ];
    }

    /**
     * Tests the hasUnfinishedProcesses method.
     * @param array $pendingProcessData
     * @param array $runningProcesses
     * @param bool $expectedResult
     * @throws ReflectionException
     * @covers ::hasUnfinishedProcesses
     * @dataProvider provideHasUnfinishedProcesses
     */
    public function testHasUnfinishedProcesses(
        array $pendingProcessData,
        array $runningProcesses,
        bool $expectedResult
    ): void {
        $manager = new ProcessManager();
        $this->injectProperty($manager, 'pendingProcessData', $pendingProcessData);
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);

        $result = $manager->hasUnfinishedProcesses();
        $this->assertSame($expectedResult, $result);
    }
}
