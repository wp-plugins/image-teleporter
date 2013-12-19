=== Image Teleporter ===
Contributors: bluemedicinelabs, internetmedicineman, trishacupra 
Donate link: http://www.BlueMedicineLabs.com/
Tags: images, gallery, photobloggers, attachments, photo, links, external, photographers, Flickr, save, download
Requires at least: 2.7
Tested up to: 3.8
Stable tag: 1.1.0

Makes local copies of all the linked images in a post or page, adding them as gallery attachments.

== Description ==

Create local copies of external images in the src attribute of img tags.  This plugin extracts a list of IMG tags in the post, saves copies of those images locally as gallery attachments on the post.

= Features =
* Finds all external images linked in the SRC attribute of IMG tags and makes local copies of those images
* Allows the SRC to be updated to point to those local copies
* Can be applied to posts in all categories, or only those selected
* Can be applied to all authors, or only selected authors

Administrator has the option to replace the external src with the url of the local copy. Another option allows the plugin to be applied to all external images, or only to those on Flickr.

This plugin is particularly useful for photobloggers, especially those who update using the mail2blog Flickr API.   The plugin will saved the linked image file from Flickr locally.

= Video =
[youtube http://www.youtube.com/watch?v=VpLp6OqFwWw]

= Stay Up to Date =
* Follow us on Facebook: https://www.facebook.com/bluemedicinelabs
* Visit our Website: http://www.BlueMedicineLabs.com/

= Planned features: =
* Code to work with spaces and some legal special characters
* Add internationalization support
* Integrate with Flickr API in order to allow always downloading the original image size regardless of which is linked

= Credits: =
This plugin is based on the work done in the "Add Linked Images to Gallery" plugin by http://www.bbqiguana.com/

== Installation ==

1. Download the Image Teleporter zip file.
2. Extract the files to your WordPress plugins directory.
3. Activate the plugin via the WordPress Plugins tab.

== Frequently Asked Questions ==

= How does this plugin work? =

The plugin examines the HTML source of your post when you save it, inspecting each IMG tag, and processing them according to the options you have selected.  

Under the default settings, it will find IMG tags with links to images on other web sites and copy those images to your web site, updating the IMG src to point to your copy.

= Is that illegal or unethical? =

I built this plugin for the purpose on one-click publishing of my photoblog. I publish my own photos (to which I have all rights) to Flickr, and then my plugin copies the file from Flickr's server to my own web server.

Yes, there are numerous ways that this plugin could be used unethically, but there are just as many perfectly reasonable uses for it.  I leave it to you to make the right decision.

= How can I know that it is working? =

* Create a new post, and add a link to a test image (such as one on your Flickr account).
* Now click the "Save Draft" button.
* If your editor is in HTML mode, you will see that the SRC attribute has changed.
* If not, you can click on the Add Image icon and you will see a new image has been added to the Gallery for this post.

== Screenshots ==

1. This screenshot is the admin screen you will find under Settings.

== Changelog ==

= 1.1.0 =
* Fullfilled Feature request to allow batch processing of past posts in cases where there are thousands of posts. Implemented batch feature. If you leave it blank it processes everything. If you add a number you are defining the batch it will process each time. When the batch is finished a Continue button is available to continue to the next batch. Thanks Nadeem Khan (chillopedia) for the suggestion!

= 1.0.7 =
* Left default in place of only pulling last 5 posts, fixed to include all posts.

= 1.0.6 =
* Fixed bug where proccessing old posts would ignore posts and only process pages. Now Everything is processed.

= 1.0.5 =
* Added YouTube Video

= 1.0.4 =
* Added Screenshot.

= 1.0.3 = 
* Added the ability to handle HTTPS URL's and ignore case in URL's.

= 1.0.2 = 
* Working on cleaning up code
* Adding comments into the code and preparing to convert to Class

= 1.0.1 = 
* Added the ability to work on all Pages/Posts/Custom Post Types

= 1.0.0 = 
* Forked Plugin from BBQ Iguana at: http://bbq-iguana.appspot.com/wordpress-plugins/add-linked-images-to-gallery/
* Previous developer dropped all development of Plugin.
* New Plugin, New Name, all previous changelogs can be viewed from original developer.