<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

class qsot_core_hacks {
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options = call_user_func_array(array($options_class_name, "instance"), array());
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());
			add_action('qsot-draw-page-template-list', array(__CLASS__, 'page_draw_page_template_list'), 10, 1);
			add_filter('qsot-page-templates-list', array(__CLASS__, 'page_templates_list'), 10, 2);
			add_filter('qsot-get-page-template-list', array(__CLASS__, 'page_get_page_template_list'), 10, 1);
			add_filter('page_template', array(__CLASS__, 'page_template_default'), 10, 1);
			add_filter('qsot-maybe-override-theme_default', array(__CLASS__, 'maybe_override_template'), 10, 3);
			add_action('save_post', array(__CLASS__, 'save_page'), 10, 2);
			add_action('load-post.php', array(__CLASS__, 'hack_template_save'), 1);
			add_action('load-post-new.php', array(__CLASS__, 'hack_template_save'), 1);
			add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'), 100);

			add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'update_service_fee_subtotal_on_order_creation'), 10, 2);
			add_filter('woocommerce_get_order_note_type', array(__CLASS__, 'get_order_note_type'), 10, 2);

			add_action('save_post', array(__CLASS__, 'update_order_user_addresses'), 1000000, 2);
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'add_sync_billing_field' ), 10, 1 );

			add_action('pre_user_query', array(__CLASS__, 'or_display_name_user_query'), 101, 1);

			add_filter('product_type_options', array(__CLASS__, 'add_no_processing_option'), 999);
			add_action('woocommerce_order_item_needs_processing', array(__CLASS__, 'do_not_process_product'), 10, 3);
			add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_meta'), 999, 2);

			add_action('woocommerce_order_actions', array(__CLASS__, 'add_view_customer_facing_emails'), 10, 1);
			add_action('woocommerce_order_action_view-completed-email', array(__CLASS__, 'view_completed_email'), 10, 1);
		}
	}

	public static function plugins_loaded() {
		remove_action('wp_ajax_woocommerce_json_search_customers', 'woocommerce_json_search_customers');
		add_action('wp_ajax_woocommerce_json_search_customers', array(__CLASS__, 'woocommerce_json_search_customers'));

		remove_action('wp_ajax_woocommerce_add_order_note', 'woocommerce_add_order_note');
		add_action('wp_ajax_woocommerce_add_order_note', array(__CLASS__, 'woocommerce_add_order_note'));

		remove_action('wp_ajax_woocommerce_add_order_fee', 'woocommerce_ajax_add_order_fee');
		add_action('wp_ajax_woocommerce_add_order_fee', array(__CLASS__, 'woocommerce_ajax_add_order_fee'));

		remove_action('wp_ajax_woocommerce_add_order_item', 'woocommerce_ajax_add_order_item');
		add_action('wp_ajax_woocommerce_add_order_item', array(__CLASS__, 'woocommerce_ajax_add_order_item'));
	}

	// effort to work around the new wp core page template existence validation, which prohibits page templates not in the theme
	public static function hack_template_save() {
		wp_reset_vars( array( 'action' ) );
		if ( isset( $_GET['post'] ) )
			$post_id = $post_ID = (int) $_GET['post'];
		elseif ( isset( $_POST['post_ID'] ) )
			$post_id = $post_ID = (int) $_POST['post_ID'];
		else
			$post_id = $post_ID = 0;

		$post = $post_type = $post_type_object = null;

		if ( $post_id )
			$post = get_post( $post_id );

		$post_type = $post ? $post->post_type : ( isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : false );
		if (!$post_type) return;
		if ($post_type !== 'page') return;
		if (!isset($_REQUEST['page_template'])) return;

		update_post_meta($post->ID, '_wp_page_template', $_REQUEST['page_template']);
		unset($_REQUEST['page_template']);
	}

	public static function add_view_customer_facing_emails($list) {
		$list['view-completed-email'] = __('View Order Receipt','opentickets-community-edition');
		return $list;
	}

	public static function view_completed_email($order) {
		$email_exchanger = new WC_Emails();

		$email = new WC_Email_Customer_Completed_Order();
		$email->object = $order;

		?><html>
			<head>
				<title><?php echo $email->get_subject().' - '; _e('Preview','opentickets-community-edition'); echo ' - '.get_bloginfo('name') ?></title>
			</head>
			<body>
				<?php echo $email->get_content(); ?>
			</body>
		</html><?php
		exit;
	}

	public static function add_no_processing_option($list) {
		$list['no_processing'] = array(
			'id' => '_no_processing',
			'wrapper_class' => 'show_if_simple show_if_grouped show_if_external show_if_variable no-wrap',
			'label' => __('Bypass Process','opentickets-community-edition'),
			'description' => __('Checking this box bypasses the Processing step and marks the order as Complete. (if other products in an order require processing, the order will still goto processing)','opentickets-community-edition'),
		);
	
		return $list;
	}

	public static function do_not_process_product($is, $product, $order_id) {
		if (get_post_meta($product->id, '_no_processing', true) == 'yes') $is = false;
		return $is;
	}

	public static function save_product_meta($post_id, $post) {
		$is_ticket = isset($_POST['_no_processing']) ? 'yes' : 'no';
		update_post_meta($post_id, '_no_processing', $is_ticket);
	}

	// add subtotal to fees also, for proper accounting
	public static function update_service_fee_subtotal_on_order_creation($order_id, $posted) {
		$order = new WC_Order($order_id);
		foreach ($order->get_fees() as $oiid => $fee) {
			if (woocommerce_get_order_item_meta($oiid, '_line_subtotal', true) === '')
				woocommerce_update_order_item_meta($oiid, '_line_subtotal', woocommerce_get_order_item_meta($oiid, '_line_total', true));
		}
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow class assignment and template overriding
	function woocommerce_ajax_add_order_fee() {
		check_ajax_referer( 'order-item', 'security' );

		$order_id 	= absint( $_POST['order_id'] );
		$order 		= new WC_Order( $order_id );

		// Add line item
		$item_id = woocommerce_add_order_item( $order_id, array(
			'order_item_name' 		=> '',
			'order_item_type' 		=> 'fee'
		) );

		// Add line item meta
		if ( $item_id ) {
			woocommerce_add_order_item_meta( $item_id, '_tax_class', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_total', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_tax', '' );
		}

		$items = $order->get_items();
		$item = $items[$item_id];

		// allow class specification
		$class = apply_filters('woocommerce_admin_order_items_class', '', $item, $order);

		//include( trailingslashit($woocommerce->plugin_path).'admin/post-types/writepanels/order-fee-html.php' );
		if ( $template = QSOT_Templates::locate_woo_template( 'post-types/meta-boxes/views/html-order-fee.php', 'admin' ) )
			include( $template );

		// Quit out
		die();
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow template overriding
	function woocommerce_ajax_add_order_item() {
		check_ajax_referer( 'order-item', 'security' );

		$item_to_add = sanitize_text_field( $_POST['item_to_add'] );
		$order_id = absint( $_POST['order_id'] );

		// Find the item
		if ( ! is_numeric( $item_to_add ) )
			die();

		$post = get_post( $item_to_add );

		if ( ! $post || ( $post->post_type !== 'product' && $post->post_type !== 'product_variation' ) )
			die();

		$_product = get_product( $post->ID );

		$order = new WC_Order( $order_id );
		$class = 'new_row';

		// Set values
		$item = array();

		$item['product_id'] 			= $_product->id;
		$item['variation_id'] 			= isset( $_product->variation_id ) ? $_product->variation_id : '';
		$item['name'] 					= $_product->get_title();
		$item['tax_class']				= $_product->get_tax_class();
		$item['qty'] 					= 1;
		$item['line_subtotal'] 			= number_format( (double) $_product->get_price_excluding_tax(), 2, '.', '' );
		$item['line_subtotal_tax'] 		= '';
		$item['line_total'] 			= number_format( (double) $_product->get_price_excluding_tax(), 2, '.', '' );
		$item['line_tax'] 				= '';

		$item = apply_filters('woocommerce_ajax_before_add_order_item', $item, $_product, $order);
		// Add line item
		$item_id = woocommerce_add_order_item( $order_id, array(
			'order_item_name' 		=> $item['name'],
			'order_item_type' 		=> 'line_item'
		) );

		$class = apply_filters('woocommerce_admin_order_items_class', $class, $item, $order);

		// Add line item meta
		if ( $item_id ) {
			woocommerce_add_order_item_meta( $item_id, '_qty', $item['qty'] );
			woocommerce_add_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
			woocommerce_add_order_item_meta( $item_id, '_product_id', $item['product_id'] );
			woocommerce_add_order_item_meta( $item_id, '_variation_id', $item['variation_id'] );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal', $item['line_subtotal'] );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal_tax', $item['line_subtotal_tax'] );
			woocommerce_add_order_item_meta( $item_id, '_line_total', $item['line_total'] );
			woocommerce_add_order_item_meta( $item_id, '_line_tax', $item['line_tax'] );
		}

		do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item );

		//include( 'admin/post-types/writepanels/order-item-html.php' );
		if ( $template = QSOT_Templates::locate_woo_template( 'post-types/meta-boxes/views/html-order-item.php', 'admin' ) )
			include $template;

		// Quit out
		die();
	}
	// copied from woocommerce/admin/post-types/writepanels/writepanel-order_data.php
	/**
	 * Displays the order totals meta box.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	public function woocommerce_order_totals_meta_box( $post ) {
		global $theorder, $wpdb;
		$woocommerce = WC();

		if ( ! is_object( $theorder ) )
			$theorder = new WC_Order( $post->ID );

		$order = $theorder;

		$data = get_post_meta( $post->ID );
		?>
		<div class="totals_group">
			<h4><span class="discount_total_display inline_total"></span><?php _e( 'Discounts','opentickets-community-edition' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Cart Discount:','opentickets-community-edition' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Discounts before tax - calculated by comparing subtotals to totals.','opentickets-community-edition' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_cart_discount" name="_cart_discount" placeholder="0.00" value="<?php
						if ( isset( $data['_cart_discount'][0] ) )
							echo esc_attr( $data['_cart_discount'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Order Discount:','opentickets-community-edition' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Discounts after tax - user defined.','opentickets-community-edition' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_order_discount" name="_order_discount" placeholder="0.00" value="<?php
						if ( isset( $data['_order_discount'][0] ) )
							echo esc_attr( $data['_order_discount'][0] );
					?>" />
				</li>

			</ul>

			<ul class="wc_coupon_list">

			<?php
				$coupons = $order->get_items( array( 'coupon' ) );

				foreach ( $coupons as $item_id => $item ) {

					$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $item['name'] ) );

					$link = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : admin_url( 'edit.php?s=' . esc_url( $item['name'] ) . '&post_status=all&post_type=shop_coupon' );

					echo '<li class="tips code" data-tip="' . esc_attr( woocommerce_price( $item['discount_amount'] ) ) . '"><a href="' . $link . '"><span>' . esc_html( $item['name'] ). '</span></a></li>';

				}
			?>

			</ul>
			<div class="clear"></div>

			<?php do_action('woocommerce_admin_order_totals_after_coupon_list', $order) ?>

		</div>

		<?php /* adding if statement around shipping to hide it if it is irrelevant, like many other woocommerce features */ ?>
		<?php if (get_option('woocommerce-order-totals') == 'yes'): ?>
			<div class="totals_group">
				<h4><?php _e( 'Shipping','opentickets-community-edition' ); ?></h4>
				<ul class="totals">

					<li class="wide">
						<label><?php _e( 'Label:','opentickets-community-edition' ); ?></label>
						<input type="text" id="_shipping_method_title" name="_shipping_method_title" placeholder="<?php _e( 'The shipping title the customer sees','opentickets-community-edition'); ?>" value="<?php
							if ( isset( $data['_shipping_method_title'][0] ) )
								echo esc_attr( $data['_shipping_method_title'][0] );
						?>" class="first" />
					</li>

					<li class="left">
						<label><?php _e( 'Cost:','opentickets-community-edition' ); ?></label>
						<input type="number" step="any" min="0" id="_order_shipping" name="_order_shipping" placeholder="0.00 <?php _e( '(ex. tax)','opentickets-community-edition' ); ?>" value="<?php
							if ( isset( $data['_order_shipping'][0] ) )
								echo esc_attr( $data['_order_shipping'][0] );
						?>" class="first" />
					</li>

					<li class="right">
						<label><?php _e( 'Method:','opentickets-community-edition' ); ?></label>
						<select name="_shipping_method" id="_shipping_method" class="first">
							<option value=""><?php _e( 'N/A','opentickets-community-edition' ); ?></option>
							<?php
								$chosen_method 	= ! empty( $data['_shipping_method'][0] ) ? $data['_shipping_method'][0] : '';
								$found_method 	= false;

								if ( $woocommerce->shipping() ) {
									foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {

										if ( strpos( $chosen_method, $method->id ) === 0 )
											$value = $chosen_method;
										else
											$value = $method->id;

										echo '<option value="' . esc_attr( $value ) . '" ' . selected( $chosen_method == $value, true, false ) . '>' . esc_html( $method->get_title() ) . '</option>';
										if ( $chosen_method == $value )
											$found_method = true;
									}
								}

								if ( ! $found_method && ! empty( $chosen_method ) ) {
									echo '<option value="' . esc_attr( $chosen_method ) . '" selected="selected">' . __( 'Other','opentickets-community-edition' ) . '</option>';
								} else {
									echo '<option value="other">' . __( 'Other','opentickets-community-edition' ) . '</option>';
								}
							?>
						</select>
					</li>

				</ul>
				<?php do_action( 'woocommerce_admin_order_totals_after_shipping', $post->ID ) ?>
				<div class="clear"></div>
			</div>
		<?php endif; ?>

		<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>

		<div class="totals_group tax_rows_group">
			<h4><?php _e( 'Tax Rows','opentickets-community-edition' ); ?></h4>
			<div id="tax_rows" class="total_rows">
				<?php
					global $wpdb;

					$rates = $wpdb->get_results( "SELECT tax_rate_id, tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_name" );

					$tax_codes = array();

					foreach( $rates as $rate ) {
						$code = array();

						$code[] = $rate->tax_rate_country;
						$code[] = $rate->tax_rate_state;
						$code[] = $rate->tax_rate_name ? sanitize_title( $rate->tax_rate_name ) : 'TAX';
						$code[] = absint( $rate->tax_rate_priority );

						$tax_codes[ $rate->tax_rate_id ] = strtoupper( implode( '-', array_filter( $code ) ) );
					}

					foreach ( $order->get_taxes() as $item_id => $item )
						if ( $template = QSOT_Templates::locate_woo_template( 'post-types/meta-boxes/views/html-order-tax.php', 'admin' ) )
							include( $template );
				?>
			</div>
			<h4><a href="#" class="add_tax_row"><?php _e( '+ Add tax row','opentickets-community-edition' ); ?> <span class="tips" data-tip="<?php _e( 'These rows contain taxes for this order. This allows you to display multiple or compound taxes rather than a single total.','opentickets-community-edition' ); ?>">[?]</span></a></a></h4>
			<div class="clear"></div>
		</div>
		<div class="totals_group">
			<h4><span class="tax_total_display inline_total"></span><?php _e( 'Tax Totals','opentickets-community-edition' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Sales Tax:','opentickets-community-edition' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Total tax for line items + fees.','opentickets-community-edition' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_order_tax" name="_order_tax" placeholder="0.00" value="<?php
						if ( isset( $data['_order_tax'][0] ) )
							echo esc_attr( $data['_order_tax'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Shipping Tax:','opentickets-community-edition' ); ?></label>
					<input type="number" step="any" min="0" id="_order_shipping_tax" name="_order_shipping_tax" placeholder="0.00" value="<?php
						if ( isset( $data['_order_shipping_tax'][0] ) )
							echo esc_attr( $data['_order_shipping_tax'][0] );
					?>" />
				</li>

			</ul>
			<div class="clear"></div>
		</div>

		<?php endif; ?>

		<div class="totals_group">
			<h4><?php _e( 'Order Totals','opentickets-community-edition' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Order Total:','opentickets-community-edition' ); ?></label>
					<input type="number" step="any" min="0" id="_order_total" name="_order_total" placeholder="0.00" value="<?php
						if ( isset( $data['_order_total'][0] ) )
							echo esc_attr( $data['_order_total'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Payment Method:','opentickets-community-edition' ); ?></label>
					<select name="_payment_method" id="_payment_method" class="first">
						<option value=""><?php _e( 'N/A','opentickets-community-edition' ); ?></option>
						<?php
							$chosen_method 	= ! empty( $data['_payment_method'][0] ) ? $data['_payment_method'][0] : '';
							$found_method 	= false;

							if ( $woocommerce->payment_gateways() ) {
								foreach ( $woocommerce->payment_gateways->payment_gateways() as $gateway ) {
									if ( $gateway->enabled == "yes" ) {
										echo '<option value="' . esc_attr( $gateway->id ) . '" ' . selected( $chosen_method, $gateway->id, false ) . '>' . esc_html( $gateway->get_title() ) . '</option>';
										if ( $chosen_method == $gateway->id )
											$found_method = true;
									}
								}
							}

							if ( ! $found_method && ! empty( $chosen_method ) ) {
								echo '<option value="' . esc_attr( $chosen_method ) . '" selected="selected">' . __( 'Other','opentickets-community-edition' ) . '</option>';
							} else {
								echo '<option value="other">' . __( 'Other','opentickets-community-edition' ) . '</option>';
							}
						?>
					</select>
				</li>

			</ul>
			<div class="clear"></div>

			<?php do_action('woocommerce_admin_after_order_totals', $order) ?>

		</div>
		<p class="buttons">
			<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>
				<button type="button" class="button calc_line_taxes"><?php _e( 'Calc taxes','opentickets-community-edition' ); ?></button>
			<?php endif; ?>
			<button type="button" class="button calc_totals button-primary"><?php _e( 'Calc totals','opentickets-community-edition' ); ?></button>
		</p>
		<?php
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow other comment types to have an action to save info on
	function woocommerce_add_order_note() {
		check_ajax_referer( 'add-order-note', 'security' );

		$post_id 	= (int) $_POST['post_id'];
		$note		= wp_kses_post( trim( stripslashes( $_POST['note'] ) ) );
		$note_type	= $_POST['note_type'];

		$is_customer_note = $note_type == 'customer' ? 1 : 0;

		if ( $post_id > 0 ) {
			$order = new WC_Order( $post_id );
			$comment_id = $order->add_order_note( $note, $is_customer_note );
			do_action('woocommerce_ajax_save_order_note', $comment_id, $note_type, $note, $order);

			echo '<li rel="'.$comment_id.'" class="note ';
			if ($is_customer_note) echo 'customer-note';
			echo '"><div class="note_content">';
			echo wpautop( wptexturize( $note ) );
			echo '</div><p class="meta">';
			echo '('.apply_filters('woocommerce_get_order_note_type', 'private', get_comment($comment_id)).')';
			echo '<a href="#" class="delete_note">'.__( 'Delete note','opentickets-community-edition' ).'</a>';
			echo '</p>';
			echo '</li>';

		}

		// Quit out
		die();
	}

	public static function get_order_note_type($type, $note) {
		if (get_comment_meta($note->comment_ID, 'is_customer_note', true) == 1) $type = 'customer note';
		return $type;
	}

	/**
	 * Search for customers and return json
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_json_search_customers() {

		check_ajax_referer( 'search-customers', 'security' );

//		header( 'Content-Type: application/json; charset=utf-8' );

		$term = urldecode( stripslashes( strip_tags( $_GET['term'] ) ) );

		if ( empty( $term ) )
			die();

		$default = isset( $_GET['default'] ) ? $_GET['default'] : __( 'Guest', 'opentickets-community-edition' );

		$found_customers = array( '' => $default );

		$customers_query = new WP_User_Query( array(
			'fields'			=> 'all',
			'orderby'			=> 'display_name',
			'search'			=> '*' . $term . '*',
			'search_columns'	=> array( 'ID', 'user_login', 'user_email', 'user_nicename' ),
			/*
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'billing_first_name',
					'value' => $term,
					'compare' => 'LIKE',
				),
				array(
					'key' => 'billing_last_name',
					'value' => $term,
					'compare' => 'LIKE',
				),
				array(
					'key' => 'billing_email',
					'value' => $term,
					'compare' => 'LIKE',
				),
			),
			*/
		) );

		// SELECT DISTINCT SQL_CALC_FOUND_ROWS wp_users.*
		// FROM wp_users 
		// INNER JOIN wp_usermeta
		//		ON (wp_users.ID = wp_usermeta.user_id)
		// INNER JOIN wp_usermeta AS mt1
		//		ON (wp_users.ID = mt1.user_id)
		// INNER JOIN wp_usermeta AS mt2
		//		ON (wp_users.ID = mt2.user_id)
		// WHERE 1=1
		//		AND (ID = 'john' OR user_login LIKE '%john%' OR user_email LIKE '%john%' OR user_nicename LIKE '%john%')
		//		AND (
		//			(wp_usermeta.meta_key = 'billing_first_name' AND CAST(wp_usermeta.meta_value AS CHAR) LIKE '%john%') OR
		//			(mt1.meta_key = 'billing_last_name' AND CAST(mt1.meta_value AS CHAR) LIKE '%john%') OR 
		//			(mt2.meta_key = 'billing_email' AND CAST(mt2.meta_value AS CHAR) LIKE '%john%')
		//		)
		// ORDER BY display_name ASC;

		$customers = $customers_query->get_results();

		if ( $customers ) {
			foreach ( $customers as $customer ) {
				$found_customers[ $customer->ID ] = $customer->display_name . ' (#' . $customer->ID . ' &ndash; ' . sanitize_email( $customer->user_email ) . ')';
			}
		}

		echo json_encode( $found_customers );
		die();
	}

	public static function or_display_name_user_query(&$query) {
		if (!isset($_GET['term'], $_REQUEST['s'])) return;
		global $wpdb;
		$term = preg_replace('#\s+#', '%', urldecode( stripslashes( strip_tags( $_GET['term'] ) ) ));
		$term = empty($term) && is_admin() ? preg_replace('#\s+#', '%', urldecode( stripslashes( strip_tags( $_REQUEST['s'] ) ) )) : $term;
		if (!empty($term)) $query->query_where = preg_replace('#^(.*)(where 1=1 and \()(.*)#si', '\1\2'.$wpdb->prepare('display_name like %s or ', '%'.$term.'%').'\3', $query->query_where);
		$query->query_orderby = ' GROUP BY '.$wpdb->users.'.id '.$query->query_orderby;
	}

	public static function save_page($post_id, $post) {
		$opost = clone $post;
		if ($post->post_type == 'revision') $post = get_post($post->post_parent);
		if (!post_type_supports($post->post_type, 'page-attributes')) return;

		$templates = array_flip(apply_filters('qsot-get-page-template-list', array()));
		if (isset($_POST['page_template']) && isset($templates[$_POST['page_template']])) {
			update_post_meta($opost->ID, '_wp_page_template', $_POST['page_template']);
			update_post_meta($post->ID, '_wp_page_template', $_POST['page_template']);
		}
	}

	public static function page_get_page_template_list($current) {
		$templates = apply_filters('qsot-page-templates-list', get_page_templates(), $current);
		return $templates;
	}

	public static function page_template_default($template) {
		$post = get_queried_object();
		if ($post->post_type != 'page') return $template;

		$page_template = get_page_template_slug();

		return apply_filters('qsot-maybe-override-theme_default', $template, $page_template, 'page.php');
	}

	public static function maybe_override_template($template='', $possible_plugin_filename='', $theme_filename='') {
		if (empty($possible_plugin_filename) || empty($theme_filename)) return $template;

		$defaults = array(
			trailingslashit(get_template_directory()).$theme_filename => 1,
			trailingslashit(get_stylesheet_directory()).$theme_filename => 1,
		);
		if (!isset($defaults[$template])) return $template;

		$dirs = apply_filters('qsot-theme-template-dirs', array(self::$o->core_dir.'templates/theme/'), $template, $possible_plugin_filename, $theme_filename);
		
		foreach ($dirs as $dir) {
			$dir = trailingslashit($dir);
			if (file_exists($dir.$possible_plugin_filename) && is_file($dir.$possible_plugin_filename)) {
				$template = $dir.$possible_plugin_filename;
				break;
			}
		}

		return $template;
	}

	public static function page_draw_page_template_list($default) {
		$templates = apply_filters('qsot-get-page-template-list', array());
		ksort( $templates );
		foreach (array_keys( $templates ) as $template )
			: if ( $default == $templates[$template] )
				$selected = " selected='selected'";
			else
				$selected = '';
		echo "\n\t<option value='".$templates[$template]."' $selected>$template</option>";
		endforeach;
	}

	public static function page_templates_list($list, $selected) {
		$dirs = apply_filters('qsot-page-template-dirs', array(self::$o->core_dir.'templates/theme/'), $list, $selected);
		$regex = '#^.+\.php$#i';
		$add_list = array();

		foreach ($dirs as $dir) {
			try {
				$iter = new RegexIterator(
					new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator(
							$dir
						),
						RecursiveIteratorIterator::SELF_FIRST
					),
					$regex,
					RecursiveRegexIterator::GET_MATCH
				);

				// require every file found
				foreach ($iter as $fullpath => $file) {
					if (!preg_match('|Template Name:(.*)$|mi', file_get_contents($fullpath), $header))
						continue;
					$file = is_array($file) ? array_shift($file) : $file;
					$file = str_replace(trailingslashit($dir), '', $file);
					$add_list[$file] = _cleanup_header_comment($header[1]);
				}
			} catch (Exception $e) {}
		}

		$list = array_flip(array_merge($add_list, array_flip($list)));
		ksort($list);

		return $list;
	}

	public static function add_sync_billing_field( $order ) {
		// only allow updating the user's billing information if we require users to signup, creating user accounts
		if ( get_option( 'woocommerce_enable_guest_checkout' ) != 'yes' ) {
			echo '<div class="edit_address"><p class="form-field _billing_sync_customer_address">'
				. '<input type="hidden" name="_billing_sync_customer_address" value="0" />'
				. '<input type="checkbox" name="_billing_sync_customer_address" value="1" class="billing-sync-customer-address" />'
				. '<span for="_billing_sync_customer_address">Update Customer Address</span>'
			. '</p></div>';
		}
	}

	public static function update_order_user_addresses($post_id, $post) {
		if ($post->post_type != 'shop_order' && !empty($_POST)) return;

		$billing_update = isset($_POST['_billing_sync_customer_address']) && !empty($_POST['_billing_sync_customer_address']);
		$shipping_update = isset($_POST['_shipping_sync_customer_address']) && !empty($_POST['_shipping_sync_customer_address']);

		if (!$billing_update && !$shipping_update) return;

		$meta = $new = $cur = array();
		foreach (array('billing', 'shipping') as $prefix) {
			$update_key = $prefix.'_update';
			if ($$update_key) {
				foreach (array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country', 'email', 'phone') as $suffix) {
					$new[$prefix.'_'.$suffix] = isset($_POST['_'.$prefix.'_'.$suffix]) ? $_POST['_'.$prefix.'_'.$suffix] : '';
					$cur[$prefix.'_'.$suffix] = get_post_meta($post_id, $prefix.'_'.$suffix, true);
				}
			}
		}

		foreach ($cur as $k => $v) {
			if ($v != $new[$k]) {
				$meta[$k] = $new[$k];
			}
		}

		if (!empty($meta)) {
			if (isset($meta['billing_first_name'])) $meta['first_name'] = $meta['billing_first_name'];
			if (isset($meta['billing_last_name'])) $meta['last_name'] = $meta['billing_last_name'];

			$customer_user_id = (int)$_POST['customer_user'];
			if (empty($customer_user_id)) return;
			$user = new WP_User($customer_user_id);
			if (!is_object($user) || !isset($user->ID)) return;

			foreach ($meta as $k => $v) update_user_meta($user->ID, $k, $v);

			if (isset($meta['first_name'], $meta['last_name']) && !empty($meta['first_name']) && !empty($meta['last_name'])) {
				global $wpdb;
				$wpdb->update($wpdb->users, array('display_name' => $meta['first_name'].' '.$meta['last_name']), array('id' => $user->ID));
			}
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_core_hacks::pre_init();
}
