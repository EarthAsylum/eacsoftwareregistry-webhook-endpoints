=== {eac}SoftwareRegistry WooCommerce Webhook Endpoints  ===
Plugin URI:         https://swregistry.earthasylum.com/webhooks-for-woocommerce/
Author:             [EarthAsylum Consulting](https://www.earthasylum.com)
Stable tag:         1.1.3
Last Updated:       19-Apr-2025
Requires at least:  5.8
Tested up to:       6.8
Requires PHP:       7.4
Contributors:       kevinburkholder
License:            GPLv3 or later
License URI:        https://www.gnu.org/licenses/gpl.html
Tags:               software registration, software registry, software license, WooCommerce, Webhooks, subscriptions, {eac}SoftwareRegistry
WordPress URI:      https://wordpress.org/plugins/eacsoftwareregistry-webhook-endpoints
Github URI:         https://github.com/EarthAsylum/eacsoftwareregistry-webhook-endpoints

Enables the use of WooCommerce Webhooks to create or update a software registration in {eac}SoftwareRegistry when an order or subscription is created or updated.

== Description ==

**{eac}SoftwareRegistry Webhook Endpoints** is an extension plugin to [{eac}SoftwareRegistry Software Registration Server](https://swregistry.earthasylum.com/software-registration-server/).

WooCommerce Webhooks are used to create or update a software registration in {eac}SoftwareRegistry when a WooCommerce order is created or updated.

>   A Webhook is an event notification sent to a URL of your choice. Users can configure them to trigger events on one site to invoke behavior on another.

The Software Registration Webhooks use the same internal methods as the Software Registry API (see [Implementing the Software Registry SDK](https://swregistry.earthasylum.com/software-registry-sdk/)).

We use webhooks so that you may sell your software on a different site then where you register your software. When an order is created or updated on your WooCommerce site, that order information is sent to your registration server via a webhook so that the registration server may create or update the registration.

WooCommerce Webhooks are created by going to: **WooCommerce → Settings → Advanced → Webhooks** from the dashboard of your WooCommerce shop site.

To get to the settings for this extension, go to **Software Registry → Settings → Woocommerce** from the dashboard of your software registration server.

On your WooCommerce site, use the **Webhook Secret** and **Order Delivery URL** defined by this extension when creating your webhooks. The **Webhook Secret** is used to authenticate the webhook and the **Delivery URL** is the webhook end-point (your registration server).

You should create a WooCommerce Webhook for **Order created** and **Order updated**, and you may optionally create webhooks for **Order deleted** and **Order restored** if your want registrations to be terminated or reactivated when an order is moved to the trash or restored.

\* See [Subscriptions](#subscriptions) below.

On your registration server, select the appropriate **Webhook Endpoints** in this extension based on the WooCommerce webhooks created.

= Product Variations and Registry Values =

On your shop site, you may create a variable product with a product attribute ('Used for variations' checked), like:

    registry_license    ->  'Lite' | 'Basic' | 'Standard' | 'Professional' | 'Enterprise' | 'Developer'

-- and/or --

    registry_count      ->  '1-User' | '10-Users' | '50-Users' | '100-Users' | 'Unlimited Users'

Then configure (or remove) each of the variations accordingly.
This produces a product variation for each license level (or user count) and passes `registry_license` (or `registry_count`) through the webhook overriding the default registration server settings.

As well, you may create an attribute and variation like:

    registry_expires    ->  '14 Days' | '30 Days' | '6 Months' | '1 Year'

To override the default registration term.

These variations may be combined to create a large number of variable products, each passing the given registry values through the webhooks. For example, one variable product may have a variation combination of `'Basic', '10-users', '6 Months'`


= Item Mapping =

On your registration server, you may specify the items (SKUs) that are to be registered in the **Registration Item Mapping** as:

`item_sku=package_name` or `item_sku=package_name1,package_name2` (to create a bundle).

Even if the item sku is the product to be registered, enter `item_sku=item_sku`.

Items that don't match these SKU(s) will be ignored.

Since WooCommerce won't allow duplicate SKUs, regular expressions may be used for "item_sku" matching. For example: `MyItemSku*=MyPackage` Will match any SKU in the order beginning with "MyItemSku" (e.g. "MyItemSku_1", "MyItemSku_2") and map (register) it as "MyPackage".


= Subscriptions =

By adding [{eac}SoftwareRegistry Subscriptions for WooCommerce](https://swregistry.earthasylum.com/subscriptions-for-woocommerce/) to your WooCommerce store site, subscription orders and updates (when using [Woo Subscriptions](https://woocommerce.com/document/subscriptions/) or [SUMO Subscription](https://codecanyon.net/item/sumo-subscriptions-woocommerce-subscription-system/16486054)), as well as product meta data, may also be passed to your registration server.

*{eac}SoftwareRegistry Subscriptions for WooCommerce* is a plugin, installed on your WooCommerce site, that adds a custom Webhook topic for subscription updates to the WooCommerrce webhooks, and adds subscription and product data to WooCommerce order webhooks.

On your WooCommerce site, add a new Webhook using **{eac}SoftwareRegistry WC Subscription** or **{eac}SoftwareRegistry Sumo Subscription** for the topic; the same **Webhook Secret** used for the order webhooks; and the **Subscription Delivery URL** rather than the **Order Delivery URL**.

With this plugin enabled, not only can you update registrations by order updates, but also by subscription updates, including renewals, expirations, and cancelations, making it easy to keep your registrations in sync with your subscriptions.

In addition, this plugin will add product meta data to the orders and subscriptions passed through the webhooks so that you may define registry values as custom fields at the product level.

For example, rather than needing to create variable products, you can simply add custom fields:

    registry_license    ->  'Basic'
    registry_count      ->  '10-Users'
    registry_expires    ->  '6 Months'

And, rather than relying on the `item_sku` list in the *Registration Item Mapping*, you can add a custom field...

    registry_product    ->  'package_name'

...that will register or update any subscription for the given item as `package_name` regardless of the item's SKU or the *Registration Item Mapping* list.

**If all of your orders are subscriptions...**

With this plugin enabled on your shop site, there's a high probability you don't need to use the "order" webhooks. Your subscriptions will be updated more efficiently from the shop subscription records.

When a new (or renewal) subscription order is created, it will trigger the "Order created", "Order updated" (payment processed) and the "Subscription updated" webhooks when all you need is the subscription to create or update the registration.

On the other hand, since this plugin adds an array of subscription records to the orders passed through the webhooks, you may prefer to use only the order webhooks and not the subscription webhook.


= Return Value =

As of version 1.1, this plugin now returns a result array which can be retrieved via the `woocommerce_webhook_delivery` action:

    array(
        'action'	=> string       // the webhook action,
        'resource'	=> int          // the webhook resource id (order/subscription id),
        'status' 	=> string       // 'success' | 'ignored' | 'error',
        'result'	=> array|string // success: array of [ sku => [status => registry_key | error_message] ]
                                    // ignored|error: string error_message
    )

*Note that 'success' means the webhook was succesfull, 'result' could contain an error status/message from {eac}SoftwareRegistry.*

Examples:

    array(
         'action' => 'order.created',
         'resource' => 2715,
         'status' => 'ignored',
         'result' => 'order with subscription',
    ),

    array(
        'action' => 'action.wc_eacswregistry_sumosub',
        'resource' => 2715,
        'status' => 'success',
        'result' => array (
            0 => array(
                'eacDoojigger' =>  array(
                    '200' => 'bad53cd3-f397-4f47-9d28-xxxxxxxxxxxx',
                ),
            ),
            1 => array(
                'eacSoftwareRegistry' => array(
                    '406' => 'registration with this email and product already exists',
                ),
            ),
        ),
    ),


== Installation ==

**{eac}SoftwareRegistry Webhook Endpoints** is an extension plugin to and requires installation and registration of [{eac}SoftwareRegistry](https://swregistry.earthasylum.com/).

= Automatic Plugin Installation =

This plugin is available from the [WordPress Plugin Repository](https://wordpress.org/plugins/search/earthasylum/) and can be installed from the WordPress Dashboard » *Plugins* » *Add New* page. Search for 'EarthAsylum', click the plugin's [Install] button and, once installed, click [Activate].

See [Managing Plugins -> Automatic Plugin Installation](https://wordpress.org/support/article/managing-plugins/#automatic-plugin-installation-1)

= Upload via WordPress Dashboard =

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacsoftwareregistry-webhook-endpoints.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

= Manual Plugin Installation =

You can install the plugin manually by extracting the eacsoftwareregistry-webhook-endpoints.zip file and uploading the 'eacsoftwareregistry-webhook-endpoints' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

= Settings =

Options for this extension will be added to the *Software Registry → Settings → Woocommerce* tab.


== Screenshots ==

1. Software Registry → Settings → WooCommerce
![{eac}SoftwareRegistry WooCommerce Integration](https://ps.w.org/eacsoftwareregistry-webhook-endpoints/assets/screenshot-1.png)

2. WooCommerce → Settings → Advanced → Webhooks
![{eac}SoftwareRegistry WooCommerce Integration](https://ps.w.org/eacsoftwareregistry-webhook-endpoints/assets/screenshot-2.png)


== Other Notes ==

= See Also =

+   [{eac}SoftwareRegistry – Software Registration Server](https://swregistry.earthasylum.com/software-registration-server/)

+   [{eac}SoftwareRegistry Subscriptions for WooCommerce](https://swregistry.earthasylum.com/subscriptions-for-woocommerce/)

+   [Implementing the Software Registry SDK](https://swregistry.earthasylum.com/software-registry-sdk/)


== Upgrade Notice ==

= 1.1.0 =

This version requires {eac}SoftwareRegistry v1.3.3+

= 1.0.9 =

This version requires {eac}SoftwareRegistry v1.2+



== Copyright ==

= Copyright © 2019-2025, EarthAsylum Consulting, distributed under the terms of the GNU GPL. =

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


== Changelog ==

= Version 1.1.3 – April 19, 2025 =

+   Compatible with WordPress 6.8+ and WooCommerce 9.8+

= Version 1.1.2 – November 23, 2024 =

+   Support for Sumo Subscription 'switch' (up/down-grade subscription).
+   Added server-side CORS support with `http_origin` and `allowed_http_origins` using `x-wc-webhook-source`.
+   Cleaned up settings screen and set tab name and enable option.
+   Compatible with WordPress 6.7, WooCommerce 9.4, and Sumo Subscriptions 15.7.

= Version 1.1.1 – September 8, 2024 =

+   'suspended' is a valid status (from Sumo Subscriptions).
+   Compatible with WordPress 6.6+ and WooCommerce 9.0+

= Version 1.1.0 – April 4, 2024 =

+   Support for SUMO Subscriptions from eacSoftwareRegistry_Subscription_Webhooks plugin.
+   Added webhook return value array.
+   Compatible with WordPress 6.5+ and WooCommerce 8.7+

= Version 1.0.10 – June 8, 2023 =

+   Removed unnecessary plugin_update_notice trait.

= Version 1.0.9 – March 24, 2023 =

+   Fixed timing of 'rest_api_init' (moved to constructor).
+   Tested with WordPress version 6.2 (RC4).

= Version 1.0.8 – November 15, 2022 =

+   Updated for {eac}SoftwareRegistry v1.2 and {eac}Doojigger v2.0.
+   Uses 'options_settings_page' action to register options.
+   Moved plugin_action_links_ hook to eacSoftwareRegistry_load_extensions filter.
+   Improved plugin loader and updater.

= Version 1.0.7 – September 24, 2022 =

+   Fixed potential PHP notice on load (plugin_action_links_).

= Version 1.0.6 – September 13, 2022 =

+   Added upgrade notice trait for plugins page.
+   Added expiration grace period for subscriptions.
+   Reworked dashboard options screen.

= Version 1.0.5 – August 28, 2022 =

+   Updated to / Requires {eac}Doojigger 1.2.0
+   Added 'Settings', 'Docs' and 'Support' links on plugins page.

= Version 1.0.4 – July 12, 2022 =

+   Removed excessive debugging log statements.
+   Cosmetic changes for WordPress submission.

= Version 1.0.3 – May 20, 2022 =

+   Support (strip) variation attributes with "pa_" prefix.
+   Clear next pay date when cancelled.

= Version 1.0.2 – May 4, 2022 =

+   Fixes for dates and schedules, minor enhancements.

= Version 1.0.1 – April 22, 2022 =

+   Added subscription support.

= Version 1.0.0 – April 15, 2022 =

+   Initial release.
