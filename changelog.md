# Changelog

All notable changes of krokedil/klarna-express-checkout are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Fixed

* Fixed a potential issue that could happen if we tried to initialize the KEC button before the Klarna Payments script was loaded and had registered the `Klarna.Payments.Buttons` object in the window.

------------------
## [1.4.1] - 2024-11-12
### Changed
* The KEC button will remain hidden on a variable product page until a variant is selected.
* Updated the product description as shown on the settings page.

## [1.4.0] - 2024-09-11
### Added

* Preview images for each of the button theme and shape combinations.
* A new theme for the outlined version of the KEC button.

### Changed

* Changed the settings to match the new layout and structure for the Klarna Payments plugins settings pages. Changed the WooCommerce settings API section start and end to be of the types `kp_section_start` and `kp_section_end` respectively.

### Removed

* Removed the setting for the Client ID from the package, and instead uses the new `kp_get_client_id` function from Klarna Payments to get the client id based on the customers country and availability.
* Removed the default theme option, since this was just a different name for the dark theme.

### Fixed

* Fixed an issue with showing the Klarna Express Checkout button on the product page when the product is out of stock.

## [1.3.1] - 2024-04-22
### Fix

* Fixed an issue where a new KEC button would be created on a variation product every time the customer selected a variation option.
* Moved from client_key to client_id as the former has now been deprecated by Klarna.

## [1.3.0] - 2024-01-31
### Added

* Added support for passing locale to the KEC initialization.

### Fix

* We will no longer test against shipping address when the delivery address is forced to be the billing address.

## [1.2.1] - 2024-01-16
### Fix

* Removed the required flag from the Credentials Secret setting as it is not required.

## [1.2.0] - 2024-01-12
### Added

* Added handling to switch to Klarna Payments normal flow away from Klarna Express Checkout on a change to the shipping address in the checkout.

### Changed

* Changed the name of the Credentials Secret setting to Klarna Client Identifier and added links to the merchant portal where they can be found.

## [1.1.0] - 2023-12-15

### Added

* Added support for Product pages as well as the cart page.
* Added setting to disable the Klarna Express Checkout button.

### Changed

* Changed how we integrate the Klarna Express Checkout with Klarna Payments. We no longer set the Klarna session_id to hook into the Klarna Payments flow. But rather take over from the package if a KEC token is set.

## [1.0.0] - 2023-12-12

### Added

* Initial release of the package.
