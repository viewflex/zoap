# Changelog

This document tracks changes in release versions of **Zoap**. This project adheres to the [Semantic Versioning](http://semver.org/spec/v2.0.0.html) standard.

## Version [1.1.0](https://github.com/viewflex/zoap/tree/1.1.0) - 2026-11-10

### Added

- PHPUnit test suite (Orchestra Testbench), plus a live test suite for running against an installed application.
- GitHub Actions test matrix covering PHP 7.3 – 8.5 and Laravel 8 – 13, with the newest and lowest supported laminas-soap.
- Declared requirements: PHP `^7.3 || ^8.0`, `ext-soap`, and `illuminate/*` 8 – 13.

### Changed

- Require laminas-soap `>=2.9.0 <2.16.0` (tested releases) instead of `^2.9`.
- Read the WSDL query parameter and SOAP request body from the Laravel request instead of `$_GET` and `php://input` (Laravel Octane support, see #33).
- Generate the WSDL in-process for SOAP requests instead of fetching it from the service endpoint over HTTP.
- Faults for exceptions not registered in the service config, and for PHP errors, return the message 'Unknown error'.
- Demo service no longer sets raw HTTP status headers.

### Fixed

- SOAP requests hanging under `php artisan serve` (#35).
- Faults returned without a real HTTP 500 status outside PHP-FPM/CGI.
- Fault view failing to render when PHP's `short_open_tag` is enabled, producing an HTML error page instead of a SOAP fault.
- Fault code and message not XML-escaped.
- PHP errors producing an HTML error page instead of a SOAP fault.
- Missing optional service config keys (`strategy`, `types`, `headers`, `exceptions`) causing errors.
- Fault responses for invalid service configurations sent with an HTML content type.
- README DocBlock example namespace.

## Version [1.0.5](https://github.com/viewflex/zoap/tree/1.0.5) - 2021-02-24

### Changed

- Use laminas-soap package instead of zend-soap.

## Version [1.0.4](https://github.com/viewflex/zoap/tree/1.0.4) - 2019-09-04

### Changed

- Use Arr::add() instead of deprecated array_add().

## Version [1.0.3](https://github.com/viewflex/zoap/tree/1.0.3) - 2018-12-9

### Added

- Config for server options.

## Version [1.0.2](https://github.com/viewflex/zoap/tree/1.0.2) - 2018-05-25

### Removed

- Line requiring laravel/framework in composer.json.

## Version [1.0.1](https://github.com/viewflex/zoap/tree/1.0.1) - 2018-05-25

### Added

- Changelog.

### Changed

- Minor refactoring for Lumen compatibility

## Version [1.0.0](https://github.com/viewflex/zoap/tree/1.0) - 2018-04-26

Initial public release.
