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

$processManager = new ProcessManager($numberOfParallelProcesses, $pollInterval);

// Add some processes
// Processes get executed automatically once they are added to the manager. 
// If the limit of parallel processes is reached, they are placed in a queue and wait for a process to finish.
$processManager->addProcess(new Process('ls -l'));
$processManager->addProcess(new Process('ls -l'));

// Wait for all processes to finish
$processManager->waitForAllProcesses();


```

## Callbacks

The process manager allows for some callbacks to be specified, which get called depending on the state of a process.

* **processStartCallback:** Triggered before a process is started.
* **processFinishCallback:** Triggered when a process has finished.
* **processSuccessCallback:** Triggered when a processes has finished with an exit code of 0.
* **processFailCallback:** Triggered when a processes has failed with an exit code other than 0.

_Note:_ Each process will trigger either the `processSuccessCallback` or the `processFailCallback` depending of its exit 
code. The `processFinishCallback` will always be triggered afterwards, ignoring the exit code.

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

$processManager->addProcess(new Process('ls -l'));
$processManager->waitForAllProcesses();
```