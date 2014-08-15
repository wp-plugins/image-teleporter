=== Image Teleporter ===
Contributors: bluemedicinelabs, internetmedicineman, trishacupra 
Donate link: http://www.BlueMedicineLabs.com/
Tags: images, gallery, photobloggers, attachments, photo, links, external, photographers, Flickr, save, download
Requires at least: 2.7
Tested up to: 3.9.2
Stable tag: 1.1.4

This plugin turns images that are hosted elsewhere into images that are now in your Media Library, and the code on your page is automatically updated.

== Description ==

Do you have images in your WordPress that are hosted on other sites, such as Flickr? And do you want a magical way to have all those images (past and future) automatically copied into your Media Library? And would you like the code of your site to be automatically updated to point to the new image in your Media Library rather than the external link to Flickr or other site?

Maybe you're a photoblogger and you add your photos to Flickr, and then embed them into a new post in your WordPress site. Or maybe you're moving an old static HTML website to WordPress and you don't want to have to download and upload every single image and modify the image code in WordPress to reflect the changes.

= Here are some problems we can help you with: =

* You can't create a WP gallery or post thumbnails with images that aren't in your WP Media Library. That's annoying.
* You copy an article from another site you own, including the code for the images that go with it. Later in future, you change the article on the other site and delete one of the images, forgetting that you're using that image on your WP site. Now you have a missing image on the second article, and you don't even realize it. That's embarrassing.
* What happens if Flickr (or any other image hosting service you may use) goes down, or even goes bust, or something bad happens to your account? Your images on your WP site will all disappear. That's scary.
* Moving a static HTML website, or a blog on another platform (Blogger, Typepad, WordPress.com, etc) can be a real hassle when it comes to your images. Often your new WP site's code will still be using the images from your old site. If you get rid of your old site, all your images will suddenly disappear along with it. That's a major pain in the backside (especially if you don't have backups of all those images.)

= What does the Image Teleporter plugin do? =

= The Geeky Version =
(Skip over this section if you aren't a techo-wiz)

Create local copies of external images in the src attribute of img tags. This plugin extracts a list of IMG tags in the post, and saves copies of those images locally as gallery attachments on the post.

= Features =
* Finds all external images linked in the SRC attribute of IMG tags and makes local copies of those images
* Allows the SRC to be updated to point to those local copies
* Can be applied to posts in all categories, or only those selected
* Can be applied to all authors, or only selected authors
* Administrator has the option to replace the external src with the url of the local copy. Another option allows the plugin to be applied to all external images, or only to those on Flickr.
* This plugin is particularly useful for photobloggers, especially those who update using the mail2blog Flickr API. The plugin will saved the linked image file from Flickr locally.

= The Plain English Translation =
This plugin waves a magic wand and turns images that are hosted elsewhere (like in your Flickr account or on another website) into images that are now in your Media Library. The code on your page is automatically updated so that your site now uses the version of the images that are in your Media Library instead. It saves them as 'gallery attachments' to the pages/posts they are on, so you can create a WordPress Gallery with the images now.

= Features =
* Finds all the images on every Post and Page that haven't been uploaded to your Media Library (because you used the Flicker HTML code, for example), and automatically adds them to your Media Library as though you had originally uploaded them manually (i.e. they are 'teleported' to your website)
* Updates your WordPress code to point to those copies that are now in your Media Library
* You can choose whether you want Image Teleporter to run on posts in all categories, or only those you specifically want it to work on
* You can choose whether you want Image Teleporter to run on posts by all authors, or only selected authors
* You can run Image Teleporter only on new Posts and Pages, or to run on all past and future images.
* Another option allows the Image Teleporter to be applied to all external images, or only to those on Flickr.

= Video: How the Image Teleporter works =
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

= 1.1.3 =
* Fixed some PHP Notices and Warnings found by David Law (seo-dave).

= 1.1.2 =
* Polished code and cleaned up some functions.
* Added additional credit and donation attributes to settings page.

= 1.1.1 =
* Reported bug - not working on PHP 5.3.0. Updated functions to use finfo information instead of mime_content_type for PHP 5.3.0 or greater
* Reported bug - Doesn't work on images with GET variables on the end of the URL. Added strtok to clean off the GET Variables ^ Might need to add functionality to turn on or off that feature.

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
