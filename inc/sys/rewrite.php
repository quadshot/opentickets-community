<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );

// handles the basic rewrite rule changes and routing required for our plugin, and really any plugin that wants to use this
class qsot_rewriter {
	protected static $rules = array();
	protected static $defaults = array(
		'name' => '%SLUG%',
		'query_vars' => array( '%SLUG%' ),
		'rules' => array( '%SLUG%/(.*)?' => '%SLUG%=' ),
		'func' => array( __CLASS__, 'generic_intercept' ),
		'epmask' => EP_PAGES,
	);

	public static function pre_init() {
		// allow plugins and classes to add special rules to the rewrites
		add_action( 'qsot-rewriter-add', array( __CLASS__, 'add_rule' ), 10, 2 );

		// handle incoming urls that are for ticket functions
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ), 10 );
		add_action( 'wp', array( __CLASS__, 'intercept_ticket_request' ), 11 );
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'rewrite_rules_array' ), PHP_INT_MAX );

		// during activation, we need to flush the rewrite rules, because there are a few classes that register spcial rules
		add_action( 'qsot-activate', array( __CLASS__, 'on_activate' ), 1000 );
	}

	// ona ctivation, flush the rewrite rules so that our cusotm ones can be calculated on page load
	public static function on_activate() {
		flush_rewrite_rules();
	}

	// actually add the rules requested by plugins and classes to our list of rules to create/parse/intercept
	public static function add_rule( $slug, $args='' ) {
		// sanitize the args passed
		$args = self::_sane_args( wp_parse_args( $args, self::$defaults ), $slug );
		
		// add the rule
		self::$rules[ $slug ] = $args;
	}

	// add the query args for every rule we have registered
	public static function query_vars( $vars ) {
		$new_items = array();

		// for every rule we have registered
		foreach ( self::$rules as $slug => $args ) {
			// add each of it's query vars to our new query vars list
			foreach ( $args['query_vars'] as $ind => $var ) {
				$new_items[] = $var;
			}
		}

		// return a unique list of params to accept
		return array_unique( array_merge( $vars, $new_items ) );
	}

	// intercept requests that are using any of our registered query vars
	public static function intercept_ticket_request( &$wp ) {
		// aggregate a list of othe registered query_vars
		$all_vars = array();
		foreach ( self::$rules as $slug => $args )
			foreach ( $args['query_vars'] as $var ) $all_vars[ $var ] = $slug;

		// determine which of the registered ones were matched in the query
		$exists = array_intersect( array_keys( $all_vars ), array_keys( $wp->query_vars ) );
		$query_vars = $wp->query_vars;

		// for each matched registered query var, pop an action for it to be intercepted elsewhere if required
		foreach ( $exists as $qvar ) {
			$value = $wp->query_vars[ $qvar ];
			$slug = $all_vars[ $qvar ];
			$all_data = array();
			if ( isset( self::$rules[ $slug ], self::$rules[ $slug ]['query_vars'] ) )
				foreach ( self::$rules[ $slug ]['query_vars'] as $qv )
					$all_data[ $qv ] = isset( $query_vars[ $qv ] ) ? $query_vars[ $qv ] : '';

			if ( isset( self::$rules[ $all_vars[ $qvar ] ], self::$rules[ $all_vars[ $qvar ] ]['func'] ) && is_callable( self::$rules[ $all_vars[ $qvar ] ]['func'] ) ) {
				call_user_func_array( self::$rules[ $all_vars[ $qvar ] ]['func'], array( $value, $qvar, $all_data, $query_vars ) );
			} else {
				do_action( 'qsot-rewriter-intercepted-' . $qvar, $value, $qvar, $all_data, $query_vars );
			}
		}
	}

	public static function generic_intercept( $value, $qvar, $all_data, $query_vars ) {
		do_action( 'qsot-rewriter-intercepted-' . $qvar, $value, $qvar, $all_data, $query_vars );
	}

	// actually add the rules to the list of rewrite_rules
	public static function rewrite_rules_array( $current ) {
		global $wp_rewrite;

		// aggregate a grouped list of all the rules, and a lookup that we can use for EP_MASK
		$all_rules = $masks = array();
		foreach ( self::$rules as $slug => $args ) {
			$masks[ $slug ] = $args['epmask']; // EP_MASK lookup
			$all_rules[ $slug ] = array(); // grouped list of rules for this slug
			foreach ( $args['rules'] as $find => $replace ) $all_rules[ $slug ][ $find ] = $replace;
		}
		// allow plugins and classes to modify if need by
		$all_rules = apply_filters( 'qsot-rewriter-rules', $all_rules );

		$extra = array();

		// cycle through our groups
		foreach ( $all_rules as $slug => $rules ) {
			$cnt = 0;
			// cycle through this group's rules and generate then add them
			foreach ( $rules as $find => $replace ) {
				$cnt++;
				// create a unique rule key
				$key = $slug . $cnt;

				// use the key to make a rule based on the patterns we have in our settings
				$wp_rewrite->add_permastruct( $key, '%' . $key . '%', false, $masks[ $slug ] );
				$wp_rewrite->add_rewrite_tag( '%' . $key . '%', $find, $replace );
				$uri_rules = $wp_rewrite->generate_rewrite_rules( '%' . $key . '%', $masks[ $slug ] );

				// add the rule to our list of new rules
				$extra = array_merge( $extra, $uri_rules );
			}
		}

		return $extra + $current;
	}

	// santize the entire argument list
	protected static function _sane_args( $args, $slug ) {
		// create a list of search replace values, and allow external plugins to manipulate it
		$query = apply_filters( 'qsot-rewriter-sane-args-query', array( '%SLUG%' => $slug ), $args, $slug );

		// perform the search replace
		$args = self::_sane_replace( $args, $query );

		// perform our basic argument sanitization
		$args['name'] = trim( $args['name'] );
		$args['name'] = strlen( $args['name'] ) ? $args['name'] : $slug;

		$args['query_vars'] = array_filter( is_array( $args['query_vars'] ) ? $args['query_vars'] : array() );
		$args['query_vars'] = ! empty( $args['query_vars'] ) ? $args['query_vars'] : array( $slug );

		$args['rules'] = array_filter( is_array( $args['rules'] ) ? $args['rules'] : array() );
		$args['rules'] = ! empty( $args['rules'] ) ? $args['rules'] : self::_sane_replace( self::$defaults['rules'], $query );

		$args['func'] = is_callable( $args['func'] ) ? $args['func'] : self::$defaults['func'];

		$args['epmask'] = is_int( $args['epmask'] ) ? $args['epmask'] : self::$defaults['epmask'];

		// allow plugins to modify the results
		$args = apply_filters( 'qsot-rewriter-sane-args', $args, $args, $slug, $query );

		return $args;
	}

	// make any string replacements we need to, based on our map $query
	protected static function _sane_replace( $args, $query, $depth=0 ) {
		// dont go deeper than 5 levels
		if ( $depth > 5 ) return $args;

		// separate the search text from the replace text
		$find = array_keys( $query );
		$replace = array_values( $query );
		$out = array();

		// recursively cycle through all args, and make any replacements we can
		foreach ( $args as $k => $v ) {
			if ( is_callable( $v ) ) { // if the argument is a callback, then just pipe it through without any modification
				$out[ $k ] = $v;
			} else if ( is_array( $v ) ) { // arrays create recursion
				$new_k = str_replace( $find, $replace, $k );
				$out[ $new_k ] = self::_sane_replace( $args[ $k ], $query, $depth + 1 );
			} else if ( is_scalar( $v ) ) { // scalar values can have replaces done on them
				$new_k = str_replace( $find, $replace, $k );
				$out[ $new_k ] = str_replace( $find, $replace, $v );
			} else { // everything else, assume it is already sane
				$out[ $k ] = $v;
			}
		}

		return $out;
	}
}

if ( defined( 'ABSPATH' ) && function_exists( 'add_action' ) ) qsot_rewriter::pre_init();
