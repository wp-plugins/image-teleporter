<?php
/**
 * Plugin Name: Image Teleporter
 * Plugin URI: http://www.BlueMedicineLabs.com/
 * Description: Add linked images to your Media Library automatically. Examines the text of a post/page and makes local copies of all the images linked though IMG tags, adding them as gallery attachments on the post/page itself.
 * Version: 1.0.6
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
			$imgid   = bml_it_sideload($imgs[$i], $filename, $post_id);
			$imgpath = wp_get_attachment_url($imgid);
			if (!is_wp_error($imgpath)) {
				if ($l=='custtag') {
					add_post_meta($post_id, $k, $imgs[$i], false);
				} else {
					$trans = preg_quote($imgs[$i], "/");
					$content = preg_replace('/(<img[^>]* src=[\'"]?)('.$trans.')/', '$1'.$imgpath, $content);
					$replaced = true;
				}
				$processed[] = $imgs[i];
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
	if (function_exists('mime_content_type'))
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

function bml_it_backcatalog () {
	global $bml_it_count;
	$count = 0;
	$ppages = get_pages(  );
	$pposts = get_posts(  );
	$pp = array_merge($ppages,$pposts);
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
	echo '<div class="wrap">';
	echo '<h2>Image Teleporter (Add linked images to your Media Library automatically)</h2>';

	if ($_POST['action']=='backcatalog') {
		bml_it_backcatalog();
		echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Finished processing past posts!</p></div>';
	}

	if ($_POST['action']=='update') {
		update_option('bml_it_whichimgs',   $_POST['bml_it_whichimgs'] );
		update_option('bml_it_replacesrc',  $_POST['bml_it_replacesrc'] );
		update_option('bml_it_custtagname', $_POST['bml_it_custtagname'] );
		if($_POST['bml_it_catlist'])  update_option('bml_it_catlist',  $_cats );
		update_option('bml_it_catlist',  ($_POST['bml_it_catlist'] ) ? implode(',', $_POST['bml_it_cats'] ) : '');
		update_option('bml_it_authlist', ($_POST['bml_it_authlist']) ? implode(',', $_POST['bml_it_auths']) : '');
		echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Settings updated.</p></div>';
	}
	echo '<big>Options</big>';
	echo '<form name="bml_it-options" method="post" action="">';
	settings_fields('bml_it');
	echo '<table class="form-table"><tbody>';
	echo '<tr valign="top"><th scope="row"><strong>Which external IMG links to process:</strong></th>';
	echo '<td><label for="myradio1"><input id="myradio1" type="radio" name="bml_it_whichimgs" value="All" ' . (get_option('bml_it_whichimgs')!='Flickr'?'checked="checked"':'') . '/> All images</label><br/>';
	echo '<label for="myradio2"><input id="myradio2" type="radio" name="bml_it_whichimgs" value="Flickr" ' . (get_option('bml_it_whichimgs')=='Flickr'?'checked="checked"':'') . ' /> Only Flickr images</label><br/>';
	echo '<p>By default, all external images are processed.  This can be set to only apply to Flickr.</p>';
	echo '</td></tr>';
	echo '<tr valign="top"><th scope="row"><strong>What to do with the images:</th>';
	echo '<td><label for="myradio3"><input id="myradio3" type="radio" name="bml_it_replacesrc" value="replace" ' . (get_option('bml_it_replacesrc')!='custtag'?'checked="checked"':'') . ' /> Replace SRC attribute with local copy</label><br/>';
	echo '<label for="myradio4"><input id="myradio4" type="radio" name="bml_it_replacesrc" value="custtag" ' . (get_option('bml_it_replacesrc')=='custtag'?'checked="checked"':'') . ' /> Use custom tag:</label> ';
	echo '<input type="text" size="20" name="bml_it_custtagname" value="' . get_option('bml_it_custtagname') . '" /><br/>';
	echo '<p>Replacing the SRC attribute will convert the external IMG link to a local link, pointed at the local copy downloaded by this plugin. If the SRC attribute is not replaced, the plugin needs to mark the IMG as having been processed somehow, so this is done by tracking processed images in custom_tag values.  You can change the name of the custom tag.</p></td></tr>';
	
	echo '<tr align="top"><th scope="row"><strong>Apply to these categories:</strong></th>';
	echo '<td><label for="myradio5"><input type="radio" id="myradio5" name="bml_it_catlist" value="" ' . (get_option('bml_it_catlist')==''?'checked="checked"':'') . ' /> All categories</label><br/>';
	echo '<label for="myradio6"><input type="radio" id="myradio6" name="bml_it_catlist" value="Y" ' . (get_option('bml_it_catlist')!=''?'checked="checked"':'') . ' /> Selected categories</label><br/>';
	
	$_cats = explode(',', get_option('bml_it_catlist'));
	$chcount = 0;
	$cats = get_categories();
	foreach ($cats as $cat) {
		$chcount++;
		echo '<label for="mycheck'.$chcount.'"><input type="checkbox" id="mycheck'.$chcount.'" name="bml_it_cats[]" value="' . $cat->cat_ID . '" '.(in_array($cat->cat_ID, $_cats)?'checked="checked"':'').' /> ' . $cat->cat_name . '</label><br/>';
	}
	echo '</td></tr>';
	echo '<tr align="top"><th scope="row"><strong>Apply to these authors:</strong></th>';
	echo '<td><label for="myradio7"><input type="radio" id="myradio7" name="bml_it_authlist" value="" ' . (get_option('bml_it_authlist')==''?'checked="checked"':'') . ' /> All authors</label><br/>';
	echo '<label for="myradio8"><input type="radio" id="myradio8" name="bml_it_authlist" value="Y" ' . (get_option('bml_it_authlist')!=''?'checked="checked"':'') . ' /> Selected authors</label><br/>';
	
	$_auths = explode(',', get_option('bml_it_authlist'));
	$auths = bml_it_getauthors();
	foreach ($auths as $auth) {
		$chcount++;
		echo '<label for="mycheck'.$chcount.'"><input type="checkbox" id="mycheck'.$chcount.'" name="bml_it_auths[]" value="' . $auth->ID . '" '.(in_array($auth->ID, $_auths)?'checked="checked"':'').'/> ' . $auth->display_name . '</label><br/>';
	}
	echo '</td></tr>';
	
	echo '</tbody></table>';
	echo '<div class="submit">';
	echo '<input type="submit" name="submit" class="button-primary" value="' . __('Save Changes') . '" />';
	echo '</div>';
	echo '</form>';

	echo '<form name="bml_it-backcatalog" method="post" action="">';
	echo '<div class="wrap">';
	echo '<big>Process all posts</big>';
	echo '<p>Use this function to apply the plugin to all previous posts. The settings specified above will still be respected.</p>';
	echo '<p><em>Please note that this can take a long time for sites with a lot of posts.</em></p>';
	echo '<div class="submit">';
	echo '<input type="hidden" name="action" value="backcatalog">';
	echo '<input type="submit" class="button-primary" value="' . __('Process') . '" />';
	echo '</div>';
	echo '<p>&nbsp;</p>';
	echo '</div>';
	echo '</form>';

	echo '';
	echo '</div>';
}

?>
