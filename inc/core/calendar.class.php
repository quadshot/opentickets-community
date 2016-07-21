<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// controls all aspects of the calendar used by our plugin
class qsot_frontend_calendar {
	protected static $o = null;
	protected static $options = null;
	protected static $shortcode = 'qsot-event-calendar';

	protected static $current_theme = null;

	// setup the actions and filters we will need to run this feature
	public static function pre_init() {
		self::_setup_admin_options();

		// add a bit that runs some installation/activation logic
		add_action( 'qsot-activate', array( __CLASS__, 'create_calendar_page' ), 10 );

		// handle registration and enqueuing of our calendar assets
		add_filter( 'init', array( __CLASS__, 'register_assets' ), 10 );
		add_filter( 'wp_enqueue_scripts', array( __CLASS__, 'add_assets' ), 10000 );

		// handle the ajax requests for the calendar
		$aj = QSOT_Ajax::instance();
		$aj->register( 'qscal', array( __CLASS__, 'handle_ajax' ) );
		add_action( 'qsot-calendar-settings', array( __CLASS__, 'calendar_settings' ), 10, 3 );

		// filter to fetch an event in a form that the calendar software can understand. works with the ajax stuff above
		add_filter( 'qsot-calendar-event', array( __CLASS__, 'get_calendar_event' ), 10, 2 );

		// add a page template and a sidebar for the calendar page. also add a shortcode for using the calendar elsewhere
		add_shortcode( self::$shortcode, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'add_sidebar' ), 11 );
		add_filter( 'qsot-templates-page-templates', array( __CLASS__, 'add_calendar_template' ) );

		// add the admin metabox to control the calendar settings
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 1000 );
		add_action( 'postbox_classes_page_qsot-calendar-settings-box', array( __CLASS__, 'mb_calendar_settings_classes' ), 1000, 1 );
		add_action( 'save_post', array( __CLASS__, 'save_page_calendar_settings' ), 1000, 2 );

		// load admin js and css
		add_action( 'qsot-admin-load-assets-page', array( __CLASS__, 'load_admin_assets' ), 1000, 2 );

		// calendar template wrapper, based on current theme
		add_action( 'init', array( __CLASS__, 'determine_current_theme' ), 10 );
		add_action( 'qsot-before-calendar-content', array( __CLASS__, 'before_calendar_template' ), 10 );
		add_action( 'qsot-after-calendar-content', array( __CLASS__, 'after_calendar_template' ), 10 );
	}

	// determine what the current theme is. this will be used to determine the wrappers for the calendar page content
	public static function determine_current_theme() {
		self::$current_theme = wp_get_theme();
	}

	// print out the 'opening wrapper' for the calendar page, depending on the current theme (at least the ones we are going to include by default in our plugin)
	public static function before_calendar_template() {
		$name = sanitize_title_with_dashes( self::$current_theme->template );
		switch ( $name ) {
			// core WP themes
			case 'twentytwelve':
				echo '<div id="primary"><div id="content" role="main">';
			break;

			case 'twentythirteen':
				echo '<div id="primary" class="content-area"><div id="content" class="site-content" role="main">';
			break;

			case 'twentyfourteen':
				echo '<div id="main-content" class="main-content"><div id="primary" class="content-area"><div id="content" class="site-content" role="main"><div id="page-entry">';
			break;

			case 'twentyfifteen':
				echo '<div id="primary" class="content-area"><main id="main" class="site-main" role="main">';
			break;

			// canvas, OT preferred theme
			case 'canvas':
    		echo '<div id="content" class="col-full"><div id="main-sidebar-container"><section id="main">';
			break;

			// all other themes can add their own templates if needed, or have something defined in their child theme functions.php to handle it
			default:
				if ( has_action( 'qsot-before-calendar-content-' . $name ) )
					do_action( 'qsot-before-calendar-content-' . $name, self::$current_theme );
				else
					echo '<div id="main-content" class="main-content"><div id="primary" class="content-area"><div id="content" class="row-fluid clearfix site-content calendar-content">'
							.'<div class="span12 container"><div id="page-entry" class="calendar-content-wrap"><div class="fluid-row">';
			break;
		}
	}

	// print out the 'closing wrapper' for the calender page, depending on the current theme
	public static function after_calendar_template() {
		$name = sanitize_title_with_dashes( self::$current_theme->template );
		switch ( $name ) {
			// core WP themes
			case 'twentytwelve':
			case 'twentythirteen':
				echo '</div></div>';
			break;

			case 'twentyfourteen':
				echo '</div></div></div></div>';
			break;

			case 'twentyfifteen':
				echo '</main></div>';
			break;

			// canvas, OT perferred theme
			case 'canvas':
				echo '</section></div></div>';
			break;

			// all other themes
			default:
				if ( has_action( 'qsot-after-calendar-content-' . $name ) )
					do_action( 'qsot-after-calendar-content-' . $name, self::$current_theme );
				else
				echo '</div></div></div></div></div></div>';
			break;
		}
	}

	// add the calendar page template to the list of page templates
	public static function add_calendar_template( $list ) {
		$list['qsot-calendar.php'] = __( 'OpenTickets Calendar', 'opentickets-community-edition' );
		return $list;
	}

	// register all the scripts and styles we need for this feature
	public static function register_assets() {
		// get the reusable values
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$base_url = QSOT::plugin_url();
		$version = QSOT::version();

		// register the moment.js library since the fullcalendar lib uses it
		wp_register_script( 'moment-core-js', $base_url . 'assets/js/libs/fullcalendar/lib/moment.min.js', array( 'jquery' ), '2.11.0' );
		wp_register_script( 'moment-js', $base_url . 'assets/js/libs/moment-timezone/moment-timezone-all-years.js', array( 'moment-core-js' ), '0.5.4-2016d' ); //timezone lib

		// register the fullcalendar jquery plugin's assets
		// base javascript
		wp_register_script( 'fullcalendar', $base_url . 'assets/js/libs/fullcalendar/fullcalendar' . $suffix . '.js', array( 'moment-js', 'jquery-ui-draggable', 'jquery-ui-datepicker' ), '2.6.0' );

		// language files
		wp_register_script( 'fullcalendar-lang-ar-ma', $base_url . 'assets/js/libs/fullcalendar/lang/ar-ma.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ar-sa', $base_url . 'assets/js/libs/fullcalendar/lang/ar-sa.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ar-tn', $base_url . 'assets/js/libs/fullcalendar/lang/ar-tn.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ar', $base_url . 'assets/js/libs/fullcalendar/lang/ar.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-bg', $base_url . 'assets/js/libs/fullcalendar/lang/bg.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ca', $base_url . 'assets/js/libs/fullcalendar/lang/ca.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-cs', $base_url . 'assets/js/libs/fullcalendar/lang/cs.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-da', $base_url . 'assets/js/libs/fullcalendar/lang/da.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-de-at', $base_url . 'assets/js/libs/fullcalendar/lang/de-at.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-de', $base_url . 'assets/js/libs/fullcalendar/lang/de.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-el', $base_url . 'assets/js/libs/fullcalendar/lang/el.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-en-au', $base_url . 'assets/js/libs/fullcalendar/lang/en-au.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-en-ca', $base_url . 'assets/js/libs/fullcalendar/lang/en-ca.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-en-gb', $base_url . 'assets/js/libs/fullcalendar/lang/en-gb.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-en-ie', $base_url . 'assets/js/libs/fullcalendar/lang/en-ie.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-en-nz', $base_url . 'assets/js/libs/fullcalendar/lang/en-nz.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-es', $base_url . 'assets/js/libs/fullcalendar/lang/es.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-fa', $base_url . 'assets/js/libs/fullcalendar/lang/fa.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-fi', $base_url . 'assets/js/libs/fullcalendar/lang/fi.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-fr-ca', $base_url . 'assets/js/libs/fullcalendar/lang/fr-ca.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-fr-ch', $base_url . 'assets/js/libs/fullcalendar/lang/fr-ch.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-fr', $base_url . 'assets/js/libs/fullcalendar/lang/fr.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-he', $base_url . 'assets/js/libs/fullcalendar/lang/he.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-hi', $base_url . 'assets/js/libs/fullcalendar/lang/hi.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-hr', $base_url . 'assets/js/libs/fullcalendar/lang/hr.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-hu', $base_url . 'assets/js/libs/fullcalendar/lang/hu.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-id', $base_url . 'assets/js/libs/fullcalendar/lang/id.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-is', $base_url . 'assets/js/libs/fullcalendar/lang/is.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-it', $base_url . 'assets/js/libs/fullcalendar/lang/it.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ja', $base_url . 'assets/js/libs/fullcalendar/lang/ja.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ko', $base_url . 'assets/js/libs/fullcalendar/lang/ko.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-lt', $base_url . 'assets/js/libs/fullcalendar/lang/lt.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-lv', $base_url . 'assets/js/libs/fullcalendar/lang/lv.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-nb', $base_url . 'assets/js/libs/fullcalendar/lang/nb.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-nl', $base_url . 'assets/js/libs/fullcalendar/lang/nl.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-pl', $base_url . 'assets/js/libs/fullcalendar/lang/pl.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-pt-br', $base_url . 'assets/js/libs/fullcalendar/lang/pt-br.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-pt', $base_url . 'assets/js/libs/fullcalendar/lang/pt.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ro', $base_url . 'assets/js/libs/fullcalendar/lang/ro.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-ru', $base_url . 'assets/js/libs/fullcalendar/lang/ru.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-sk', $base_url . 'assets/js/libs/fullcalendar/lang/sk.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-sl', $base_url . 'assets/js/libs/fullcalendar/lang/sl.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-sr-cyrl', $base_url . 'assets/js/libs/fullcalendar/lang/sr-cyrl.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-sr', $base_url . 'assets/js/libs/fullcalendar/lang/sr.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-sv', $base_url . 'assets/js/libs/fullcalendar/lang/sv.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-th', $base_url . 'assets/js/libs/fullcalendar/lang/th.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-tr', $base_url . 'assets/js/libs/fullcalendar/lang/tr.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-uk', $base_url . 'assets/js/libs/fullcalendar/lang/uk.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-vi', $base_url . 'assets/js/libs/fullcalendar/lang/vi.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-zh-cn', $base_url . 'assets/js/libs/fullcalendar/lang/zh-cn.js', array( 'fullcalendar' ), '2.6.0' );
		wp_register_script( 'fullcalendar-lang-zh-tw', $base_url . 'assets/js/libs/fullcalendar/lang/zh-tw.js', array( 'fullcalendar' ), '2.6.0' );

		// calendar styles
		wp_register_style( 'fullcalendar-base', $base_url . 'assets/css/features/calendar/fullcalendar.css', array(), '2.6.0' );
		wp_register_style( 'fullcalendar-print', $base_url . 'assets/css/features/calendar/fullcalendar.print.css', array( 'fullcalendar-base' ), '2.6.0', 'print' );
		wp_register_style( 'fullcalendar', $base_url . 'assets/css/features/calendar/calendar.css', array( 'fullcalendar-base', 'fullcalendar-print' ), '2.6.0' );

		// register our calendar controller javascript for this plugin
		wp_register_script( 'qsot-frontend-calendar', $base_url . 'assets/js/features/calendar/calendar.js', array( 'fullcalendar', 'qsot-tools' ), $version );

		// load the styles we will use for the calendar in this plugin
		wp_register_style( 'qsot-frontend-calendar-style', $base_url . 'assets/css/features/calendar/calendar.css', array( 'fullcalendar' ), $version );
		wp_register_script( 'qsot-admin-calendar', $base_url . 'assets/js/features/calendar/admin.js', array( 'jquery-ui-datepicker', 'qsot-frontend-calendar' ), $version );
	}

	// load the appropriate language file for the calendar
	public static function load_calendar_language() {
		// find the current locale being used
		$locale = str_replace( '_', '-', strtolower( get_locale() ) );
		$locale_parts = explode( '-', $locale );

		global $wp_scripts;
		if ( ! is_object( $wp_scripts ) )
			return;
		// see if we have a registered script for this language
		if ( isset( $wp_scripts->registered[ 'fullcalendar-lang-' . $locale ] ) )
			wp_enqueue_script( 'fullcalendar-lang-' . $locale );
		else if ( isset( $wp_scripts->registered[ 'fullcalendar-lang-' . $locale_parts[0] ] ) )
			wp_enqueue_script( 'fullcalendar-lang-' . $locale_parts[0] );
	}

	// load the admin js and css
	public static function load_admin_assets( $exists, $post_id ) {
		wp_enqueue_script( 'qsot-admin-calendar' );
		self::load_calendar_language();
		wp_enqueue_style( 'qsot-admin-styles' );
		do_action( 'qsot-calendar-settings' );
	}

	// add a sidebar that lives on the calendar page template that we added for the calendar
	public static function add_sidebar() {
		// if this theme does not use a dynamic sidebar, then bail
		if ( is_dynamic_sidebar() )
			return;

		// get the name
		$slug = sanitize_title( 'qsot-calendar' );

		global $wp_registered_sidebars;
		// inject the sidebar now, if it is not already registered
		if ( ! isset( $wp_registered_sidebars[ $slug ] ) ) {
			$a = array(
				'id' => $slug,
				'name' => __( 'Non-JS Calendar Page', 'opentickets-community-edition' ),
				'description' => __( 'Widget area on calendar template that shows when a user does not have javascript enabled.', 'opentickets-community-edition' ),
				'before_widget' => '<div id="%1$s" class="widget %SPAN% %2$s"><div class="widget-inner">',
				'after_widget' => '</div><div class="clear"></div></div>',
				'before_title' => '<h3 class="widgettitle">',
				'after_title' => '</h3>',
			);
			register_sidebar( $a );
		}
	}

	// queue up the assets on pages that require calendar assets
	public static function add_assets() {
		// if this is the admin, then bail right now, because they dont need to be there on any of those pages
		if ( is_admin() )
			return;

		// load the post that this page represents, and if the page does not represent a post, bail
		$post = get_post();
		if ( ! is_object( $post ) || is_wp_error( $post ) )
			return;

		// figure out if the current page needs the calendar assets
		$needs_calendar = ( $post->post_type == 'page' && $post->ID == get_option( 'qsot_calendar_page_id', '' ) ); // set as the calendar page in the settings
		if ( ! $needs_calendar ) // using the page template
			$needs_calendar = (bool) preg_match( '#qsot-calendar\.php$#', get_post_meta( $post->ID, '_wp_page_template', true ) );
		if ( ! $needs_calendar ) // the page content contains the shortcode for the calendar
			$needs_calendar = (bool) preg_match( '#\[' . self::$shortcode . '[^\[\]]*\]#', $post->post_content );

		// if this page needs the calendar assets, then queue them now
		if ( $needs_calendar ) {
			// queue the basics
			wp_enqueue_script( 'qsot-frontend-calendar' );
			self::load_calendar_language();
			wp_enqueue_style( 'qsot-frontend-calendar-style' );
			do_action( 'qsot-calendar-settings' );

			// get the site language, so we can load the appropriate calendar language template
			$language = strtolower( get_bloginfo( 'language' ) );
			$language_pieces = explode( '-', $language );

			$added = false;
			// queue the language js template
			if ( isset( $GLOBALS['wp_scripts']->registered[ 'fullcalendar-lang-' . $language ] ) ) {
				$added = true;
				wp_enqueue_script( 'fullcalendar-lang-' . $language );
			}
			// if the specific language template is not registered, then try the 'primary language' (the first 'segment' of the $language value [segments delimited by '-'] )
			if ( ! $added && count( $language_pieces ) > 1 && isset( $GLOBALS['wp_scripts']->registered[ 'fullcalendar-lang-' . $language_pieces[0] ] ) ) {
				$added = true;
				wp_enqueue_script( 'fullcalendar-lang-' . $language_pieces[0] );
			}
			// if neither exist, then do not load a special one, and default to english. sorry guys

			// allow injection here
			do_action( 'qsot-calendar-settings', $post, $needs_calendar, self::$shortcode );
		}
	}

	// create a metabox that holds the extended settings for the calendar page
	public static function add_meta_boxes() {
		// add the metaboxes to the 'edit page' page in the admin
		$screens = array('page');

		// register the metaboxes for each of those pages
		foreach ( $screens as $screen ) {
			// register the box that allows the user to control the additional settings for rendering the calendar
			add_meta_box(
				'qsot-calendar-settings-box',
				__( 'Calendar Settings', 'opentickets-community-edition' ),
				array( __CLASS__, 'mb_calendar_settings' ),
				$screen,
				'side',
				'core'
			);
		}
	}

	// add the js settings that are passed to our custom js, which tells the js the settings for the calendar
	public static function calendar_settings( $post, $needs_calendar=true, $shortcode='' ) {
		// generice settings for the calendar
		wp_localize_script( 'qsot-frontend-calendar', '_qsot_calendar_settings', array(
			// needs redoing, to include templates and stuff... but sigh, as a patch
			'show_count' => 'yes' === apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' ),
			'str' => array(
				'Loading...' => __( 'Loading...', 'opentickets-community-edition' ),
				'Goto Month' => __( 'Goto Month', 'opentickets-community-edition' ),
			),
		) );

		// add the data that is passed to our calendar js
		wp_localize_script(
			'qsot-frontend-calendar',
			'_qsot_event_calendar_ui_settings',
			apply_filters('qsot-event-calendar-ui-settings', array(
				'ajaxurl' => add_query_arg( array( // base calendar ajax url
					'action' => 'qsot-ajax',
					'sa' => 'qscal',
					'_n' => wp_create_nonce( 'do-qsot-ajax' ),
				), admin_url( '/admin-ajax.php' ) ),
				'options' => array(
					'month_img' => 'yes' === apply_filters( 'qsot-get-option-value', 'no', 'qsot-calendar-show-image-on-month' ),
				),
				'templates' => self::_get_frontend_templates( $post ),
				'gotoDate' => self::_get_calendar_start_date( $post ),
			), $post)
		);
	}

	// run the shortcode logic. it adds the placeholder container for the calendar js to replace/fill with the actual calendar
	public static function shortcode($atts) {
		return '<div class="calendar event-calendar"></div>';
	}

	// load the templates we need for displaying the calendar
	protected static function _get_frontend_templates( $post ) {
		$list = array();
		// load each of the needed templates
		$list['month-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/month.php', array( 'post' => $post ) );
		$list['basic-week-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/basic-week.php', array( 'post' => $post ) );
		$list['basic-day-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/basic-day.php', array( 'post' => $post ) );
		$list['basic-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/basic.php', array( 'post' => $post ) );
		$list['agenda-week-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/agenda-week.php', array( 'post' => $post ) );
		$list['agenda-day-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/agenda-day.php', array( 'post' => $post ) );
		$list['agenda-view'] = QSOT_Templates::maybe_include_template( 'qsot-calendar/views/agenda.php', array( 'post' => $post ) );

		// allow addition of more views if needed
		$list = apply_filters( 'qsot-frontend-calendar-views', $list, $post );

		return $list;
	}

	// grab a list of all the events that meet the supplied criteria
	public static function handle_ajax( $response, $event ) {
		$parents = $final = array();

		// setup the basic posts query to find out events
		$args = array(
			'post_type' => apply_filters( 'qsot-setting', 'qsot-event', 'core_post_type' ),
			'post_status' => array( 'publish', 'protected' ),
			'posts_per_page' => -1,
			'post_parent__not' => 0,
			'suppress_filters' => false,
		);

		// if there was a data range supplied (the days shown on the current calendar page), then use those in the lookup
		if ( isset( $_REQUEST['start'] ) ) $args['start_date_after'] = date( 'Y-m-d H:i:s', strtotime( $_REQUEST['start'] ) );
		if ( isset( $_REQUEST['end'] ) ) $args['start_date_before'] = date( 'Y-m-d H:i:s', strtotime( $_REQUEST['end'] ) );

		// if there are pricing specific filters supplied, add those to the query
		if ( isset( $_REQUEST['priced_like'] ) ) $args['priced_like'] = (int)$_REQUEST['priced_like'];
		if ( isset( $_REQUEST['has_price'] ) ) $args['has_price'] = $_REQUEST['has_price'];

		// can the current user see specially statused events? if so add those also
		if ( apply_filters( 'qsot-show-hidden-events', current_user_can( 'edit_posts' ) || current_user_can( 'box_office' ) ) ) $args['post_status'][] = 'hidden';
		if ( current_user_can( 'read_private_posts' ) || current_user_can( 'box_office' ) ) $args['post_status'][] = 'private';

		// aggregate the list of events to render
		$final = self::get_all_calendar_events( get_posts( $args ) );
		
		return $final;
	}

	// derive the short description from the event and parent event
	public static function _short_description( $event, $parent, $length=800 ) {
		// figure out the text to use for the description
		$text = empty( $event->post_content ) ? $parent->post_content : $event->post_content;

		// strip all tags, and shorten it to $length
		$text = trim( strip_tags( $text ) );
		$text = strlen( $text ) > $length ? substr( $text, 0, $length ) . '...' : $text;

		return $text;
	}

	// aggregate all the data about a list of calendar events
	public static function get_all_calendar_events( $events ) {
		global $wpdb;
		static $show_count = null;
		// figure out if we need to include the count of the availability
		if ( null == $show_count )
			$show_count = 'yes' === apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' );

		// if there were no regular events passed, then bail
		if ( empty( $events ) )
			return array();

		$parents = $indexed_meta = $parent_ids = $event_ids = array();
		// get a list of all parent and event ids from the event list for later usage
		foreach ( $events as $event ) {
			$parent_ids[] = $event->post_parent;
			$event_ids[] = $event->ID;
		}

		// get a list of all parent events, and index the list by the parent id
		if ( ! empty( $parent_ids ) ) {
			$raw_parents = get_posts( array( 'post__in' => $parent_ids, 'post_status' => 'any', 'post_type' => 'any', 'post_per_page' => -1 ) );
			foreach ( $raw_parents as $parent )
				$parents[ $parent->ID ] = $parent;
		}

		// get all the meta for each event
		if ( ! empty( $event_ids ) ) {
			foreach ( $event_ids as $event_id )
				$indexed_meta[ $event_id ] = get_post_meta( $event_id );
		}

		// add the parent post and meta to each child event, if the parent or meta was found
		foreach ( $events as $ind => $event ) {
			if ( isset( $parents[ $event->post_parent ] ) )
				$events[ $ind ]->parent_post = $parents[ $event->post_parent ];
			if ( isset( $indexed_meta[ $event->ID ] ) )
				$events[ $ind ]->meta = $indexed_meta[ $event->ID ];
		}

		$final = array();
		// organize each event's data in a way that the event calendar can properly display it
		foreach ( $events as $event ) {
			$tmp = apply_filters( 'qsot-calendar-event', false, $event );
			if ( $tmp !== false )
				$final[] = $tmp;
		}

		return $final;
	}

	// aggregate a formatted list of a given event's information, for use in the event calendar
	public static function get_calendar_event( $current, $event ) {
		static $show_count = null;
		// figure out if we need to include the count of the availability
		if ( null == $show_count )
			$show_count = 'yes' === apply_filters( 'qsot-get-option-value', 'yes', 'qsot-show-available-quantity' );

		// verify we have all the information about an event that we need. if not, then pass through
		if ( ! is_object( $event ) || ! isset( $event->post_parent, $event->post_title, $event->ID, $event->meta ) ) return $current;

		// get the keys to use for meta dates
		$keys = array(
			'start' => apply_filters( 'qsot-setting', '', 'meta_key.start' ),
			'end' => apply_filters( 'qsot-setting', '', 'meta_key.end' ),
		);

		// gather information about this event's parent, because it will be used in the output of the event data
		$par = isset( $event->parent_post ) && is_object( $event->parent_post ) ? $event->parent_post : get_post( $event->post_parent );

		// get the event area and zoner so we can show availability
		$event_area = apply_filters( 'qsot-event-area-for-event', false, $event->ID );
		$zoner = is_object( $event_area ) && isset( $event_area->area_type ) && is_object( $event_area->area_type ) && ! is_wp_error( $event_area->area_type ) ? $event_area->area_type->get_zoner() : false;

		// start compiling the organized list of information
		$meta = isset( $event->meta ) && is_array( $event->meta ) ? $event->meta : get_post_meta( $event->ID );
		$e = array(
			// use the parent event title as the event title, because it does not have the date, which will already be displayed based on the calendar position. if that is not avaiable, just use the clunky title
			'title' => apply_filters( 'the_title', is_object( $par ) && isset( $par->post_title ) ? $par->post_title : $event->post_title ),
			// short and long description of the event
			'description' => '', //apply_filters( 'the_content', empty( $event->post_content ) ? $par->post_content : $event->post_parent ),
			'short_description' => self::_short_description( $event, $par ),
			// add the start and end dates
			'start' => isset( $meta[ $keys['start'] ] ) ? current( $meta[ $keys['start'] ] ) : '',
			'end' => isset( $meta[ $keys['end'] ] ) ? current( $meta[ $keys['end'] ] ) : '',
			// add the link to the event
			'url' => get_permalink( $event->ID ),
			// add the event image
			'img' => get_the_post_thumbnail( $event->ID ),
			'img_full' => get_the_post_thumbnail( $event->ID, 'full' ),
			// add the status and visibility information
			'status' => $event->post_status,
			'protected' => $event->post_password ? 1 : 0,
			'passed' => false,
		);

		// fill in the availibility
		if ( is_object( $event_area ) && is_object( $zoner ) ) {
			$available = $zoner->get_availability( $event, $event_area );
			$capacity = $event_area->meta['_capacity'];
			$e['avail-words'] = apply_filters( 'qsot-availability-words', '', $capacity, $available );
			// if we need to show the availability, then 
			if ( $show_count ) {
				$e['capacity'] = $capacity;
				$e['available'] = $available;
			}
		}

		// add extra meta that is used internally by the calendar js code
		$e['_start'] = strtotime( $e['start'] );
		$e['_end'] = strtotime( $e['end'] );

		// double check that this event can still have tickets solve for it. there is a setting that can stop sales X amount of time before a show start. this handles that in the calendar interface
		if ( ! apply_filters( 'qsot-can-sell-tickets-to-event', false, $event->ID ) ) {
			$e['avail-words'] = __( 'Ended', 'opentickets-community-edition' );
			$e['passed'] = true;
		}

		// if we are in the admin, we need the id for the admin calendar interface, because it has extra js that uses the id
		if ( is_admin() ) {
			$e['id'] = $event->ID;
		}

		return $e;
	}

	// actually save the calendar settings
	public static function save_page_calendar_settings( $post_id, $post ) {
		//// today = initialize the display on today's date
		//// first = initialize on the day of the first upcoming event
		//// manual = initialize the calendar on a specific date
		// save the start method.
		if ( isset( $_POST['_calendar_start_method'] ) )
			update_post_meta( $post_id, '_calendar_start_method', $_POST['_calendar_start_method'] );

		// save the manual value entered by the admin user
		if ( isset( $_POST['_calendar_start_manual'] ) )
			update_post_meta( $post_id, '_calendar_start_manual', $_POST['_calendar_start_manual'] );
	}

	// hide the metabox by default if the qsot-calendar.php template is not the selected template
	public static function mb_calendar_settings_classes($classes) {
		$template = get_page_template_slug( get_queried_object_id() );
		if ( $template == 'qsot-calendar.php' )
			$classes[] = 'hide-if-js';
		return $classes;
	}

	// get a list of the valid calendar modes
	protected static function _get_modes() {
		// generate a list of valid options for the calendar starting date modes
		return apply_filters( 'qsot-calendar-modes', array(
			'today' => __( 'Today', 'opentickets-community-edition' ), // starts the calendar at today, when the calendar page is loaded
			'first' => __( 'Date of next Event', 'opentickets-community-edition' ), // starts the calendar at the date of the first event, when calendar is loaded
			'manual' => __( 'Manually entered date', 'opentickets-community-edition' ), // uses a manually entered date as the start date of the calendar when it is loaded
		) );
	}

	// actually draw the settings metabox for the calendar page
	public static function mb_calendar_settings($post, $mb) {
		// generate a list of valid options for the calendar starting date modes
		$valid = self::_get_modes();

		// get the current settings
		$method = get_post_meta( $post->ID, '_calendar_start_method', true );
		$date = get_post_meta( $post->ID, '_calendar_start_manual', true );

		// default the settings to 'today' (above)
		$method = isset( $valid[ $method ] ) ? $method : 'today';

		// draw the form
		?>
			<p><strong><?php _e('Starting Date Method','opentickets-community-edition') ?></strong></p>

			<p>
				<?php foreach ( $valid as $meth => $label ): ?>
					<input type="radio" name="_calendar_start_method" class="qsot-cal-meth" id="qsot-cal-meth-<?php echo esc_attr( $meth ) ?>"
						value="<?php echo esc_attr( $meth ) ?>" <?php checked( $method, $meth ) ?> />
					<label class="screen-reader-text" for="qsot-cal-meth-<?php echo esc_attr( $meth ) ?>"><?php echo force_balance_tags( $label ) ?></label>
					<span class="cb-display"><?php echo force_balance_tags( $label ) ?></span></br>
				<?php endforeach; ?>
			</p>

			<div class="hide-if-js extra-box" rel="extra-manual">
				<p><strong><?php _e( 'Manually entered date', 'opentickets-community-edition' ) ?></strong></p>
				<label class="screen-reader-text" for="qsot-cal-start-manual"><?php _e( 'Manually entered date', 'opentickets-community-edition' ) ?></label>
				<input size="11" type="text" class="use-datepicker" id="qsot-cal-start-manual-display" name="_calendar_start_manual_display"
						value="<?php echo esc_attr( date( __( 'm-d-Y', 'opentickets-community-edition' ), strtotime( $date ) ) ) ?>"
						real="#qsot-cal-start-manual" scope="[rel='extra-manual']" frmt="<?php echo esc_attr( __( 'mm-dd-yy', 'opentickets-community-edition' ) ) ?>" />
				<input type="hidden" id="qsot-cal-start-manual" name="_calendar_start_manual" value="<?php echo esc_attr( $date ) ?>" />
			</div>

			<?php
				// allow plugins to add to this
				do_action( 'qsot-calendar-settings-metabox-extra', $post, $mb );
			?>
		<?php
	}

	// figure out the calendar start date
	protected static function _get_calendar_start_date($post) {
		// only process this for posts we can find
		if ( ! is_object( $post ) )
			$post = get_post();
		if ( ! is_object( $post ) || is_wp_error( $post ) )
			return;

		// generate a list of valid options for the calendar starting date modes
		$valid = self::_get_modes();

		// get the current settings
		$method = get_post_meta( $post->ID, '_calendar_start_method', true );
		$date = get_post_meta( $post->ID, '_calendar_start_manual', true );

		// default the settings to 'today' (above)
		$method = isset( $valid[ $method ] ) ? $method : 'today';

		switch ( $method ) {
			// date of the first upcoming event
			case 'first':
				$out = self::_next_event_date();
			break;

			// a manually entered date
			case 'manual':
				$out = strtotime( $date ) < strtotime( 'today' ) ? current_time( 'mysql' ) : $date;
			break;

			default:
			// today's date
			case 'today':
				$out = date( 'Y-m-d' );
			break;
		}

		return $out;
	}

	// determine the date of the next event, after today
	protected static function _next_event_date() {
		$start_mk = apply_filters( 'qsot-setting', '', 'meta_key.start' );
		$post_type = apply_filters( 'qsot-setting', 'qsot-event', 'core_post_type' );

		// get the next event id 
		$posts = get_posts(array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'post_parent__not' => 0,
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => $start_mk,
					'value' => current_time('mysql'),
					'type' => 'DATETIME',
					'compare' => '>=',
				),
			),
			'meta_key' => $start_mk,
			'orderby' => 'meta_value_date',
			'order' => 'asc',
			'fields' => 'ids',
			'suppress_filters' => false,
		));

		// get the id if it exists
		$post_id = ( ! empty( $posts ) ) ? current( $posts ) : 0;

		// fetch the start date of the found event, and default it to today if it does not exist or has no start date
		$start = get_post_meta( $post_id, $start_mk, true );
		$start = empty( $start ) || $start == '0000-00-00 00:00:00' ? current_time( 'mysql' ) : $start;

		return $start;
	}

	// during activation, we might need to create the initial event calendar page
	public static function create_calendar_page() {
		// check if there is a page already set in our settings
		$page_id = intval( get_option( 'qsot_calendar_page_id', 0 ) );

		// if there is a page_id already, then bail now, cause it already exists
		if ( $page_id > 0 )
			return;

		// basic settings of the page itself
		$data = array(
			'post_title' => __( 'Event Calendar', 'opentickets-community-edition' ),
			'post_name' => 'calendar',
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => 1,
			'post_type' => 'page',
		);

		// insert the post
		$page_id = wp_insert_post( $data );

		// if the post was added successfully, then update the meta and the site settings that says not to recreate this again
		if ( is_numeric( $page_id ) && ! empty( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'qsot-calendar.php' );
			update_post_meta( $page_id, '_calendar_start_method', 'today' );
			update_option( 'qsot_calendar_page_id', $page_id );
		}
	}
	// setup the options that are available to control tickets. reachable at WPAdmin -> OpenTickets (menu) -> Settings (menu) -> Frontend (tab) -> Tickets (heading)
	protected static function _setup_admin_options() {
		$class = apply_filters( 'qsot-options-class-name', false );
		if ( empty( $class ) )
			return;

		$options = call_user_func( array( $class, 'instance' ) );
		// setup the default values
		$options->def( 'qsot-calendar-show-image-on-month', 'yes' );

		// the 'Tickets' heading on the Frontend tab
		$options->add( array(
			'order' => 700,
			'type' => 'title',
			'title' => __( 'Calendar Settings', 'opentickets-community-edition' ),
			'id' => 'heading-frontend-calendar-1',
			'page' => 'frontend',
			'section' => 'calendar',
		) );

		// whether or not to show the event image on the calendar 'month' view
		$options->add( array(
			'order' => 729,
			'id' => 'qsot-calendar-show-image-on-month',
			'type' => 'checkbox',
			'title' => __( 'Month View Image', 'opentickets-community-edition' ),
			'desc' => __( 'Yes. Show the event featured image on the default calendar view (month view).', 'opentickets-community-edition' ),
			'default' => 'no',
			'page' => 'frontend',
			'section' => 'calendar',
		) );

		// end the 'Tickets' section on the page
		$options->add( array(
			'order' => 799,
			'type' => 'sectionend',
			'id' => 'heading-frontend-calendar-1',
			'page' => 'frontend',
			'section' => 'calendar',
		) );
	}
}

// security
if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) )
	qsot_frontend_calendar::pre_init();
