<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

// handle the registration, loading, rendering and saving of the new extensions page
class QSOT_Extensions_Page {
	// singleton container
	protected static $_instance = null;

	// setup the singleton for this lass
	public static function instance() {
		// figure out the current class
		$class = __CLASS__;

		// if we already have an instance, use that
		if ( isset( self::$_instance ) && self::$_instance instanceof $class )
			return self::$_instance;

		// otherwise create one and return it
		return self::$_instance = new QSOT_Extensions_Page();
	}

	// create the page
	public function __construct() {
		// add the page to the admin menu
		add_action( 'admin_menu', array( &$this, 'register_menu_item' ), 20 );
	}

	// register the new menu item as a sub menu item of the appropriate menu
	public function register_menu_item() {
		// figure out the menu item slug that this should be under
		$main = apply_filters( 'qsot-get-menu-slug', '', 'main' );
		if ( empty( $main ) )
			return;

		// add the menu item
		$hook = add_submenu_page(
			$main,
			__( 'Extensions', 'opentickets-community-edition' ),
			'<span class="otce-highlight-link">' . __( 'Extensions', 'opentickets-community-edition' ) . '</span>',
			'view_woocommerce_reports',
			'qsot-extensions',
			array( &$this, 'render_page' )
		);

		// add a hook to load assets for the page
		add_action( 'admin_print_scripts-' . $hook, array( &$this, 'load_assets' ), 1 );

		// add hook to handle page load logic, like saving and such
		add_action( 'load-' . $hook, array( &$this, 'on_load' ), 10 );
	}

	// on page load, load any assets needed by the page
	public function load_assets() {
		// spoof css for thickbox, as if on plugins page
		add_filter( 'admin_body_class', array( &$this, 'fake_plugins_page' ) );
	}

	// fool the admin into thinking this is the plugins page. this makes the details thickbox have the same styling as the plugins page, and nothing else, afaik
	public function fake_plugins_page( $classes ) {
		return $classes . ' plugins-php';
	}

	// during page load, there may be things we have to handle first, like saving
	public function on_load() {
		// if we are being asked to force refresh the list of known plugins, then do so now
		if ( isset( $_GET['force-check'] ) && 1 == $_GET['force-check'] ) {
			QSOT_Extensions::instance()->force_refresh_known_plugins();
			wp_safe_redirect( remove_query_arg( array( 'force-check', 'updated' ) ) );
			exit;
		}
	}

	// draws the page
	public function render_page() {
		// extension object that holds all the data about out extensions
		$ext = QSOT_Extensions::instance();

		// list of all the extensions this handler knows about from our remote repo
		$known = $ext->get_known();

		// find the slug map to map filenames to slugs. used for the details box popping
		$slugs = $ext->get_file_slug_map();

		// list of all the known plugins that are already installed
		$installed = $ext->get_installed( true );
		$installed = array_flip( $installed );

		// list of all the plugins that are installed and activated with a license
		$activated = $ext->get_activated();
		$activated = array_flip( $activated );

		// find the uploads dir information, so we can construct the image urls
		$u = wp_upload_dir();
		$url = trailingslashit( $u['baseurl'] );

		// licenses page url
		@list( $settings_uri, $settings_hook ) = apply_filters( 'qsot-get-menu-page-uri', '', 'settings' );
		$licenses_url = add_query_arg( array( 'tab' => 'licenses' ), $settings_uri );

		?>
			<div class="wrap">
				<h2><?php _e( 'OpenTickets Extensions', 'opentickets-community-edition' ) ?></h2>
				<div class="qsot-list" role="list">
					<?php foreach ( $known as $file => $data ): ?>
						<div class="extension" role="extension" data-extension="<?php echo esc_attr( $file ) ?>">
							<div class="inner">
								<div class="header"><?php echo empty( $data['icon_rel_path'] ) ? '' : sprintf(
									'<img src="%s" width="%s" title="%s%s" />',
									esc_attr( $url . $data['icon_rel_path'] ),
									'300',
									esc_attr( __( 'View', 'opentickets-community-edition' ) ),
									esc_attr( $data['label'] )
								) ?></div>

								<div class="meta">
									<div class="status">
										<?php if ( isset( $activated[ $file ] ) ): ?>
											<strong><em><?php _e( 'Activated', 'opentickets-community-edition' ) ?></em></strong>
										<?php elseif ( isset( $installed[ $file ] ) ): ?>
											<em><?php _e( 'Installed', 'opentickets-community-edition' ) ?></em>
										<?php else: ?>
											<?php _e( 'Available', 'opentickets-community-edition' ) ?>
										<?php endif; ?>
									</div>

									<div class="price"><?php echo $data['display_price'] ?></div>

									<div class="clear"></div>
								</div>

								<div class="entry">
									<h3><?php echo apply_filters( 'the_title', $data['label'] ) ?></h3>
									<div class="description"><?php echo apply_filters( 'the_content', $data['short'] ) ?></div>
								</div>

								<div class="actions"><div class="inside">
									<?php if ( isset( $activated[ $file ] ) ): ?>
										<input type="button" class="button right disabled" value="<?php echo esc_attr( __( 'Already Activated', 'opentickets-community-edition' ) ) ?>" />
									<?php elseif ( isset( $installed[ $file ] ) ): ?>
										<a href="<?php echo esc_attr( $licenses_url . '#focus,' . $file ) ?>" class="button right"><?php _e( 'Activate License', 'opentickets-community-edition' ) ?></a>
									<?php else: ?>
										<a href="<?php echo esc_attr( $data['to_cart_url'] ) ?>" target="_blank" class="button-primary right"><?php _e( 'Purchase', 'opentickets-community-edition' ) ?></a>
									<?php endif; ?>

									<?php
										$details_link = ( $slug = isset( $slugs[ $file ] ) ? $slugs[ $file ] : false )
												? self_admin_url( 'plugin-install.php?tab=plugin-information&amp;plugin=' . $slug . '&amp;TB_iframe=true&amp;width=600&amp;height=550' )
												: 'javascript:void();';
									?>
									<a href="<?php echo esc_attr( $details_link ) ?>" class="button thickbox"><?php echo _e( 'Details', 'opentickets-community-edition' ) ?></a>

									<div class="clear"></div>
								</div></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	return QSOT_Extensions_Page::instance();
