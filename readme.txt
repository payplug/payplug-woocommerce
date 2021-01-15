=== PayPlug for WooCommerce (Official) ===
Contributors: PayPlug
Tags: payplug, woocommerce, gateway, payment, credit card, carte de crÃ©dit, carte bancaire, paiement, one click, paiement en ligne
Requires at least: 4.4
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

PlayPlug is a French payment solution allowing small and medium e-commerce companies to accept online payments from Visa, MasterCard and CB cards. 

== Description ==

What this product does for you

PayPlug’s primary goal is to meet the specific needs of small and medium e-commerce companies :
- Simple set up and management
- Safety
- Optimized conversion
- Support


Features

Accept payments
- Fastest set up on the market, with no technical skills required
- Plugin developed by PayPlug, configurable with just a few clicks
- Reception of online credit card payments from CB, Visa, Mastercard, without needing an extra merchant account

Boost your sales
- Customizable payment page, optimized for mobile devices, integrated or redirected
- One-click payment with secure card information storage
- Customizable or predictive 3-D Secure tool (Smart 3-D Secure)

Monitor your performances
- Real time updates on your transactions and possibility to send payment demands via SMS or email to recover sales
- Transaction history and accounting records with one click, using the management interface
- Personalized support, in your preferred language

Discover our features: https://www.payplug.com/features
PayPlug offer several plans to suit your needs and business requirements. No set-up fees and no commitment ; you can change your offer whenever you want. More details: https://www.payplug.com/pricing.

Other

PayPlug is the first online payment solution made specifically for small businesses. In 2015, PlayPlug was certified as a payment operator with the French Prudential Supervision and Resolution Authority (public authority monitoring banks). The solution has a PCI DSS certification, the highest security standard for the treatment and storage of payment information. In 2017, PlayPlug joined the Natixis group.

+ 7 000 customers use PayPlug everyday
+ 300 web agencies recommend us


"Before contacting you, we were looking for simplicity, efficiency and speed in order to have secured payments. Now it's done!" - Guillaume Lombard, founder of Apiculture.net.
"PayPlug is a convenient and very easy-to-use solution. Customer service is efficient and allows a privileged relationship with a highly available account manager." - Ariane Phoumilay, founder of AyaNature
"We chose PayPlug because it is a simple solution created by a French start-up. Since we started using their solution, we've seen a 7% improvement in the conversion rate." - Jan Schutte, founder of Le Chemiseur
"We especially value the simplicity of installation, the integrated payment page and the extremely intuitive interface" - Xavier Poitau, founder of Trenta-Axome.
Based on a recent survey, 91% of merchants using PlayPlug would recommend the solution to another e-commerce website.

== Installation ==

1. Sign up for free on PayPlug : https://portal.payplug.com/signup
2. Install the plugin on WordPress
3. Activate the plugin in Plugins >  Installed Plugins
4. In Plugins > Installed Plugins > PayPlug for WooCommerce (Official) settings, log in with your PayPlug credentials
5. In Settings, check that  PayPlug is enabled, chose your payment settings and save changes

== Screenshots ==

1. Settings
2. Display on a WordPress website

== Changelog ==

= 1.1.0 =
One click payments with 3D Secure validation to be compatible with new DSP2 requirements

= 1.0.22 =
Update dependencies
Tested up to WordPress 5.4
Tested up to Woocommerce 4.0

= 1.0.21 =
The customer's phone number is now only forwarded to the bank networks if its format complies with the E.164 standard.
An incorrect phone number will not block the transaction

= 1.0.20 =
PSD 2 compatibility
Tested up to Woocommerce 3.7

= 1.0.18 =
Fix on cancelled orders with successful payment : allow PayPlug notification responses for cancelled orders
Fix on  miscreated orders : loading of the PayPlug form.js latest version from the plugin. With the latest version of form.js, the plugin does not have to wait for url_return redirection (5sec) to create payment within WooCommerce
Update devDependencies

= 1.0.17 =
Fix special characters in password for the PayPlug login
Update screens according to the new graphic charter
Upgrade LIVE keys retrieve when switching form TEST to LIVE mode, once PayPlug account has been activated (Password request pop-in)
Tested up to Woocommerce 3.6

= 1.0.15 =
Fix notification processing
Add new settings (Payment Gateway): Title and description

= 1.0.14 =
Fix message for Payment Method in the order confirmation email
Fix payment scheme logo size on checkout step
Fix lightbox access error
Upgrade payment scheme logos on checkout step
Specific payment scheme logos for Italian visitors (PostePay instead of CB) on checkout step

= 1.0.12 =
Security fix in dependencies
Fix typos in translations

= 1.0.11 =
Fix wrong message on the payment page

= 1.0.10 =
Fix stored credit cards display

= 1.0.7 =
Fix translations

= 1.0.0 =
Initial release

= 0.1.2 =
Beta version

== Upgrade Notice ==

= 1.0.0 =
Translation in English and Italian
Interface improvement
Display fix

= 0.1.2 =
Beta version
