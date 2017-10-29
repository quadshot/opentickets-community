# OpenTickets Community Edition #
An event management and online ticket sales platform, built on top of WooCommerce. The most complete WordPress Ticketing system.

**Keywords:** event tickets, tickets, ticketing, event ticketing, ticket sales, events, event management, ecommerce, wordpress  

**Requires** at least: WordPress 4.0
Tested up to: WordPress 4.7.0  
**Stable tag:** master  
**Changelog** See the [Release Notes](RELEASE_NOTES.md)

**Copyright:** Copyright (C) 2009-2017 Quadshot Software LLC  
**License:** GNU General Public License, version 3 (GPL-3.0)  
**License URI:** http://www.gnu.org/copyleft/gpl.html  

**Donate link:** http://opentickets.com/  

## Special Thanks ##
**Contributors:** quadshot, loushou, coolmann  

**testing and bug reports**
@bradleysp, @petervandoorn

**translations**
@ht-2, @luminia, @ton, @firgolitsch, @jtiihonen, @diplopito, @galapas

## Frequently Asked Questions ##

The FAQ's for OpenTickets Community Edition is currently located on [our website's FAQs Page](http://opentickets.com/faq).

# Introduction #

[OpenTickets Community Edition](http://opentickets.com/community-edition "Event management and online ticket sales platform") ("OTCE") is a free, open source WordPress plugin that allows you to publish events and sell event tickets online. OTCE was created to allow people with WordPress websites to easily setup and sell tickets to their events.

OTCE is an alternative to other ticketing systems, that will reduce your overhead and eliminate service fees, because it is software you run on your own existing website. It was created for venues, artists, bands, nonprofits, festivals and event organizers who sell General Admission tickets.

OTCE runs on [WordPress](http://wordpress.org/ "Your favorite software") and requires [WooCommerce](http://woocommerce.com/ "Free WordPress based eCommerce Software") to operate. WooCommerce is a free open source ecommerce platform for WordPress. You can download your own copy of that from the [WooCommerce Wordpress.org Plugin Page](https://wordpress.org/plugins/woocommerce/)

With WordPress and WooCommerce installed, you can install the OTCE plugin and start selling event tickets wihtin a few minutes. OTCE information and instructions are available on our website's [Community Edition page](http://opentickets.com/community-edition/ "Visit the Community Edition information page"), or you can watch some of our videos on how to get started on our [Videos page](http://opentickets.com/videos/ "Visit our videos page").

The OTCE plugin empowers you with tools to:

* Create and Sell Event Tickets
* Display Calendar of Events
* Publish Venues
* Publish Events
* Allow Customers to keep Digital and/or Print e-Tickets
* Checkin People to your Events with a QR Reader
* Event Ticket Reporting

## Enterprise Premium Extensions ##
This is a core plugin, but you can add even more functionality with licensed premium extensions.

See the complete list of the [available extensions at http://opentickets.com/extensions](http://opentickets.com/extensions).

Some features available as extensions:
* Seating Charts
* Box Office
* Coupons & Passes
* Multi-Pricing
* Additional Event Displays
* Advanced Reporting

## Need some help? ##

Need help creating your first ticket and setting up your first event? Visit the [OpenTickets Community Edition Basic Help](http://opentickets.com/community-edition/#your-first-event) and follow the steps under _Creating your first Event, Start to Finish_.

 Watch some of our Instructional Videos to learn how to install OpenTickets and setup an event!

1. [Installation](http://youtu.be/7syX3-oXDLg "Basic Installation Video")
1. [Setting up your First Event](http://youtu.be/Y4Sr9hPcbwY "Step-by-step instructions for setting up your First Event")
1. [Using the Event Calendar](http://youtu.be/sq-sPkFxobc "Demonstrates the power of the calendar")
1. For a full list of our Instructional Videos, visit [our website's videos page](http://opentickets.com/videos "OpenTickets.com Videos Page")

## Get Involved ##

Are you developer? Want to contribute to the source code? Check us out on the [OpenTickets Community Edition GitHub Repository](https://github.com/quadshot/opentickets-community).

# Instructional Videos #

If you are more of a 'just give me a video to show me how to do it' type person, then we have a few new videos that can help show you how to Install and Setup OpenTickets.

1. [Installation](http://youtu.be/7syX3-oXDLg "Basic Installation Video")
1. [Setting up your First Event](http://youtu.be/Y4Sr9hPcbwY "Step-by-step instructions for setting up your First Event")
1. [Using the Event Calendar](http://youtu.be/sq-sPkFxobc "Demonstrates the power of the calendar")
1. For a full list of our Instructional Videos, visit [our website's videos page](http://opentickets.com/videos "OpenTickets.com Videos Page")

# Basic Installation #

These instructions are pretty universal, standard methods employed when you install any plugin. If you have ever installed one for your WordPress installation, you can probably skip this part.

## The below instructions assume that you have: ##

1. Downloaded the OpenTickets software from WordPress.org.
1. Have already installed WooCommerce, and set it up to your liking.
1. Possess a basic understanding of WooCommerce concepts, as well as how to create products.
1. Have either some basic knowledge of the WordPress admin screen or some basic ftp and ssh knowledge.
1. The ability to follow an outlined list of instructions. ;-)

## Via the WordPress Admin: ##

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

## Via SSH: ##

1. FTP the file you downloaded from http://WordPress.org/ to your server. (We assume you know how to do this)
1. Login to your server via ssh. (.... you know this too).
1. Issue the command `cd /path/to/your/website/installation/wp-content/plugins/`, where /path/to/your/website/installation/wp-content/plugins/ is the plugins directory on your site.
1. `cp /path/to/opentickets-community.zip .`, to copy the downloaded file to your plugins directory.
1. `unzip opentickets-community.zip`, to unzip the downloaded file, creating an opentickets directory inside your plugins directory.
1. Open a browser, and follow steps 1-2 under 'Via the WordPress Admin' above, to get to your 'plugins' page.
1. Find OpenTickets on your plugins list, click 'Activate' which is located directly below the name of the plugin.
1. Start using OpenTickets Community Edition.

# Start using it #

## Setup a 'ticket product': ##

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Products' menu item on the left sidebar.
1. Near the 'Products' page title, click the 'Add Product' button.
1. Fill out the product name and product description, in the middle column of the page.
1. In the 'Product Data' metabox, check the box next to 'Ticket'
1. In the upper right hand metabox labeled 'Publish', edit the 'Catelog visibility' and change it to 'hidden'.
1. Fill out any other information you may require, and then click the blue 'Publish' button.

## Setup a 'Venue': ##

1. Login to the admin dashboard of your WordPress site.
1. Click the 'Venues' menu item on the left sidebar.
1. Near the 'Venues' page title, click the 'Add Venue' button.
1. Fill out the venue name and the venue description, as you did with the ticket product.
1. Further down the middle column, fill out the 'Venue Information' metabox
1. If applicable, fill out the 'Venue Social Information' metabox.
1. Complete any other information you wish on the page, and click the blue 'Publish' button.

## Setup an 'Event Area': ##

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

## Setup an 'Event': ##

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

# Concepts #

The software has three key concepts that must be defined before you can create an event. These concepts provide the context for your 'Event', as well as the ticketing product that you will sell for your event.

First you need a 'Venue', which in general terms, is just a location that has areas which can be used to host events. A good example of a Venue would be a Hotel. Hotels, generally speaking, have multiple conference rooms available for rental. Ergo, on any given day, during any given time, any number of these several conference rooms could be occupied with a different event.

Then you need an 'Event Area'. In general, an event area is meant to represent a sub-location of the Venue; for instance, a conference room inside the aforementioned Hotel. Each room may have it's own configurations of seats, it's own stage position, it's own entrances and exits, and it's own pricing. There are scenarios in which this does not entirely hold up as an example, but in general, try to think of it this way.

With this information, we can now piece together an event. An event is hosted by a 'Venue' and has pricing and a layout designated in the 'Event Area'.
