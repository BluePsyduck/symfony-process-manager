<?php

declare(strict_types=1);

namespace BluePsyduckTest\SymfonyProcessManager;

use BluePsyduck\TestHelper\ReflectionTrait;
use BluePsyduck\SymfonyProcessManager\ProcessManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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
        $processStartDelay = 21;

        $manager = new ProcessManager($numberOfParallelProcesses, $pollInterval, $processStartDelay);
        $this->assertSame($numberOfParallelProcesses, $this->extractProperty($manager, 'numberOfParallelProcesses'));
        $this->assertSame($pollInterval, $this->extractProperty($manager, 'pollInterval'));
        $this->assertSame($processStartDelay, $this->extractProperty($manager, 'processStartDelay'));
    }

    /**
     * Tests the setNumberOfParallelProcesses method.
     * @throws ReflectionException
     * @covers ::setNumberOfParallelProcesses
     */
    public function testSetNumberOfParallelProcesses(): void
    {
        $numberOfParallelProcesses = 42;

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['executeNextPendingProcess'])
                        ->setConstructorArgs([])
                        ->getMock();
        $manager->expects($this->once())
                ->method('executeNextPendingProcess');

        $result = $manager->setNumberOfParallelProcesses($numberOfParallelProcesses);
        $this->assertSame($manager, $result);
        $this->assertSame($numberOfParallelProcesses, $this->extractProperty($manager, 'numberOfParallelProcesses'));
    }

    /**
     * Tests the setPollInterval method.
     * @throws ReflectionException
     * @covers ::setPollInterval
     */
    public function testSetPollInterval(): void
    {
        $pollInterval = 1337;

        $manager = new ProcessManager();

        $result = $manager->setPollInterval($pollInterval);
        $this->assertSame($manager, $result);
        $this->assertSame($pollInterval, $this->extractProperty($manager, 'pollInterval'));
    }

    /**
     * Tests the setProcessStartDelay method.
     * @throws ReflectionException
     * @covers ::setProcessStartDelay
     */
    public function testSetProcessStartDelay(): void
    {
        $processStartDelay = 21;

        $manager = new ProcessManager();

        $result = $manager->setProcessStartDelay($processStartDelay);
        $this->assertSame($manager, $result);
        $this->assertSame($processStartDelay, $this->extractProperty($manager, 'processStartDelay'));
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
     * Tests the setProcessTimeoutCallback method.
     * @covers ::setProcessTimeoutCallback
     * @throws ReflectionException
     */
    public function testSetProcessTimeoutCallback(): void
    {
        $callback = 'strval';

        $manager = new ProcessManager();
        $result = $manager->setProcessTimeoutCallback($callback);
        $this->assertSame($manager, $result);
        $this->assertSame($callback, $this->extractProperty($manager, 'processTimeoutCallback'));
    }

    /**
     * Tests the setProcessCheckCallback method.
     * @covers ::setProcessCheckCallback
     * @throws ReflectionException
     */
    public function testSetProcessCheckCallback(): void
    {
        $callback = 'strval';

        $manager = new ProcessManager();
        $result = $manager->setProcessCheckCallback($callback);
        $this->assertSame($manager, $result);
        $this->assertSame($callback, $this->extractProperty($manager, 'processCheckCallback'));
    }

    /**
     * Provides the data for the invokeCallback test.
     * @return array<mixed>
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
     * Provides the data for the executeNextPendingProcess test.
     * @return array<mixed>
     */
    public function provideExecuteNextPendingProcess(): array
    {
        return [
            [42, true, false],
            [null, false, true],
        ];
    }

    /**
     * Tests the executeNextPendingProcess method.
     * @param int|null $pid
     * @param bool $expectRunningProcess
     * @param bool $expectCheck
     * @throws ReflectionException
     * @covers ::executeNextPendingProcess
     * @dataProvider provideExecuteNextPendingProcess
     */
    public function testExecuteNextPendingProcess(?int $pid, bool $expectRunningProcess, bool $expectCheck): void
    {
        $processStartDelay = 1337;
        $callback = 'strval';
        $processStartCallback = 'intval';
        $env = ['foo' => 'bar'];

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

        $runningProcesses = [1337 => 'ghi'];
        $expectedRunningProcesses = $expectRunningProcess ? [1337 => 'ghi', 42 => $process] : ['1337' => 'ghi'];

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['canExecuteNextPendingRequest', 'sleep', 'invokeCallback', 'checkRunningProcess'])
                        ->setConstructorArgs([0, 0, $processStartDelay])
                        ->getMock();
        $manager->expects($this->once())
                ->method('canExecuteNextPendingRequest')
                ->willReturn(true);
        $manager->expects($this->once())
                ->method('sleep')
                ->with($processStartDelay);
        $manager->expects($expectCheck ? $this->once() : $this->never())
                ->method('checkRunningProcess')
                ->with($pid, $process);


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
     * @return array<mixed>
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
     * @param array<Process<string>> $runningProcesses
     * @param int $numberOfParallelProcesses
     * @param array<mixed> $pendingProcessData
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
     * @return array<mixed>
     */
    public function provideCheckRunningProcess(): array
    {
        return [
            [42, true, false, false],
            [42, false, true, true],
            [42, false, true, true],
            [null, false, true, false],
        ];
    }

    /**
     * Tests the checkRunningProcess method.
     * @param int|null $pid
     * @param bool $resultIsRunning
     * @param bool $expectFinish
     * @param bool $expectUnset
     * @throws ReflectionException
     * @covers ::checkRunningProcess
     * @dataProvider provideCheckRunningProcess
     */
    public function testCheckRunningProcess(
        ?int $pid,
        bool $resultIsRunning,
        bool $expectFinish,
        bool $expectUnset
    ): void {
        /* @var Process|MockObject $process */
        $process = $this->getMockBuilder(Process::class)
                        ->setMethods(['isRunning'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $process->expects($this->once())
                ->method('isRunning')
                ->willReturn($resultIsRunning);

        /* @var Process $process2 */
        $process2 = $this->createMock(Process::class);
        $runningProcesses = [42 => $process, 1337 => $process2];
        $expectedRunningProcesses = $expectUnset ? [1337 => $process2] : [42 => $process, 1337 => $process2];

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['checkProcessTimeout', 'invokeCallback', 'executeNextPendingProcess'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($this->once())
                ->method('checkProcessTimeout')
                ->with($process)
                ->willReturn(false);
        $manager->expects($this->exactly($expectFinish ? 2 : 1))
                ->method('invokeCallback')
                ->withConsecutive(
                    ['intval', $process],
                    ['strval', $process]
                );
        $manager->expects($expectFinish ? $this->once() : $this->never())
                ->method('executeNextPendingProcess');
        $manager->setProcessFinishCallback('strval')
                ->setProcessCheckCallback('intval');
        $this->injectProperty($manager, 'runningProcesses', $runningProcesses);

        $this->invokeMethod($manager, 'checkRunningProcess', $pid, $process);
        $this->assertEquals($expectedRunningProcesses, $this->extractProperty($manager, 'runningProcesses'));
    }

    /**
     * Provides the data for the checkProcessTimeout test.
     * @return array<mixed>
     */
    public function provideCheckProcessTimeout(): array
    {
        return [
            [false, false],
            [true, true],
        ];
    }

    /**
     * Tests the checkProcessTimeout method.
     * @param bool $throwException
     * @param bool $expectInvoke
     * @throws ReflectionException
     * @covers ::checkProcessTimeout
     * @dataProvider provideCheckProcessTimeout
     */
    public function testCheckProcessTimeout(bool $throwException, bool $expectInvoke): void
    {
        /* @var Process|MockObject $process */
        $process = $this->getMockBuilder(Process::class)
                        ->setMethods(['checkTimeout'])
                        ->disableOriginalConstructor()
                        ->getMock();

        if ($throwException) {
            $process->expects($this->once())
                    ->method('checkTimeout')
                    ->willThrowException($this->createMock(ProcessTimedOutException::class));
        } else {
            $process->expects($this->once())
                    ->method('checkTimeout');
        }

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['invokeCallback'])
                        ->disableOriginalConstructor()
                        ->getMock();
        $manager->expects($expectInvoke ? $this->once() : $this->never())
                ->method('invokeCallback')
                ->with('strval', $process);
        $manager->setProcessTimeoutCallback('strval');

        $this->invokeMethod($manager, 'checkProcessTimeout', $process);
    }

    /**
     * Tests the waitForAllProcesses method.
     * @covers ::waitForAllProcesses
     */
    public function testWaitForAllProcesses(): void
    {
        $pollInterval = 1337;

        /* @var ProcessManager|MockObject $manager */
        $manager = $this->getMockBuilder(ProcessManager::class)
                        ->setMethods(['hasUnfinishedProcesses', 'sleep', 'checkRunningProcesses'])
                        ->setConstructorArgs([42, $pollInterval, 21])
                        ->getMock();
        $manager->expects($this->exactly(3))
                ->method('hasUnfinishedProcesses')
                ->willReturnOnConsecutiveCalls(
                    true,
                    true,
                    false
                );
        $manager->expects($this->exactly(2))
                ->method('sleep')
                ->with($pollInterval);
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
        $milliseconds = 100;
        $manager = new ProcessManager();

        $startTime = microtime(true);
        $this->invokeMethod($manager, 'sleep', $milliseconds);
        $endTime = microtime(true);
        $this->assertTrue($endTime >= $startTime + $milliseconds / 1000);
    }

    /**
     * Provides the data for the hasUnfinishedProcesses test.
     * @return array<mixed>
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
     * @param array<mixed> $pendingProcessData
     * @param array<Process<string>> $runningProcesses
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
