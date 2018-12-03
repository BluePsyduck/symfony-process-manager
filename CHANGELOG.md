# Changelog

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
