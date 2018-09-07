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
     * The interval to wait between the polls of the processes, in milliseconds.
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
     * The callback for when a process is about to be started.
     * @var callable|null
     */
    protected $processStartCallback;

    /**
     * The callback for when a process has finished.
     * @var callable|null
     */
    protected $processFinishCallback;

    /**
     * The callback for when a process has successfully finished with exit code 0.
     * @var callable|null
     */
    protected $processSuccessCallback;

    /**
     * The callback for when a process has failed to finish with exit code 0.
     * @var callable|null
     */
    protected $processFailCallback;

    /**
     * ProcessManager constructor.
     * @param int $numberOfParallelProcesses The number of processes to run in parallel.
     * @param int $pollInterval The interval to wait between the polls of the processes, in milliseconds.
     */
    public function __construct(int $numberOfParallelProcesses = 1, int $pollInterval = 100)
    {
        $this->numberOfParallelProcesses = $numberOfParallelProcesses;
        $this->pollInterval = $pollInterval;
    }

    /**
     * Sets the callback for when a process is about to be started.
     * @param callable|null $processStartCallback The callback, accepting a Process as only argument.
     * @return $this
     */
    public function setProcessStartCallback(?callable $processStartCallback)
    {
        $this->processStartCallback = $processStartCallback;
        return $this;
    }

    /**
     * Sets the callback for when a process has finished.
     * @param callable|null $processFinishCallback The callback, accepting a Process as only argument.
     * @return $this
     */
    public function setProcessFinishCallback(?callable $processFinishCallback)
    {
        $this->processFinishCallback = $processFinishCallback;
        return $this;
    }

    /**
     * Sets the callback for when a process has failed to finish with exit code 0.
     * @param callable|null $processSuccessCallback The callback, accepting a Process as only argument.
     * @return $this
     */
    public function setProcessSuccessCallback(?callable $processSuccessCallback)
    {
        $this->processSuccessCallback = $processSuccessCallback;
        return $this;
    }

    /**
     * Sets the callback for when a process has successfully finished with exit code 0.
     * @param callable|null $processFailCallback The callback, accepting a Process as only argument.
     * @return $this
     */
    public function setProcessFailCallback(?callable $processFailCallback)
    {
        $this->processFailCallback = $processFailCallback;
        return $this;
    }

    /**
     * Invokes the callback if it is an callable.
     * @param callable|null $callback
     * @param Process $process
     */
    protected function invokeCallback(?callable $callback, Process $process): void
    {
        if (is_callable($callback)) {
            $callback($process);
        }
    }

    /**
     * Adds a process to the manager.
     * @param Process $process
     * @param callable|null $callback
     * @param array $env
     * @return $this
     */
    public function addProcess(Process $process, callable $callback = null, array $env = [])
    {
        $this->pendingProcessData[] = [$process, $callback, $env];
        $this->executeNextPendingProcess();
        $this->checkRunningProcesses();
        return $this;
    }

    /**
     * Executes the next pending process, if the limit of parallel processes is not yet reached.
     */
    protected function executeNextPendingProcess(): void
    {
        if ($this->canExecuteNextPendingRequest()) {
            list($process, $callback, $env) = array_shift($this->pendingProcessData);
            /* @var Process $process */
            $this->invokeCallback($this->processStartCallback, $process);
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
        foreach ($this->runningProcesses as $process) {
            $this->checkRunningProcess($process);
        }
    }

    /**
     * Checks the process whether it has finished.
     * @param Process $process
     */
    protected function checkRunningProcess(Process $process): void
    {
        $process->checkTimeout();
        if (!$process->isRunning()) {
            $this->invokeCallback(
                $process->isSuccessful() ? $this->processSuccessCallback : $this->processFailCallback,
                $process
            );
            $this->invokeCallback($this->processFinishCallback, $process);

            unset($this->runningProcesses[$process->getPid()]);
            $this->executeNextPendingProcess();
        }
    }

    /**
     * Waits for all processes to be finished.
     * @return $this
     */
    public function waitForAllProcesses()
    {
        while ($this->hasUnfinishedProcesses()) {
            $this->sleep();
            $this->checkRunningProcesses();
        }
        return $this;
    }

    /**
     * Sleeps for the next poll.
     */
    protected function sleep(): void
    {
        usleep($this->pollInterval * 1000);
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
