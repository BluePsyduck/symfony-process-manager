# Changelog

## 1.3.2 - 2020-12-03

### Added

- Support for PHP 8.0

## 1.3.1 - 2020-01-12

### Added

- Support for symfony/process 5.x to be used with the process manager.

## 1.3.0 - 2019-01-07

### Added

- New callback `processCheckCallback`.

## 1.2.0 - 2018-12-03

### Added

- New callback `processTimeoutCallback`.

### Fixed

- Process manager failed to continue executing processes when one of them timed out. 

## 1.1.0 - 2018-10-07

### Added

- Option to delay the start of processes to space them out.
- Added setter methods for the options.
- `ProcessManagerInterface`.

### Fixed

- Failing to always retrieve PID on immediately completing processes.

## 1.0.0 - 2018-09-10

- Initial version of the process manager.
