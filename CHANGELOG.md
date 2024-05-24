# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.1] - 2024-05-24

### Fixed

- Sidecar dependency version constraint

## [1.3.0] - 2024-03-21

### Added

- Laravel 11 support

## [1.2.1] - 2023-12-11

### Fixed

- Fetching Lambda layers locally with wrong URL (not faking environment on the process)

## [1.2.0] - 2023-11-03

### Added

- Error handling when fetching function layers (checking compatible runtimes & architectures)

### Fixed

- Issue with Apple Silicon and ARM-based devices running images from Docker Hub (#4)

## [1.1.0] - 2023-10-19

### Added

- Support for Lambda layers, locally downloaded and merged into `resources/layers/FunctionName`
- Support for [`beforeDeployment` hook function](https://hammerstone.dev/sidecar/docs/main/functions/hooks#example-before-deployment)

## [1.0.2] - 2023-10-19

### Fixed

- Docker restart policy

## [1.0.1] - 2023-10-19

### Fixed

- Command `sidecar:local` stop and run options not triggering

## [1.0.0] - 2023-10-19

### Added

- Initial release!
