# Symfony Process Manager

[![Latest Stable Version](https://poser.pugx.org/bluepsyduck/symfony-process-manager/v/stable)](https://packagist.org/packages/bluepsyduck/symfony-process-manager)
[![License](https://poser.pugx.org/bluepsyduck/symfony-process-manager/license)](https://packagist.org/packages/bluepsyduck/symfony-process-manager)
[![Build Status](https://travis-ci.com/BluePsyduck/symfony-process-manager.svg?branch=master)](https://travis-ci.com/BluePsyduck/symfony-process-manager)
[![codecov](https://codecov.io/gh/BluePsyduck/symfony-process-manager/branch/master/graph/badge.svg)](https://codecov.io/gh/BluePsyduck/symfony-process-manager)

This package provides a simple process manager class to be able to execute multiple processes with a specified limit
of parallel processes. The class expects the processes to use the [Symfony Process](https://github.com/symfony/process) 
component.

## Usage

The usage of the process manager is straight forward and best explained with an example.

```php
<?php
use BluePsyduck\SymfonyProcessManager\ProcessManager;
use Symfony\Component\Process\Process;

$numberOfParallelProcesses = 4; // The number of processes to execute in parallel.
$pollInterval = 100; // The interval to use for polling the processes, in milliseconds.
$processStartDelay = 0; // The time to delay the start of processes to space them out, in milliseconds.

$processManager = new ProcessManager($numberOfParallelProcesses, $pollInterval, $processStartDelay);

// Add some processes
// Processes get executed automatically once they are added to the manager. 
// If the limit of parallel processes is reached, they are placed in a queue and wait for a process to finish.
$processManager->addProcess(Process::fromShellCommandline('ls -l'));
$processManager->addProcess(Process::fromShellCommandline('ls -l'));

// Wait for all processes to finish
$processManager->waitForAllProcesses();


```

## Callbacks

The process manager allows for some callbacks to be specified, which get called depending on the state of a process.

* **processStartCallback:** Triggered before a process is started.
* **processFinishCallback:** Triggered when a process has finished.
* **processTimeoutCallback:** Triggered when a process timed out. Note that the _processFinishCallback_ will be 
  triggered afterwards as well.
* **processCheckCallback:** Triggered whenever a process is checked for completion. Note that this callback is called 
  periodically, but at least once, between the `processStartCallback` and the `processFinishCallback` or 
  `processTimeoutCallback` respectively.

Each callback gets the process instance which triggered the event passed as only parameter. Here is an example of 
setting a `processStartCallback`:

```php
<?php
use BluePsyduck\SymfonyProcessManager\ProcessManager;
use Symfony\Component\Process\Process;

$processManager = new ProcessManager();

$processManager->setProcessStartCallback(function (Process $process): void {
    echo 'Starting process: ' . $process->getCommandLine();
});

$processManager->addProcess(Process::fromShellCommandline('ls -l'));
$processManager->waitForAllProcesses();
```
