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
     * Tests the setProcessStartCallback method.
     * @covers ::setProcessStartCallback
     * @throws ReflectionException
     */
    public function testSetProcessStartCallback(): void
    {
        $callback = 'strval';

        $manager = new ProcessManager();
        $result = $manager->setProcessStartCallback($callback);
        $this->assertSame($manager, $result);
        $this->assertSame($callback, $this->extractProperty($manager, 'processStartCallback'));
    }
    
    /**
     * Tests the setProcessFinishCallback method.
     * @covers ::setProcessFinishCallback
     * @throws ReflectionException
     */
    public function testSetProcessFinishCallback(): void
    {
        $callback = 'strval';

        $manager = new ProcessManager();
        $result = $manager->setProcessFinishCallback($callback);
        $this->assertSame($manager, $result);
        $this->assertSame($callback, $this->extractProperty($manager, 'processFinishCallback'));
    }

    /**
     * Provides the data for the invokeCallback test.
     * @return array
     */
    public function provideInvokeCallback(): array
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * Tests the invokeCallback method.
     * @param bool $withCallback
     * @throws ReflectionException
     * @covers ::invokeCallback
     * @dataProvider provideInvokeCallback
     */
    public function testInvokeCallback(bool $withCallback): void
    {
        /* @var Process $process */
        $process = $this->createMock(Process::class);

        $callback = null;
        $missingCallback = false;
        if ($withCallback) {
            $missingCallback = true;
            $callback = function (Process $p) use ($process, &$missingCallback): void {
                $this->assertSame($process, $p);
                $missingCallback = false;
            };
        }

        $manager = new ProcessManager();
        $this->invokeMethod($manager, 'invokeCallback', $callback, $process);
        $this->assertFalse($missingCallback);
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

        $result = $manager->addProcess($process, $callback, $env);
        $this->assertSame($manager, $result);
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
        $processStartCallback = 'intval';
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
                        ->setMethods(['canExecuteNextPendingRequest', 'invokeCallback'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->once())
                ->method('canExecuteNextPendingRequest')
                ->willReturn(true);
        $manager->expects($this->once())
                ->method('invokeCallback')
                ->with($processStartCallback, $process);

        $this->injectProperty($manager, 'pendingProcessData', $pendingProcessData);
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);
        $this->injectProperty($manager, 'processStartCallback', $processStartCallback);

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
        /* @var Process $process1 */
        $process1 = $this->createMock(Process::class);
        /* @var Process $process2 */
        $process2 = $this->createMock(Process::class);

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['checkRunningProcess'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->exactly(2))
                ->method('checkRunningProcess')
                ->withConsecutive(
                    [42, $process1],
                    [1337, $process2]
                );
        $this->injectProperty($manager, 'runningProcesses', [42 => $process1, 1337 => $process2]);

        $this->invokeMethod($manager, 'checkRunningProcesses');
    }

    /**
     * Provides the data for the checkRunningProcess test.
     * @return array
     */
    public function provideCheckRunningProcess(): array
    {
        return [
            [true, false],
            [false, true],
            [false, true],
        ];
    }

    /**
     * Tests the checkRunningProcess method.
     * @param bool $resultIsRunning
     * @param bool $expectFinish
     * @throws ReflectionException
     * @covers ::checkRunningProcess
     * @dataProvider provideCheckRunningProcess
     */
    public function testCheckRunningProcess(bool $resultIsRunning, bool $expectFinish): void
    {
        $pid = 42;
        /* @var Process|MockObject $process */
        $process = $this->getMockBuilder(Process::class)
                        ->setMethods(['checkTimeout', 'isRunning'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $process->expects($this->once())
                ->method('checkTimeout');
        $process->expects($this->once())
                ->method('isRunning')
                ->willReturn($resultIsRunning);

        /* @var Process $process2 */
        $process2 = $this->createMock(Process::class);
        $runningProcesses = [42 => $process, 1337 => $process2];
        $expectedRunningProcesses = $expectFinish ? [1337 => $process2] : [42 => $process, 1337 => $process2];

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['invokeCallback', 'executeNextPendingProcess'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($expectFinish ? $this->once() : $this->never())
                ->method('invokeCallback')
                ->with('strval', $process);
        $manager->expects($expectFinish ? $this->once() : $this->never())
                ->method('executeNextPendingProcess');
        $manager->setProcessFinishCallback('strval');
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);

        $this->invokeMethod($manager, 'checkRunningProcess', $pid, $process);
        $this->assertEquals($expectedRunningProcesses, $this->extractProperty($manager, 'runningProcesses'));
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

        $result = $manager->waitForAllProcesses();
        $this->assertSame($manager, $result);
    }

    /**
     * Tests the sleep method.
     * @throws ReflectionException
     * @covers ::sleep
     */
    public function testSleep(): void
    {
        $pollInterval = 100;
        $manager = new ProcessManager(42, $pollInterval);

        $startTime = microtime(true);
        $this->invokeMethod($manager, 'sleep');
        $endTime = microtime(true);
        $this->assertTrue($endTime >= $startTime + $pollInterval / 1000);
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
