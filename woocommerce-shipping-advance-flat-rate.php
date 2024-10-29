<?php

/**
* Plugin Name: Advance Flat Rate Shipping For WooCommerce
* Description: AFR Shipping For WooCommerce provides ability to set city wise shipping rates for shipping classes and weight ranges.
* Author: Ishtiaq A.
* Version: 1.1.0
* WC requires at least: 3.2.0
* WC tested up to: 3.2.6
* Copyright: 2017 AllShoreVirtualStaffing.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* Text Domain: woocommerce-shipping-afr
*
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{
 
 	

	define( 'WC_SHIPPING_AFR_VERSION', '1.1.0' );
	define( 'WC_SHIPPING_AFR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'WC_SHIPPING_AFR_MINIMUM_WP_VERSION', '2.6.0' );
	define( 'WC_SHIPPING_AFR_MINIMUM_WC_VERSION', '3.2.0' );

	/**
	 * The code that runs during plugin activation.
	 *
	 * @since 1.0.0
	 *
	 */
	
	function wc_shipping_afr_activate() {
		add_option( 'wc_shipping_afr_show_upgrade_notice','yes');
	}
	register_activation_hook( __FILE__, 'wc_shipping_afr_activate' );

	/**
	 * The code that runs during plugin deactivation.
	 *
	 * @since 1.0.0
	 * @version 1.0.1
	 */
	function wc_shipping_afr_deactivate() {
		delete_option( 'wc_shipping_afr_show_upgrade_notice');
	}
	register_deactivation_hook( __FILE__, 'wc_shipping_afr_deactivate' );

	 


	class WC_Shipping_AFR_Init {
		 /**
		 * Plugin's Instance.
		 *
		 * @access private
		 * @since 1.0.0
		 * @var WC_Shipping_AFR_Init
		 */		
		private static $instance;

		/**
		 * Get the class instance
		 *
		 * @access public
		 * @since 1.0.0
		 * @return mixed WC_Shipping_AFR_Init instance
		 */
		public static function get_instance() {
			return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
		}

		/**
		 * Class constructor
		 *
		 * @access public
		 * @since 1.0.0
		 *
		 */
		public function __construct() {
			
			if ( class_exists( 'WC_Shipping_Method' ) ):
				add_action( 'admin_init', array( $this, 'install' ), 5 );
				add_action( 'init', array( $this, 'load_textdomain' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
				
				add_action( 'woocommerce_shipping_init', array( $this, 'includes' ) );
				add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
			
				add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
				add_action( 'wp_ajax_afr_dismiss_upgrade_notice', array( $this, 'afr_dismiss_upgrade_notice' ) );
				add_action( 'wp_ajax_nopriv_afr_dismiss_upgrade_notice', array( $this, 'afr_dismiss_upgrade_notice' ) );

				add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );
			else:
				add_action( 'admin_notices', array( $this, 'wc_deactivated' ) );
			endif;
		}

		/**
		 * WooCommerce notice
		 *
		 * @access public
		 * @since 1.0.0
		 * @return mixed raw html
		 *
		 */
		public function wc_deactivated() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Advance Flate Rate Shipping requires %s to be installed and active.', 'woocommerce-shipping-afr' ), '<a href="https://woocommerce.com" target="_blank">WooCommerce</a>' ) . '</p></div>';
		}

		/**
		 *  WooCommerce incompatible version notice
		 *
		 * @access public
		 * @since 1.0.0
		 * @return mixed raw html
		 *
		 */
		public function wc_incompitable_version() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Advance Flate Rate Shipping requires %s version %s to be installed and active.', 'woocommerce-shipping-afr' ), '<a href="https://woocommerce.com" target="_blank">WooCommerce</a>',WC_SHIPPING_AFR_MINIMUM_WC_VERSION ) . '</p></div>';
		}

		/**
		 * WordPress incompatible version notice
		 *
		 * @access public
		 * @since 1.0.0
		 * @return mixed raw html
		 */
		public function wp_incompitable_version() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Advance Flate Rate Shipping requires %s version %s or greater to be installed and active.', 'woocommerce-shipping-afr' ), '<a href="https://wordpress.org" target="_blank">WordPress</a>',WC_SHIPPING_AFR_MINIMUM_WP_VERSION ) . '</p></div>';
		}


		/**
		 * installation
		 *
		 * @access public
		 * @since 1.0.0
		 * @version 1.0.1
		 */
		public function install() {
			global $wp_version;

			if ( version_compare( $wp_version, WC_SHIPPING_AFR_MINIMUM_WP_VERSION, '<' )  ) :
				add_action( 'admin_notices', array( $this, 'wp_incompitable_version' ) );
			elseif ( version_compare( WC_VERSION, WC_SHIPPING_AFR_MINIMUM_WC_VERSION, '<' ) ):
				add_action( 'admin_notices', array( $this, 'wc_incompitable_version' ) );
			endif;

			return true;
		}

		/**
		 * Localisation
		 *
		 * @access public
		 * @since 1.0.0
		 *
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'woocommerce-shipping-afr', false, basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		* Plugin page links.
		 *
		 * @access public
		 * @since 1.0.0
		 * @param mixed $links plugin links
		 * @return mixed $links plugin links
		*/
		public function plugin_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=afr' ) . '">' . __( 'Settings', 'woocommerce-shipping-afr' ) . '</a>',
				'<a href="http://www.datumsquare.com/#contact">' . __( 'Support', 'woocommerce-shipping-afr' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}


		/**
		 * woocommerce_init_shipping_table_rate function.
		 *
		 * @access public
		 * @since 1.0.0
		 *
		 */
		public function includes() {
			include_once( WC_SHIPPING_AFR_PLUGIN_DIR . '/includes/class-wc-shipping-afr.php' );
		}

		/**
		 * Add AFR shipping method to WC
		 *
		 * @access public
		 * @since 1.0.0
		 * @param mixed $methods shipping methods
		 * @return mixed $methods shipping methods
		 */
		public function add_method( $methods ) {
			$methods['afr'] = 'WC_Shipping_AFR';
			

			return $methods;
		}

		/**
		 * Show the user a notice for plugin updates
		 *
		 * @access public
		 * @since 1.0.0
		 * @return string raw html/javascript
		 */
		public function upgrade_notice() {
			$show_notice = get_option( 'wc_shipping_afr_show_upgrade_notice' );

			if ( 'yes' !== $show_notice ):
				return;
			endif;

			$query_args = array( 'page' => 'wc-settings', 'tab' => 'shipping' );
			$zones_admin_url = add_query_arg( $query_args, get_admin_url() . 'admin.php' );
			?>
			<div class="notice notice-success is-dismissible wc-afr-notice">
				<p><?php echo sprintf( __( 'Advance Flat Rate supports shipping zones. See the zones %1$shere%2$s ', 'woocommerce-shipping-afr' ),'<a href="' . $zones_admin_url . '">','</a>' ); ?></p>
			</div>

			<script type="application/javascript">
				jQuery( '.notice.wc-afr-notice' ).on( 'click', '.notice-dismiss', function () {
					wp.ajax.post('afr_dismiss_upgrade_notice');
				});
			</script>
			<?php
		}

		/**
		 * Turn of the dismisable upgrade notice.
		 *
		 * @access public
		 * @since 1.0.0
		 *
		 */
		public function afr_dismiss_upgrade_notice() {
			update_option( 'wc_shipping_afr_show_upgrade_notice', 'no' );
		}
	}

	add_action( 'plugins_loaded' , array( 'WC_Shipping_AFR_Init', 'get_instance' ), 0 );
}