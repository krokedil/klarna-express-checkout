# Changelog

All notable changes of krokedil/klarna-express-checkout are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

------------------

## [1.1.0] - 2023-12-15

### Added

* Added support for Product pages as well as the cart page.
* Added setting to disable the Klarna Express Checkout button.

### Changed

* Changed how we integrate the Klarna Express Checkout with Klarna Payments. We no longer set the Klarna session_id to hook into the Klarna Payments flow. But rather take over from the package if a KEC token is set.

## [1.0.0] - 2023-12-12

### Added

* Initial release of the package.
