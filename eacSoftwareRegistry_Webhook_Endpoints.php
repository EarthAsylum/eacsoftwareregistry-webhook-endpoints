<?php
/**
 * EarthAsylum Consulting {eac} Software Registration Server - WooCommerce Webhook Endpoints
 *
 * @category	WordPress Plugin
 * @package		{eac}SoftwareRegistry WooCommerce Webhook Endpoints
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 * @uses		{eac}SoftwareRegistry
 *
 * @wordpress-plugin
 * Plugin Name:			{eac}SoftwareRegistry Webhook Endpoints
 * Description:			Software Registration Server WooCommerce Webhook Endpoints - enables the use of WooCommerce Webhooks to create or update a software registration.
 * Version:				1.1.4
 * Requires at least:	5.8
 * Tested up to:		6.8
 * Requires PHP:		7.4
 * Plugin URI:          https://swregistry.earthasylum.com/webhooks-for-woocommerce/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 * License: 			GPLv3 or later
 * License URI: 		https://www.gnu.org/licenses/gpl.html
 * Text Domain:			eacSoftwareRegistry
 * Domain Path:			/languages
 */

/*
 * This simple plugin file responds to the 'eacSoftwareRegistry_load_extensions' filter to load additional extensions.
 * Using this method prevents overwriting extensions when the plugin is updated or reinstalled.
 */

namespace EarthAsylumConsulting;

class eacSoftwareRegistry_Webhook_Endpoints
{
	/**
	 * constructor method
	 *
	 * @return	void
	 */
	public function __construct()
	{
		/**
		 * eacSoftwareRegistry_load_extensions - get the extensions directory to load
		 *
		 * @param 	array	$extensionDirectories - array of [plugin_slug => plugin_directory]
		 * @return	array	updated $extensionDirectories
		 */
		add_filter( 'eacSoftwareRegistry_load_extensions',	function($extensionDirectories)
			{
				/*
    			 * Enable update notice (self hosted or wp hosted)
    			 */
				eacSoftwareRegistry::loadPluginUpdater(__FILE__,'wp');

				/*
    			 * Add links on plugins page
    			 */
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),function($pluginLinks, $pluginFile, $pluginData)
					{
						return array_merge(
							[
								'settings'		=> eacSoftwareRegistry::getSettingsLink($pluginData,'woocommerce'),
								'documentation'	=> eacSoftwareRegistry::getDocumentationLink($pluginData),
								'support'		=> eacSoftwareRegistry::getSupportLink($pluginData),
							],
							$pluginLinks
						);
					},20,3
				);

				/*
    			 * Add our extension to load
    			 */
				$extensionDirectories[ plugin_basename( __FILE__ ) ] = [plugin_dir_path( __FILE__ )];
				return $extensionDirectories;
			}
		);
	}
}
new \EarthAsylumConsulting\eacSoftwareRegistry_Webhook_Endpoints();
?>
