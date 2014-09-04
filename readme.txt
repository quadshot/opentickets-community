=== OpenTickets Community Edition ===
Contributors: quadshot, loushou, camu
Donate link: http://opentickets.com/
Tags: events, event management, tickets, ticket sales, ecommerce
Requires at least: 3.6.1
Tested up to: 3.9.2
Stable tag: trunk
Copyright: Copyright (C) 2009-2014 Quadshot Software LLC
License: GNU General Public License, version 3 (GPL-3.0)
License URI: http://www.gnu.org/copyleft/gpl.html

An event managment and online ticket sales platform, built on top of WooCommerce.

== Description ==

OpenTickets Community Edition

[OpenTickets Community Edition](http://opentickets.com/community-edition "Event managment and online ticket sales platform") (“OT-CE”) is a free open source WordPress plugin that allows you to publish events and sell tickets online. OT-CE was created to allow people with WordPress websites to easily setup and sell tickets to their events. 

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

== Installation ==

= Basic Installation =

These instructions are pretty universal, standard methods employed when you install any plugin. If you have ever installed one for your WordPress installation, you can probably skip this part.

The below instructions assume that you have:
1. Downloaded the OpenTickets software from WordPress.org.
2. Have already installed WooCommerce, and set it up to your liking.
3. Possess a basic understanding of WooCommerce concepts, as well as how to create products.
4. Have either some basic knowledge of the WordPress admin screen or some basic ftp and ssh knowledge.
5. The ability to follow an outlined list of instructions. ;-)

Via the WordPress Admin:
1. Login to the admin dashboard of your WordPress site.
2. Click the 'Plugins' menu item on the left sidebar, usually found somewhere near the bottom.
3. Near the top left of the loaded page, you will see an Icon, the word 'Plugins', and a button next to those, labeled 'Add New'. Click 'Add New'.
4. In the top left of this page, you will see another Icon and the words 'Install Plugins'. Directly below that are a few links, one of which is 'Upload'. Click 'Upload'.
5. On the loaded screen, below the links described in STEP #4, you will see a location to upload a file. Click the button to select the file you downloaded from http://WordPress.org/.
6. Once the file has been selected, click the 'Install Now' button.
6.a. Depending on your server setup, you may need to enter some FTP credentials, which you should have handy.
7. If all goes well, you will see a link that reads 'Activate Plugin'. Click it.
8. Once you see the 'Activated' confirmation, you will see new icons in the menu.
9. Start using OpenTickets Community Edition.

Via SSH:
1. FTP the file you downloaded from http://WordPress.org/ to your server. (We assume you know how to do this)
2. Login to your server via ssh. (.... you know this too).
3. Issue the command 'cd /path/to/your/website/installation/wp-content/plugins/', where /path/to/your/website/installation/wp-content/plugins/ is the plugins directory on your site.
4. 'cp /path/to/opentickets-community.zip .', to copy the downloaded file to your plugins directory.
5. 'unzip opentickets-community.zip', to unzip the downloaded file, creating an opentickets directory inside your plugins directory.
6. Open a browser, and follow steps 1-2 under 'Via the WordPress Admin' above, to get to your 'plugins' page.
7. Find OpenTickets on your plugins list, click 'Activate' which is located directly below the name of the plugin.
8. Start using OpenTickets Community Edition.

= Start using it =

Setup a 'ticket product':
1. Login to the admin dashboard of your WordPress site.
2. Click the 'Products' menu item on the left sidebar.
3. Near the 'Products' page title, click the 'Add Product' button.
4. Fill out the product name and product description, in the middle column of the page.
5. In the 'Product Data' metabox, check the box next to 'Ticket'
6. In the upper right hand metabox labeled 'Publish', edit the 'Catelog visibility' and change it to 'hidden'.
7. Fill out any other information you may require, and then click the blue 'Publish' button.

Setup a 'Venue':
1. Login to the admin dashboard of your WordPress site.
2. Click the 'Venues' menu item on the left sidebar.
3. Near the 'Venues' page title, click the 'Add Venue' button.
4. Fill out the venue name and the venue description, as you did with the ticket product.
5. Further down the middle column, fill out the 'Venue Information' metabox
6. If applicable, fill out the 'Venue Social Information' metabox.
7. Complete any other information you wish on the page, and click the blue 'Publish' button.

Setup an 'Event Area':
1. Login to the admin dashboard of your WordPress site.
2. Click the 'Venues' menu item on the left sidebar.
3. Click the title of the venue you created earlier, as if to edit the venue information.
4. Find the metabox labeled 'Event Areas'.
5. Click the 'add' button inside the Event Areas metabox.
6. Click 'Select Image' and use the media library to choose an image that shows the layout of the event.
7. Give the area a name.
8. Set a positive 'capacity'. This should be the maximum number of tickets available for this event.
9. Select the 'ticket product' you created earlier, under the 'Available Pricing' option.
10. Click the blus 'save' button inside the metabox.

Setup an 'Event':
1. Login to the admin dashboard of your WordPress site.
2. Click the 'Events' menu item on the left sidebar.
3. Near the 'Events' page title, click the 'Add Event' button.
4. Fill out the event name and description, in the middle column of the page.
5. Setup the showing date and times in the 'Event Date Time Settings' metabox, which has a similar interface to Google Calendar.
5.a. Click the 'New Event Date' button in the middle of the calendar header.
5.b. Fillout the starting and ending date and time of the first day of this event.
5.c. If this event will happen more than once, check the 'repeat...' checkbox
5.c.i. Fill out the repeat interval information
5.d. Once all the event date and repeat interval information is filled out, click the blue 'Add to Calendar' button.
5.e. Verify that your event dates have been added to the calendar, by browsing the calendar using the calendar navigation.
6. Further down in the 'Event Date Time Settings' box, under the 'Event Date/Times' heading, find the list of events you just created.
7. Using standard 'selection techniques' (eg: "shift" to select adjacent items, "cmd/ctrl" to select individual items), select any number of your event showings.
8. On the right hand side of the list, some basic settings will appear. Adjust the settings accordingly. 
8.a. Visibility - determines who can see the showing, and where it appears.
8.b. Venue - the "Venue" in which the showing is taking place.
8.c. Area / Price - the "Event Area" and accompanying ticket price for the event
9. Click the blue 'Publish' button in the upper right metabox

== Changelog ==

= 1.2.5 =
* initial public release

== Arbitrary section ==

This software is designed with the idea that there are several components that make up an event, all of which have a specific association to the next, and all of which work together to define the finished product. 

First you need a 'Venue', which in general terms, is just a location that has areas which can be used to host events. A good example of a Venue would be a Hotel. Hotels, generally speaking, have multiple conference rooms available for rental. Ergo, on any given day, during any given time, any number of these several conference rooms could be occupied with a different event.

Then you need an 'Event Area'. In general, an event area is meant to represent a sub-location of the Venue; for instance, a conference room inside the aforementioned Hotel. Each room may have it's own configurations of seats, it's own stage position, it's own entrances and exits, and it's own pricing. There are scenarios in which this does not entirely hold up as an example, but in general, try to think of it this way.

With this information, we can now piece together an event. An event is hosted by a 'Venue' and has pricing and a layout designated in the 'Event Area'.

Hopefully this clears up some general concepts about the idea behind the software.
