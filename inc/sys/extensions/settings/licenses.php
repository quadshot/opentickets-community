<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
/**
 * OpenTickets Extensions License Settings
 *
 * @author 		Quadshot (modeled from work done by WooThemes)
 * @category 	Admin
 * @package 	OpenTickets/Admin
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'QSOT_Settings_Licenses' ) ) :

class QSOT_Settings_Licenses extends QSOT_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id = 'licenses';
		$this->label = __( 'Licenses', 'opentickets-community-edition' );

		add_action( 'qsot_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_filter( 'qsot_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'qsot_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'qsot_settings_save_' . $this->id, array( $this, 'save' ) );

		if ( ( $styles = WC_Frontend_Scripts::get_styles() ) && array_key_exists( 'woocommerce-general', $styles ) )
			add_action( 'woocommerce_admin_field_frontend_styles', array( $this, 'frontend_styles_setting' ) );

		// add an action to display any 'global activation' errors
		add_action( 'admin_notices', array( &$this, 'display_global_errors' ) );

		// add a hook that runs on loading of this page, which checks if there are any onetime requests, like deactivating a license
		@list( $uri, $hook ) = apply_filters( 'qsot-get-menu-page-uri', '', 'settings' );
		add_action( 'load-' . $hook, array( &$this, 'maybe_one_time_requests' ), 1 );
	}

	// output the page itself, which displays a list of all the installed softwares, and their respective registered license information
	public function output() {
		// load all relevant data
		$extensions = QSOT_Extensions::instance();
		$installed = $extensions->get_installed();
		$known = $extensions->get_known();
		$licenses = $extensions->get_licenses();
		$now = time();
		$base_url = remove_query_arg( array( 'updated', 'deact', 'act', 'error', 'msg' ) );

		$cnt = 0;
		// render the list of available plugins that have or need a license, that we know of
		?>
			<?php if ( empty( $installed ) ): // this should never show, but just in case someone is being dumb... ?>
				<p><?php _e( 'You do not have any Software installed that requires an OpenTickets Extension License.', 'opentickets-community-edition' ) ?></p>
			<?php else: ?>
				<div class="qsot-panel">
					<div class="item-list installed-extensions">

						<div class="list-item heading">
							<div class="status-icon-heading heading-item"><?php _e( 'Status', 'opentickets-community-edition' ) ?></div>
							<div class="software-heading heading-item"><?php _e( 'Software', 'opentickets-community-edition' ) ?></div>
						</div>

						<?php foreach ( $known as $file => $plugin ): ?>
							<div class="list-item installed-extension <?php echo 0 == $cnt++ % 2 ? 'odd' : 'even' ?>" data-extension="<?php echo esc_attr( $file ) ?>" role="extension">
								<div class="status-icon">
									<?php if ( ! isset( $licenses[ $file ] ) ): // not registered ?>
									<?php else: ?>
										<?php if ( isset( $plugin['needs_license'] ) && ! $plugin['needs_license'] ): // expired ?>
											<span class="dashicons dashicons-yes"></span>
										<?php elseif ( $now > $licenses[ $file ]['expires'] ): // expired ?>
											<span class="dashicons dashicons-warning"></span>
										<?php else: // valid and registered ?>
											<span class="dashicons dashicons-yes"></span>
										<?php endif; ?>
									<?php endif; ?>
								</div>

								<div class="fields">
									<div class="field">
										<span class="label"><?php _e( 'Name', 'opentickets-community-edition' ) ?></span>:
										<?php
											$name = isset( $installed[ $file ], $installed[ $file ]['Name'] ) && ! empty( $installed[ $file ]['Name'] ) ? $installed[ $file ]['Name'] : $plugin['label'];
											$version = isset( $installed[ $file ], $installed[ $file ]['Version'] ) && ! empty( $installed[ $file ]['Version'] ) ? $installed[ $file ]['Version'] : $plugin['version'];
										?>
										<span class="value"><?php echo apply_filters( 'the_title', $name . ' ' . sprintf( __( '(version %s)', 'opentickets-community-edition' ), $version ) ) ?></span>
									</div>

									<?php $is_installed = isset( $installed[ $file ] ) ? __( 'Installed', 'opentickets-community-edition' ) : __( 'Not Installed', 'opentickets-community-edition' ) ?>

									<?php if ( isset( $plugin['needs_license'] ) && ! $plugin['needs_license'] ): ?>
										<div class="field">
											<span class="label"><?php _e( 'Status', 'opentickets-community-edition' ) ?></span>:
											<span class="value">
												<?php echo sprintf( __( '(%s%s%s)', 'opentickets-community-edition' ), '<em>', $is_installed, '</em>' ) ?>
												<?php _e( 'No License Required', 'opentickets-community-edition' ) ?>
											</span>
										</div>
									<?php elseif ( ! isset( $licenses[ $file ] ) || ! isset( $licenses[ $file ]['verification_code'] ) || empty( $licenses[ $file ]['verification_code'] ) ): ?>
										<?php $item = isset( $licenses[ $file ] ) ? $licenses[ $file ] : array() ?>
										<div class="field">
											<span class="label"><?php _e( 'Status', 'opentickets-community-edition' ) ?></span>:
											<span class="value">
												<?php echo sprintf( __( '(%s%s%s)', 'opentickets-community-edition' ), '<em>', $is_installed, '</em>' ) ?>
												<?php _e( 'Not licensed', 'opentickets-community-edition' ) ?>
												<?php echo sprintf(
													__( '(%sget a license now%s)', 'opentickets-community-edition' ),
													sprintf( '<a href="%s" title="%s" target="_blank">', isset( $plugin['to_cart_url'] ) ? $plugin['to_cart_url'] : '#', __( 'Purchase a License', 'opentickets-community-edition' ) ),
													'</a>'
												) ?>
											</span>
										</div>

										<div class="field">
											<div class="fields two-cols">
												<div class="field">
													<span class="label"><?php _e( 'License Key', 'opentickets-community-edition' ) ?></span>
													<input type="text" name="license[<?php echo esc_attr( $file ) ?>][license]" value="<?php echo esc_attr( isset( $item['license'] ) ? $item['license'] : '' ) ?>" class="widefat" />
												</div>

												<div class="field">
													<span class="label"><?php _e( 'Registered Email', 'opentickets-community-edition' ) ?></span>
													<input type="text" name="license[<?php echo esc_attr( $file ) ?>][email]" value="<?php echo esc_attr( isset( $item['email'] ) ? $item['email'] :  get_bloginfo( 'admin_email' ) ) ?>" class="widefat" />
												</div>

												<div class="clear"></div>

												<?php if ( isset( $licenses[ $file ], $licenses[ $file ]['errors'] ) ): ?>
													<div class="qsot-errors">
														<?php foreach ( $licenses[ $file ]['errors'] as $msgs_pkg ): ?>
															<?php if ( isset( $msgs_pkg['msgs'] ) ) foreach ( $msgs_pkg['msgs'] as $msg ): ?>
																<div class="qsot-error"><?php echo force_balance_tags( $msg ) ?></div>
															<?php endforeach; ?>
														<?php endforeach; ?>
													</div>
												<?php endif; ?>
											</div>
										</div>
									<?php elseif ( $now > $licenses[ $file ]['expires'] ): ?>
										<div class="field">
											<span class="label"><?php _e( 'Status', 'opentickets-community-edition' ) ?></span>:
											<span class="value">
												<?php echo sprintf( __( '(%s%s%s)', 'opentickets-community-edition' ), '<em>', $is_installed, '</em>' ) ?>
												<?php _e( 'License is Expired', 'opentickets-community-edition' ) ?>
												<?php echo sprintf(
													__( '(%sdeactivate now%s)', 'opentickets-community-edition' ),
													sprintf(
														'<a href="%s" title="%s">',
														add_query_arg( array( 'deact' => $file, 'donce' => wp_create_nonce( 'deactivate-' . $file ) ), $base_url ),
														__( 'Deactivate this License', 'opentickets-community-edition' )
													),
													'</a>'
												) ?>
											</span>
										</div>

										<div class="field">
											<span class="label"><?php _e( 'Expired On', 'opentickets-community-edition' ) ?></span>:
											<span class="value"><?php echo date_i18n( get_option( 'date_format', 'F jS, Y' ), $licenses[ $file ]['expires'] ) ?></span>
										</div>
									<?php else: ?>
										<div class="field">
											<span class="label"><?php _e( 'Status', 'opentickets-community-edition' ) ?></span>:
											<span class="value">
												<?php echo sprintf( __( '(%s%s%s)', 'opentickets-community-edition' ), '<em>', $is_installed, '</em>' ) ?>
												<?php _e( 'Activated', 'opentickets-community-edition' ) ?>
												<?php echo sprintf(
													__( '(%sdeactivate now%s)', 'opentickets-community-edition' ),
													sprintf(
														'<a href="%s" title="%s">',
														add_query_arg( array( 'deact' => $file, 'donce' => wp_create_nonce( 'deactivate-' . $file ) ), $base_url ),
														__( 'Deactivate this License', 'opentickets-community-edition' )
													),
													'</a>'
												) ?>
											</span>
										</div>

										<div class="field">
											<span class="label"><?php _e( 'Expires On', 'opentickets-community-edition' ) ?></span>:
											<span class="value"><?php echo date_i18n( get_option( 'date_format', 'F jS, Y' ), $licenses[ $file ]['expires'] ) ?></span>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>

					</div>
				</div>

				<script language="javascript">
					if ( 'undfined' != typeof jQuery && null !== jQuery ) 
						jQuery( function( $ ) {
							var hash = window.location.hash, params = hash.split( /,/ ), action = params.shift();
							switch ( action ) {
								case '#focus':
									var target = params.shift();
									if ( target ) {
										var item = $( '[role="extension"]' ).filter( function() {
											var data = $( this ).data( 'extension' );
											return data == target;
										} ).filter( ':eq(0)' );

										item.css( { backgroundColor:'rgb( 255, 248, 159 )' } ).find( ':input:visible:eq(0)' ).focus();

										item.closest( 'form' ).attr( 'action', window.location.href.split('#')[0] );
									}
								break;
							}
						} );
				</script>
			<?php endif; ?>
		<?php
	}

	/**
	 * Save settings
	 */
	public function save() {
		// clear out any convereted key messages we were previously showing
		delete_option( 'qsot-converted-msg' );

		// get a list of the installed plugins we need to worry about
		$installed = QSOT_Extensions::instance()->get_installed();

		$list = array();
		// grab the relevant information for each license and put it into an organized list
		if ( isset( $_POST['license'] ) )
			foreach ( $_POST['license'] as $file => $data )
				$list[ $file ] = array(
					'license' => isset( $data['license'] ) && is_scalar( $data['license'] ) ? trim( $data['license'] ) : '',
					'email' => isset( $data['email'] ) && is_scalar( $data['email'] ) ? trim( $data['email'] ) : '',
					'base_file' => $file,
					'version' => isset( $installed[ $file ]['Version'] ) ? $installed[ $file ]['Version'] : '',
					'verification_code' => '',
					'expires' => '',
				);

		// if there are no items in our list, then clear our the settings, and bail
		if ( empty( $list ) ) {
			update_option( 'qsot-licenses', array(), 'no' );
			return;
		}

		$request = array();
		// create the data we need to perform the activation request
		foreach ( $list as $file => $data ) {
			// if the email or key are not present, then skip this item
			if ( empty( $data['license'] ) || empty( $data['email'] ) )
				continue;

			// otherwise add the item to the request
			$request[ $file ] = array(
				'license' => $data['license'],
				'email' => $data['email'],
				'file' => $file,
				'version' => $data['version'],
			);
		}

		// if there are no licenses that are trying to be activated, then just bail
		if ( empty( $request ) )
			return;

		// get the api response for activation
		$api = QSOT_Extensions_API::instance();
		$response = $api->activate( array( 'activate' => $request ) );

		// if the response is a hard fail, then store that in the db, and abort
		if ( is_wp_error( $response ) ) {
			// save the submitted data that passed validation
			update_option( 'qsot-licenses', $list, 'no' );

			// make an array from the wp_error response
			$errors = $this->_array_from_error( $response );
			update_option( 'qsot-licenses-error', $errors, 'no' );
			return;
		}

		// cycle through all the requested items and merge the response with the data we already have
		foreach ( $list as $file => $data ) {
			// if the item is not in our response, skip this item
			if ( ! isset( $response[ $file ] ) )
				continue;

			// remove any old errors from the item, and reset the verification_code and expires keys also
			unset( $data['errors'] );
			$data['expires'] = $data['verification_code'] = '';

			// merge all the new data with the data we already have
			$data = array_merge( $data, $response[ $file ] );

			// update the item in the list
			$list[ $file ] = $data;
		}

		// finally, update our option containing the option status for each item
		QSOT_Extensions::instance()->save_licenses( $list );
	}

	// display any global activation errors
	public function display_global_errors() {
		// load any global errors
		$errors = get_option( 'qsot-licenses-error', array() );

		// remove them so they dont show on two page loads
		update_option( 'qsot-licenses-error', array() );

		// if the list of errors is empty, then bail
		if ( empty( $errors ) )
			return;

		?>
			<div class="error">
				<?php foreach ( $errors as $code => $list ): ?>
					<?php foreach ( $list as $msg ): ?>
						<p><?php echo apply_filters( 'the_title', $msg ) ?></p>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		<?php
	}

	// sometimes there are one time requests to handle. if so, do that now
	public function maybe_one_time_requests() {
		// if there is a valid deactivation request, then process it now
		if ( isset( $_GET['deact'], $_GET['donce'] ) && ! empty( $_GET['deact'] ) && ! empty( $_GET['donce'] ) && wp_verify_nonce( $_GET['donce'], 'deactivate-' . $_GET['deact'] ) ) {
			$extensions = QSOT_Extensions::instance();
			$installed = $extensions->get_installed();
			// first make sure the requested item is actually active. if not, bail
			$licenses = $extensions->get_licenses();
			if ( ! isset( $licenses[ $_GET['deact'] ], $licenses[ $_GET['deact'] ]['verification_code'] ) || empty( $licenses[ $_GET['deact'] ]['verification_code'] ) )
				return;

			$item = $licenses[ $_GET['deact'] ];
			// construct the data needed for the deactivation request
			$data = array(
				'license' => $item['license'],
				'email' => $item['email'],
				'verification_code' => $item['verification_code'],
				'file' => $item['base_file'],
				'version' => isset( $installed[ $item['base_file'] ], $installed[ $item['base_file'] ]['Version'] ) ? $installed[ $item['base_file'] ]['Version'] : '',
			);

			// run the request
			$api = QSOT_Extensions_API::instance();
			$response = $api->deactivate( $data );

			// if the request hard failed, then add that error to the global error messages, so that it is displayed on next license page load
			if ( is_wp_error( $response ) ) {
				// convert the error to an array, and store it
				$errors = $this->_array_from_error( $response );
				update_option( 'qsot-licenses-error', $errors );
			}

			// update the license in the db
			$data['verification_code'] = '';
			$data['expires'] = '';
			$data['license'] = '';
			$data['msg'] = __( 'This license was successfully deactivated.', 'opentickets-community-edition' );

			// save the new license info
			$extensions->save_licenses( array( $item['base_file'] => $data ) );

			// redirect to prevent double submission
			$url = remove_query_arg( array( 'updated', 'deact', 'act', 'error', 'msg' ) );
			wp_safe_redirect( add_query_arg( array( 'updated' => 1 ), $url ) );
			exit;
		}
	}

	// convert a wp_error object to an array
	protected function _array_from_error( $wp_error ) {
		$arr = array();
		// for each code that has errors on it
		foreach ( $wp_error->get_error_codes() as $code ) {
			$arr[ $code ] = array();
			// add each message to the list of messages for this code
			foreach ( $wp_error->get_error_messages( $code ) as $msg )
				$arr[ $code ][] = $msg;
		}

		return $arr;
	}
}

endif;

return new QSOT_Settings_Licenses();
