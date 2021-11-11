=== The Tech Tribe ===
Contributors: nigelmoore1, allan.casilum
Donate link: thetechtribe.com
Tags: comments, spam
Requires at least: 5.0
Tested up to: 5.8
Stable tag: 0.12.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Tech Tribe plugin allows Tech Tribe members to automatically post Blog content to their Wordpress website.

== Description ==

The Tech Tribe plugin allows members of the Tech Tribe to automatically post blog content to their website from the Monthly Marketing Packs included in their Membership. 

It allows members to:

* Set what Author they want as Default on all the Posts
* Decide between Automatic posting or Manual posting in case they want to check first

You can find out more about The Tech Tribe at: https://thetechtribe.com/

== Installation ==

Simply follow these steps:

1. Install the Plugin from the Wordpress Marketplace
2. Activate the Plugin through the 'Plugins' menu in Wordpress
3. Go to the main page of the Plugin and paste in your API Key to Verify
(your API Key can be found at https://portal.thetechtribe.com/my-tribe-account)
4. Once it has been activated, go to the Settings tab and select your Default Author and whether you want your posts to be published Automatically or Manually
5. If you want to kick off a Manual Import, click on the Import Tab and click the "START MANUAL IMPORT" button 

== Frequently Asked Questions ==

= What is the Tech Tribe? =

The Tech Tribe is a program & community for the owners of MSP & IT Support Businesses chock full of resources, templates & workshops to help MSPs & ITSPs better run & grow their business. 

You can find out more at https://thetechtribe.com/

= Where can I get Help or Support? =

Simply shoot an email to help@thetechtribe.com if you ever need any assistance. 

If you're having an error, make sure to include screenshots and any details that might help us work out what is going wrong. 

= What does this plugin do? =

One of the benefits our Tech Tribe members get every month is freshly written blog posts that they can use in their Marketing.

This plugin automatically pulls down those Blog Posts and publishes them on their site so they don't have to lift a finger in the publishing process. 

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Screenshots are stored in the /assets directory.

== Changelog ==

= 0.12.0 =
* Improve the data sanitization both output and input.
* Update the 3rd party assets.
* Improve the importing posts with categories, accept multiple categories.

= 0.11.0 =
* Improve status notification.
* Seperate API Key to its own tab.
* Remove extra carriage return in the content.
* Add log for debug purposes.

= 0.10.0 =
* Fix inline image in the content, wherein the path is not changed according to the wordpress media settings.

= 0.9.1 =
* Update notification message when no blogs to import.
* Added progress status when importing blogs.
* Improve activating and deactivating cron jobs, set cron jobs only if the status is active and remove if not.

= 0.8.1 =
* Add end line attribute on each of the end of content.

= 0.8.0 =
* Fix the next schedule display.
* Update the text label in date Import status tab.
* Fix the settings update, if publish post and author only changed then update that changes only.

= 0.5.0 =
* Update status verbage when success.

= 0.4.0 =
* Add next schedule cron to be display.

= 0.3.0 =
* Updated the UI to make it just two tabs, settings and import manual.

= 0.2.0 =
* Created the UI page settings.

= 0.1.0 =
* Bootstrap Build of the plugin.

== Upgrade Notice ==

= 0.1.0 =
Beta version.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`