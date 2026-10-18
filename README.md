# Coinsnap for PrestaShop

![Coinsnap for PrestaShop](https://resources.coinsnap.org/products/prestashop/images/cover.png)

**Bitcoin payment module for PrestaShop — accept Bitcoin and Lightning with Coinsnap or your own BTCPay Server.**

Coinsnap for PrestaShop is an open-source Bitcoin payment module for PrestaShop stores. It integrates Bitcoin payments into the existing PrestaShop checkout and order workflow.

The current module supports **PrestaShop 1.7 through 9.2** and can be used with either **Coinsnap** or **BTCPay Server** as the Bitcoin payment backend.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in PrestaShop
- Support for **Coinsnap** and **BTCPay Server**
- No own Lightning node or payment server required when using Coinsnap
- Native integration into the PrestaShop checkout
- Configurable payment status handling
- Custom redirect after payment
- Optional automatic redirect after payment
- Webhook-based payment status updates
- Support for PrestaShop **1.7 to 9.2**
- Open source under the MIT License

## Requirements

The current module release is **1.3.2** and supports:

- **PrestaShop 1.7 through 9.2**
- **PHP 7.1 or newer**
- A **Coinsnap merchant account**, or
- Your own **BTCPay Server**

When using Coinsnap, merchants do not need to operate their own Bitcoin or Lightning payment infrastructure.

For Bitcoin settlement, you can use a compatible Bitcoin or Lightning wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

## How it works

1. Create a [Coinsnap merchant account](https://app.coinsnap.io/register).
2. Download and install the Coinsnap module in your PrestaShop back office.
3. Open the module configuration.
4. Select **Coinsnap** or **BTCPay Server** as your payment provider.
5. Enter the required Store ID and API credentials.
6. Configure the payment and order status settings.
7. Enable Bitcoin payments and perform a test transaction.

## Coinsnap or BTCPay Server

The module supports two payment-provider modes:

- **Coinsnap** — for merchants who want to accept Bitcoin without running their own Bitcoin or Lightning payment infrastructure.
- **BTCPay Server** — for merchants who operate their own BTCPay Server and want to connect it to PrestaShop.

BTCPay Server support was added in version 1.2.0. The integration includes API authorization, minimum payment amount handling and webhook processing.

## Installation

### 1. Download the module

Download the current version from this GitHub repository.

### 2. Upload the module in PrestaShop

In the PrestaShop administration:

1. Go to **Modules → Module Manager**.
2. Choose **Upload a module**.
3. Upload the downloaded ZIP archive.
4. Search for **Coinsnap**.
5. Install and activate the module.

### 3. Configure Coinsnap

Open the Coinsnap module configuration and enter your:

- **Store ID**
- **API key**

These values are available in your Coinsnap merchant account.

You can then configure the payment status, redirect behavior and other module settings.

## BTCPay Server configuration

If you prefer to use your own BTCPay Server:

1. Select **BTCPay Server** as the payment provider.
2. Enter your BTCPay Server URL.
3. Authorize the required API key.
4. Enter the BTCPay Store ID and API key.
5. Save the configuration and test the connection.
6. Enable Bitcoin payments in your PrestaShop store.

## Resources

- [Coinsnap PrestaShop demo store](https://prestashop.coinsnap.org/)
- [Coinsnap PrestaShop article](https://coinsnap.io/coinsnap-for-prestashop-payment-plugin/)
- [PrestaShop Addons listing](https://addons.prestashop.com/en/billing-invoicing-prestashop-modules/93233-coinsnap-bitcoin-lightning-payment.html)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Coinsnap website](https://coinsnap.io/)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### What are the requirements for accepting Bitcoin in my PrestaShop store?

You need a compatible PrestaShop installation and either a Coinsnap merchant account or your own BTCPay Server. When using Coinsnap, you do not need to run your own Lightning node or Bitcoin payment server.

### Which PrestaShop versions are supported?

The current module supports PrestaShop **1.7 through 9.2**.

### Can I use my own BTCPay Server instead of Coinsnap?

Yes. BTCPay Server support is built into the module and can be selected as the payment provider.

### Which Bitcoin or Lightning wallets can I use?

You can use a compatible Bitcoin or Lightning wallet for settlement. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Other compatible wallets may include solutions such as Wallet of Satoshi, Blink and Zeus, depending on the settlement setup you choose.

### Do I need technical knowledge to install the module?

No advanced technical knowledge is required for the standard Coinsnap setup. The module is installed through the PrestaShop Module Manager and connected using your Coinsnap Store ID and API key.

### How are payment status changes handled?

The module uses webhooks to receive payment status updates from Coinsnap or BTCPay Server and maps them to the configured PrestaShop order status.

### Can I customize the redirect after payment?

Yes. The module supports a custom redirect after payment and an optional automatic redirect setting.

### Is the module open source?

Yes. The source code is available in this repository under the MIT License.

### Where can I get support?

Use the support area in your Coinsnap merchant account or join the official [Coinsnap Telegram channel](https://t.me/coinsnap).

## Changelog

#### 1.3.2 :: 2026-10-18
* Fixed "PaymentModule::validateOrder - Order Status cannot be loaded" error on checkout.
* Fixed bug with automatic redirect after payment.
* Update: Tested compatibility with Prestashop 9.2.0

#### 1.3.1 :: 2026-04-10
* Fixed bug with currency check.
* Updated Coinsnap PHP Library.
* Update: Tested compatibility with Prestashop 9.1.1

#### 1.3.0 :: 2025-11-15
* Update: Added Bitcoin discount (amount or percents) on checkout.
* Update: Stored webhooks filtration.

### 1.2.1 — 2025-10-09

- Added handling for currencies not supported by BTCPay Server during invoice creation.
- Added custom redirect after payment.
- Added automatic redirect after payment option.

### 1.2.0 — 2025-08-12

- Added BTCPay Server as a Bitcoin payment gateway.
- Added minimum payment amount calculation.
- Added webhook processing.

### 1.1.1 — 2025-03-01

- Updated redirect URL handling based on accepted link rules.

### 1.1.0 — 2025-02-20

- Fixed PHP, requirement and compatibility warnings.
- Applied code standards.
- Added compatibility with PrestaShop 9.0.

### 1.0.8 — 2024-08-23

- Fixed dynamic property errors on PHP 8.2.

### 1.0.5 — 2024-06-30

- Updated Order ID data source.

### 1.0.0 — 2024-03-12

- Initial release.
