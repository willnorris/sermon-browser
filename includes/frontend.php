<?php
/**
* Include file, called when is_admin() is true
* 
* @package SermonBrowser
* @subpackage frontend
* @author Mark Barnes
*/

add_action ('init', 'mbsb_frontend_init');

/**
* Runs on the init action
* 
* Sets up most of the required features.
* 
*/
function mbsb_frontend_init() {
	$date = @filemtime(mbsb_plugin_dir_path('css/frontend-style.php'));
	wp_register_style ('mbsb_frontend_style', mbsb_plugins_url('css/frontend-style.php'), array(), $date);
	add_filter ('the_content', 'mbsb_provide_content');
	
}

/**
* Filters the_content for sermon browser custom post types, and provides all output
* 
* @param mixed $content
*/
function mbsb_provide_content($content) {
	global $post;
	if (substr($post->post_type, 0, 5) == 'mbsb_')
		if ($post->post_type == 'mbsb_sermon') {
			$sermon = new mbsb_sermon($post->ID);
			return $sermon->get_frontend_output();
		}
	return $content;
}
?>