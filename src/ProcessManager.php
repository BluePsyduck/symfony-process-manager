<?php

declare(strict_types=1);

namespace BluePsyduck\SymfonyProcessManager;

use Symfony\Component\Process\Process;

/**
 * The process manager for executing multiple processes in parallel.
 *
 * @author BluePsyduck <bluepsyduck@gmx.com>
 * @license http://opensource.org/licenses/GPL-3.0 GPL v3
 */
class ProcessManager
{
    /**
     * The number of processes to run in parallel.
     * @var int
     */
    protected $numberOfParallelProcesses;

    /**
     * The interval to wait between the polls of the processes, in microseconds.
     * @var int
     */
    protected $pollInterval;

    /**
     * The processes currently waiting to be executed.
     * @var array
     */
    protected $pendingProcessData = [];

    /**
     * The processes currently running.
     * @var array|Process[]
     */
    protected $runningProcesses = [];

    /**
     * ProcessManager constructor.
     * @param int $numberOfParallelProcesses The number of processes to run in parallel.
     * @param int $pollInterval The interval to wait between the polls of the processes, in microseconds.
     */
    public function __construct(int $numberOfParallelProcesses = 1, int $pollInterval = 1000)
    {
        $this->numberOfParallelProcesses = $numberOfParallelProcesses;
        $this->pollInterval = $pollInterval;
    }

    /**
     * Adds a process to the manager.
     * @param Process $process
     */
    public function addProcess(Process $process, callable $callback = null, array $env = []): void
    {
        $this->pendingProcessData[] = [$process, $callback, $env];
        $this->executeNextPendingProcess();
        $this->checkRunningProcesses();
    }

    /**
     * Executes the next pending process, if the limit of parallel processes is not yet reached.
     */
    protected function executeNextPendingProcess(): void
    {
        if ($this->canExecuteNextPendingRequest()) {
            list($process, $callback, $env) = array_shift($this->pendingProcessData);
            /* @var Process $process */
            $process->start($callback, $env);
            $this->runningProcesses[$process->getPid()] = $process;
        }
    }

    /**
     * Checks whether a pending request is available and can be executed.
     * @return bool
     */
    protected function canExecuteNextPendingRequest(): bool
    {
        return count($this->runningProcesses) < $this->numberOfParallelProcesses
            && count($this->pendingProcessData) > 0;
    }

    /**
     * Checks the running processes whether they have finished.
     */
    protected function checkRunningProcesses(): void
    {
        foreach ($this->runningProcesses as $pid => $process) {
            $process->checkTimeout();
            if (!$process->isRunning()) {
                unset($this->runningProcesses[$pid]);
                $this->executeNextPendingProcess();
            }
        }
    }

    /**
     * Waits for all processes to be finished.
     */
    public function waitForAllProcesses(): void
    {
        while ($this->hasUnfinishedProcesses()) {
            $this->sleep();
            $this->checkRunningProcesses();
        }
    }

    /**
     * Sleeps for the next poll.
     */
    protected function sleep(): void
    {
        usleep($this->pollInterval);
    }

    /**
     * Returns whether the manager still has unfinished processes.
     * @return bool
     */
    public function hasUnfinishedProcesses(): bool
    {
        return count($this->pendingProcessData) > 0 || count($this->runningProcesses) > 0;
    }
}
