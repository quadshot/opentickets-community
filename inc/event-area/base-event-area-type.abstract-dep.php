<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// the base class for all event area types. requires some basic functions and defines basic properties
abstract class QSOT_Base_Event_Area_Type {
	// an incremeneted value so that every area type can have it's own priority by default
	protected static $inc_priority = 1;

	// this specific area type's priority
	protected $priority = 0;

	// the priority to use when determining the type of an arae we dont know the type of
	protected $find_priority = PHP_INT_MAX;

	// container for the list of meta_boxes that this type uses
	protected $meta_boxes = null;

	// name and slug of this area type
	protected $name = '';
	protected $slug = '';

	// basic constructor for the area type
	public function __construct() {}

	// default initialization
	public function initialize( $ajax=true ) {
		$this->priority = self::$inc_priority++;
		$this->slug = sanitize_title_with_dashes( 'area-type-' . $this->priority );
		$this->name = sprintf( __( 'Area Type %d', 'opentickets-community-edition' ), $this->priority );
	}

	// get the priority of this area type
	public function get_priority() { return $this->priority; }

	// get the slug of this area type
	public function get_slug() { return $this->slug; }

	// get the name of this area type
	public function get_name() { return $this->name; }

	// get the find priority of this area type. this will determine the order in which this type is tested, to determine the type of an unknown typed event area
	public function get_find_priority() { return $this->find_priority; }

	// get the list of metaboxes that this type requires
	public function get_meta_boxes() { return $this->meta_boxes; }

	// determine if this area type uses a metabox with id and screen supplied
	public function uses_meta_box( $id, $screen ) {
		$uses = false;

		$meta_boxes = $this->get_meta_boxes();
		// cycle through our list of metaboxes, and figure out if we have one that matches the id and screen of the queried metabox
		if ( is_array( $meta_boxes) ) foreach ( $meta_boxes as $mb ) {
			if ( $id == $mb[0] && $screen == $mb[3] ) {
				$uses = true;
				break;
			}
		}

		return $uses;
	}

	// register all the assets used by this area type
	public function register_assets() {}

	// enqueue the frontend assets needed by this type
	public function enqueue_assets( $event ) {}

	// enqueue the admin assets needed by this type
	public function enqueue_admin_assets( $type=null, $exists=false, $post_id=0 ) {}

	// get the frontend templates to use for this event area type
	public function get_templates( $event ) { return array(); }

	// get the admin templates to use for this event area type
	public function get_admin_templates( $list, $type, $args='' ) { return $list; }

	// add the event area type specific data to the load event ajax response for the admin
	public function admin_ajax_load_event( $data, $event, $event_area, $order ) { return $data; }

	// when compiling the ticket data to display to the user on the ticket page, we might need some logic based on the area_type
	public function compile_ticket( $ticket ) { return $ticket; }

	// include a template, and make specific $args local vars
	protected function _include_template( $template, $args ) {
		// extract args to local vars
		extract( $args );

		include $template;
	}

	// include a template part
	protected function _maybe_include_template( $template_name, $args ) {
		// get the template from the template filename
		$template = apply_filters( 'qsot-locate-template', false, array( $template_name ), false, false );

		// extract the vars to use in this template
		extract( $args );

		// if there is no defined template, then bail
		if ( empty( $template ) )
			return '';

		// otherwise, include that template
		ob_start();
		include $template;
		$out = ob_get_contents();
		ob_end_clean();

		return trim( preg_replace( '#>\s+<#s', '> <', $out ) );
	}

	// handle the additional display of order items for tickets that use this event area type
	public function order_item_display( $item, $product, $event ) {}

	// during the saving of the order items from the edit order screen in the admin, we may need to update the item's reservations
	public function save_order_item( $order_id, $order_item_id, $item, $updates, $event_area ) {
		// get the zoner for this area
		$zoner = $this->get_zoner();
		if ( ! is_object( $zoner ) )
			return false;
		$stati = $zoner->get_stati();

		// update this order item's reservations
		$res = $zoner->update( false, array(
			'order_id' => $order_id,
			'event_id' => $item['event_id'],
			'state' => array( $stati['r'][0], $stati['c'][0] ),
			'order_item_id' => $order_item_id,
		), array( 'quantity' => $updates['order_item_qty'] ) );

		return $res;
	}

	// get the cart item quantity of the matched row/s
	public function cart_item_match_quantity( $item, $rows ) {
		return array_sum( wp_list_pluck( $rows, 'quantity' ) );
	}

	// during the save action of an event, we need to update it's event_area, based on the submitted data
	public function save_event_settings( $meta_to_save, $sub_event ) {
		return $meta_to_save;
	}

	// draw event area image
	public function draw_event_area_image( $event_area, $event, $reserved ) {
		// get the id of the image we should use for this feature
		$image_id = intval( get_post_meta( $event_area->ID, '_thumbnail_id', true ) );

		// if there is no image id, then bail
		if ( $image_id <= 0 )
			return;

		// get the image information
		list( $image_url, $width, $height, $resized ) = wp_get_attachment_image_src( $image_id, 'full' );

		// if there is noe image data, then bail
		if ( empty( $image_url ) )
			return;

		// otherwise, draw the image
		echo sprintf(
			'<div class="event-area-image-wrap"><img src="%s" width="%s" height="%s" alt="%s" /></div>',
			esc_attr( $image_url ),
			$width,
			$height,
			sprintf( __( 'Image of the %s event area', 'opentickets-community-edition' ), esc_attr( apply_filters( 'the_title', $event_area->post_title, $event_area->ID ) ) )
		);
	}

	// get the display name of a given ticket order item, for the upcoming tickets my-account module
	public function upcoming_tickets_display_name( $ticket ) {
		return sprintf(
			'%s @ %s',
			isset( $ticket->product ) ? $ticket->product->get_title() : __( 'Ticket', 'opentickets-community-edition' ),
			isset( $ticket->_line_subtotal ) ? wc_price( $ticket->_line_subtotal ) : __( '(free)', 'opentickets-community-edition' )
		);
	}

	// method to fetch the capacity of the event area
	public function get_capacity( $area, $type='total' ) {
		return $area->_capacity;
	}

	// the function that returns the object that controls the reservations for this event area type
	abstract public function get_zoner();

	// function used to determine if the supplied post matches this type's needed data. used to determine the type of the event area, when the type is not stored in meta
	abstract public function post_is_this_type( $post );

	// function used to save the relevant information for the event areas of this type
	abstract public function save_post( $post_id, $post, $updated );

	// determine the ticket price, based on some supplied criteria
	abstract public function get_ticket_type( $data='' );

	// render the frontend ticket selection ui
	abstract public function render_ui( $event, $event_area );

	// method to handle the confirmation of tickets purchased
	abstract public function confirm_tickets( $item, $item_id, $order, $event, $event_area );

	// method to handle the unconfirmation of tickets purchased, like when put into pending
	abstract public function unconfirm_tickets( $item, $item_id, $order, $event, $event_area );

	// method to handle the cancellation of tickets purchased
	abstract public function cancel_tickets( $item, $item_id, $order, $event, $event_area );
}
