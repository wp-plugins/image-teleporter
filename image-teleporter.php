<?php
/**
 * Plugin Name: Image Teleporter
 * Plugin URI: http://www.BlueMedicineLabs.com/
 * Description: This plugin waves a magic wand and turns images that are hosted elsewhere (like in your Flickr account or on another website) into images that are now in your Media Library. The code on your page is automatically updated so that your site now uses the version of the images that are in your Media Library instead.
 * Version: 1.1.4
 * Author: Blue Medicine Labs
 * Author URI: http://www.BlueMedicineLabs.com/
 * License: GPL2
 */

/*  Copyright 2013  Blue Medicine Labs  (email : us@bluemedicinelabs.com)

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

require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$bml_it_count = 0;

// PHP_VERSION_ID is available as of PHP 5.2.7, if our 
// version is lower than that, then emulate it
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);

    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

if ( is_admin() ) { // admin actions
	add_action('admin_menu', 'bml_it_menu');
	add_action('admin_init', 'bml_it_init');
}
register_activation_hook(__FILE__, 'bml_it_install');

add_action('save_post', 'bml_it_find_imgs');


// Primary function runs on Save Post
function bml_it_find_imgs ($post_id) {

	// Check for AutoSave and cancel if it is.
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	
	// Check for post revision and cancel if it is.
	if (wp_is_post_revision($post_id)) return;
	
	// Verify against settings that we are teleporting this category
	if ($c=get_option('bml_it_catlist')) {
		$catfound = false;
		$catlist = get_the_category($post_id);
		foreach ($catlist as $category) {
			if (in_array($category->cat_ID, explode(',', $c)))
				$catfound = true;
		}
		if (!$catfound) return;
	}
	
	$post = get_post($post_id);
	$a = get_option('bml_it_authlist');
	if($a && !in_array($post->post_author, explode(',', $a))) return;
	
	$l = get_option('bml_it_replacesrc');
	$k = get_option('bml_it_custtagname');
	$processed = get_post_custom_values($k, $post_id);
	$replaced = false;
	$content = $post->post_content;
	$imgs = bml_it_get_img_tags($post_id);
	global $bml_it_count;
	
	for($i=0; $i<count($imgs); $i++) {
		if (!$processed || !in_array($imgs[$i], $processed)) {
			
			$parseurl = parse_url($imgs[$i]);
			$pathname = $parseurl['path'];
			$filename = substr(strrchr($pathname, '/'), 1);
			if (preg_match ('/(\.php|\.aspx?)$/', $filename) ) $filename .= '.jpg';
			$imgid   = bml_it_sideload(strtok($imgs[$i], '?'), $filename, $post_id);
			$imgpath = wp_get_attachment_url($imgid);
			if (!is_wp_error($imgpath)) {
				if ($l=='custtag') {
					add_post_meta($post_id, $k, $imgs[$i], false);
				} else {
					$trans = preg_quote($imgs[$i], "/");
					$content = preg_replace('/(<img[^>]* src=[\'"]?)('.$trans.')/', '$1'.$imgpath, $content);
					$replaced = true;
				}
				$processed[] = $imgs[$i];
				$bml_it_count++;
			}
		}
	}
	if ($replaced) {
		$upd = array();
		$upd['ID'] = $post_id;
		$upd['post_content'] = $content;
		wp_update_post($upd);
	}
}

// Grab the extention of the file
function bml_it_getext ($file) {
	if ( PHP_VERSION_ID > 50299 )
		$mime = _mime_content_type($file);
	elseif ( function_exists('mime_content_type') )
		$mime = mime_content_type($file);
	else return '';
	switch($mime) {
		case 'image/jpg':
		case 'image/jpeg':
			return '.jpg';
			break;
		case 'image/gif':
			return '.gif';
			break;
		case 'image/png':
			return '.png';
			break;
	}
	return '';
}

function _mime_content_type($filename) {
	// Instantiate finfo
	$result = new finfo();
	// Get Mime Type with PHP 5.3 compatible method
	if (is_resource($result) === true) {
		return $result->file($filename, FILEINFO_MIME_TYPE);
	}
	
	return false;
}

function bml_it_sideload ($file, $url, $post_id) {
	if(!empty($file)){
		$file_array['tmp_name'] = download_url($file);
		if(is_wp_error($file_array['tmp_name'])) return;
		$file_array['name'] = basename($file);
		$desc = bml_it_getext($file);

		$pathparts = pathinfo($file_array['tmp_name']);
		if (''==$pathparts['extension']) {
			$ext = bml_it_getext($file_array['tmp_name']);
			rename($file_array['tmp_name'], $file_array['tmp_name'] . $ext);
			$file_array['name'] = basename($file_array['tmp_name']) . $ext;
			$file_array['tmp_name'] .= $ext;
		}

		$id = media_handle_sideload($file_array, $post_id, $desc);
		$src = $id;

		if(is_wp_error($id)) {
			@unlink($file_array['tmp_name']);
			return $id;
		}
	}
	if (!empty($src)) return $src;
	else return false;
}

function bml_it_get_img_tags ($post_id) {
	$post = get_page($post_id);
	$w = get_option('bml_it_whichimgs');
	$s = get_option('siteurl');
	
	$result = array();
	preg_match_all('/<img[^>]* src=[\'"]?([^>\'" ]+)/', $post->post_content, $matches);
	for ($i=0; $i<count($matches[0]); $i++) {
		$uri = $matches[1][$i];
		
		//only check FQDNs
		if (preg_match('/^https?:\/\//i', $uri)) {
			//make sure it's not external
			if ($s != substr($uri, 0, strlen($s)) ) {
				//only match Flickr images?
				if($w == 'All' || 
				   ($w == 'Flickr' && preg_match('/^http:\/\/[a-z0-9]+\.static\.flickr\.com\//', $uri)) ) {
					$result[] = $matches[1][$i];
				}
			}
		}
	}
	return $result;
}

function bml_it_savefile ($file, $url, $post_id) {
	$time = null;
	
	$uploads = wp_upload_dir($time);
	$filename = wp_unique_filename( $uploads['path'], $url, $unique_filename_callback );
	$savepath = $uploads['path'] . "/$filename";
	
	if($fp = fopen($savepath, 'w')) {
		fwrite($fp, $file);
		fclose($fp);
	}
	
	$wp_filetype = wp_check_filetype( $savepath, $mimes );
	$type = $wp_filetype['type'];
	$title = $filename;
	$content = '';
	
	// Construct the attachment array
	$attachment = array(
						'post_mime_type' => $type,
						'guid' => $uploads['url'] . "/$filename",
						'post_parent' => $post_id,
						'post_title' => $title,
						'post_content' => $content
						);
	
	// Save the data
	$id = wp_insert_attachment($attachment, $savepath, $post_id);
	if ( !is_wp_error($id) ) {
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	} else return '';
	return $uploads['url'] . "/$filename";
}


//modified from code found at http://www.bin-co.com/php/scripts/load/
function bml_it_loadimage ($url) {
	
	$url_parts = parse_url($url);
	$ch = false;
	$info = array(//Currently only supported by curl.
				  'http_code'    => 200
				  );
	$response = '';
	
	$send_header = array(
						 'Accept' => 'text/*',
						 'User-Agent' => 'Image-Teleporter WordPress Plugin (http://www.BlueMedicineLabs.com/)'
						 );
	
	
	
	///////////////////////////// Curl /////////////////////////////////////
	//If curl is available, use curl to get the data.
	if(function_exists("curl_init")) {  //$options['use'] == 'fsocketopen'))) { //Don't use curl if it is specifically stated to use fsocketopen in the options
		
		$page = $url;
		
		$ch = curl_init($url_parts['host']);
		
		curl_setopt($ch, CURLOPT_URL, $page) or die("Invalid cURL Handle Resouce");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Just return the data - not print the whole thing.
		curl_setopt($ch, CURLOPT_HEADER, true); //We need the headers
		curl_setopt($ch, CURLOPT_NOBODY, false); //Yes, get the body.
		
		//Set the headers our spiders sends
		curl_setopt($ch, CURLOPT_USERAGENT, $send_header['User-Agent']); //The Name of the UserAgent we will be using ;)
		$custom_headers = array("Accept: " . $send_header['Accept'] );
		curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);
		
		@curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/binget-cookie.txt"); //If ever needed...
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		@curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		if(isset($url_parts['user']) and isset($url_parts['pass'])) {
			$custom_headers = array("Authorization: Basic ".base64_encode($url_parts['user'].':'.$url_parts['pass']));
			curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);
		}
		
		$response = curl_exec($ch);
		$info = curl_getinfo($ch); //Some information on the fetch
		if('http://l.yimg.com/g/images/photo_unavailable.gif'==$info['url']) $body = '';
		curl_close($ch);  //If the session option is not set, close the session.
		
		//////////////////////////////////////////// FSockOpen //////////////////////////////
	} else { //If there is no curl, use fsocketopen - but keep in mind that most advanced features will be lost with this approch.
		if(isset($url_parts['query'])) {
			$page = $url_parts['path'] . '?' . $url_parts['query'];
		} else {
			$page = $url_parts['path'];
		}
		
		if(!isset($url_parts['port'])) $url_parts['port'] = 80;
		$fp = fsockopen($url_parts['host'], $url_parts['port'], $errno, $errstr, 30);
		if ($fp) {
			$out = "GET $page HTTP/1.0\r\n"; //HTTP/1.0 is much easier to handle than HTTP/1.1
			$out .= "Host: $url_parts[host]\r\n";
			$out .= "Accept: $send_header[Accept]\r\n";
			$out .= "User-Agent: {$send_header['User-Agent']}\r\n";
			$out .= "Connection: Close\r\n";
			
			//HTTP Basic Authorization support
			if(isset($url_parts['user']) and isset($url_parts['pass'])) {
				$out .= "Authorization: Basic ".base64_encode($url_parts['user'].':'.$url_parts['pass']) . "\r\n";
			}
			$out .= "\r\n";
			
			fwrite($fp, $out);
			while (!feof($fp)) {
				$response .= fgets($fp, 128);
			}
			fclose($fp);
		}
	}
	
	//Get the headers in an associative array
	$headers = array();
	
	if($info['http_code'] == 404 || $info['http_code'] == 400) {
		$body = '';
		$headers['Status'] = 404;
	} else {
		//Seperate header and content
		$header_text = substr($response, 0, $info['header_size']);
		$body = substr($response, $info['header_size']);
		
		foreach(explode("\n",$header_text) as $line) {
			$parts = explode(": ",$line);
			if(count($parts) == 2) $headers[$parts[0]] = chop($parts[1]);
		}
	}
	
	return $body;
}

function bml_it_backcatalog ($batch,$offset) {
	global $bml_it_count;
	$count = 0;
	if ($batch) {
		if ($offset) {
			$pp = get_posts( array( 'numberposts' => $batch, 'offset' => $offset ) );			
		} else {
			$ppages = get_pages(  );
			$pposts = get_posts( array( 'numberposts'=> $batch ) );
			$pp = array_merge($ppages,$pposts);
		}
	} else {
		$ppages = get_pages(  );
		$pposts = get_posts( array( 'numberposts'=>-1 ) );
		$pp = array_merge($ppages,$pposts);
	}
	foreach ($pp as $p) {
		try {
			echo '<p>[' . $p->ID . '] ' . $p->post_title . ': ';
			bml_it_find_imgs($p->ID);
			echo '<em>' . $bml_it_count . ' images processed.</em></p>';
		} catch (Exception $e) {
			echo '<em>an error occurred</em>.</p>';
		}
		$count += $bml_it_count;
	}
	if (count($pp) < $batch || !$batch) {
		return 1;
	} else {
		return 0;
	}
}

function bml_it_getauthors() {
	global $wpdb;
	$query = "SELECT $wpdb->users.* FROM $wpdb->users ORDER BY display_name;";
	$authors = $wpdb->get_results($query);
	return $authors;
}

function bml_it_menu () {
	if ( function_exists('add_options_page') ) {
		add_options_page('Image Teleporter', 'Image Teleporter', 8, 'bml_it', 'bml_it_options');
	}
}

function bml_it_init () {	
	register_setting('bml_it', 'bml_it_whichimgs');
	register_setting('bml_it', 'bml_it_replacesrc');
	register_setting('bml_it', 'bml_it_custtagname');
	register_setting('bml_it', 'bml_it_cats');
	register_setting('bml_it', 'bml_it_auths');
	register_setting('bml_it', 'bml_it_catlist');
	register_setting('bml_it', 'bml_it_authlist');
}

function bml_it_install () {
	//add default options
	$whichimgs   = get_option('bml_it_whichimgs');
	$replacesrc  = get_option('bml_it_replacesrc');
	$custtagname = get_option('bml_it_custtagname');
	$catlist     = get_option('bml_it_catlist');
	$authlist    = get_option('bml_it_authlist');
	
	if(!$whichimgs)   update_option('bml_it_whichimgs',   'All');
	if(!$replacesrc)  update_option('bml_it_replacesrc',  'replace');
	if(!$custtagname) update_option('bml_it_custtagname', 'imgteleport');
	if(!$catlist)     update_option('bml_it_catlist',     '');
	if(!$authlist)    update_option('bml_it_authlist',    '');
}

function bml_it_options () {
	$_cats  = '';
	$_auths = '';
	echo '<div class="wrap" style="width: 60%;padding: 10px 20px 10px 20px;float: left;">';
	echo '<h1 style="font-size: 38px;font-weight: 300;line-height: 1.25;margin-bottom:10px;">Image Teleporter</h1><i>Add linked images to your Media Library automatically</i>';
	
	if (isset($_POST['action'])) {
		$action = $_POST['action'];
	} else {
		$action = '';
	}

	if ( $action=='signup' ) {
		require_once 'MCAPI.class.php';
		require_once 'config.inc.php';
		$api = new MCAPI($apikey);

		$merge_vars = array('FNAME'=>$_POST['FNAME'], 
		                  'GROUPINGS'=>array(
		                        array('name'=>'Plugins', 'groups'=>'Image Teleporter'),
		                        array('name'=>'Location', 'groups'=>'Plugin Signup'),
		                        array('name'=>'Notification', 'groups'=>'Plugin Updates'),
		                        )
		                    );
		
		// By default this sends a confirmation email - you will not see new members
		// until the link contained in it is clicked!
		if ($_POST['b_07ec85a6e97e7da84feb2fb3d_1b516804a2'] == "") {
			$retval = $api->listSubscribe( $listId, $_POST['EMAIL'], $merge_vars,'html',TRUE,TRUE,FALSE,TRUE );

			if ($api->errorCode){
			    echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>';
				echo "Unable to load listSubscribe()!\n";
				echo "\tCode=".$api->errorCode."\n";
				echo "\tMsg=".$api->errorMessage."\n";
			    echo '</p></div>';
			} else {
			    echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Subscribed - look for the confirmation email!</p></div>';
			}
		}
	}
	
	if ( $action=='backcatalog' ) {
		$done = bml_it_backcatalog($_POST['batch'],$_POST['offset']);
		if ($_POST['batch'] && !$done) {
			echo '<form name="bml_it-backcatalog" method="post" action="">';
			echo '<div class="submit">';
			$offset = $_POST['offset'] + $_POST['batch'];
			echo '<input type="hidden" name="action" value="backcatalog"><input type="hidden" name="offset" value="' . $offset . '"><input type="hidden" name="batch" value="' . $_POST['batch'] . '">';
			echo '<input type="submit" class="button-primary" value="' . __('Continue Processing') . '" />';
			echo '</div>';
			echo '</form>';
		} else {
			echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Finished processing past posts!</p></div>';
		}
	}

	if ( $action=='update' ) {
		update_option('bml_it_whichimgs',   $_POST['bml_it_whichimgs'] );
		update_option('bml_it_replacesrc',  $_POST['bml_it_replacesrc'] );
		update_option('bml_it_custtagname', $_POST['bml_it_custtagname'] );
		if($_POST['bml_it_catlist'])  update_option('bml_it_catlist',  $_cats );
		update_option('bml_it_catlist',  ($_POST['bml_it_catlist'] ) ? implode(',', $_POST['bml_it_cats'] ) : '');
		update_option('bml_it_authlist', ($_POST['bml_it_authlist']) ? implode(',', $_POST['bml_it_auths']) : '');
		echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Settings updated.</p></div>';
	}
	bml_it_signup();
	echo '<h2>Options</h2>';
	echo '<p>The following options will start working the next time you save a Post/Page. (You can also make this work retrospectively on past Posts/Pages using the next section below.)</p>';
	echo '<form name="bml_it-options" method="post" action="">';
	settings_fields('bml_it');
	echo '<table class="form-table"><tbody>';
	echo '<tr valign="top"><th scope="row"><strong>Which external IMG links to process:</strong></th>';
	echo '<td><label for="myradio1"><input id="myradio1" type="radio" name="bml_it_whichimgs" value="All" ' . (get_option('bml_it_whichimgs')!='Flickr'?'checked="checked"':'') . '/> All images</label><br/>';
	echo '<label for="myradio2"><input id="myradio2" type="radio" name="bml_it_whichimgs" value="Flickr" ' . (get_option('bml_it_whichimgs')=='Flickr'?'checked="checked"':'') . ' /> Only Flickr images</label><br/>';
	echo '<p style="font-size: 12px;margin: 8px 10px 0 26px;">By default, all external images are processed.  This can be set to only apply to Flickr.</p>';
	echo '</td></tr>';
	echo '<tr valign="top"><th scope="row"><strong>What to do with the images:</th>';
	echo '<td><label for="myradio3"><input id="myradio3" type="radio" name="bml_it_replacesrc" value="replace" ' . (get_option('bml_it_replacesrc')!='custtag'?'checked="checked"':'') . ' /> Replace SRC attribute with local copy</label><br/>';
	echo '<label for="myradio4"><input id="myradio4" type="radio" name="bml_it_replacesrc" value="custtag" ' . (get_option('bml_it_replacesrc')=='custtag'?'checked="checked"':'') . ' /> Use custom tag:</label> ';
	echo '<input type="text" size="20" name="bml_it_custtagname" value="' . get_option('bml_it_custtagname') . '" /><br/>';
	echo '<p style="font-size: 12px;margin: 8px 10px 0 26px;">Replacing the SRC attribute will convert the external IMG link to a local link, pointed at the local copy downloaded by this plugin. If the SRC attribute is not replaced, the plugin needs to mark the IMG as having been processed somehow, so this is done by tracking processed images in custom_tag values.  You can change the name of the custom tag.</p></td></tr>';
	
	echo '<tr align="top"><th scope="row"><strong>Apply to these categories:</strong></th>';
	echo '<td><label for="myradio5"><input type="radio" id="myradio5" name="bml_it_catlist" value="" ' . (get_option('bml_it_catlist')==''?'checked="checked"':'') . ' /> All categories</label><br/>';
	echo '<label for="myradio6"><input type="radio" id="myradio6" name="bml_it_catlist" value="Y" ' . (get_option('bml_it_catlist')!=''?'checked="checked"':'') . ' /> Selected categories</label><br/>';
	
	$_cats = explode(',', get_option('bml_it_catlist'));
	$chcount = 0;
	$cats = get_categories();
	foreach ($cats as $cat) {
		$chcount++;
		echo '<label for="mycheck'.$chcount.'"><input type="checkbox" id="mycheck'.$chcount.'" name="bml_it_cats[]" value="' . $cat->cat_ID . '" '.(in_array($cat->cat_ID, $_cats)?'checked="checked"':'').' style="margin-left: 25px;" /> ' . $cat->cat_name . '</label><br/>';
	}
	echo '</td></tr>';
	echo '<tr align="top"><th scope="row"><strong>Apply to these authors:</strong></th>';
	echo '<td><label for="myradio7"><input type="radio" id="myradio7" name="bml_it_authlist" value="" ' . (get_option('bml_it_authlist')==''?'checked="checked"':'') . ' /> All authors</label><br/>';
	echo '<label for="myradio8"><input type="radio" id="myradio8" name="bml_it_authlist" value="Y" ' . (get_option('bml_it_authlist')!=''?'checked="checked"':'') . ' /> Selected authors</label><br/>';
	
	$_auths = explode(',', get_option('bml_it_authlist'));
	$auths = bml_it_getauthors();
	foreach ($auths as $auth) {
		$chcount++;
		echo '<label for="mycheck'.$chcount.'"><input type="checkbox" id="mycheck'.$chcount.'" name="bml_it_auths[]" value="' . $auth->ID . '" '.(in_array($auth->ID, $_auths)?'checked="checked"':'').' style="margin-left: 25px;" /> ' . $auth->display_name . '</label><br/>';
	}
	echo '</td></tr>';
	
	echo '</tbody></table>';
	echo '<div class="submit">';
	echo '<input type="submit" name="submit" class="button-primary" value="' . __('Save Changes') . '" />';
	echo '</div>';
	echo '</form>';

	echo '<form name="bml_it-backcatalog" method="post" action="">';
	echo '<div class="wrap">';
	echo '<h2>Process Pre-existing Posts/Pages</h2>';
	echo '<p>Use this function to apply the Image Teleporter to all your pre-existing Pages and Posts. The settings specified above will still be respected.&nbsp;<em>Please note that this can take a long time for sites with a lot of Posts and/or Pages.</em></p>';
	echo '<p>If you leave the&nbsp;<strong>Batch</strong>&nbsp;field empty below, it will process <em>all</em> Posts and Pages. However you can enter a number of posts to process in a batch, and it will process all Pages and then the number of Posts you’ve selected. You will then be provided a <strong>Continue</strong> button to process the next batch.</p>';
	echo '<div class="submit">';
	echo '<input type="hidden" name="action" value="backcatalog"><input type="hidden" name="offset" value="0">';
	echo 'Batch:<input type="text" name="batch" value=""><br /><br /><input type="submit" class="button-primary" value="' . __('Process') . '" />';
	echo '</div>';
	echo '<p>&nbsp;</p>';
	echo '</div>';
	echo '</form>';

	echo '';
	echo '</div>';
	bml_it_sidenav();
	
}
function bml_it_signup() {
	echo '<h2 style="padding-top:20px;">Stay up-to-date</h2>
		<div style="background: none repeat scroll 0 0 #FEFD9B;border: 1px solid #E8E70A;padding: 20px 20px 15px;overflow: auto;">
			<p style="margin-top:0;">Enter your name and email address here and we will email you short announcements whenever we make any 
			important changes to this plugin. Now you’ll know straight away when there is an update available. (You 
			can stop receiving the emails at any time with one simple click.)</p>
			<form name="bml_it-signup" method="post" action="">
			<input type="hidden" name="action" value="signup">
			<div class="formname" style="display: inline-block;float: left;margin: 0 30px 10px 0;">
				<strong>First Name:</strong> <input type="text" value="" name="FNAME" class="required" id="mce-FNAME">
			</div>
			<div class="formemail" style="display: inline-block;float: left;margin: 0 60px 10px 0;">
				<strong>Email:</strong> <input type="email" value="" name="EMAIL" class="required email" id="mce-EMAIL">
			</div>
			<div style="position: absolute; left: -5000px;"><input type="text" name="b_07ec85a6e97e7da84feb2fb3d_1b516804a2" value=""></div>
			<div class="submit" style="padding: 0;">
				<input type="submit" value="Sign up" name="subscribe" id="mc-embedded-subscribe" class="button button-primary">
			</div>
			</form>
		</div>';
}
function bml_it_sidenav () {
	echo '<div class="sidebar" style="width: 240px;float: right;display: inline;">
		<style>
			.wrap p {font-size:14px;}
			.sidebar {
				padding: 20px 20px 0 0;
			}
			.sidebar .widget { 
				background: #F7F7F7;
				border-top: 0px solid #DDDDDD;
				border-bottom: 2px solid #DDDDDD;
				border-left: 0px solid #DDDDDD;
				border-right: 0px solid #DDDDDD;
				margin: 0px 0 15px;
				padding: 0 0 15px;
				-webkit-border-radius: 3px;
				border-radius: 3px;
			}
			.sidebar .widget h4 {
				background: #333333;
				border-top: 0px solid #DDDDDD;
				border-bottom: 2px solid #DDDDDD;
				border-left: 0px solid #DDDDDD;
				border-right: 0px solid #DDDDDD;
				margin: 0;
				padding: 15px 25px 15px 25px;
				color: #FFFFFF;
				font-family: "Open Sans",sans-serif;
				font-size: 16px;
				font-weight: 600;
				line-height: 1.25;
				-webkit-border-radius: 3px;
				border-radius: 3px;
			}
			.sidebar .widget-wrap p {
				margin: 10px 10px 0;
			}
			.sidebar .widget-wrap .button-primary {
				margin: 10px 10px 0;
			}
		</style>';
	bml_it_support();
	bml_it_aboutus();
	bml_it_socialmedia();
	bml_it_donate();
	echo '</div>';
}
function bml_it_support () {
	echo '<section id="swboc" class="widget">
		<div class="widget-wrap">
			<h4 class="widget-title">Having trouble?</h4>
			<p>
				<a href="http://www.bluemedicinelabs.com/submit-ticket/">
					<img class="aligncenter size-full wp-image-125" alt="bugbee" src="' . plugins_url( 'images/bugbee.png' , __FILE__ ) . '" width="215" height="205">
				</a>
				Have you found a bug? Or are you having trouble getting this plugin to work? Are the instructions 
				too geeky to understand? Please let us know right away, so we can help you out.
			</p>
			<div>
				<a title="Get Support" href="http://www.bluemedicinelabs.com/submit-ticket/">
					<input class="button-primary" type="submit" name="submit" value="Get Support">
				</a>
			</div>
		</div>
		</section>';
}
function bml_it_aboutus () {
	echo '<section id="swboc" class="widget">
		<div class="widget-wrap">
			<h4 class="widget-title widgettitle">Who made this plugin?</h4>
			<p>
				<a href="http://bluemedicinelabs.com">
				<img class="aligncenter size-full wp-image-112" alt="Blue-Medicine-Labs-profile-picture" src="' . plugins_url( 'images/Blue-Medicine-Labs-profile-picture.jpg' , __FILE__ ) . '" width="215" height="215">
				</a>
			</p>
			<p>
				<strong>Jason Diehl </strong>&amp;<strong> Trisha Cupra</strong> turn WordPress headaches into successful, pain-free small business websites.
			</p>
			<div>
				<a title="Blue Medicine Labs" href="http://www.bluemedicinelabs.com/">
					<input class="button-primary" type="submit" name="submit" value="Visit our website">
				</a>
			</div>
		</div></section>';
}
function bml_it_socialmedia () {
	echo '<style>
			.metro-social li {
				position: relative;
				cursor: pointer;
				list-style: none;
				margin: 1px;
			}
			.metro-social li a {
				float: left;
				margin: 1px;
				position: relative;
				display: block;
				padding: 0;
			}
			.metro-social .metro-facebook {
				background: url(' . plugins_url( 'images/facebook.png' , __FILE__ ) . ') no-repeat center center #1f69b3;
				width: 47%;
				height: 140px;
			}
			.metro-social .metro-googleplus {
				background: url(' . plugins_url( 'images/google.png' , __FILE__ ) . ') no-repeat center center #da4a38;
				width: 23.3%;
				height: 69px;
			}
			.metro-social .metro-twitter {
				background: url(' . plugins_url( 'images/twitter.png' , __FILE__ ) . ') no-repeat center center #43b3e5;
				width: 23%;
				height: 69px;
			}
			.metro-social .metro-pinterest {
				background:url(' . plugins_url( 'images/pinterest.png' , __FILE__ ) . ') no-repeat center center #d73532;
				width:23.2%;
				height:69px;
			}
			.metro-social .metro-linkedin {
				background:url(' . plugins_url( 'images/linkedin.png' , __FILE__ ) . ') no-repeat center center #0097bd;
				width:23%;
				height:69px;
			}
			.metro-social .metro-youtube {
				background:url(' . plugins_url( 'images/youtube.png' , __FILE__ ) . ') no-repeat center center #e64a41;
				width:47%;
				height:69px;
			}
			.metro-social .metro-rss {
				background:url(' . plugins_url( 'images/feed.png' , __FILE__ ) . ') no-repeat center center #e9a01c;
				width:47%;
				height:69px;
			}
		</style>';
	echo '<section id="swboc" class="widget">
		<div class="widget-wrap"><h4 class="widget-title widgettitle">Connect with us</h4>
			<div class="metro-social" style="width:230px;height: 212px;padding: 10px 10px 0;">
				<li><a class="metro-facebook" target="_blank" href="http://www.facebook.com/bluemedicinelabs"></a></li>
				<li><a class="metro-googleplus" target="_blank" href="https://plus.google.com/101111543664634710017/"></a></li>
				<li><a class="metro-twitter" target="_blank" href="http://www.twitter.com/bluemedicinelab"></a></li>
				<li><a class="metro-linkedin" target="_blank" href="http://www.linkedin.com/company/blue-medicine-labs"></a></li>
				<li><a class="metro-pinterest" target="_blank" href="http://www.pinterest.com/bluemedicinelab"></a></li>
				<li><a class="metro-rss" target="_blank" href="http://bluemedicinelabs.com/feed/"></a></li>
				<li><a class="metro-youtube" target="_blank" href="http://www.youtube.com/bluemedicinelabs"></a></li>
			</div>
        </div>
        </section>';
}
function bml_it_donate () {
	echo '<section id="swboc" class="widget">
		<div class="widget-wrap">
			<h4 class="widget-title">Donate</h4>
			<p>
				Thanks for contributing to our lunch money.
				<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5S792BZ877JT6">
					<img src="' . plugins_url( 'images/sandwich-74330_640.png' , __FILE__ ) . '" alt="sandwich" width="215" height="172" class="aligncenter size-full wp-image-126">
				</a>
			</p>
			<div>
				<a title="Donate" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5S792BZ877JT6">
					<input class="button-primary" type="submit" name="submit" value="Buy us a Sandwich">
				</a>
			</div>
		</div>
		</section>';
}

?>
