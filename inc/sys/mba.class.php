<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
// if accessed directly, redirect to home
/**
Plugin Name: Lou Mediabox Anywhere
Plugin URI: http://lou.quadshot.com/
Description: A utility plugin that allows other plugins the ability to launch a mediabox anywhere in the admin, for selecting an attachment.
Version: 0.1
Author: Loushou
Author URI: http://lou.quadshot.com/
License: GPL2
*/
/*
Copyright 2012 Loushou (email: lou@quadshot.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('lou_media_box_anywhere')):
	/**
	 * Mediabox Anywhere
	 * Allows the usage of the WordPress built in media box, anywhere in the admin. Traditionally, using the media box requires
	 * that the wysiwyg is present. This works around that requirement, and allows the programmer to pop the media box anywhere
	 * inside the admin.
	 */
	class lou_media_box_anywhere {
		protected static $_hooks = array();
		protected static $_tokens = array();
		//protected static $_fields = array();
		protected static $_base_url;
		protected static $_js_url;
		protected static $version = '0.1-lou';
		protected static $o = array();

		// setup the basic actions that are needed to get this plugin going. sets up hooks that occur later in the wordpress loading process, as well as
		// registers our needed js, adding our special logic hook, and setting a default logic set that should cover most cases.
		public static function pre_init() {
			// first thing, load all the options, and share them with all other parts of the plugin
			$settings_class_name = apply_filters('qsot-settings-class-name', '');
			self::$o = call_user_func_array(array($settings_class_name, "instance"), array());

			// calculate and store the urls to our basic resources for this plugin
			self::$_base_url = self::$o->core_url;
			self::$_js_url = self::$_base_url.'assets/js/admin/mba/';

			// register this plugin's main hooks first in queue. doing this will allow our code to run before most other plugins.
			// since they may rely on our code, that is a must.
			add_action('admin_init', array(__CLASS__, 'a_admin_init'), 1);

			// hook that allows other plugins to register their own logic for this plugin (see the a_register_mediabox_logic function for more info)
			add_action('mba-register-mediabox-logic', array(__CLASS__, 'a_register_mediabox_logic'), 10, 2);
			// register the basic button logic that will be used by most people
			do_action('mba-register-mediabox-logic', 'basic');

			// the proper way to register admin scripts
			add_action('init', array(__CLASS__, 'register_assets'), 10);
		}

		public static function register_assets() {
			// registers the script that launches the media box on click of the buttons this plugin creates
			wp_register_script('lou-mba-launcher', self::$_js_url.'launcher.js', array('jquery', 'media-upload', 'thickbox'), self::$version, false);
		}

		// register all actions and filters that should only be available for use if we are in the admin section of the site.
		public static function a_admin_init() {
			// the hook used to draw the actual buttons or get the code for the buttons
			add_action('mba-mediabox-button', array(__CLASS__, 'a_create_mediabox_button'), 10, 2);
			add_filter('get-mba-mediabox-button', array(__CLASS__, 'f_get_create_mediabox_button'), 10, 3);

			// almost a waste of a funciton, but for some reason in FF, the '.button' class that is applied to anchor tags has weird
			// vertical spacing and padding. this solves that problem, and mkaes the button not overlap text above or below
			add_action('admin_head', array(__CLASS__, 'a_fix_buttons'));

			// loads only when inside the media box. this will create a work around for form submission in the mediabox so that it does not lose 
			// track of the fact that we clicked a button made by this plugin, whe performing 'searches' or 'filtering' inside the mediabox
			add_action('load-media-upload.php', array(__CLASS__, 'a_media_upload_hook'));

			// changes the buttons at the bottom of each individual item in the media box.
			add_filter('attachment_fields_to_edit', array(__CLASS__, 'f_attachment_fields_to_edit'), PHP_INT_MAX, 2);
			add_filter('attachment_fields_to_save', array(__CLASS__, 'f_attachment_fields_to_save'), PHP_INT_MAX, 2);
			add_action('add_attachment', array(__CLASS__, 'a_add_attachment'), 10, 1);
			add_action('edit_attachment', array(__CLASS__, 'a_edit_attachment'), 10, 1);

			// overtakes the method that happens once the user clicks the 'use this image' or 'insert into post' buttons
			add_filter('media_send_to_editor', array(__CLASS__, 'a_media_send_to_editor'), 1000, 3);

			// makes sure that the thickbox js plugin is loaded, and that all it's style and special sauce is enabled
			add_thickbox();

			// queues the script that launches the media box on click of the buttons this plugin creates
			wp_enqueue_script('lou-mba-launcher');
		}

		// allows more finely tuned control over what happens before attachments are saved inside a mediabox spawned by a button made with this plugin,
		// after attachments are saved inside a mediabox spawned by a button made with this plugin, and what fields are displayed inside of the mediabox
		// spawned by a button made with this plugin.
		// @param string a unique name for this logic set to be known by
		// @param string/array wp_args that allow control over the before and after save actions as well as the action to draw each attachments fields in the mediabox
		public static function a_register_mediabox_logic($slug, $settings=array()) {
			// normalize the slug to maintain a uniformed key structure accross the plugin
			$slug = sanitize_title_with_dashes($slug);
			if (empty($slug)) return false;

			// the default settings for this grouping of logic
			$defaults = array(
				'field_name' => 'send-'.$slug, // the name of the field that is used to track when our 'use this image' button is hit
				'tracking_token_name' => $slug.'-tracking', // the url param that is intercepted by the mediabox to determine that this logic is to be used
				'button-attributes' => false, // callback used to midfy the attributes of each button this plugin creates
				'before-attachment-save' => false, // the callback function that is used before saving an attachment
				'after-attachment-save' => false, // the callback function that is used after saving an attachment
				'fields-to-edit' => false, // the callback that is used when drawing the fields (and buttons) for each individual image in the mediabox
				'select-fields' => false, // callback used when constructing the message to return to the parent window upon selecting an attachment
			);

			// register the hook in our hook list, for later lookup purposes
			self::$_hooks[$slug] = wp_parse_args((array)$settings, $defaults);
			// update the reverse lookup based on token. useful when loading the mediabox and trying to determine what logic to use inside it
			self::$_tokens[self::$_hooks[$slug]['tracking_token_name']] = $slug;
			// update the reverse lookup based on field. used when selecting an image to use inside the mediabox, so we know how to handle it
			//self::$_fields[self::$_hooks[$slug]['field_name']] = $slug;

			// setup the callbacks for this logic set, if they were defined
			if (!empty(self::$_hooks[$slug]['button-attributes'])) {
				add_action('lou-mba-button-attributes-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['button-attributes'], 10, 2);
			}
			if (!empty(self::$_hooks[$slug]['select-fields'])) {
				add_action('media_send_to_editor-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['select-fields'], 10, 4);
			}
			if (!empty(self::$_hooks[$slug]['fields-to-edit'])) {
				add_action('attachment_fields_to_edit-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['fields-to-edit'], 10, 2);
			}
			if (!empty(self::$_hooks[$slug]['before-attachment-save'])) {
				add_action('attachment_fields_to_save-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['before-attachment-save'], 10, 2);
			}
			if (!empty(self::$_hooks[$slug]['after-attachment-save'])) {
				add_action('edit_attachment-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['after-attachment-save'], 10, 1);
				add_action('add_attachment-'.self::$_hooks[$slug]['tracking_token_name'], self::$_hooks[$slug]['after-attachment-save'], 10, 1);
			}

			return $slug;
		}
		
		// determine the tokens that were matched on this request. usually this will be used inside the mediabox while browning from
		// tab to tab or while submitting forms or uploading images.
		protected static function _matched_tokens($request=false) {
			$request = $request === false ? $_REQUEST : (array)$request;
			// only calculate this once, since it will not change during a single request
			static $matched = false;

			// if it has not been calculated during this request, do so now
			if ($matched === false) {
				// cross reference the subbmitted data-keys with the known token that are registered and return the list.
				$matched = array_intersect(array_keys(self::$_tokens), array_keys($request));
			}

			// return the cached value
			return $matched;
		}
		
		// once an attachment is saved there is a pre-processing step that happens before the attachment is actually saved. this hooks into
		// that, allowing differnent logic sets registered with this plugin (via the 'mba-register-mediabox-logic' action) to do preprocessing
		// of attachment info that is submitted inside the mediabox using that logic set. thus if you registered a logic set called 'bubba',
		// then created a mediabox button using that logic set, then clicked that button in the admin, then changed information about an
		// attachment inside that mediabox, and then hit save inside the mediabox, then this code would run.
		// @param array list of values for this attachment that will be put into the database
		// @param array list of the submitted data from the form inside the mediabox that is used to update the attachment information
		public static function f_attachment_fields_to_save($post, $attachment) {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			// if the request is not, then passthru
			if (!is_array($shared) || empty($shared)) return $post;

			// if it is then run the associated filters setup in our registration process above, foreach logic set detected
			// param 'before-attachment-save'
			foreach ($shared as $token) $post = apply_filters('attachment_fields_to_save-'.$token, $post, $attachment);

			return $post;
		}

		// sets up the use of the 'after-attachment-save' functions registered in our logic registration process, for NEW attachments
		// @param int the id of the attachment that was saved
		public static function a_add_attachment($attachment_id) {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			// if the request is not, then passthru
			if (!is_array($shared) || empty($shared)) return;

			// if it is, then run the associated actions setup in our registration process above, foreach logic set detected
			// param 'after-attachment-save'
			foreach ($shared as $token) do_action('add_attachment-'.$token, $attachment_id);
		}

		// sets up the use of the 'after-attachment-save' functions registered in our logic registration process, for EXISTING attachments
		// @param int the id of the attachment that was saved
		public static function a_edit_attachment($attachment_id) {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			if (!is_array($shared) || empty($shared)) return;

			// if it is, then run the associated actions setup in our registration process above, foreach logic set detected
			// param 'after-attachment-save'
			foreach ($shared as $token) do_action('edit_attachment-'.$token, $attachment_id);
		}

		// wrapper function to get the code for the buttons rather than printing them out
		// @param mixed the current value of the mediabox code (disgarded)
		// @param string/array list of params that will be passed to the function that draws the buttons
		// @param string the name of the logic set to use
		public static function f_get_create_mediabox_button($current, $args=array(), $slug='basic') {
			// force the setting to return the buttons instead of echoing them
			$args['echo'] = false;
			return self::a_create_mediabox_button($slug, $args);
		}

		// function that actually draws the mba buttons. accepts params that can be customized on each iteration. 
		// @param array/string wp_args that are used to determine the look, feel, and functionality of the buttons
		// @param string the unique name of the logic set to use when making the buttons
		public static function a_create_mediabox_button($args=array(), $slug='basic') {
			// get the tracking token settings for the plugin requesting the information
			$hook_settings = self::_get_mediabox_hook_settings($slug);
			if (empty($hook_settings)) return false;

			// default settings
			$defaults = array(
				'id-field' => '', /* jquery selector */ // the field in which the image id will be stored
				'src-field' => '', /* jquery selector */ // the field in which the text of the url for the image will be stored
				'title-field' => '', // container to store the text of the image title in
				'alt-field' => '', // container to store the text of the image alt in
				'preview-container' => '', /* jquery selector */ // the container to create the preview image inside
				'relative' => 'parent', // tells the javascript to look inside a specific element for this button. one way to get multiple buttons on one page
				// valid 'relative' values are 'parent' or any jQuery selector. if 'parent' is specified, then $(button).parent(selector) is used with the next option
				'parent' => ':eq(0)', /* jquery selector */ // used inside $(button).parent(selector) to get the parent element that holds all fields this button should fill
				'preview-size' => 'thumbnail', // size of the preview image
				'post-id' => 0, // the id of the parent post that this media box should be linked to
				'upload-button-text' => 'Upload / Select Image', // the text of the upload image button (the one the pops the mediabox)
				'upload-button-classes' => 'button', // the classes of said button (class="..." tag attribute)
				'remove-button-text' => 'Remove Image', // the text of the remove button (deselects the image and clears all related fields)
				'remove-button-classes' => 'button', // the classes of remove button
				'default-tab' => 'type', // default tab to show when the media box pops up
				'base' => admin_url('media-upload.php'), // url for the media box. can be customized
				'extra-attr' => '', // additional tag attributes to add to the button (wp_args string/array)
				'echo' => true, // whether or not to print the buttons (false to just return an array of buttons)
			);
			$args = wp_parse_args((array)$args, $defaults);

			// normalize the preview size and classes fields because they accept arrays
			$args['preview-size'] = is_array($args['preview-size'])
				? implode(',', $args['preview-size'])
				: (empty($args['preview-size']) ? 'thumbnail' : $args['preview-size']);
			$args['upload-button-classes'] = is_array($args['upload-button-classes']) || is_object($args['upload-button-classes'])
				? implode(' ', (array)$args['upload-button-classes'])
				: (string)$args['upload-button-classes'];
			$args['remove-button-classes'] = is_array($args['remove-button-classes']) || is_object($args['remove-button-classes'])
				? implode(' ', (array)$args['remove-button-classes'])
				: (string)$args['remove-button-classes'];

			// create the url that will be used to pop the media boz (works with thickbox to make the mediabox with our tracking token)
			$url = add_query_arg(
				array(
					'post_id' => $args['post-id'],
					'type' => 'image',
					'tab' => $args['default-tab'],
					$hook_settings['tracking_token_name'] => 1,
					'_preview_size' => $args['preview-size'],
					'TB_iframe' => 1
				),
				$args['base']
			);

			$attrs_raw = array(
				'id-field' => $args['id-field'],
				'src-field' => $args['src-field'],
				'title-field' => $args['title-field'],
				'alt-field' => $args['alt-field'],
				'preview' => $args['preview-container'],
				'relative' => $args['relative'],
				'parent' => $args['parent'],
			);

			// calculate the extra tag attributes for the a tag representing the button
			$extraAttr = '';
			if (is_string($args['extra-attr']) && strlen($args['extra-attr']) >= 1) // if we have a param string (wp_args) then convert it to an array
				parse_str($args['extra-attr'], $args['extra-attr']);

			if (is_array($args['extra-attr'])) $attrs_raw = array_merge($attrs_raw, $args['extra-attr']);
			$attrs_raw = apply_filters('lou-mba-button-attributes-'.$slug, $attrs_raw, $args);

			$attrs = array();
			foreach ($attrs_raw as $k => $v) $attrs[] = $k.'="'.esc_attr($v).'"';
			$attrs = implode(' ', $attrs);

			// draw the two buttons. the upload button and the remove button
			ob_start(); // upload button start
			?>
				<a <?php echo $attrs ?> href="<?php echo esc_attr($url) ?>" class="<?php echo ($args['upload-button-classes']) ?> lou-mba-image-selector button-fix"><?php echo
					$args['upload-button-text']
				?></a>
			<?php
			$select_btn = ob_get_contents();
			ob_end_clean(); // upload button end

			ob_start(); // remove button start
			?>
				<a <?php echo $attrs ?> href="#" class="<?php echo ($args['remove-button-classes']) ?> lou-mba-image-deselector button-fix"><?php echo
					$args['remove-button-text']
				?></a>
			<?php
			$deselect_btn = ob_get_contents();
			ob_end_clean(); // remove button end

			// if we are printing them out now, do it
			if ($args['echo']) echo $select_btn.$deselect_btn;
			
			// return an array of buttons
			return array('select' => $select_btn, 'deselect' => $deselect_btn);
		}

		// fetches the plugin tracking token settings by normalizing the slug passed in and comparing it to the list of logic sets that have 
		// been registered above
		// @param string the unique name of a logic set that has been registered
		protected static function _get_mediabox_hook_settings($slug='basic') {
			// normalize the name as always
			$slug = sanitize_title_with_dashes($slug);
			// if there is no name then return nothing
			if (empty($slug)) return false;
			// if it is not a valid logi set, then return nothing
			if (!self::_is_mediabox_hook($slug)) return false;
			// otherwise return the settings for this logic set
			return self::$_hooks[$slug];
		}

		// checks if the provided slug is that of a registered logic set inside this plugin
		// @param string the unique name of a logic set that has been registered
		protected static function _is_mediabox_hook($slug) {
			// normalize the name as always
			$slug = sanitize_title_with_dashes($slug);
			// if there is no name then return nothing
			if (empty($slug)) return false;
			// return whether the name is that of a registered logic set or not
			return in_array($slug, array_keys(self::$_hooks));
		}

		// css hack to make a.button tags not overlap text above and below
		public static function a_fix_buttons() {
			?><style>html body a.button-fix { line-height:25px; }</style><?php
		}

		// this is triggered inside the mediabox when it loads up. the goal here is to add in a javascript that will overtake any 
		// forms inside the mediabox and add in the tracking token and the preview size, so that if the user does something like
		// searching or filtering (which use a form) then the mediabox wil retain the fact that it was launched by this plugin
		public static function a_media_upload_hook() {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			// if not, do nothing
			if (!is_array($shared) || empty($shared)) return;

			// since we know that this mediabox was launched using this plugin, we need to launch the javascrpt that will take over the 
			// forms. that javascript is actually a php script that accepts two params, on for the tracking token and the other for the 
			// preview size. it creates javascript code that will use those params to create hidden fields in all forms inside the mediabox

			// since you can only click one button to open the mediabox, then it is only possible to use one logic set for the mediabox,
			// thus we only need to pass one token inside the mediabox, so get the first item off the matched toekns array (which should
			// only contain one entry anyways)
			$token = array_shift($shared);

			// normalize the preview size param so that if it is specified, we will pass it on, but if not we will always use the 
			// builtin wordpress default preview size of 'thumbnail' as a backup
			$size = isset($_REQUEST['_preview_size']) && !empty($_REQUEST['_preview_size']) ? $_REQUEST['_preview_size'] : 'thumbnail';

			//$_SERVER['REQUEST_URI'] = add_query_arg(array(self::$_hooks[self::$_tokens[$token]]['tracking_token_name'] => 1, 'size' => $size));

			// construct the url that points to the javascript used to overtake all forms inside the mediabox
			$js_url = add_query_arg(
				array(
					'field' => self::$_hooks[self::$_tokens[$token]]['tracking_token_name'],
					'size' => $size,
				),
				self::$_js_url.'upload.box.fix.php'
			);
			// queue up that javascript so that it runs inside the mediabox
			wp_enqueue_script('mba-media-upload-box-fix', $js_url, array('jquery'), self::$version, true);

			// since the uploader is flash based, we need to do some special workaround so that when uploading a new file, we do not
			// lose track of the fact that the mediabox was launched by this plugin and that it's info needs to be processed differently
			add_action('admin_print_footer_scripts', array(__CLASS__, 'a_overtake_flash_uploader'), PHP_INT_MAX);
		}

		// replaces the 'Insert into Post' button, because it does not much make sense, when this is 90% of the time going to have
		// use outside of the wysiwyg. changes the text to something more meaningful, 'Use this image'. also keeps the delete
		// link, because we dont want to remove that feature by adding this one.
		// @param array the current list of fields that will be printed foreach item in the mediabox
		// @param object the object representation of the current item in the mediabox
		public static function f_attachment_fields_to_edit($fields, $post) {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin, the traditional way
			$shared = self::_matched_tokens();

			// this is tricky. basically, after you upload a new image, the mediabox creates a placeholder element inside the upload
			// tab. THEN, AFTER the uploader is done, the mediabox js uses this placeholder to launch another ajax request that then
			// populates the data about the newly uploaded image, and places it inside the placeholder it created. this image data
			// includes the new 'use this image' button that we are adding with this function. THAT request is not overtakeable (or
			// at least i have not found a way without a core hack), so when that happens you must get creative. my solution is to
			// detect if the referer url for the ajax request came from inside the mediabox, and if that referer url has the info we
			// need in order to proceed.
			$referer = explode('?', $_SERVER['HTTP_REFERER']);
			array_shift($referer);
			$referer_params = implode('?', $referer);
			parse_str($referer_params, $refparams);
			$shared2 = self::_matched_tokens($refparams);

			// if we did not find the tracking token in either method above, then just passthru. otherwise, use whatever array of
			// matched tokens we actually have
			if (!is_array($shared) || empty($shared)) {
				if (!is_array($shared2) || empty($shared2)) return $fields;
				$shared = $shared2;
			}

			// since you can only click one button to open the mediabox, then it is only possible to use one logic set for the mediabox,
			// thus we only need to pass one token inside the mediabox, so get the first item off the matched toekns array (which should
			// only contain one entry anyways)
			$token = array_shift($shared);

			// gather some data that is used when creating the form elements. 
			$attachment_id = $post->ID;
			$filename = basename( $post->guid );

			// maintain the format for the fields array that is already in use
			if (!isset($fields['buttons'])) $fields['buttons'] = array();

			// creates the tracking field and new button with meaningful text
			$send_marker = '<input type="hidden" name="'.self::$_hooks[self::$_tokens[$token]]['field_name'].'['.$attachment_id.']" value="1"/>';
			$use_button = '<input type="submit" class="button" name="send['.$attachment_id.']" value="'.__('Use this image','opentickets-community-edition').'"/>';

			// the is pretty much a copy of part of the get_media_item() function inside /wp-admin/includes/media.php . this generates the 
			// delete button in all the states that are appropriate to it. it is also the reason we needed to calculate the $attachment_id 
			// and $fielname above, since we want it be as close a copy as possible, so it is easy to update if needed.
			if (current_user_can('delete_post', $attachment_id)) {
				if ( !EMPTY_TRASH_DAYS ) {
					$delete = '<a href="'.wp_nonce_url('post.php?action=delete&amp;post='.$attachment_id, 'delete-attachment_'.$attachment_id)
						.'" id="del['.$attachment_id.']" class="delete">'.__('Delete Permanently','opentickets-community-edition').'</a>';
				} elseif ( !MEDIA_TRASH ) {
					$delete = 
						'<a href="#" class="del-link" onclick="document.getElementById(\'del_attachment_'.$attachment_id.'\').style.display=\'block\';return false;">'
							.__('Delete','opentickets-community-edition')
						.'</a>'
						.'<div id="del_attachment_'.$attachment_id.'" class="del-attachment" style="display:none;">'
							.sprintf(__('You are about to delete <strong>%s</strong>.','opentickets-community-edition'), $filename)
							.'<a href="'.wp_nonce_url('post.php?action=delete&amp;post='.$attachment_id, 'delete-attachment_'.$attachment_id)
									.'" id="del['.$attachment_id.']" class="button">'
								.__('Continue','opentickets-community-edition')
							.'</a>'
							.'<a href="#" class="button" onclick="this.parentNode.style.display=\'none\';return false;">'
								.__('Cancel','opentickets-community-edition')
							.'</a>'
						.'</div>';
				} else {
					$delete =
						'<a href="'.wp_nonce_url('post.php?action=trash&amp;post='.$attachment_id, 'trash-attachment_'.$attachment_id).'" id="del['.$attachment_id.']" class="delete">'
							.__('Move to Trash','opentickets-community-edition')
						.'</a>'
						.'<a href="'.wp_nonce_url('post.php?action=untrash&amp;post='.$attachment_id, 'untrash-attachment_'.$attachment_id).'"
								id="undo['.$attachment_id.']" class="undo hidden">'
							.__('Undo','opentickets-community-edition')
						.'</a>';
				}
			}

			// molds all three (tracking field, button, and delete button) into a single td for each individual item in the media box, again
			// following the pattern in the get_media_item() function inside /wp-admin/includes/media.php
			$fields['buttons']['tr'] = "\t\t<tr class='submit'><td></td><td class='savesend'>$send_marker $use_button $delete</td></tr>\n";

			// make sure that any special logic specified in out logic set for constructing these fields is run
			// param 'fields-to-edit'
			foreach ($shared as $token) $fields = apply_filters('attachment_fields_to_edit-'.$token, $fields, $post);

			return $fields;
		}

		// since we overtook the 'insert into post' button with out 'use this image' button, and since that new button is suppose to 
		// gather information about the image, rather than insert it into the wysiwyg, we need to process the selection of an image 
		// differently than the basic mediabox would. we need to gather information about the image, put it in a format that our 
		// messaging javascript can use, and then send the message from the mediabox to the parent page it is hosted on. this is done
		// through the message handler defined in js/launcher.js . basically it allows communication from inside the iframe that
		// contains to mediabox, to the parent page that will use the information about the image.
		// @param string the current generated html for the response thus far
		// @param int the id of the attachment to send information about
		// @param array the information for this attachment that was submitted via the form inside the mediabox when clicking 'use this image'
		public static function a_media_send_to_editor($html, $send_id, $attachment) {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			// if not then passthru
			if (!is_array($shared) || empty($shared)) return $html;

			// get the size that is requested for the thumbnail preview. falls back on 'thumbnail' (wp default)
			$request_size = isset($_REQUEST['_preview_size']) && !empty($_REQUEST['_preview_size']) ? explode(',', $_REQUEST['_preview_size']) : array('thumbnail');
			if (count($request_size) == 1) $request_size = array_shift($request_size);

			// get the url and dimensions of the preview image that will be sent back in our message
			list($src, $w, $h) = wp_get_attachment_image_src($send_id, $request_size);

			// construct a descriptive object for the image that will be passed in the message to the parent window
			$args = array(
				'id' => $send_id, // image id
				'src' => $src, // thumbnail url
				'w' => $w, // thumbnail width
				'h' => $h, // thumbnail height
				'title' => $attachment['post_content'], // title submitted in the individual item form from the media box
				'alt' => !empty($attachment['image_alt']) ? $attachment['image_alt'] : $attachment['post_title'], // alt text submitted
			);

			// foreach matched tracking token, run any filters that were setup to modify the information we send back to the parent
			// window. this would have been set in the logic registration process using action 'mba-register-mediabox-logic'
			// param 'select-fields'
			foreach ($shared as $token) $fields = apply_filters('media_send_to_editor-'.$token, $args, $send_id, $attachment, $html);

			// transmit our message via some javascript that communicates without our message handler in js/launcher.js
			?>
			<script language="javascript" type="text/javascript">
				// on page load, send our message to the parent window
				(function(win, $){ $(win).trigger('transmitMBAMsg', [<?php echo json_encode($args) ?>]); })((w=parent||top), w.jQuery);
			</script>
			<?php
			die();
		}

		public static function a_overtake_flash_uploader() {
			// figure out if this is a request sent from a mediabox created from a button made by this plugin
			$shared = self::_matched_tokens();
			if (!is_array($shared) || empty($shared)) return;

			// if there is at least on plugin's tracking var in the request, that means that at least one plugin is waiting on information
			// to be sent from the media box. since that is the case, we need to process that information, and send it through a message
			// that the launcher script can understand and dispatch

			/** only use the first match on the hook comparison until a suitable solutions for handling multiple on the same form is created */
			/** TODO: allow for multiple of these to be tracked on a single form */
			$token = array_shift($shared);
			
			?>
			<script id="swfupload-settngs-takeover" language="javascript" type="text/javascript">
				if (typeof swfu != 'undefined' && swfu != null) {
					if (typeof console == 'undefined' || console == null) var console = {log:function(){}};
					jQuery(function($) {
						console.log('SWFU', swfu);
					});
				}
			</script>
			<?php
			return;
			
			// this is more or less a copy of large portions of the function media_upload_form() in /wp-admin/includes/media.php , tailored
			// so that it does not lose track of mba's tracking cookie when the form is submitted. ill only comment my changes
			global $type, $tab;

			$flash_action_url = add_query_arg(array(
				self::$_hooks[self::$_tokens[$token]]['tracking_token_name'] => "1"
			), admin_url('async-upload.php'));

			// If Mac and mod_security, no Flash. :(
			$flash = true;
			if ( false !== stripos($_SERVER['HTTP_USER_AGENT'], 'mac') && apache_mod_loaded('mod_security') )
				$flash = false;

			$flash = apply_filters('flash_uploader', $flash);
			$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;

			$upload_size_unit = $max_upload_size = wp_max_upload_size();
			$sizes = array( 'KB', 'MB', 'GB' );
			for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ )
				$upload_size_unit /= 1024;
			if ( $u < 0 ) {
				$upload_size_unit = 0;
				$u = 0;
			} else {
				$upload_size_unit = (int) $upload_size_unit;
			}

			?>
			<div style="display:none;"><div id="flash-browse-button"></div><div id="html-upload-ui"></div><div id="flash-upload-ui"></div></div>
			<script tag="swfupload-settings-override" language="javascript">
			var swfu;
			SWFUpload.onload = function() {
				var settings = {
						button_text: '<span class="button"><?php _e('Select Files','opentickets-community-edition'); ?><\/span>',
						button_text_style: '.button { text-align: center; font-weight: bold; font-family:"Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif; font-size: 11px; text-shadow: 0 1px 0 #FFFFFF; color:#464646; }',
						button_height: "23",
						button_width: "132",
						button_text_top_padding: 3,
						button_image_url: '<?php echo includes_url('images/upload.png?ver=20100531'); ?>',
						button_placeholder_id: "flash-browse-button",
						upload_url : "<?php echo esc_attr( $flash_action_url ); ?>",
						flash_url : "<?php echo includes_url('js/swfupload/swfupload.swf'); ?>",
						file_post_name: "async-upload",
						file_types: "<?php echo apply_filters('upload_file_glob', '*.*'); ?>",
						post_params : {
							"post_id" : "<?php echo $post_id; ?>",
							"auth_cookie" : "<?php echo (is_ssl() ? $_COOKIE[SECURE_AUTH_COOKIE] : $_COOKIE[AUTH_COOKIE]); ?>",
							"logged_in_cookie": "<?php echo $_COOKIE[LOGGED_IN_COOKIE]; ?>",
							"_wpnonce" : "<?php echo wp_create_nonce('media-form'); ?>",
							"type" : "<?php echo $type; ?>",
							"tab" : "<?php echo $tab; ?>",
							"short" : "1",
							<?php /* THIS IS MY CHANGE. it ensures that the tracking token gets passed with the rest of the request as to not lose track of it */ ?>
							"<?php echo self::$_hooks[self::$_tokens[$token]]['tracking_token_name'] ?>": "1"
						},
						file_size_limit : "<?php echo $max_upload_size; ?>b",
						file_dialog_start_handler : fileDialogStart,
						file_queued_handler : fileQueued,
						upload_start_handler : uploadStart,
						upload_progress_handler : uploadProgress,
						upload_error_handler : uploadError,
						upload_success_handler : uploadSuccess,
						upload_complete_handler : uploadComplete,
						file_queue_error_handler : fileQueueError,
						file_dialog_complete_handler : fileDialogComplete,
						swfupload_pre_load_handler: swfuploadPreLoad,
						swfupload_load_failed_handler: swfuploadLoadFailed,
						custom_settings : {
							degraded_element_id : "html-upload-ui", // id of the element displayed when swfupload is unavailable
							swfupload_element_id : "flash-upload-ui" // id of the element displayed when swfupload is available
						},
						debug: true
					};
					swfu = new SWFUpload(settings);
			};
			</script>
			<?php
		}
	}

	// baseic secondary verifications that we are in the wp system and not being accessed directly
	if (defined('ABSPATH') && function_exists('add_action')) {
		lou_media_box_anywhere::pre_init();
	}

endif;
