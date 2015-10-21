=== OpenTickets Community Edition ===
Contributors: quadshot, loushou, coolmann
Donate link: http://opentickets.com/
Tags: events, event management, tickets, ticket sales, ecommerce
Requires at least: 4.0
Tested up to: 4.3
Stable tag: trunk
Copyright: Copyright (C) 2009-2014 Quadshot Software LLC
License: GNU General Public License, version 3 (GPL-3.0)
License URI: http://www.gnu.org/copyleft/gpl.html

An event management and online ticket sales platform, built on top of WooCommerce.

== Description ==

= OpenTickets Community Edition =

[OpenTickets Community Edition](http://opentickets.com/community-edition "Event management and online ticket sales platform") ("OT-CE") is a free open source WordPress plugin that allows you to publish events and sell tickets online. OT-CE was created to allow people with WordPress websites to easily setup and sell tickets to their events. 

OT-CE was created for venues, artists, bands, nonprofits, festivals and event organizers who sell General Admission tickets. OT-CE is an alternative to reduce the overhead and eliminate service fees from software you run on your own website.

OT-CE runs on [WordPress](http://wordpress.org/ "Your favorite software") and requires [WooCommerce](http://woocommerce.com "Free WordPress based eCommerce Software") to operate. WooCommerce is a free open source ecommerce platform for WordPress. You can download that at http://woocommerce.com 

With WordPress and WooCommerce installed, you then install the OT-CE plugin. OT-CE information and instructions are available at http://opentickets.com/community-edition 

The OT-CE plugin empowers functionality to:

* Publish Venues
* Publish Events
* Display Calendar of Events
* Create and Sell Tickets
* Allow Customers to keep Digital and/or Print Tickets
* Checkin People to Events with a QR Reader 
* Ticket Sales Reporting

OT-CE is licensed under GPLv3.

= Your first Event =

Need help setting up your first event? Visit the [OpenTickets Community Edition Basic Help](http://opentickets.com/community-edition/#your-first-event) and follow the steps under _Creating your first Event, Start to Finish_.

= Need some help? =

Need help getting started? Watch some of our Instructional Videos to learn how to install OpenTickets and setup an event!

1. [Installation](http://youtu.be/7syX3-oXDLg "Basic Installation Video")
1. [Setting up your First Event](http://youtu.be/Y4Sr9hPcbwY "Step-by-step instructions for setting up your First Event")
1. [Using the Event Calendar](http://youtu.be/sq-sPkFxobc "Demonstrates the power of the calendar")
1. For a full list of our Instructional Videos, visit [our website's videos page](http://opentickets.com/videos "OpenTickets.com Videos Page")

= Get Involved =

Are you developer? Want to contribute to the source code? Check us out on the [OpenTickets Community Edition GitHub Repository](https://github.com/quadshot/opentickets-community).

= Special Thanks =

*testing and bug reports*
@bradleysp, @petervandoorn

*tranlations*
@ht-2, @luminia, @ton, @firgolitsch, @jtiihonen, @diplopito, @galapas

== Installation ==

= Instructional Videos =

If you are more of a 'just give me a video to show me how to do it' type person, then we have a few new videos that can help show you how to Install and Setup OpenTickets.

1. [Installation](http://youtu.be/7syX3-oXDLg "Basic Installation Video")
1. [Setting up your First Event](http://youtu.be/Y4Sr9hPcbwY "Step-by-step instructions for setting up your First Event")
1. [Using the Event Calendar](http://youtu.be/sq-sPkFxobc "Demonstrates the power of the calendar")
1. For a full list of our Instructional Videos, visit [our website's videos page](http://opentickets.com/videos "OpenTickets.com Videos Page")

= Basic Installation =

These instructions are pretty universal, standard methods employed when you install any plugin. If you have ever installed one for your WordPress installation, you can probably skip this part.

The below instructions assume that you have:

1. Downloaded the OpenTickets software from WordPress.org.
1. Have already installed WooCommerce, and set it up to your liking.
1. Possess a basic understanding of WooCommerce concepts, as well as how to create products.
1. Have either some basic knowledge of the WordPress admin screen or some basic ftp and ssh knowledge.
1. The ability to follow an outlined list of instructions. ;-)

Via the WordPress Admin:

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Plugins' menu item on the left sidebar, usually found somewhere near the bottom.
1. Near the top left of the loaded page, you will see an Icon, the word 'Plugins', and a button next to those, labeled 'Add New'. Click 'Add New'.
1. In the top left of this page, you will see another Icon and the words 'Install Plugins'. Directly below that are a few links, one of which is 'Upload'. Click 'Upload'.
1. On the loaded screen, below the links described in STEP #4, you will see a location to upload a file. Click the button to select the file you downloaded from http://WordPress.org/.
1. Once the file has been selected, click the 'Install Now' button.
    * Depending on your server setup, you may need to enter some FTP credentials, which you should have handy.
1. If all goes well, you will see a link that reads 'Activate Plugin'. Click it.
1. Once you see the 'Activated' confirmation, you will see new icons in the menu.
1. Start using OpenTickets Community Edition.

Via SSH:

1. FTP the file you downloaded from http://WordPress.org/ to your server. (We assume you know how to do this)
1. Login to your server via ssh. (.... you know this too).
1. Issue the command `cd /path/to/your/website/installation/wp-content/plugins/`, where /path/to/your/website/installation/wp-content/plugins/ is the plugins directory on your site.
1. `cp /path/to/opentickets-community.zip .`, to copy the downloaded file to your plugins directory.
1. `unzip opentickets-community.zip`, to unzip the downloaded file, creating an opentickets directory inside your plugins directory.
1. Open a browser, and follow steps 1-2 under 'Via the WordPress Admin' above, to get to your 'plugins' page.
1. Find OpenTickets on your plugins list, click 'Activate' which is located directly below the name of the plugin.
1. Start using OpenTickets Community Edition.

= Start using it =

Setup a 'ticket product':

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Products' menu item on the left sidebar.
1. Near the 'Products' page title, click the 'Add Product' button.
1. Fill out the product name and product description, in the middle column of the page.
1. In the 'Product Data' metabox, check the box next to 'Ticket'
1. In the upper right hand metabox labeled 'Publish', edit the 'Catelog visibility' and change it to 'hidden'.
1. Fill out any other information you may require, and then click the blue 'Publish' button.

Setup a 'Venue':

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Venues' menu item on the left sidebar.
1. Near the 'Venues' page title, click the 'Add Venue' button.
1. Fill out the venue name and the venue description, as you did with the ticket product.
1. Further down the middle column, fill out the 'Venue Information' metabox
1. If applicable, fill out the 'Venue Social Information' metabox.
1. Complete any other information you wish on the page, and click the blue 'Publish' button.

Setup an 'Event Area':

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Venues' menu item on the left sidebar.
1. Click the title of the venue you created earlier, as if to edit the venue information.
1. Find the metabox labeled 'Event Areas'.
1. Click the 'add' button inside the Event Areas metabox.
1. Click 'Select Image' and use the media library to choose an image that shows the layout of the event.
1. Give the area a name.
1. Set a positive 'capacity'. This should be the maximum number of tickets available for this event.
1. Select the 'ticket product' you created earlier, under the 'Available Pricing' option.
1. Click the blus 'save' button inside the metabox.

Setup an 'Event':

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Events' menu item on the left sidebar.
1. Near the 'Events' page title, click the 'Add Event' button.
1. Fill out the event name and description, in the middle column of the page.
1. Setup the showing date and times in the 'Event Date Time Settings' metabox, which has a similar interface to Google Calendar.
    1. Click the 'New Event Date' button in the middle of the calendar header.
		1. Fillout the starting and ending date and time of the first day of this event.
		1. If this event will happen more than once, check the 'repeat...' checkbox
        * Fill out the repeat interval information
    1. Once all the event date and repeat interval information is filled out, click the blue 'Add to Calendar' button.
    1. Verify that your event dates have been added to the calendar, by browsing the calendar using the calendar navigation.
1. Further down in the 'Event Date Time Settings' box, under the 'Event Date/Times' heading, find the list of events you just created.
1. Using standard 'selection techniques' (eg: "shift" to select adjacent items, "cmd/ctrl" to select individual items), select any number of your event showings.
1. On the right hand side of the list, some basic settings will appear. Adjust the settings accordingly. 
    1. Visibility - determines who can see the showing, and where it appears.
    1. Venue - the "Venue" in which the showing is taking place.
    1. Area / Price - the "Event Area" and accompanying ticket price for the event
1. Click the blue 'Publish' button in the upper right metabox

== Frequently Asked Questions ==

The FAQ's for OpenTickets Community Edition is currently located on [our website's FAQs Page](http://opentickets.com/faq).

== Changelog ==

= 1.13.3 =
* [new] added option to control if parent events are displayed on homepage
* [tweak] by default events are not shown on homepage anymore
* [fix] repaired the product category pages

= 1.13.2 =
* [tweak] improved seating extension compatibility
* [fix] corrected changelog entries
* [fix] solved edgecase of admin reserved tickets expiring

= 1.13.1 =
* [fix] ticket selection UI removed from ticket display

= 1.13.0 =
* [improvement] completely reworked th seating report for speed and efficiency
* [improvement] added printer friendly version of seating report

= 1.12.11 =
* [tweak] slight logic change to fix display options extension issue

= 1.12.10 =
* [tweak] removed warnings on orderagain logic

= 1.12.9 =
* [tweak] changed load order of all js and css to overcome version clashes with WC
* [tweak] hide permalink debug on non wp_debug sites

= 1.12.8 =
* [improvement] allows order id lookup in advanced tools

= 1.12.7 =
* [new] added advanced tools, which come with a warning
* [new] added code to handle the 'order again' action
* [improvement] more efficient and extendable regular tools
* [improvement] simplified ticket security
* [fix] edge case bug where cart sync process was not working

= 1.12.6 =
* [tweak] js tools updated for new Seating features

= 1.12.5 =
* [fix] videos outlink on menu repaired

= 1.12.4 =
* [new] links to external doc and videos
* [improvement] max quantity on ticket selection set to purchase limit
* [improvement] made 'new event date' button more eyecatching in admin
* [improvement] settings page experience improved
* [tweak] added like us, rate us
* [fix] branding image save issue resolved
* [fix] mutli-day event creation and display issue resolved
* [fix] seating report when working with Seating Extension
* [fix] color picker problems on settings page are resolved

= 1.12.3 =
* [fix] resolved Windows Server PDF generation issues

= 1.12.2 =
* [tweak] changed base venue info template to include a clear, in case of floaty images and short descriptions

= 1.12.1 =
* [translation] updated all PO and MO files, and unfuzzied all entries, so they actually translate
* [translation] updated all date formats for nl_NL translation
* [change] when purchase limit is 1, after adding reservations, the quantity can no longer be edited
* [tweak] minor param tweak for purchase limit to allow WC 2.3.x to work
* [fix] correctd newly introduced php error on edit user pages
* [fix] corrected 'state/county' issue where value keeps setting to 'null'
* [fix] minor php notices during event save process


= 1.12.0 =
* [new] feature to limit the number of tickets that can be purchased per event
* [new] feature to allow locking the quantity after the user selected it the first time. users can still un-reserve
* [tweak] removed some legacy code that was cluttering up JS
* [tweak] updating old syntax jQuery to modern syntax

= 1.11.7 =
* [change] all 'reserved' tickets count against the availability
* [tweak] more ability to debug checkin process
* [tweak] better error reporting on checkin process
* [fix] transition from and too seating plugin
* [fix] edge case overbooking bug

= 1.11.6 =
* [update] updated DE and FI translations
* [tweak] changed how menu is constructed
* [tweak] removed pdf warnings and streamlined pdf code generation slightly
* [fix] menu items not getting translations
* [fix] 'show time' option was not actually showing time. resolved

= 1.11.5 =
* [update] added security to all directories for misconfigured server protection
* [update] added security to top of all templates
* [tweak] changed some messages to respect new show available counts option
* [tweak] cleaned up non-js version css

= 1.11.4 =
* [new] added global option to control display of the availability counts
* [update] complete take over of db upgrade, so null are handled
* [tweak] added cached _price for DO extension
* [fix] resolved checkin bug when using the seating plugin

= 1.11.3 =
* [fix] capacity bug has been refixed
* [fix] fixed doing it wrong on legacy code

= 1.11.2 =
* [translation] added pt_BR translation
* [new] added date/time to display on cart, checkout, my account, and edit order screens
* [update] manually added translation for new date format m/d/Y to all POs
* [update] changed 'cart' to 'basket' in GB translations
* [tweak] added order_item_id to $ticket in the ticket template
* [tweak] updated EA form (and other js forms) to accept accented characters
* [fix] bbpress (and other plugin) compatibility
* [fix] solved edge-case problem with date picker on event edit pages

= 1.11.1 =
* [fix] repaired event save issue
* [fix] mediabox image selection bug

= 1.11.0 =
* [translation] added FI translation
* [translation] added ES translation
* [translation] NL translation corrections
* [new] the ability to toggle date and time on child event titles
* [update] all translation files have new strings
* [update] legacy global $woocommerce replaced with WC()
* [tweak] some js libs are now more accurate
* [fix] calendar template loader (edge case php warning)
* [fix] event area display limit corrected in venue edit page
* [fix] PDF ticket map issue now resolved

= 1.10.29 =
* added public api to fetch plugin settings
* added new js for i18n datepicker
* updated nl_NL translation. @Ton
* tweaked report page tab rendering
* tweaked report js to remove legacy jquery functions
* fixed a bug in the_content filter for event descriptions
* fixed report related notices
* fixed PDF render when installation in subdirectory

= 1.10.28 =
* added a wrapper to each lib that bails if lib is already present
* added an indication on the settings page that the 6th ticket image is static

= 1.10.27 =
* added filter to obtain event availability
* added ability to augment 'Tools' page
* tweaked PDF font updates to overwrite upon plugin update
* tweaked event area frontend image sizing css
* fixed PDF render problems

= 1.10.26 =
* added country and state selection to venue info form, modeled after WC user info
* added map to venue info page output
* added wysiwyg to notes and instructions fields for venues

= 1.10.25 =
* added adjacent post code fixes
* fixed blank site bug

= 1.10.24 =
* added venue image to ticket page
* added more informative error messages for ticket display problems
* added venue information print out on venue pages, with options
* added strings for availability labels
* fixed end time bug on ticket
* fixed ticket data aggregation warning

= 1.10.23 =
* added dutch translation (@luminia)
* added data requirements check to ticket display template
* added css to ticket selection UI to handle float issues above and below
* tweaked settings for synopsis so they are adjacent
* tweaked billing data check on admin order save for pending and cancelled orders
* tweaked ticket printing styles
* changed admin order save billing data validation to mirror checkout

= 1.10.22 =
* adding css libraries that WC removed

= 1.10.21 =
* updated PO and MO files
* removed several deprecated templates and files

= 1.10.20 =
* added PDF file preprocessor, to improve performance and eliminate local & remote asset problems
* added branding image row to tickets, with configurable images, configured on OT settings page (intruderHT : @ht-2)
* added many blocks of code documentation where previously absent
* added ability to set both images on ticket
* added logic to properly handle map images on PDF
* added system status tool to remove local ticket asset cache
* added custom DOMPDF settings generator upon install
* added security to DOMPDF lib core files
* added backbone modal handler
* added deprecated action and filter handler
* tweaked ticket qr code image to have dimensions and to be slightly smaller
* tweaked permalink flush on install
* updated all admin order screen takeover templates
* updated ticket format for i18n compat (intruderHT : @ht-2)
* replaced old media library tool on venue edit screen
* fixed ticket image alignment

= 1.10.19 =
* fixed shipping order item column alignment in admin
* fixed month name typo in admin
* fixed recurring event date problem in admin even ui

= 1.10.18 =
* updated admin templates for latest WC compatibility
* updated admin js for jQuery Migrate compatibility
* updated all PO and MO files with latest strings (need translations)
* improved order admin takeover styling
* removed double call to WC saved_items action
* removed old legacy admin template takeovers

= 1.10.17 =
* added UK translation PO and MO for date formatting

= 1.10.16 =
* added German translation, thanks to H.T. (@ht-2)
* added changes to the ticket format to accommodate i18n

= 1.10.15 =
* added ability to choose synopsis location on event pages
* added template for 'past event' display
* improved OTCE version storage in db
* fixed IE js issue on order pages

= 1.10.14 =
* added version number of plugin to DB for external plugins
* improved cart to reservation syncing, and vice versa
* removed cruft JS code
* removed obselete payment gateway patches

= 1.10.13 =
* added ability for PO file to control date formats in datepickers
* added core en_US PO
* updated italian PO

= 1.10.12 =
* added js polyfill for hide-if-js-less themes
* added filter for ticket information on ticket template
* added 'has' feature to QS.CBS js utility
* improved admin order load event function documentation and readability
* improved checkin code to be more extendable
* improved order/cart to reservation syncing
* improved ticket code generation function and ticket assignment
* repaired edgecase unconfirm function issue
* repaired edgecase reservation error

= 1.10.11 =
* added a default event.css, for those without write access
* added a default calendar.css for those without write access
* added calendar color settings to admin panel
* improved calendar theme framing
* tweaked non-js ticket selection to look more like js version
* tweaked calendar template to be more cross theme friendly
* tweaked calendar style to be a bit more responsive
* fixed single ticket QR urls
* fixed visual of 'red box' in javascript enable browsers

= 1.10.10 =
* changed PDF to be a download file instead of browser load
* changed QRs to embeded images instead of separate requests, when generating the PDF

= 1.10.9 =
* tweaked/forked dompdf to not require allow_url_fopen

= 1.10.8 =
* moved login augmentation to early loaded location
* moved all modules and plugin loads until after all plugins loaded
* tweaked db upgrade process to handle rare mysql letter case issue

= 1.10.7 =
* turned off db errors during db upgrade

= 1.10.6 =
* added additional template location checks
* added 'tool' to force db reinit
* changed child event title date format to WP date setting
* enhanced db-upgrade process to handle missing tables and to allow 'pre updates' to be run

= 1.10.5 =
* fixed QR Code not showing issue (thanks @backbeatjohn)

= 1.10.4 =
* tweaked logic to account for GAMP plugin reservations

= 1.10.3 =
* fixed alternate view of myaccount page

= 1.10.2 =
* added a 'top customized completed email' setting
* tweaked spacing on the 'new user' button, so it is away from the new WC
* 'view other orders' link 
* tweaked 'multiday event' display
* fixed 'locked event settings' box issue
* fixed calendar event fetch edgecase bug
* fixed locale issue on my account page 
* fixed the 'customized email' issue

= 1.10.1 =
* added missing file

= 1.10.0 =
* added the ability to change the event url slug
* added code to uniquify the ticket QRs while maintaining security
* added login page for QR Code failures, if applicable
* tweaked checkin process to work with default permalinks
* tweaked js tools to be more flexible
* fixed several php notices
* removed cruft files

= 1.9.3 =
* fixed the 'removed event area' issue

= 1.9.2 =
* fixing plugin description typo

= 1.9.1 =
* added wysiwyg to settings page for html email
* fixed conflict with The Events Calendar plugin
* fixed weird ordering problem on event settings
* fixed minor bug in system status page

= 1.9.0 =
* added WC2.3 compatibility
* updated 'edit order' metabox takeovers for WC2.3
* tweaked 'new event' form styles
* fixed several residual php notices
* removed WC2.1 compatibility

= 1.8.10 =
* updated faqs and such

= 1.8.9 =
* updated style and template for calendar to look better on 2015 theme
* fixed event css writer code
* fixed the non-ssl to ssl-ajax cookie problem for the calendar
* fixed display of the protected events for 4.1
* fixed frontend to backend http to https issue (force ssl admin issue)

= 1.8.8 =
* added patch for CORE bug caused by FORCE_SSL_ADMIN and CORS standards

= 1.8.7 =
* updated all js callback areas to use QS.CB
* fixed 'new event date' button bug
* fixed 'event settings' js bug

= 1.8.6 =
* added ability to order QS.CB callback functions
* updated order data metabox takeover class variable visibility for latest woocmmerce version
* fixed post_parent__not_in and post_parent__in sql syntax errors

= 1.8.5 =
* fixed the 'new event date' button bug

= 1.8.4 =
* fixed deprecated php that causes ticket selection to not work

= 1.8.3 =
* added more information to the system status page
* changed how IPN payment completions mark tickets paid

= 1.8.2 =
* added the 85% of i18n changes that were missed during the first merge
* added the paypal gateway patch
* added system status page and tools page
* added tools to repair paypal issue
* repaired cart to ticket sync, so paid tickets are not removed

= 1.8.1 =
* repaired ticket validation form and admin user access

= 1.8.0 =
* added i18n support
* added po for italian

= 1.7.2 =
* fixed 'compile stylesheet' error

= 1.7.1 =
* added frontend style settings page

= 1.7.0 =
* added styling to the settings pages
* added settings for frontend colors (thanks @bradleysp)
* added ticket option for order number on ticket
* added ticket option for image to use on ticket (thanks @bradleysp)
* added ticket option for using shipping info instead of billing info (thanks @bradleysp)
* added ending date to ticket
* moved settings to appropriate new settings pages
* removed frontend event.css file, and made it create on activate & settings save

= 1.6.15 =
* added overloader function for coupons extension
* repaired more logic to handle the new GAMP plugin

= 1.6.14 =
* fixed core event saving visibility bug

= 1.6.13 =
* added code to update internal table names when switching blogs
* improved woocommerce check to include network plugins

= 1.6.12 =
* added better error handling on ticket reservation form
* improved 'qsBlock' code to adjust properly to covered element size 
* updated 'over available tickets' check to be more universal

= 1.6.11 =
* repaired 'my-orders' section of the my account printout on the edit user page in the admin (thanks @rjh1990)

= 1.6.10 =
* repaired ticket confirmation code to be more universal

= 1.6.9 =
* added more filters and hooks for extensions

= 1.6.8 =
* added multiple hooks to help support new multi price plugin
* fixed bug with event area image selection, when adding multiple EAs

= 1.6.7 =
* updated javasript declaration locations which resolves the event area add button bug

= 1.6.6 =
* added setting to customize the event base url (@Temitayo Akeem)
* repaired 'user profile'/'edit user' page php errors
* repaired 'new event area' on 'new venue' bug (@cbell)

= 1.6.5 =
* added filter to inform other plugins of sub-event save
* added more javascript hooks
* added various utility functions to core class
* improved base styling of ticket selection form
* improved zoner flexibility
* improved cart to reservation table (and vice versa) syncing
* improved handling of multiple different values when editing event settings
* updated order item display on order confirmations to show label for ticket link
* removed unused icon files
* repaired 'new event area' saving bug
* repaired infinite loop bug when saving orders in admin
* repaired sprite filename
* repaired 'event settings' underlapping next metabox bug
* corrected ticket selection error message verbiage
* tweaked change log

= 1.6.4 =
* added ticket checked in count to checkin status screens
* repaired the checkin process (thanks @bradleysp)

= 1.6.3 =
* updated admin order save billing information validation
* updated qrcode declaration to account for possible different server setups (thanks @bradleysp & @regenbauma)
* repaired edge case infinite loop on admin order save

= 1.6.2 =
* repaired the event date editor so that it proper handles empty dates

= 1.6.1 =
* repaired the event date editor in the 'event settings' ui

= 1.6.0 =
* added the ability to 'password protect' events like you would normally do for a regular post (thanks @bradleysp)
* updated the 'event settings' ui to handle new funcitonality and to be better organized
* updated calendar styling to have visual markers depending on event status
* repaired 'hidden event' functionality so that those users with the link can view the event (thanks @bradleysp)

= 1.5.4 =
* changed image format of map so it always shows in PDF tickets (thanks @bradleysp)
* repaired the empty cart issue (thanks @regenbauma) for anonymous users

= 1.5.3 =
* tweaked woocommerce checker to check for github-generated plugin directories
* changed image format of barcode so it always shows in PDF tickets (thanks @bradleysp)
* repaired email ticket link auth code verification
* repaired a my-account event visibility bug

= 1.5.2 =
* updated myaccount page to not show ticket links until order is complete
* adjusted WC ajax takeovers so that our metaboxes are loaded on save/load order items
* repaired 'new user' funcitonality on the edit order screen
* repaired auto loading of user information when user is selected during admin order creation
* repaired the issue where ticket purchases were getting tallied multiple times (thanks Robert Trevellyan) 

= 1.5.1 =
* repaired installation bug where event links are not immediately available
* removed new WooCommerce stati from event stati list

= 1.5.0 =
* woocommerce 2.2.0+ compatibility patches
* added backward compatibility for WC2.1.x
* updated all edit order metaboxes in admin
* converted all order status checkes to WC2.2.x method
* repaired new order status change bug where reservations were getting cancelled

= 1.4.1 =
* added the ability to choose the date the event calendar opens to
* fixed bug where ticket links become available before order is completed

= 1.4.0 =
* added functionality to base reporting class for easier extension
* added upcoming tickets panel to my-account page
* updated ticket permalinks to work with 'default permalinks'
* fixed event permalinks problem when using 'default permalinks' - Core WP Bug
* fixed reporting page tab selection bug

= 1.3.5 =
* added a minimum memory limit check/auto adjust if possible
* added better event date range function
* changed API for reporting to be more flexible
* repaired event date range calculation

= 1.3.2 =
* added plugin icons
* changed the 'inifinite login' feature, so that it works with 4.0 and does not prevent 4.0 from handling sessions properly
* removed more php notices

= 1.3.1 =
* changed how frontend ajax is handled
* repaired seating report for recent changes
* removed 'web interfaces' from included packages, for wp.org compliance

= 1.3.0 =
* changed how the order details metabox is overtaken, to preserve WC methodology
* repaired edge case with zoner entry removal
* repaired ticket display area name issue
* repaired more php notices

= 1.2.7 =
* repaired php notice issues
* removed deprecated user query filtering

= 1.2.6 =
* updated ticket permalinks to automatically work on installation

= 1.2.5 =
* initial public release

== Upgrade Notice ==

= 1.11.1 =
I want to personally apologize for first allowing an event save bug to get pushed to the live plugin and second for the delay in a fix. There are plenty of excuses to go around, but the responsibility falls square on my shoulders, and for that I apologize. This patch repairs that functionality. -- Loushu

= 1.11.0 =
Was your map not showing on your PDFs? This fixes it. Is your site using fi_FI, nl_NL, or es_ES languages? Congrats! You now have a translation thanks to the community! Want to remove the date/time from your event titles!? You got it!

= 1.10.29 =
Have you had problems with the PDF output for tickets? This patch finalizes the changes that will resolve these issues.

= 1.10.16 =
Thanks to H.T. (@ht-2) we now have a German Translation. 1.10.16 includes this translation.

= 1.9.0 =
WooCommerce 2.3.x was recently released. OpenTickets Community Edition v1.9.x enables WC2.3 compatibility. Upgrade to version 1.9.x around the time you upgrade WooCommerce to 2.3.x.

== Arbitrary section ==

This software is designed with the idea that there are several components that make up an event, all of which have a specific association to the next, and all of which work together to define the finished product. 

First you need a 'Venue', which in general terms, is just a location that has areas which can be used to host events. A good example of a Venue would be a Hotel. Hotels, generally speaking, have multiple conference rooms available for rental. Ergo, on any given day, during any given time, any number of these several conference rooms could be occupied with a different event.

Then you need an 'Event Area'. In general, an event area is meant to represent a sub-location of the Venue; for instance, a conference room inside the aforementioned Hotel. Each room may have it's own configurations of seats, it's own stage position, it's own entrances and exits, and it's own pricing. There are scenarios in which this does not entirely hold up as an example, but in general, try to think of it this way.

With this information, we can now piece together an event. An event is hosted by a 'Venue' and has pricing and a layout designated in the 'Event Area'.

Hopefully this clears up some general concepts about the idea behind the software.
