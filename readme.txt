=== PayPlug for WooCommerce (Official) ===
Contributors: PayPlug
Tags: payplug, woocommerce, gateway, payment, credit card, carte de crédit, carte bancaire, paiement, one click, paiement en ligne, oney
Requires at least: 4.4
Tested up to: 6.0.2
Requires PHP: 5.6
Stable tag: 1.10.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

PlayPlug is a French payment solution allowing small and medium e-commerce companies to accept online payments from Visa, MasterCard and CB cards.

== Description ==

**What PayPlug does for merchants**

PayPlug primary goal is to give you the tools you need to sell to your clients, wherever they are.

* Simple set up and management

* Safety

* Optimized conversion

* Support


**Our main features**

= Accept payments =

* Fastest set up on the market, with no technical skills required

* Plugin developed by PayPlug, configurable with just a few clicks

* Reception of online credit card payments from CB, Visa, Mastercard, without needing an extra merchant account


= Boost your sales =

* Customizable payment page, optimized for mobile devices, integrated or redirected

* One-click payment with secure card information storage

* Installment payments with Oney. Main benefits: immediately receive the complete amount of the order to enjoy a serene cash flow; Potential frauds and unpaid transactions will be managed by Oney


= Monitor your performances =

* Transaction history and accounting records with one click, using the management interface

* Personalized support, in your preferred language

Do you want to know more about our features? Visit <https://www.payplug.com/online-payment>



**PayPlug in general**

PayPlug is a French omnichannel payment solution dedicated to merchants. It allows you to accept credit card payments both online and in-store.

* 10 000 merchants trust PayPlug for their payments

* 800 partners recommend us daily

PayPlug offers several plans to suit your needs and business requirements. **No set-up fees and no commitment;** you can change your offer whenever you want. More details: <https://www.payplug.com/pricing>


== Installation ==

1. Sign up for free on PayPlug: <https://portal.payplug.com/signup>
2. Install the plugin on WordPress
3. Activate the plugin in Plugins > Installed Plugins
4. In Plugins > Installed Plugins > PayPlug for WooCommerce (Official) settings, log in with your PayPlug credentials
5. In Settings, check that  PayPlug is enabled, chose your payment settings and save changes

== Screenshots ==

1. Settings
2. Display on a WordPress website

== Changelog ==
= 1.10.0 =
* American Express
* Tested up to Woocommerce 7.0.0
* Tested up to Wordpress 6.0.2

= 1.9.4 =
* Fix for the Apple Pay payment method activation
* Minor fixes
* Tested up to Woocommerce 7.0.0
* Tested up to Wordpress 6.0.2

= 1.9.3 =
* Minor fixes for full support php7
* Tested up to Woocommerce 6.8.0
* Tested up to Wordpress 6.0.1

= 1.9.2 =
* WP version updates
* Tested up to Woocommerce 6.8.0
* Tested up to Wordpress 6.0.1

= 1.9.1 =
* IPN minor fix
* Tested up to Woocommerce 6.8.0
* Tested up to Wordpress 6.0.1

= 1.9.0 =
* Apple Pay (beta)
* Tested up to Woocommerce 6.7.0
* Tested up to Wordpress 6.0.1

= 1.8.2 =
* Minor fixes (Specifications of payment gateway logs and fail safe on the return url)
* Tested up to Woocommerce 6.6.1
* Tested up to Wordpress 6

= 1.8.1 =
* Paylater Improvements
* Bancontact Improvements
* Minor fixes
* Tested up to Woocommerce 6.6.1
* Tested up to Wordpress 6

= 1.8.0 =
* Bancontact (beta)
* Translations change for Oney Italy
* Tested up to Woocommerce 6.5.1
* Tested up to Wordpress 6

= 1.7.2 =
* Minor fixes
* Tested up to Woocommerce 6.4
* Tested up to Wordpress 5.9

= 1.7.1 =
* Updating IT translation
* Tested up to Woocommerce 6.4
* Tested up to Wordpress 5.9

= 1.7.0 =
* Oney Threshold
* Minor fixes
* Tested up to Woocommerce 6.4
* Tested up to Wordpress 5.9

= 1.6.0 =
* Tested up to Woocommerce 5.9
* Tested up to Wordpress 5.9
* Minor fixes

= 1.5.0 =
* Oney without fees support
* Update npm dependencies
* Tested up to Woocommerce 5.9
* Tested up to Wordpress 5.9
* Minor fixes

= 1.4.0 =
* Update translation rules for Oney Marketing animation
* Compatibility with php 8.0
* Tested up to Woocommerce 5.9
* Tested up to Wordpress 5.9
* Minor fixes

= 1.3.0 =
* Minor fixes
* Always display explanation of each PayPlug features in BO

= 1.2.11 =
* Minor fixes
* Remove Oney legal notices validation from PayPlug configuration in Woocommerce backoffice

= 1.2.10 =
* Minor fixes

= 1.2.9 =
* Minor fixes
* Tested up to Wordpress 5.8

= 1.2.8 =
* Minor fixes
* Update npm dependencies

= 1.2.7 =
* Minor fixes
* Update npm dev-dependencies

= 1.2.6 =
* Tested up to Woocommerce 5.3.0

= 1.2.5 =
* Optimization and minor fixes

= 1.2.4 =
* Minor fixes
* Tested up to Woocommerce 5.2.2

= 1.2.3 =
* Minor fixes
* Tested up to Wordpress 5.7
* Tested up to Woocommerce 5.1.0

= 1.2.2 =
* Tested up to Wordpress 5.6

= 1.2.1 =
* Rollback to Release 1.1.0

= 1.2.0 =
* Guaranteed installment payments by Oney

= 1.1.0 =
* One click payments with 3D Secure validation to be compatible with new DSP2 requirements

= 1.0.22 =
* Update dependencies
* Tested up to WordPress 5.4
* Tested up to Woocommerce 4.0

= 1.0.21 =
* The customer's phone number is now only forwarded to the bank networks if its format complies with the E.164 standard.
* An incorrect phone number will not block the transaction

= 1.0.20 =
* PSD 2 compatibility
* Tested up to Woocommerce 3.7

= 1.0.18 =
* Fix on cancelled orders with successful payment : allow PayPlug notification responses for cancelled orders
* Fix on  miscreated orders : loading of the PayPlug form.js latest version from the plugin. With the latest version of form.js, the plugin does not have to wait for url_return redirection (5sec) to create payment within WooCommerce
* Update devDependencies

= 1.0.17 =
* Fix special characters in password for the PayPlug login
* Update screens according to the new graphic charter
* Upgrade LIVE keys retrieve when switching form TEST to LIVE mode, once PayPlug account has been activated (Password request pop-in)
* Tested up to Woocommerce 3.6

= 1.0.15 =
* Fix notification processing
* Add new settings (Payment Gateway): Title and description

= 1.0.14 =
* Fix message for Payment Method in the order confirmation email
* Fix payment scheme logo size on checkout step
* Fix lightbox access error
* Upgrade payment scheme logos on checkout step
* Specific payment scheme logos for Italian visitors (PostePay instead of CB) on checkout step

= 1.0.12 =
* Security fix in dependencies
* Fix typos in translations

= 1.0.11 =
* Fix wrong message on the payment page

= 1.0.10 =
* Fix stored credit cards display

= 1.0.7 =
* Fix translations

= 1.0.0 =
* Initial release

= 0.1.2 =
* Beta version

== Upgrade Notice ==

= 1.0.0 =
* Translation in English and Italian
* Interface improvement
* Display fix

= 0.1.2 =
* Beta version
