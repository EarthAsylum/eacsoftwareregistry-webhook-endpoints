<?php
namespace EarthAsylumConsulting\Extensions;

/**
 * EarthAsylum Consulting {eac} Software Registration WooCommerce Webhooks
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 */

class woocommerce_webhooks extends \EarthAsylumConsulting\abstract_extension
{
	/**
	 * @var string extension version
	 */
	const VERSION	= '25.0904.1';

	/**
	 * @var string extension tab name
	 */
	const TAB_NAME 	= 'WooCommerce';

	/**
	 * @var string|array|bool to set (or disable) default group display/switch
	 */
	const ENABLE_OPTION	= 'WooCommerce WebHooks';

	/**
	 * @var array result data
	 */
	private $result	= array();

	/**
	 * @var array order status to registry status
	 */
	const ORDER_STATUS = [
		'completed'		=> 'active',
		'overdue'		=> 'active',
		'pending'		=> 'pending',
		'processing'	=> 'pending',
		'on-hold'		=> 'pending',
		'cancelled'		=> 'terminated',
		'refunded'		=> 'inactive',
		'failed'		=> 'inactive',
		'trash'			=> 'terminated',
	];

	/**
	 * @var array subscription status to registry status
	 */
	const SUBSCRIPTION_STATUS = [
		'pending'		=> 'pending',			// created, not yet paid/active
		'active'		=> 'active',			// paid/active
		'on-hold'		=> 'pending',			// placed on hold awaiting payment
		'pause'			=> 'pending',			// paused subscription ?
		'trial'			=> 'trial',				// trial subscription (resumed)
		'expired'		=> 'expired',			// passed end date
		'pending-cancel'=> 'pending-cancel', 	// cancelled, not yet past pre-paid date (invalid, retains current/default)
		'suspended'		=> 'inactive',			// suspended, (sumo) past pre-paid date
		'cancelled'		=> 'inactive',			// cancelled, past pre-paid date
		'trash'			=> 'inactive',			// don't trash subscriptions, can't restore from here
	];

	/**
	 * @var string webhook action (order.created, order.updated, order.deleted, order.restored)
	 */
	private $webhookAction;


	/**
	 * constructor method
	 *
	 * @param 	object	$plugin main plugin object
	 * @return 	void
	 */
	public function __construct($plugin)
	{
		parent::__construct($plugin, self::ALLOW_ADMIN);

		if ($this->is_admin())
		{
			$this->registerExtension( [$this->className,self::TAB_NAME] );
			// Register plugin options when needed
			$this->add_action( "options_settings_page", array($this, 'admin_options_settings') );
		}

		add_action( 'rest_api_init', 		array($this, 'register_api_routes') );
	}


	/**
	 * register options on options_settings_page
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options_settings()
	{
		require_once 'includes/woocommerce_webhooks.options.php';
	}


	/**
	 * Register a WP REST api
	 *
	 * @return void
	 */
	public function register_api_routes($restServer)
	{
		register_rest_route( $this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION, '/wc-order', array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_order_registration' ),
					'permission_callback' => array( $this, 'rest_authentication' ),
				),
		));
		register_rest_route( $this->plugin::CUSTOM_POST_TYPE.$this->plugin::API_VERSION, '/wc-subscription', array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_subscription_registration' ),
					'permission_callback' => array( $this, 'rest_authentication' ),
				),
		));
	}


	/**
	 * REST API Authentication
	 *
	 * @param 	object	$request - WP_REST_Request Request object.
	 * @return 	bool
	 */
	public function rest_authentication($rest)
	{
		$this->plugin->setApiSource('WebHook');
		$this->plugin->setApiAction('webhook');

		if ( ($authKey = $rest->get_header( 'x-wc-webhook-signature' )) )
		{
			$hash 	= $this->get_option('registrar_webhook_key');
			$hash 	= base64_encode(hash_hmac('sha256', $rest->get_body(), $hash, true));

			if ($hash == $authKey)
			{
				$this->webhookAction = $rest->get_header( 'x-wc-webhook-topic' );
				$origin = parse_url($rest->get_header( 'x-wc-webhook-source' ));
				$this->webhookSource = $origin['host'];
				// allow CORS origin
				$origin = $origin['scheme'].'://'.$origin['host'];
				add_filter( 'http_origin', function() use ($origin) {
					return $origin;
				});
				add_filter( 'allowed_http_origins', function ($allowed) use ($origin) {
					$allowed[] = $origin;
					return $allowed;
				});

				switch ($this->webhookAction)
				{
					case 'order.created':
						return $this->is_option('registrar_webhooks','create');
					case 'order.updated':
						return $this->is_option('registrar_webhooks','revise');
					case 'order.deleted':
						return $this->is_option('registrar_webhooks','deactivate');
					case 'order.restored':
						return $this->is_option('registrar_webhooks','activate');
					case 'action.wc_eacswregistry_subscription':
						return $this->is_option('registrar_webhooks','subscription');
					case 'action.wc_eacswregistry_sumosub':
						return $this->is_option('registrar_webhooks','subscription');
				}
			}
		}
		else if (isset($_POST['webhook_id']))
		{
			// test ping from woo when the webhook is first created
			http_response_code(200);
			die();
		}

		http_response_code(401);
		return false;
	}


	/**
	 * Add filters and actions - called from main plugin
	 *
	 * @return	void
	 */
	public function addActionsAndFilters()
	{
		// override registrar options to allow setting status/expiration
		$this->add_filter( 'is_registrar_option', '__return_true' );
	}


	/**
	 * Webhook create, update, delete, or restore a registration key for all items in an order
	 *
	 * @param 	object	$rest - WP_REST_Request Request object.
	 * @return 	void
	 */
	public function update_order_registration($rest)
	{
		$request = $this->plugin->getRequestParameters($rest);
		if (is_wp_error($request)) {
			return rest_ensure_response([
				'action'	=> $this->webhookAction,
				'status' 	=> 'error',
				'result'	=> $request->get_error_message(),
			]);
		}

		$this->plugin->logDebug($request,__METHOD__);

		/*
		 * delete any/all registrations with this order id (only id is passed)
		 */
		if ($this->webhookAction == 'order.deleted')
		{
			$orderKeys = $this->getRegistrationKeys( $request['id'] );
			foreach ($orderKeys as $orderKey)
			{
				$this->updateRegistrationKey( ['registry_key'=>$orderKey->key_id], $request );
			}
			return rest_ensure_response([
				'action'	=> $this->webhookAction,
				'resource'	=> $request['id'],
				'status' 	=> 'success',
				'result'	=> $this->result,
			]);
		}

		if (isset($request['subscriptions']))
		{
			// we can ignore orders with subscriptions when we are processing subscriptions independently
			if ($this->is_option('orders_with_subscriptions','ignore')) {
				return rest_ensure_response([
					'action'	=> $this->webhookAction,
					'resource'	=> $request['id'],
					'status' 	=> 'ignored',
					'result'	=> 'order with subscription',
				]);
			}
			// use the subscription parent order id
			$request['id'] = current($request['subscriptions'])['parent_id'];
		}
		else
		{
			// We don't do subscription renewal orders unless we have the subscription, otherwise looks like a new order.
			if (in_array($request['created_via'],['subscription','sumo_subs'])) {
				return rest_ensure_response([
					'action'	=> $this->webhookAction,
					'resource'	=> $request['id'],
					'status' 	=> 'ignored',
					'result'	=> 'renewal order without subscription',
				]);
			}
		}

		// filter line items in the order
		$request['line_items'] = $this->getLineItems($request, $request['subscriptions'] ?? []);
		if (empty($request['line_items'])) {
			return rest_ensure_response([
				'action'	=> $this->webhookAction,
				'resource'	=> $request['id'],
				'status' 	=> 'ignored',
				'result'	=> 'no subscription items found',
			]);
		}

		// get existing registration
		$orderKeys = $this->getRegistrationKeys( $request['id'] );

		// initialize the registry array
		$registry = $this->initializeRegistry($request);

		/*
		 * create/update registration by order or item(s)
		 */
		if ( (empty($orderKeys) && $this->is_option('registrar_webhook_type','order')) ||
			 (key($orderKeys) == $this->formatTransId($request['id'])) )
		{
			$this->update_registration_key_by_order($registry, $request, $orderKeys);
		}
		else
		{
			$this->update_registration_key_by_item($registry, $request, $orderKeys);
		}

		return rest_ensure_response([
			'action'	=> $this->webhookAction,
			'resource'	=> $request['id'],
			'status' 	=> 'success',
			'result'	=> $this->result,
		]);
	}


	/**
	 * Webhook update a registration key from the subscription update
	 *
	 * @param 	object	$rest - WP_REST_Request Request object.
	 * @return 	void
	 */
	public function update_subscription_registration($rest)
	{
		$request = $this->plugin->getRequestParameters($rest);
		if (is_wp_error($request)) {
			return rest_ensure_response([
				'action'	=> $this->webhookAction,
				'status' 	=> 'error',
				'result'	=> $request->get_error_message(),
			]);
		}

		$this->plugin->logDebug($request,__METHOD__);

		// filter line items in the order
		$request['line_items'] = $this->getLineItems($request, [ $request['id'] => $request ]);
		if (empty($request['line_items'])) {
			return rest_ensure_response([
				'action'	=> $this->webhookAction,
				'resource'	=> $request['id'],
				'status' 	=> 'ignored',
				'result'	=> 'no subscription items found',
			]);
		}

		// use the subscription parent order id
		$request['id'] = $request['parent_id'];

		$transId = $request['switched_order'] ?? $request['id'];
		// get existing registration
		$orderKeys = $this->getRegistrationKeys( $transId );

		// initialize the registry key
		$registry = $this->initializeRegistry($request);

		if ( (empty($orderKeys) && $this->is_option('registrar_webhook_type','order')) ||
			 (key($orderKeys) == $this->formatTransId($transId)) )
		{
			$this->update_registration_key_by_order($registry, $request, $orderKeys);
		}
		else
		{
			$this->update_registration_key_by_item($registry, $request, $orderKeys);
		}

		return rest_ensure_response([
			'action'	=> $this->webhookAction,
			'resource'	=> $request['id'],
			'status' 	=> 'success',
			'result'	=> $this->result,
		]);
	}


	/**
	 * create or update a registration key for all items in an order (by order)
	 *
	 * @param 	array	$order_registry - initialized registration array
	 * @param 	array	$request - decoded rest request array
	 * @param 	array	$orderKeys - [ trans_id => {trans_id->, post_id->, key_id->} ]
	 * @return 	void
	 */
	private function update_registration_key_by_order($order_registry, $request, $orderKeys=[])
	{
		$registry = $order_registry;
		$trans_id = $this->formatTransId($request['switched_order'] ?? $request['id']);

		if (isset($orderKeys[ $trans_id ]))
		{
			$request['post_id'] 			= $orderKeys[ $trans_id ]->post_id;
			$registry['registry_key']		= $orderKeys[ $trans_id ]->key_id;
		}

		$trans_id = $this->formatTransId($request['id']);

		$registry['registry_transid']		= $trans_id;

		$items = array();
		$price = $total = 0;

		foreach ($request['line_items'] as $line_sku => $line_item)
		{
			if (empty($registry['registry_product']))
			{
				$registry['registry_product']		= $line_sku;
				$registry['registry_description']	= $line_item['name'];
				$registry['registry_status'] 		= $this->setStatus($line_item);
				$registry['registry_effective'] 	= $this->setEffective($line_item);
				$registry['registry_expires'] 		= $this->setExpiration($line_item);
				$registry['registry_paydate'] 		= $this->setPaidDate($line_item);
				$registry['registry_nextpay'] 		= $this->setNextPayDate($line_item);
			}
			else
			{
				$items[ $line_sku ] = $line_item['name'];
			}

			if (floatval($line_item['subtotal']) > 0)
			{
				$total += floatval($line_item['subtotal']);
			}

			foreach ($line_item['meta_data'] as $meta)
			{
				if (substr($meta['key'],0,9) == 'registry_' && !isset($registry[ $meta['key'] ]))
				{
					$registry[ $meta['key'] ] 	= sanitize_textarea_field(trim($meta['value']));
				}
			}
		}
		if (!empty($items))
		{
			$registry['registry_variations'] 	= $items;
		}

		if ($total > 0)
		{
			$registry['registry_paydue'] 		= $total;
			if (!empty($registry['registry_paydate'])) {
				$registry['registry_payid'] 	= $request['transaction_id'];
				$registry['registry_payamount']	= $total;
			}
		}

		$this->updateRegistrationKey($registry, $request);
	}


	/**
	 * create or update a registration key for each item in an order (by item)
	 *
	 * @param 	array	$order_registry - initialized registration array
	 * @param 	array	$request - decoded rest request array
	 * @param 	array	$orderKeys - [ trans_id => {trans_id->, post_id->, key_id->} ]
	 * @return 	void
	 */
	private function update_registration_key_by_item($order_registry, $request, $orderKeys=[])
	{
		foreach ($request['line_items'] as $line_sku => $line_item)
		{
			$registry = $order_registry;
			$trans_id = $this->formatTransId($request['switched_order'] ?? $request['id'],$line_sku);

			if (isset($orderKeys[ $trans_id ]))
			{
				$request['post_id'] 			= $orderKeys[ $trans_id ]->post_id;
				$registry['registry_key']		= $orderKeys[ $trans_id ]->key_id;
			}

			$trans_id = $this->formatTransId($request['id'],$line_sku);

			$registry['registry_transid']		= $trans_id;

			$registry['registry_product']		= $line_sku;
			$registry['registry_description']	= $line_item['name'];
			$registry['registry_status'] 		= $this->setStatus($line_item);
			$registry['registry_effective'] 	= $this->setEffective($line_item);
			$registry['registry_expires'] 		= $this->setExpiration($line_item);
			$registry['registry_paydate'] 		= $this->setPaidDate($line_item);
			$registry['registry_nextpay'] 		= $this->setNextPayDate($line_item);

			$total	= floatval($line_item['subtotal']);

			if ($total > 0)
			{
				$registry['registry_paydue'] 		= $total;
				if (!empty($registry['registry_paydate'])) {
					$registry['registry_payamount']	= $total;
					$registry['registry_payid'] 	= $request['transaction_id'];
				}
			}

			foreach ($line_item['meta_data'] as $meta)
			{
				if (substr($meta['key'],0,9) == 'registry_' && !isset($registry[ $meta['key'] ]))
				{
					$registry[ $meta['key'] ] = sanitize_textarea_field(trim($meta['value']));
				}
			}

			$this->updateRegistrationKey($registry, $request);
		}
	}


	/**
	 * update registration key
	 *
	 * performs the registry update using the plugin api
	 *
	 * @param 	array	$registry
	 * @param 	array	$request - decoded rest request array
	 * @return 	void
	 */
	private function updateRegistrationKey($registry, $request)
	{
		// WooCommerce passes Delivery URL as http_referer, (at best, it would be the store url)
		// its presence would trigger an invalid domain error
		unset($_SERVER['HTTP_REFERER']);

		switch ($this->webhookAction)
		{
			case 'order.created':

				$this->plugin->emailToClient(true);

				switch ($request['status'])
				{
					case 'completed':
						// no break
					case 'failed':
						// no break
					default:
						if (in_array($request['created_via'],['subscription','sumo_subs'])) {
							$this->plugin->setApiAction('renew');
							$result = $this->plugin->revise_registration_key($registry);
						} else if (isset($request['post_id'])) {
							$this->plugin->setApiAction('revise');
							$result = $this->plugin->revise_registration_key($registry);
						} else {
							$this->plugin->setApiAction('create');
							$result = $this->plugin->create_registration_key($registry);
						}
				}
				break;

			case 'order.updated':

				switch ($request['status'])
				{
					case 'refunded':
						break 2;
					case 'cancelled':
						if ($registry['registry_status'] == 'pending-cancel') {
							$this->plugin->setApiAction('revise');
							$result = $this->plugin->revise_registration_key($registry);
							break;
						}
						// no break
					case 'trash':
					case 'failed':
						$this->plugin->setApiAction('deactivate');
						$result = $this->plugin->deactivate_registration_key($registry);
						break;
					case 'completed':
					//	$this->plugin->emailToClient(true);
						// no break
					default:
						$this->plugin->setApiAction('revise');
						$result = $this->plugin->revise_registration_key($registry);
				}
				break;

			case 'order.deleted':

				$this->plugin->setApiAction('deactivate');
				$result = $this->plugin->deactivate_registration_key($registry);
				break;

			case 'order.restored':

				// un-trash since the api does not work with trashed posts
				$result = wp_update_post(array(
					'ID'			=> $request['post_id'],
					'post_status'	=> 'draft',
				),false); // no after filters

				if (! is_wp_error($result))
				{
					$this->plugin->setApiAction('activate');
					$result = $this->plugin->revise_registration_key($registry);
				}
				break;

			case 'action.wc_eacswregistry_subscription':
			case 'action.wc_eacswregistry_sumosub':

				$this->plugin->emailToClient(true);
				if (!isset($request['post_id']))
				{
					$this->plugin->setApiAction('create');
					$result = $this->plugin->create_registration_key($registry);
				}
				else if ($registry['registry_status'] == 'active' && (reset($request['related_orders']) == 'renewal'))
				{
					$this->plugin->setApiAction('renew');
					$result = $this->plugin->revise_registration_key($registry);
				}
				else
				{
					$this->plugin->setApiAction('revise');
					$result = $this->plugin->revise_registration_key($registry);
				}
				break;
		}

		if ($result->is_error()) {
			$result = (array)$result->get_data();
			$this->result[] = array($registry['registry_product'] => [
				$result['status']['code'] 	=> $result['error']['message'] ]);
		} else {
			$result = (array)$result->get_data();
			$this->result[] = array($registry['registry_product'] => [
				$result['status']['code'] 	=> $result['registration']['registry_key'] ]);
		}
		$this->plugin->logDebug([$registry,$result],__METHOD__.' '.$this->webhookAction);
	}


	/**
	 * get the status from line item
	 *
	 * @param 	array	$line_item line item array
	 * @return	string 	status
	 */
	private function setStatus($line_item)
	{
		if ($line_item['status'] == 'active')
		{
			$today = $this->datetime()->format('Y-m-d');
			$date = $this->getThisDate('trial',$line_item);
			if ($date > $today) return 'trial';
		}

		return $line_item['status'];
	}


	/**
	 * get the effective date from line item
	 *
	 * @param 	array	$line_item line item array
	 * @return	string 	formatted date or null
	 */
	private function setEffective($line_item)
	{
		return $this->getThisDate('start',$line_item);
	}


	/**
	 * get the expiration date from subscription
	 *
	 * @param 	array	$line_item line item array
	 * @return	string 	formatted date or null
	 */
	private function setExpiration($line_item)
	{
		$today = $this->datetime()->format('Y-m-d');
		$addToDate = $this->get_option('subscription_grace_period'); // '1 day'
		$addToDate = (!empty($addToDate) && is_numeric($addToDate[0])) ? '+'.$addToDate : null;

		// trial end date
		if( ($date = $this->getThisDate('trial',$line_item,$addToDate)) && $date > $today ) return $date;

		// next payment date
		if( ($date = $this->getThisDate('next',$line_item,$addToDate)) && $date > $today ) return $date;

		// subscription end date
		if( ($date = $this->getThisDate('end',$line_item,$addToDate)) && $date > $today ) return $date;

		return null; // let the main plugin set the expiration date
	}


	/**
	 * get the paid date from line item
	 *
	 * @param 	array	$line_item line item array
	 * @return	string 	formatted date or null
	 */
	private function setPaidDate($line_item)
	{
		// paid date is set even when not collected during trial period
		$trial 	= $this->getThisDate('trial',$line_item);
		$paid 	= $this->getThisDate('paid',$line_item);
		return (!$trial || $paid >= $trial) ? $paid : false;
	}


	/**
	 * get the next payment date from line item
	 *
	 * @param 	array	$line_item line item array
	 * @return	string 	formatted date or null
	 */
	private function setNextPayDate($line_item)
	{
		$today = $this->datetime();
		$date = false;
		// return false so we clear any previous value
		if (isset($line_item['next']) && ($date = $line_item['next']))
		{
			$date = $this->datetime($date);
		}
		return ($date > $today ) ? $date->format('Y-m-d') : false;
	}


	/**
	 * get a date value from a line_item
	 *
	 * @param 	string	$name line_item field name
	 * @param 	array	$line_item line item array
	 * @param	string	$modify time to add or subtract (+1 day)
	 * @return	string 	formatted date or null
	 */
	private function getThisDate($name,$line_item,$modify = null)
	{
		if (isset($line_item[$name]) && ($date = $line_item[$name]))
		{
			$date = $this->datetime($date,$modify);
			return ($date) ? $date->format('Y-m-d') : null;
		}
		return null;
	}


	/**
	 * format the transaction id
	 *
	 * @param 	string	$trans_id
	 * @param 	string	$trans_sku null (order level), sku (item level)
	 * @return	string 	formated transaction id
	 */
	private function formatTransId($trans_id, $trans_sku = null)
	{
		if ($trans_sku)
		{
			$trans_sku = '|'.sanitize_title_for_query($trans_sku);
		}
		//	552|dev.earthasylum.net|eacDoojigger
		return trim("{$trans_id}|{$this->webhookSource}{$trans_sku}");
	}


	/**
	 * Initialize the registry array
	 *
	 * @param 	array	$request - decoded rest request array
	 * @return	array
	 */
	public function initializeRegistry($request)
	{
		$address 		= $request['billing']['address_1'];
		if (! empty($request['billing']['address_2']))
		{
			$address 	.= "\n".$request['billing']['address_2'];
		}
		$address 		.= "\n".$request['billing']['city'].' '.
								$request['billing']['state'].' '.
								$request['billing']['postcode'];

		$registry = array(
			'registry_product'		=> null,
		//	'registry_version'		=> '',
		//	'registry_license'		=> '',
		//	'registry_count'		=> '',
			'registry_name'			=> $request['billing']['first_name'].' '.$request['billing']['last_name'],
			'registry_email'		=> $request['billing']['email'],
			'registry_company'		=> $request['billing']['company'],
			'registry_address'		=> $address,
			'registry_phone'		=> $request['billing']['phone'],
		//	'registry_variations'	=> array(),
		//	'registry_options'		=> array(),
		//	'registry_domains'		=> array(),
		//	'registry_sites'		=> array(),
			'registry_timezone'		=> $request['registry_timezone'] ?: '',
			'registry_locale'		=> $request['registry_locale'] ?: '',
		);

		return $registry;
	}


	/**
	 * Get items to be registered
	 *
	 * @param 	array	$request - decoded rest request array
	 * @param 	array 	$subscriptions - array [id=>subscription]
	 * @return	array 	filtered/matched line items
	 */
	private function getLineItems($request, $subscriptions)
	{
		// item mapping = sku=item,item,... \n sku=item,item,...
		$mapItems 	= $this->textToArray($this->get_option('registrar_webhook_items'));

		$skuSubId 	= [];	// [sku=>subscription_id or 0]
		$skuMetaA 	= [];	// [sku=>product_meta]

		// [ subid => [itemid=>meta] ]
		$products = $this->getProductMeta($request, $subscriptions);

		// from the product meta, get first product match (registry_product or sku mapping)
		foreach ($products as $sub_id => $productMeta)
		{
			foreach ($productMeta as $product)
			{
				// looking for $product[attributes|meta_data]['registry_product']
				foreach(['attributes','meta_data'] as $key)
				{
					if ( array_key_exists('registry_product',$product[$key]) &&
						!empty($product[$key]['registry_product']) )
					{
						$mapItems[ $product['sku'] ] 	= $product[$key]['registry_product'];
						$skuSubId[ $product['sku'] ] 	= $sub_id;
						$skuMetaA[ $product['sku'] ] 	= array_merge($product['meta_data'],$product['attributes']);
						break;
					}
				}
				// looking for $product['sku'] like item mapping sku
				foreach ($mapItems as $mapSku => $mapSkuItems)
				{
					if (preg_match('/^'.$mapSku.'$/i',$product['sku']))
					{
						$mapItems[ $product['sku'] ] 	= $mapSkuItems;
						$skuSubId[ $product['sku'] ] 	= $sub_id;
						$skuMetaA[ $product['sku'] ] 	= array_merge($product['meta_data'],$product['attributes']);
						break;
					}
				}
			}
		}

		if (empty($mapItems)) return [];

		$line_items = $request['line_items'];

		//$this->plugin->logDebug([$mapItems,$skuSubId,$skuMetaA],__METHOD__);

		$validItems = array();

		// now we have product mappings and meta array, find matching/valid line items
		foreach ($line_items as $line_item)
		{
			$line_item['status'] = null; // required
			foreach ($mapItems as $mapSku => $mapSkuItems)
			{
				if (preg_match('/^'.$mapSku.'$/i',$line_item['sku']))
				{
					$mapSkuItems = $this->textToArray(str_replace([',','|',';'],"\n",$mapSkuItems));
					foreach ($mapSkuItems as $mapSkuItem)
					{
						if (isset($skuSubId[$mapSku])) // should be
						{
							if (isset($subscriptions[ $skuSubId[$mapSku] ])) {
								$subscription 				= $subscriptions[ $skuSubId[$mapSku] ];
								$line_item['subId']			= $subscription['id'];
								$line_item['orderId']		= $subscription['parent_id'];
								$line_item['status']		= self::SUBSCRIPTION_STATUS[ $subscription['status'] ];
								$line_item['start']			= $subscription['schedule_start'] ?: null;
								$line_item['end']			= $subscription['schedule_end'] ?: null;
								$line_item['trial']			= $subscription['schedule_trial_end'] ?: null;
								$line_item['paid']			= $subscription['date_paid'] ?: null;
								$line_item['next']			= $subscription['schedule_next_payment'] ?: null;
							} else {
								$line_item['subId']			= 0;
								$line_item['orderId']		= $request['id'];
								$line_item['status']		= self::ORDER_STATUS[ $request['status'] ] ?? 'pending';
								$line_item['start']			= $request['date_completed_gmt'] ?: null;
								$line_item['paid']			= $request['date_paid_gmt'] ?: null;
							}
							foreach($skuMetaA[$mapSku] as $key=>$value) {
								$line_item['meta_data'][] 	= ['key'=>$key,'value'=>str_replace(['-','_','.'],' ',$value)];
							}
						}
						$validItems[$mapSkuItem] 			= $line_item;
					}
					break; // found match
				}
			}
		}

		$this->plugin->logDebug($validItems,__METHOD__);
		return $validItems;
	}


	/**
	 * Get order/subscription meta data
	 *
	 * @param 	array	$request - decoded rest request array
	 * @param 	array 	$subscriptions - array [id=>subscription]
	 * @return	array 	[ subid => [itemid=>meta] ]
	 */
	private function getProductMeta($request, $subscriptions)
	{
		$products = array();

		// first (lowest priority) get the product_meta arrays from the order
		if (isset($request['product_meta']))
		{
			// from eacSoftwareRegistry_Subscription_Webhooks
			$products[0] = $request['product_meta'];
		}
		else
		{
			// includes meta_data & attributes from line items
			$products[0] = array_map(
				function($line_item)
				{
					// strip "pa_" prefix (product attribute)
					$attributes = array_key_exists('attributes',$line_item) ? $line_item['attributes'] : [];
					$_attributes = [];
					foreach ( $attributes as $key => $value) {
						$_attributes[ preg_replace(['/^pa_(.+)/','/registry-/'],['$1','registry_'],$key) ] = $value;
					}
					// strip "pa_" prefix (product attribute)
					$metadata = array_key_exists('meta_data',$line_item) ? $line_item['meta_data'] : [];
					$_metadata = [];
					foreach ( $metadata as $value) {
						if ($value['key'][0] == '_') continue;
						$_metadata[ preg_replace(['/^pa_(.+)/','/registry-/'],['$1','registry_'],$key) ] = $value;
					}
					return [
						'sku'			=> $line_item['sku'],
						'name'			=> $line_item['name'],
						'attributes'	=> $_attributes,
						'meta_data' 	=> $_metadata,
					];
				},
				$request['line_items']
			);
		}

		// get the product_meta arrays from any subscription record(s)
		if (!empty($subscriptions))
		{
			// from eacSoftwareRegistry_Subscription_Webhooks
			foreach ($subscriptions as $sub_id => $subscription)
			{
				$products[$sub_id] = $subscription['product_meta'];
			}
		}

		//$this->plugin->logDebug($products,__METHOD__);
		return $products;
	}


	/**
	 * explode a textarea value to an array
	 *
	 * @param 	string $text as "something" or "key=something"
	 * @return	array
	 */
	private function textToArray($text)
	{
		$array = array_filter(
					array_map('trim',
						$this->plugin->explode_with_keys("\n", $text)
					),
				);
		//$this->plugin->logDebug(json_decode(json_encode($array)),__METHOD__);
		return (array)json_decode(json_encode($array));
	}


	/**
	 * get all registration keys from trans id like "id%"
	 *
	 * @param string $transId
	 * @return array [trans_id = [ {trans_id->, post_id->, key_id->} ]
	 */
	private function getRegistrationKeys($trans_id)
	{
		global $wpdb;
		$trans_id = $this->formatTransId($trans_id);

		$sql =
			"SELECT meta1.meta_value as trans_id, meta1.post_id, meta2.meta_value as key_id" .
			" FROM ".$wpdb->postmeta." AS meta1" .
			" INNER JOIN ".$wpdb->postmeta." AS meta2 ON ( meta1.post_id = meta2.post_id )" .
			" WHERE meta1.meta_key = '_registry_transid' AND meta1.meta_value LIKE '$trans_id%'" .
			"   AND meta2.meta_key = '_registry_key'";
		$posts = $wpdb->get_results( $sql, OBJECT_K );

		//$this->plugin->logDebug([$sql,(array)$posts],__METHOD__);

		// return associative array of objects, keyed by trans_id
		return (! is_wp_error($posts) && ! empty($posts)) ? (array)$posts : [];
	}


	/**
	 * Get date in given timezone
	 *
	 * @param string $date
	 * @param string $modify time to add or subtract (+1 day)
	 * @return object DateTime or null
	 */
	private function datetime( $date='now', $modify = null )
	{
		try {
			$date = $this->plugin->getDateTimeUTC($date);				// UTC
			$date = $this->plugin->getDateTimeInZone($date,$modify);	// registry default timezone
		} catch (\Throwable $e) {
			$this->plugin->logDebug($e,__METHOD__);
			$date = null;
		}
		return $date;
	}
}
/**
 * return a new instance of this class
 */
return new woocommerce_webhooks($this);
?>
