<?php
/**
 * EarthAsylum Consulting {eac} Software Registration WooCommerce Webhooks
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.earthasylum.com>
 * @version		2.x
 *
 * included for admin_options_settings() method
 * @version 24.0330.1
 */

defined( 'ABSPATH' ) or exit;

$this->registerExtension( $this->className,
	[
		'_order_info' 			=> array(
						'type'		=> 	'display',
						'label'		=> 	'<span class="dashicons dashicons-info-outline"></span> Orders',
						'default'	=>	'WooCommerce Webhooks are used to create or update a registration when a WooCommerce order is created or updated.'.
										'<p>Webhooks are created by going to: <code>WooCommerce &rarr; Settings &rarr; Advanced &rarr; Webhooks</code> from the dashboard of your WooCommerce shop site. '.
										'Use <code>Webhook Secret</code> and <code>Order Delivery URL</code> (below) when creating your webhooks.</p>'.
										'<p>You should create a webhook for <code>Order created</code> and <code>Order updated</code>, '.
										'and you may optionally create webhooks for <code>Order deleted</code> and <code>Order restored</code> '.
										'if your want registrations to be terminated or reactivated when an order is moved to the trash or restored.</p>',
						'info'		=>	'See: <a href="https://swregistry.earthasylum.com/webhooks-for-woocommerce/" target="_blank">{eac}SoftwareRegistry WebHooks for WooCommerce</a>',
					),
		'registrar_webhook_key'	=> array(
						'type'		=> 	'disabled',
						'label'		=> 	'Webhook Secret',
						'default'	=>	hash('md5', uniqid(), false),
						'title'		=>	'Your WooCommerce Webhook Secret.',
						'info'		=>	'Used to authenticate webhook requests from your WooCommerce site.',
					),
		'_webhook_url' 			=> array(
						'type'		=> 	'disabled',
						'label'		=> 	'Order Delivery URL',
						'default'	=>	home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/wc-order"),
						'title'		=>	'Your WooCommerce Webhook Delivery URL for orders.',
						'info'		=>	'The order webhook end-point on this registration server.'
					),
		'_subsription_info' 	=> array(
						'type'		=> 	'display',
						'label'		=> 	'<span class="dashicons dashicons-info-outline"></span> Subscriptions',
						'default'	=>	'By installing the <em>{eac}SoftwareRegistry Subscriptions for WooCommerce</a></em> '.
										'plugin on your WooCommerce shop site, webhooks for subscriptions are made available '.
										'and registrations can be updated when subscriptions are updated or renewed.'.
										'<p>For subscriptions, you create another webhook in WooCommerce choosing <code>{eac}SoftwareRegistry Subscription updated</code> as the topic. '.
										'Use the same <code>Webhook Secret</code> (above) with the <code>Subscription Delivery URL</code> (below).</p>',
						'info'		=>	'See: <a href="https://swregistry.earthasylum.com/subscriptions-for-woocommerce/" target="_blank">{eac}SoftwareRegistry Subscriptions for WooCommerce</a>',
					),
		'_subscription_url' 	=> array(
						'type'		=> 	'disabled',
						'label'		=> 	'Subscription Delivery URL',
						'default'	=>	home_url("/wp-json/".$this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION."/wc-subscription"),
						'title'		=>	'Your WooCommerce Webhook Delivery URL for subscriptions.',
						'info'		=>	'The subscription webhook end-point on this registration server.'
					),
		'_options_info' 	=> array(
						'type'		=> 	'display',
						'label'		=> 	'<span class="dashicons dashicons-info-outline"></span> Options',
						'default'	=>	'Webhook Processing Options.',
					),
		'registrar_webhook_type'=> array(
						'type'		=> 	'radio',
						'label'		=> 	'Registration Type',
						'options'	=> 	[
											['Per Item'		=> 'item'],
											['Per Order'	=> 'order'],
										],
						'default'	=>	'item',
						'title'		=>	'Create one registration for EACH item in the order (per item) -OR- '.
										'Create one registration for ALL items in the order (per order).',
					),
		'registrar_webhook_items'=> array(
						'type'		=> 	'textarea',
						'label'		=> 	'Registration Item Mapping',
						'title'		=>	'WooCommerce SKUs to be registered, mapped to registered package(s).',
						'info'		=>	'Enter "sku=package" (or sku=sku), or enter "sku=package1,package2" to create a bundle.<br/>'.
										'Enter 1 SKU per line. SKUs not listed will be ignored. '.
										'<small>Regular expressions supported, e.g. "sku*=package"</small>',
					),
		'registrar_webhooks'	=> array(
						'type'		=> 	'checkbox',
						'label'		=> 	'Webhook Endpoints',
						'options'	=>	[
											['Order created (New Registration)'			=> 'create'],
											['Order updated (Revise Registration)'		=> 'revise'],
											['Order deleted (Deactivate Registration)'	=> 'deactivate'],
											['Order restored (Activate Registration)'	=> 'activate'],
											['Subscription Updated (New/Renew Registration)'=> 'subscription'],
										],
						'default'	=> 	['create','revise','deactivate','activate'],
						'title'		=>	'Select the appropriate <code>Webhook Endpoints</code> based on the order and subscription webhooks created on your WooCommerce shop site.',
						'info'		=> 	'Enable end-points on this server to allow access via webhooks from your WooCommerce shop site.',
						'style'		=>	'display: block;',
					),
		'orders_with_subscriptions' => array(
						'type'		=> 	'checkbox',
						'label'		=> 	'Orders with Subscriptions',
						'options'	=>	[
											['Ignore Order Records with Subscriptions'	=> 'ignore'],
										],
						'default'	=>	'ignore',
						'title'		=>	'When both orders and subscriptions are passed through WooCommerce webhooks, '.
										'order records with subscriptions may be ignored while processing '.
										'non-subscription orders and subscription records independently.',
					),
		'subscription_grace_period'=> array(
						'type'		=> 	'select',
						'label'		=> 	'Subscription Grace Period',
						'options'	=> 	[
											'None',
											'1 day',
											'3 days',
											'5 days',
											'1 week',
											'2 weeks',
											'3 weeks',
											'1 month',
										],
						'title'		=>	'Normally, a registration is set to expire on the same day that the subscription is due to renew. '.
										'By giving a grace period, the registration will remain active for some period after the renewal date.'
					),
	]
);
