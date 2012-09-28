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
	add_action ('wp_head', 'mbsb_enqueue_frontend_scripts_and_styles');
	add_filter ('the_content', 'mbsb_provide_content');
	add_filter ('the_title', 'mbsb_filter_titles', 10, 2);
	add_filter ('the_author', 'mbsb_filter_author');
	add_filter ('author_link', 'mbsb_filter_author_link', 10, 3);
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

/**
* Filters the_title for SermonBrowser custom post types
* 
* @param string $title
* @param integer $id
* @return string
*/
function mbsb_filter_titles ($title, $id) {
	$post = get_post ($id);
	if ($post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($id);
		if ($sermon->passages->present)
			return $title.' <span class="title_passage">('.$sermon->get_formatted_passages().')</span>';
	}
	return $title;
}

/**
* Filters the_author for SermonBrowser custom post types
* 
* @param string $author
* @return string
*/
function mbsb_filter_author ($author) {
	global $post;
	if (isset($post->post_type) && $post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post->ID);
		if ($sermon->preacher->present)
			return $sermon->preacher->get_name();
	}
	return $author;
	
}

function mbsb_filter_author_link ($link, $author_id, $author_nicename) {
	global $post;
	if (isset($post->post_type) && $post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post->ID);
		if ($sermon->preacher->present)
			return $sermon->preacher->get_url();
	}
	return $link;
}

function mbsb_enqueue_frontend_scripts_and_styles() {
	global $post;
	if (isset ($post->ID) && isset ($post->post_type) && $post->post_type == 'mbsb_sermon')
		echo "<script type=\"text/javascript\">var mbsb_sermon_id=".esc_html($post->ID).";</script>\r\n";
	$date = @filemtime(mbsb_plugin_dir_path('css/frontend-style.php'));
	wp_register_style ('mbsb_frontend_style', mbsb_plugins_url('css/frontend-style.php'), array(), $date);
	wp_enqueue_style ('mbsb_frontend_style');
	wp_register_script ('mbsb_frontend_script', home_url("?mbsb_script=frontend&locale=".get_locale()), array ('jquery'), @filemtime(mbsb_plugin_dir_path('js/frontend.php')));
	wp_enqueue_script ('mbsb_frontend_script');
}
?>