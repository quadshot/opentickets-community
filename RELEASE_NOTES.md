## Change Log 2.0 ##
[1.0 Change Log](release_notes_1.x)

# 2.8.6 - Oct/12/2017 #
* [tweak] adding new hooks for new plugins to interrupt js flow
* [tweak] changes for the family tickets plugin to work
* [fix] possible solution to seating issues
* [fix] updating a template to use new WC3 fetching of order information

# 2.8.5 - May/30/2017 #
* [fix] solved issue with adding regular products to order in admin
* [fix] fixing add product, add fee and add shipping buttons

# 2.8.4 - May/18/2017 #
* [new] added filter to run function checking if a report can run
* [fix] solved issue where ticket products were not being hidden from shop. must resave each product to resolve
* [fix] resolving order notes issue
* [fix] fixing product search for event ticket setup

# 2.8.3 - May/5/2017 #
* [fix] repairing advanced tools event search

# 2.8.2 - Apr/28/2017 #
* [fix] fixing new user button issue on edit-order admin screen
* [fix] fixing license key removal issue
* [fix] fixing extension update detection issue by making update checks more aggressive and vastly more numerous

# 2.8.1 - Apr/25/2017 #
* [tweak] more changes to resolve WC3 compatibility issues, when OTCE is used with extensions
* [tweak] changes to core plugin so that extensions work with new WC

# 2.8.0 - Apr/10/2017 #
* [tweak] reworked all admin fancy select boxes to use new version of select2, instead of previous version
* [tweak] reworked ALL handling of order item meta/data to match new WC formats
* [tweak] reworked order creation flow to match new WC
* [tweak] reworked ticket creation flow to match new WC
* [tweak] reworked admin ticket selection process to work with new WC
* [tweak] reworked admin order save process to work with new WC
* [tweak] reworked core plugin reports screen to work with new WC backend code and frontend js code
* [tweak] reworked how order data is grabbed from the order objects to match new WC flow
* [tweak] updated all admin metabox overrides to use new WC format for order items
* [tweak] cleaned up all warnings and notices caused by new WC update
* [tweak] improved compatibility otce and both WC3 & WC2

# 2.7.1 - Mar/27/2017 #
* [tweak] changing how dates are calculated for DST
* [tweak] adjusted event save functions to calculate the proper UTC version of the start time, under certain circumstances
* [fix] repaired 'event sales stop time' calculation
* [fix] corrected a php warning on some settings pages
* [fix] solved warnings on edge case issues for 'view details' button on premium extensions, via plugin page

# 2.7.0 - Mar/14/2017 #
* [new] added ability to use qtranslate-x on date format fields in settings
* [tweak] update that condenses reservation updates fo order_id and status into a single step. eliminates potential dupes due to plugin conflicts
* [tweak] added code to account for PHP warnings caused by other plugins mangling internal wordpress data
* [tweak] changed qr code signing so that it no longer relies on site salts
* [tweak] removed 'stats' update code
* [fix] repaired remove from cart bug when using some extensions

# 2.6.5 - Feb/06/2017 #
* [new] added tool to restore event tart/end times from backed up values that autoscript created
* [tweak] changed logic of start/end time autofix script, to only run for events that do not have a TZ designation
* [tweak] using home_url instead of site_url for ticket links
* [tweak] added code to prevent php warning in some confirmation emails
* [fix] fixed conflict with cart addons woo plugin
* [fix] repaired an issue where updates were being checked too many times throughout the day, in some case

# 2.6.4 - Jan/13/2017 #
* [tweak] made a javascript change that works around the new WC change that prevents the 'change seat' button from working in the admin
* [tweak] another adjustment that solves an edge case daylights savings time issue

# 2.6.3 - Jan/3/2017 #
* [fix] fixing issue in admin where subsequent saves of a parent event, sometimes changed the start times of the child events

# 2.6.2 - Dec/14/2016 #
* [tweak] changed how start and end times are stored in db
* [tweak] changed calculation of proper local time, given invalid timezones that WP lets you select in settings
* [tweak] patch to make the Simple Fields plugin not overwrite child event saved meta, when saving the parent event
* [tweak] updated jquery date and momentjs date reformatting, based on settings
* [tweak] adjusted some of the visual of the calendar for some themes
* [fix] fixed issue where removing all child events in from a parent event, caused none of the children to get deleted
* [fix] fixed edge case calendar issue where event times were changing per event

# 2.6.1 - Nov/23/2016 #
* [fix] fixed issue where sometimes the 'event area' would show as '0' during event creation
* [fix] fixed issue where some time formats were always interpreted as AM when PM would be appropriate

# 2.6.0 - Nov/18/2016 #
* [new] major overhaul on displayed time formats. now they are all options in the admin
* [new] ability to edit an individual event, change the start/end time, and update the permalink with auto-redirect of old one
* [tweak] update to better process manually typed in time values when creating and editing events

# 2.5.3 - Nov/11/2016 #
* [tweak] changing how we handle DST timestamps display again

# 2.5.2 - Nov/7/2016 #
* [translation] updating all translation files with strings for the admin interface
* [translation] updating ES translation for new strings
* [translation] updating NL translation
* [new] adjusting displayed times to handle the DST offset properly

# 2.5.0 - 10/10/2016 #
* [tweak] updated date strings for ES translation
* [tweak] updated how google map url is created so that encoding is always correct
* [new] new setting for Google Maps, to provide API key, since Google now requires it for static map usage
* [fix] repaired calendar translations in the admin, so that now it is actually translated
* [fix] repaired issue where sometimes creating a new order in the admin and selecting tickets, caused an error message to show

# 2.4.6 - 09/13/2016 #
* [fix] resolved issue in MS-Edge where event areas were not getting filtered by venue selection, during event creation
* [fix] resolved activation scenario where calendar page was not getting auto created sometimes

# 2.4.5 - 08/19/2016 #
* [new] support for the new Table Service plugin
* [fix] repaired bug where logged out users who create an account during checkout, and fail their first payment, do no lose their tickets

# 2.4.4 - 07/29/2016 #
* fixing modal issues caused by changing in WooCommerce CSS

# 2.4.3 - 07/26/2016 #
* added moment-timezone library, missing from last update

# 2.4.2 - 07/21/2016 #
* [new] added momentjs-timezone lib to handle differences between server and local machine time conversions when creating events
* [fix] patch to help VBO installs that are in subdirs
* [fix] fixed php7 syntax errors. (thought we already released this, but apparently not)

# 2.4.0 - 06//16/2016 #
* [new] added the ability to specify a per-event capacity override using event meta
* [new] added filter to the 'remove child event' flow, so that externals know the event was removed
* [tweak] overhaul on handling of event times, so that they always appear in local time
* [tweak] when adding tickets to an order via the admin, the displayed capacity now updates as you select them
* [fix] repaired reports form submission issue that caused the form to disappear in some cases
* [fix] repaired javascript error preventing certain events from saving in the admin
* [fix] resolved activation bug when activating some themes while OTCE was active

# 2.3.0 - 04/15/2016 #
* [DEPRECATE] removed PDF library. all modern browsers/os combos support this natively now
* [tweak] minor js tweaks for extension compatibility
* [fix] repaired ajax bug during ticket selection, caused by WC session update, and it's effects on the nonce values
* [fix] logic tweak to prevent potential edge case GAMP reservation issues

# 2.2.6 - Mar/31/2016 #
* [new] private tickets now follow core wordpress 'private post' logic
* [translation] udpated german translation
* [tweak] changed default QR Code generator from phpqrcode library to google apis
* [tweak] added code to help phpqrcode find the wp-config file more easily
* [tweak] moved DOMPDF cache to uploads dir, since that is more likely writable than the plugin dir
* [tweak] changed styling of modals to be sized correctly
* [fix] repaired edgecase overbook bug when using GAMP extension
* [fix] repaired admin ticket selection on GA events
* [fix] repaired i18n datepicker bug

# 2.2.5 - Mar/23/2016 #
* [tweak] cleaned up the event repetition interface so that it is more userfriendly now
* [tweak] moved 'new event date' button to be centered with calendar contents
* [tweak] adjusted calendar styling to fix better on the page, and look better
* [tweak] fixed calendar syncing while adding new events, which prevents weird new event start date in some cases
* [fix] repaired update of '_purchases_ea' meta key
* [fix] corrected some typos in error messages
* [fix] repaired 'red Xs' on the admin edit event calendar, so that it no longer removes all events in some cases

# 2.2.4 - Mar/17/2016 #
* [tweak] changed all reservation checks to use millesecond precision
* [fix] resolved child event featured image issue

# 2.2.3 - Mar/11/2016 #
* [tweak] added code to prevent as many third party plugin and theme PHP errors as possible during PDF generation
* [tweak] saving WYSIWYG based settings no longer strips out formatting
* [tweak] 'custom completed order email message' now only shows on the completed order email
* [fix] fixed frontend calendar population problem, when using an HTTP frontend and an HTTPS backend
* [fix] fixed the 'red x' buttons on the 'black event boxes' on the calendar in the edit event admin page

# 2.2.2 - Mar/9/2016 #
* [fix] repaired 'remove reservation' buttons
* [fix] solved issue for users who order tickets from the same event more than once

# 2.2.1 - Mar/8/2016 #
* [new] most settings are now qtranslate capable, including email aumentations
* [new] added ability to define a custom translation, outside the plugin directory
* [new] added several filters to ticket reservation process and templates
* [tweak] changed how child events are saved
* [tweak] changed logic so that when the parent event is saved, child event slugs are not updated if the child event exists already
* [fix] fixed 'new event date' interface to accept international date formats
* [fix] corrected resync tool date format problem

# 2.2.0 - Feb/26/2016 #
* [new] all tickets on non-cancelled orders are considered 'confirmed' now
* [new] adding an order note during order cancellation that includes information about the tickets that were removed
* [new] added settings to control the ticket state timers for temporary states
* [tweak] disabled phpqr error logging by default, because of pdf creation issues
* [tweak] isolated thumbnail cascade meta lookup to only events
* [tweak] changed js to no longer override $.param function, since it does what we need now
* [tweak] adjusted line item status output on seating report
* [tweak] limiting cart to reservation sync, and pushing it after cart corrections occur
* [tweak] added message to inform user when there are not enough tickets to complete the cart update, when chaning the quantity on the cart with failure
* [tweak] updating admin override templates to better match woocommerce core templates
* [fix] fixed an edgecase overbooking issue, that could occur during admin seat allocation
* [fix] fixed weird order status bugs on seating report
* [fix] corrected a refund template warning

# 2.1.2 - Feb/12/2016 #
* [new] added box-office, box-office-manager, and content-manager roles in from old enterprise version
* [tweak] modified code for wp.dompdf.config.php generation for eaccellerate
* [tweak] changed reporting csv header logic so that headers can be defined by event
* [tweak] added ability to modify report columns
* [tweak] restricted event publishing to ppl with the ability to do so
* [fix] repaired a recurring event publish bug dealing with visibility
* [fix] fixed PDF corruption issues, both when WP_DEBUG and not

# 2.1.1 - Feb/04/2016 #
* [tweak] changed load order so that cart-timers can be modified in the theme
* [tweak] tweaked checking logic so that proper errors are reported when ticket has been previously checked in
* [fix] fixed bug where ticket codes were improperly reported on attendee report after a checkin

# 2.1.0 - Jan/21/2016 #
* [new] calendar is now i18n compatible
* [new] calendar now supports mutliple views
* [new] all calendar event blocks are theme overrideable
* [new] complete revamp of all calendar logic
* [new] new look and feel of calendar
* [new] special order note type was added, which appears on the attendee report
* [new] extra tool for updating data after a migration

# 2.0.7 - Jan/13/2016 #
* [new] now compatible with qTranslate X !!!!

# 2.0.6 - Jan/13/2016 #
* [new] added ability to make the QR codes either URLs or just Codes
* [new] added QR code to attendee report, so that they can be exported and then imported to third part scanner software
* [fix] eliminated the 'double form' issue when running reports
* [fix] repaired ticket reservation issue when using redirect payment types

# 2.0.5 - Jan/06/2016 #
* [new] attendee report shows order status on non-completed orders now, instead of just 'paid'
* [tweak] update to prevent chrome date picker from clashing with jquery datepicker
* [tweak] seating report accuracy increased and notices removed
* [tweak] change QR codes so that they are no longer random on each page load
* [fix] repaired system-status resync tool for 2.0.x
* [fix] added cache buster to reporting js

# 2.0.4 - Dev/30/2015 #
* [tweak] internal GA event area functions tweaked for flexibility
* [fix] repaired advanced tools for GA events
* [fix] fixed ticket links for edgecase email and my account issues

# 2.0.3 - Dec/30/2015 #
* [tweak] added minor functionality to a couple js tools
* [tweak] retooled base report to be more flexible for advanced reporting
* [tweak] updated seating report title and printer friendly version
* [fix] repaired activation fatal error on WP 4.0.x - 4.3.x

# 2.0.2 - Dec/22/2015 #
* [new] added deactivate license link on expired licenses
* [new] updated licenses ui to provide more information
* [tweak] show licenses page even when no extension are detected

# 2.0.1 - Dec/21/2015 #
* [tweak] change to handle PDF tickets with long venue descriptions

# 2.0.0 - Dec/17/2015 #
* [new] event areas now have their own admin menu, instead of being part of the venues interface
* [new] changed structure of how event areas are handled, which allows multiple event area types to exist on the same install (ga, gamp and seating)
* [new] all frontend and admin UI elements are now overridable in the theme, via templates
* [new] completely revamped entire ticket display template. now the template is modular, and each module is overrideable in the theme
* [new] added column to event-area list page that shows the type of event area
* [improvement] improved accuracy of event availability calculations
* [improvement] improved upcoming tickets section of my-account page for accuracy and performance
* [improvement] improved the performance of the event settings bulk editor
* [improvement] improved accuracy of reservation to cart syncing
* [improvement] improved ticket display and checkin process, and added the ability to use multiple event area types for the displayed tickets
* [improvement] improved performance and usability of the admin ticket selection / ticket change process
* [improvement] various javascript performance and usability improvements
* [improvement] performance & flexibility improvements to the template fetcher
* [fix] repairing edgecase purchase limit bug
* [fix] fixed calendar availability bug
* [fix] fixed 'view all tickets' link bug
* [fix] fixed admin ticket selection modal visual display bugs
* [fix] fixed saving event 1969 bug
* [fix] fixed the 'missing ticket links in email' bug
