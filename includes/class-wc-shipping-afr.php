<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* WC_Shipping_AFR class.
*
* @inherits  WC_Shipping_Method
* @since 1.0.0
* @version 1.1.0
*/
class WC_Shipping_AFR extends WC_Shipping_Method {

	/**
	* Shipping class defined in wooc
	*
	* @access private
	* @since 1.0.0
	* @var array shipping classes, bool use weight based shipping
	*/
	private $shipping_class;
	private $weight_factor;
	/**
	* Constructor
	*
	* @access public
	* @since 1.0.0
	* @version 1.0.1
	* @param $instance_id of shipping method
	*/
	public function __construct( $instance_id = 0 ) {
		$this->id                               = 'afr';
		$this->instance_id                      = absint( $instance_id );
		$this->method_title                     = __( 'Advance Flat Rate', 'woocommerce-shipping-afr' );
		$this->method_description               = __( 'The AFR shipping extension allows you to manage shipping price per city and shipping classes.', 'woocommerce-shipping-afr' );
		$this->supports                         = array('shipping-zones','instance-settings','settings',);
		$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
        $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;

		$this->weight_factor		= ( ( $bool = $this->get_option( 'weight_factor' ) ) && $bool === 'yes' );

		$my_wc_shipping = new WC_Shipping();
		$this->shipping_class = $my_wc_shipping->get_shipping_classes();

		$this->init();

	}
	/**
	* get shipping classes.
	*
	* @access public
	* @since 1.0.0
	* @param
	*/
	public function get_def_shipping_classes() {
		return $this->shipping_class;
	}
	/**
	* init function.
	*
	* @access public
	* @since 1.0.0
	* @param
	*/
	private function init() {
		// Load the settings.
		$this->init_form_fields();
		$this->set_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	/**
	* init_form_fields function.
	*
	* @access public
	* @since 1.0.0
	* @version 1.1.0
	* @param
	*/
	public function init_form_fields() {
		$this->instance_form_fields = include( dirname( __FILE__ ) . '/data/data-settings.php' );
		
		$this->form_fields = array(
			'debug'      => array(
				'title'           => __( 'Debug Mode', 'woocommerce-shipping-afr' ),
				'label'           => __( 'Enable debug mode', 'woocommerce-shipping-afr' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				
				'description'     => __( 'Enable debug mode to show debugging information on the cart/checkout.', 'woocommerce-shipping-afr' )
			),
			'weight_factor' 	=> array(
				'title' 		=>__( 'Weight Base Shipping', 'woocommerce-shipping-afr'  ),
				'label'         => __( 'Enable weight base shipping', 'woocommerce-shipping-afr' ),
				'type'          => 'checkbox',
				'default'       => 'no',
				
				'description'   => __( 'Enable weight base shipping to manage flat rate shipping with respect to different weight .', 'woocommerce-shipping-afr' )
			),
			'weight_ranges' 	=>  array(
				'type'          => 'adv_weight_ranges'
			),
		);
	}

	/**
	* Initialize settings
	*
	* @access public
	* @since 1.0.0
	* @version 1.0.1
	* @param
	*/
	private function set_settings() {

		$this->debug 				= ( ( $bool = $this->get_option( 'debug' ) ) && $bool === 'yes' );
		$this->title 				= $this->get_option( 'title');
		$this->table_rates 			= $this->get_option( 'table_rates', array( ));
		$this->calculation_type		= $this->get_option( 'calculation_type', 'per_item');

		$this->weight_ranges 		= $this->get_option( 'weight_ranges', array( ));

		if(!isset($this->table_rates['tr_city_name'][0]))
			$this->table_rates['tr_city_name'][0]='Default';

		if(!isset($this->table_rates['tr_no_class'][0]))
			$this->table_rates['tr_no_class'][0]='';

		if(!isset($this->table_rates['tr_enabled'][0]))
			$this->table_rates['tr_enabled'][0]='on';

		if($this->weight_factor):
			foreach($this->weight_ranges['weight_class'] as $sclass):
				if(!isset($this->table_rates['tr_class_'.$this->clean($sclass)][0]))
					$this->table_rates['tr_class_'.$this->clean($sclass)][0]='';
			endforeach;
		else:
			foreach($this->get_def_shipping_classes() as $sclass):
				if(!isset($this->table_rates['tr_class_'.$sclass->slug][0]))
					$this->table_rates['tr_class_'.$sclass->slug][0]='';
			endforeach;
		endif;

		if(!isset($this->weight_ranges['weight_class'])):
			$this->weight_ranges['weight_class'] = array('Small','Medium','Large');
			$this->weight_ranges['min_weight']  = array(10,20,50);
			$this->weight_ranges['max_weight']  = array(20,50,100);
		endif;
	}

	

	/**
	* Process settings on save
	*
	* @access public
	* @since 1.0.0
	* @param
	*/
	public function process_admin_options() {
		parent::process_admin_options();

		$this->set_settings();
	}

	/**
	* Load admin scripts
	*
	* @access public
	* @since 1.0.0
	* @param
	*/
	public function load_admin_scripts() {
		// May need this in future
	}

	/**
	* Output a message or error
	*
	* @access public
	* @since 1.0.0
	* @param $message text and $type of message like notice, warning, error
	*/
	public function debug_messages( $message, $type = 'notice' ) {

		if ( $this->debug || ( current_user_can( 'manage_options' ) && 'error' == $type ) ):
			wc_add_notice( $message, $type );
		endif;

	}
	
	/**
	* generate_adv_weight_ranges_html function.
	*
	* @access public
	* @since 1.1.0
	*/
	public function generate_adv_weight_ranges_html() {
		$current_weight_unit = get_option('woocommerce_weight_unit');
		ob_start();
		include( 'views/html-weight-ranges.php' );
		return ob_get_clean();
	}

	
	/**
	* generate_adv_table_rates_html function.
	*
	* @access public
	* @since 1.0.0
	*/
	public function generate_adv_table_rates_html() {
		ob_start();
		include( 'views/html-table-rates.php' );
		return ob_get_clean();
	}
	/**
	* validate_weight_ranges_field function.
	*
	* @access public
	* @since 1.1.0
	* @param mixed $key
	*/
	public function validate_adv_weight_ranges_field( $key ) {

		$new_weight_ranges = array();

		$new_weight_classes = isset( $_POST['weight_class'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['weight_class'] ) ) : array();

		$new_min_weights = isset( $_POST['min_weight'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['min_weight'] ) ) : array();
		$new_max_weights = isset( $_POST['max_weight'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['max_weight'] ) ) : array();

		foreach ($new_weight_classes as $key => $value):
			//Weight Class validation
			$safe_class =  $value;
			if ( strlen( $safe_class ) > 32) 
			  $safe_class = substr( $safe_class, 0, 32 );

			$new_weight_ranges['weight_class'][] = $safe_class;

			//Min Weight validation
			$safe_min = floatval( $new_min_weights[$key] );
			if ( ! $safe_min  && $safe_min!='0')
			  $safe_min = '';

			if ( strlen( $safe_min ) > 11 ) 
			  $safe_min = substr( $safe_min, 0, 11 );

			$new_weight_ranges['min_weight'][] = $safe_min;

			//Max Weight validation
			$safe_max = floatval( $new_max_weights[$key] );
			if ( ! $safe_max  && $safe_max!='0' )
			  $safe_max = '';

			if ( strlen( $safe_max ) > 11 ) 
			  $safe_max = substr( $safe_max, 0, 11 );

			$new_weight_ranges['max_weight'][] = $safe_max;

		endforeach;

		return $new_weight_ranges;
	}

	
	/**
	* validate_table_rates_field function.
	*
	* @access public
	* @since 1.0.0
	* @version 1.1.0
	* @param mixed $key
	*/
	public function validate_adv_table_rates_field( $key ) {

		$tr_city_names     = isset( $_POST['tr_city_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tr_city_name'] ) ) : array();
		$tr_no_classes      = isset( $_POST['tr_no_class'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tr_no_class'] ) )  : array();

		
		if($this->weight_factor):
			foreach($this->weight_ranges['weight_class'] as $defclasses):
				${'tr_classes_'.$this->clean($defclasses)} = isset( $_POST['tr_class_'.$this->clean($defclasses)] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tr_class_'.$this->clean($defclasses)] ) ) : array();
			endforeach;
		else:
			foreach($this->get_def_shipping_classes() as $defclasses):
				${'tr_classes_'.$defclasses->slug} = isset( $_POST['tr_class_'.$defclasses->slug] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tr_class_'.$defclasses->slug] ) )  : array();
			endforeach;
		endif;

		$tr_enableds  = isset( $_POST['tr_enabled'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['tr_enabled'] ) )  : array();

		$new_city_rates = array();

		foreach($tr_city_names as $key => $value):
			//City Name validation
			$safe_city =  $value;

			if ( strlen( $safe_city ) > 50) 
			  $safe_city = substr( $safe_city, 0, 50 );

			$new_city_rates['tr_city_name'][] = $safe_city;

			//Default Shipping validation
			$safe_default = floatval( $tr_no_classes[$key] );
			if ( ! $safe_default && $safe_default!='0')
			  $safe_default = '';

			if ( strlen( $safe_default ) > 11 ) 
			  $safe_default = substr( $safe_default, 0, 11 );

			$new_city_rates['tr_no_class'][] = $safe_default;

			if($this->weight_factor):
				foreach($this->weight_ranges['weight_class'] as $defclasses):
					//Shipping against weight class  validation
					$safe_sawc = floatval( ${'tr_classes_'.$this->clean($defclasses)}[$key] );
					if ( ! $safe_sawc  && $safe_sawc!='0')
					  $safe_sawc = '';

					if ( strlen( $safe_sawc ) > 11 ) 
					  $safe_sawc = substr( $safe_sawc, 0, 11 );

					$new_city_rates['tr_class_'.$this->clean($defclasses)][] = $safe_sawc;
				endforeach;
			else:
				foreach($this->get_def_shipping_classes() as $defclasses):
					//Shipping against shipping class  validation
					$safe_sawc = floatval( ${'tr_classes_'.$defclasses->slug}[$key] );
					if ( ! $safe_sawc  && $safe_sawc!='0')
					  $safe_sawc = '';

					if ( strlen( $safe_sawc ) > 11 ) 
					  $safe_sawc = substr( $safe_sawc, 0, 11 );

					$new_city_rates['tr_class_'.$defclasses->slug][] = $safe_sawc;					
				endforeach;
			endif;


			//Enabled validation
			$safe_enabled =  $tr_enableds[$key];

			if ( strlen( $safe_enabled ) > 3) 
			  $safe_enabled = substr( $safe_enabled, 0, 3 );

			$new_city_rates['tr_enabled'][] = $safe_enabled;

		endforeach;



		return $new_city_rates;
	}
	/**
	* is available function.
	*
	* @access public
	* @since 1.0.0
	* @param
	*/
	public function is_available( $package ) {
		//if ( empty( $package['destination']['country']) || empty($package['destination']['city'] ) ) 
		//	return false;

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );
	}


	/**
	* Calculate Shipping
	*
	* @access public
	* @since 1.0.0
	* @version 1.1.0
	* @param $package product deatils, shipping details
	* @return shipping price
	*/
	public function calculate_shipping( $package = array() ) {
		
		// Find city index 
		$cityIndex=0;
		foreach($this->table_rates['tr_city_name'] as $citykey => $cityname):
			if( strtolower($cityname) == strtolower($package['destination']['city']) ):
				if(isset($this->table_rates['tr_enabled'][$citykey]) && $this->table_rates['tr_enabled'][$citykey]=='on')
					$cityIndex=$citykey;
				break;
			endif;
		endforeach;

		if($this->weight_factor)
			$final_calculated_price = $this->weight_based($package,$cityIndex);
		else
			$final_calculated_price = $this->shipping_classes_based($package,$cityIndex);

		$this->debug_messages( __( 'AFR debug mode is on - to hide these messages, turn debug mode off in the settings.', 'woocommerce-shipping-afr' ) );

		if($final_calculated_price>0):
			$mrate = array(
		        'id' => $this->id,
		        'label' => $this->title,
		        'cost' => $final_calculated_price
		    );
		     
		else:
			$mrate = array(
		        'id' => $this->id,
		        'label' => __( 'Free Shipping', 'woocommerce-shipping-afr' ),
		        'cost' => 0.00
		    );
		endif;
		
		$this->add_rate( $mrate );
	}
	/**
	* get weight class
	*
	* @access private
	* @since 1.1.0
	* @version 1.1.0
	* @param weight
	* @return user defined weight class index
	*/
	private function get_weight_class($weight)
	{
		$weight_class_index=-1;
		if(!empty($weight) && $weight>0):
			foreach($this->weight_ranges['weight_class'] as $key => $value):
				if($this->weight_ranges['min_weight'][$key] <= $weight && $weight < $this->weight_ranges['max_weight'][$key]):
					$weight_class_index=$key;
					break;
				endif;
			endforeach;
		endif;

		return $weight_class_index;
	}

	/**
	* Calculate Shipping wrt Weights and weight classes
	*
	* @access private
	* @since 1.1.0
	* @version 1.1.0
	* @param Weight class index
	* @return available price for given weight 
	*/
	private function get_price_for_weight($weight_class_index,$cityIndex)
	{
		$available_price = 0;
		if($weight_class_index>=0):
			$weight_class = 'tr_class_'.$this->clean($this->weight_ranges['weight_class'][$weight_class_index]);
			if(isset($this->table_rates[$weight_class][$cityIndex]) && $this->table_rates[$weight_class][$cityIndex]>-1)
				$available_price =  $this->table_rates[$weight_class][$cityIndex];
			elseif(isset($this->table_rates['tr_no_class'][$cityIndex]) && $this->table_rates['tr_no_class'][$cityIndex]>-1)
				$available_price =  $this->table_rates['tr_no_class'][$cityIndex];
			elseif(isset($this->table_rates[$weight_class][0]) && $this->table_rates[$weight_class][0]>-1)
				$available_price =  $this->table_rates[$weight_class][0];
			elseif(isset($this->table_rates['tr_no_class'][0]) && $this->table_rates['tr_no_class'][0]>-1)
				$available_price =  $this->table_rates['tr_no_class'][0];;
		else:
			$weight_class = 'tr_no_class';
			if(isset($this->table_rates[$weight_class][$cityIndex]) && $this->table_rates[$weight_class][$cityIndex]>-1)
				$available_price =  $this->table_rates[$weight_class][$cityIndex];
			elseif(isset($this->table_rates[$weight_class][0]) && $this->table_rates[$weight_class][0]>-1)
				$available_price =  $this->table_rates[$weight_class][0];
		endif;

		return $available_price;
	}

	/**
	* Weight Based Shipping wrt Weights and weight classes
	*
	* @access private
	* @since 1.1.0
	* @version 1.1.0
	* @param package, city
	* @return integer final shipping cost 
	*/
	private function weight_based($package,$cityIndex) {
		
		$total_weight=0;
		$total_price=0;


		$all_weight_class_found = array();

		foreach ( $package['contents'] as $item_id => $values ):
			$cart_item_sub_weight=0;

			$cart_item_weight = $values['data']->get_weight();
			if($cart_item_weight>0)
				$cart_item_sub_weight = ($cart_item_weight * $values['quantity']);

			$weight_class_index = $this->get_weight_class($cart_item_sub_weight);

			if($this->calculation_type == 'per_order')
				$total_weight += $cart_item_sub_weight;
			else
				$total_price += floatval($this->get_price_for_weight($weight_class_index,$cityIndex));		
			
			$all_weight_class_found[] = $cart_item_sub_weight;

		endforeach;

		if($this->calculation_type == 'per_order'):
			$total_price = 0;

			$weight_class_index = $this->get_weight_class($total_weight);

			$total_price = floatval($this->get_price_for_weight($weight_class_index,$cityIndex));
		endif;

		$final_calculated_price=$total_price;

		$this->debug_messages( __( 'Weights: '.implode(', ', $all_weight_class_found).'<br>Package Details: '.json_encode($package), 'woocommerce-shipping-afr' ) );

		return $final_calculated_price;
	}


	/**
	* Calculate Shipping wrt Shipping Classes
	*
	* @access private
	* @since 1.1.0
	* @version 1.1.0
	* @param package, city
	* @return integer final shipping cost 
	*/
	private function shipping_classes_based($package,$cityIndex) {
		$allClassPrice=0;
		$minClassPrice=0;
		$maxClassPrice=0;

		$sclasses_index = 0;

		$all_shipping_class_found = array();

		foreach ( $package['contents'] as $item_id => $values ) :
			$cart_item_shipping_class = $values['data']->get_shipping_class();
			$all_shipping_class_found[] = $cart_item_shipping_class;

			$priceForClass = 0;
			if(!empty($cart_item_shipping_class)):
				if( isset($this->table_rates['tr_class_'.$cart_item_shipping_class][$cityIndex]) && $this->table_rates['tr_class_'.$cart_item_shipping_class][$cityIndex]>-1)
					$priceForClass = $this->table_rates['tr_class_'.$cart_item_shipping_class][$cityIndex];
				else if(isset($this->table_rates['tr_no_class'][$cityIndex]) && $this->table_rates['tr_no_class'][$cityIndex]>-1)
					$priceForClass = $this->table_rates['tr_no_class'][$cityIndex];
				else if(isset($this->table_rates['tr_class_'.$cart_item_shipping_class][0]) && $this->table_rates['tr_class_'.$cart_item_shipping_class][0]>-1)
					$priceForClass = $this->table_rates['tr_class_'.$cart_item_shipping_class][0];	
				else if(isset($this->table_rates['tr_no_class'][0]) && $this->table_rates['tr_no_class'][0]>-1)
					$priceForClass = $this->table_rates['tr_no_class'][0];;
			else:
				if(isset($this->table_rates['tr_no_class'][$cityIndex]) && $this->table_rates['tr_no_class'][$cityIndex]>-1)
					$priceForClass = $this->table_rates['tr_no_class'][$cityIndex];
				else if(isset($this->table_rates['tr_no_class'][0]) && $this->table_rates['tr_no_class'][0]>-1)
					$priceForClass = $this->table_rates['tr_no_class'][0];
			endif;



			$priceForClass = str_replace(" ", "", $priceForClass);
			$priceForClassArr = explode("*", $priceForClass);

			if(strtolower(@$priceForClassArr[1]) == '[qty]' && is_numeric($priceForClassArr[0]))
				$priceForClass = $priceForClassArr[0] * $values['quantity'];
			else if(is_numeric($priceForClassArr[0]))
				$priceForClass = $priceForClassArr[0];
			else
				$priceForClass=0;

			$allClassPrice += $priceForClass;

			if($sclasses_index==0):
				$minPriceClass=$priceForClass;
				$maxPriceClass=$priceForClass;
			else:
				if($priceForClass<$minPriceClass)
					$minPriceClass = $priceForClass;

				if($priceForClass>$maxPriceClass)
					$maxPriceClass = $priceForClass;
			endif;

			$sclasses_index++;
		endforeach;


		$final_calculated_price=0;

		if($this->calculation_type == 'per_order_max')
			$final_calculated_price=$maxPriceClass;
		else if($this->calculation_type == 'per_order_min')
			$final_calculated_price=$minPriceClass;			
		else 
			$final_calculated_price=$allClassPrice;
		
		$this->debug_messages( __( 'Shipping Class: '.implode(', ', $all_shipping_class_found).'<br>'.json_encode($package), 'woocommerce-shipping-afr' ) );
		
		return $final_calculated_price;
	}
	
	/**
	* clean strings
	*
	* @access private
	* @since 1.1.0
	* @version 1.1.0
	* @param $string
	* @return clean string: without special characters 
	*/
	private function clean($string) {
		$string = esc_html($string); 
		$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	   	$string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

	   	return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
	}
}
