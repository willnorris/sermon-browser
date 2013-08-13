<?php
/**
* Include file, called when is_admin() is false
* 
* @package SermonBrowser
* @subpackage Frontend
* @author Mark Barnes <mark@sermonbrowser.com>
*/

add_action ('init', 'mbsb_frontend_init');
add_action ('template_redirect', 'mbsb_frontend_init_after_query');
require (apply_filters ('mbsb_theme', mbsb_plugin_dir_path('includes/default_theme.php')));

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
	add_shortcode ('sermons', 'mbsb_display_sermons');
	add_shortcode ('series', 'mbsb_display_series');
	add_shortcode ('services', 'mbsb_display_services');
	add_shortcode ('preachers', 'mbsb_display_preachers');
}

/**
* Sets up features that need to be set up after the query runs
*
*/
function mbsb_frontend_init_after_query() {
	if ( is_post_type_archive('mbsb_sermon') or is_post_type_archive('mbsb_series') or is_post_type_archive('mbsb_preacher') or is_post_type_archive('mbsb_service') ) {
		// add actions and filters to alter podcast feeds
		add_action('rss2_ns', 'mbsb_podcast_ns');
		add_action('rss2_head', 'mbsb_podcast_head');
		add_action('rss2_item', 'mbsb_podcast_item');
		add_filter('bloginfo_rss', 'mbsb_bloginfo_rss_filter', 10, 2);
		add_filter('wp_title_rss', 'mbsb_wp_title_rss_filter');
		add_filter('rss_enclosure', 'mbsb_blankout_filter');
	}
}

/**
* Changes wp_title_rss data for podcast feed
*
* If 'podcast_feed_title' option is set, then blank out wp_title_rss data for podcast feed.  Title will be set in the mbsb_bloginfo_rss_filter function.
*/
function mbsb_wp_title_rss_filter($input) {
	$title = mbsb_get_option('podcast_feed_title');
	if ($title != '')
		return '';
	else
		return $input;
}

/**
* Changes bloginfo_rss data for podcast feed
*
*/
function mbsb_bloginfo_rss_filter($input, $show) {
	if ($show == 'name') {
		$title = mbsb_get_option('podcast_feed_title');
		if ($title != '')
			return $title;
		else
			return $input;
	}
	elseif ($show == 'description') {
		$description = mbsb_get_option('podcast_feed_description');
		if ($description != '')
			return $description;
		else
			return $input;
	}
	else
		return $input;
}

/**
* Adds code to the namespace section of podcast feed
*
*/
function mbsb_podcast_ns() {
	echo 'xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"';
}

/**
* Adds code to the head section of podcast feed
*
* Add iTunes-specific tags for podcast feed
*/
function mbsb_podcast_head() {
	$author = mbsb_get_option('podcast_feed_author');
	if ($author == '')
		$author = strip_tags(get_bloginfo('name'));
	$summary = mbsb_get_option('podcast_feed_summary');
	if ($summary == '')
		$summary = strip_tags(get_bloginfo('description'));
	//output
?>
	<itunes:author><?php echo $author; ?></itunes:author>
	<itunes:summary><?php echo $summary; ?></itunes:summary>
	<itunes:explicit>no</itunes:explicit>
	<itunes:owner>
		<itunes:name><?php echo mbsb_get_option('podcast_feed_owner_name'); ?></itunes:name>
		<itunes:email><?php echo mbsb_get_option('podcast_feed_owner_email'); ?></itunes:email>
	</itunes:owner>
<?php
	$image = mbsb_get_option('podcast_feed_image');
	if ($image) 
		echo '	<itunes:image href="', esc_url($image), '" />';
	$category = explode( '/', mbsb_get_option('podcast_feed_category') );
	if ($category) {
		if ( isset($category[0]) and $category[0] ) {
			echo '	<itunes:category text="', esc_attr($category[0]), '">', "\n";
			if ( isset($category[1]) and $category[1] )
				echo '		<itunes:category text="', esc_attr($category[1]), '" />', "\n";
			echo "	</itunes:category>\n";
		}
	}
?>
<?php
}

/**
* Adds code to the item section of podcast feed
*
* Adds enclosure tags for Sermon Browser attachments
*/
function mbsb_podcast_item() {
	$sermon = new mbsb_sermon( get_the_ID() );
	$podcast_type = 'all';
	if ( isset($_GET['podcast_type']) )
		$podcast_type = $_GET['podcast_type'];
	echo $sermon->attachments->get_podcast_enclosures($podcast_type);
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

/**
* Filters the author link to make sure that sermon authors point to the preacher URL
* 
* @param string $link
* @param integer $author_id
* @param string $author_nicename
* @return string
*/
function mbsb_filter_author_link ($link, $author_id, $author_nicename) {
	global $post;
	if (isset($post->post_type) && $post->post_type == 'mbsb_sermon') {
		$sermon = new mbsb_sermon($post->ID);
		if ($sermon->preacher->present)
			return $sermon->preacher->get_url();
	}
	return $link;
}

/**
* Enqueues scripts and styles for the frontend
*/
function mbsb_enqueue_frontend_scripts_and_styles() {
	global $post;
	if (isset ($post->ID) && isset ($post->post_type) && $post->post_type == 'mbsb_sermon') {
		echo "<script type=\"text/javascript\">var mbsb_sermon_id=".esc_html($post->ID).";</script>\r\n";
		if (mbsb_get_option('allow_user_to_change_bible') || mbsb_get_option('bible_version_'.get_locale()) == 'esv')
			wp_enqueue_style ('mbsb_esv_style', mbsb_plugins_url('css/esv.css'));
		if (mbsb_get_option('use_embedded_bible_'.get_locale()))
			wp_enqueue_script('mbsb_biblia_embedded', 'http://biblia.com/api/logos.biblia.js');
	}
	$date = @filemtime(mbsb_plugin_dir_path('css/frontend-style.php'));
	wp_enqueue_style ('mbsb_frontend_style', mbsb_plugins_url('css/frontend-style.php'), array(), $date);
	wp_enqueue_script ('mbsb_frontend_script', home_url("?mbsb_script=frontend&locale=".get_locale()), array ('jquery'), @filemtime(mbsb_plugin_dir_path('js/frontend.php')).(mbsb_get_option('use_embedded_bible_'.get_locale()) ? '-biblia' : ''));
}

/**
* Display the list of sermons. Can be added to a theme, or called using the [sermons] shortcode
*/
function mbsb_display_sermons ($atts, $content) {
	return apply_filters ('mbsb_display_sermons', $content);	
}

/**
* Display the list of series. Can be added to a theme, or called using the [sermon-series] shortcode
*/
function mbsb_display_series($atts, $content) {
	return apply_filters ('mbsb_display_series', $content);	
}

/**
* Display the list of services. Can be added to a theme, or called using the [services] shortcode
*/
function mbsb_display_services($atts, $content) {
	return apply_filters ('mbsb_display_services', $content);	
}

/**
* Display the list of preachers. Can be added to a theme, or called using the [preachers] shortcode
*/
function mbsb_display_preachers($atts, $content) {
	return apply_filters ('mbsb_display_preachers', $content);	
}

function mbsb_setup_frontend_queries() {
	add_filter ('posts_join_paged', 'mbsb_frontend_sermons_standard_join');
	add_filter ('posts_where_paged', 'mbsb_frontend_sermons_standard_where');
}

function mbsb_reset_frontend_queries() {
	remove_filter ('posts_join_paged', 'mbsb_frontend_sermons_standard_join');
	remove_filter ('posts_where_paged', 'mbsb_frontend_sermons_standard_where');
}

function mbsb_get_sermon_filters() {
	global $wpdb;
	mbsb_setup_frontend_queries();
	$query = new WP_Query();
	$sermon_ids = $query->query(array ('post_type' => 'mbsb_sermon', 'no_paging' => true, 'fields' => 'ids'));
	mbsb_reset_frontend_queries();
	if ($sermon_ids) {
		$preachers = mbsb_get_sermon_count_by ('preacher', $sermon_ids);
		$series = mbsb_get_sermon_count_by ('series', $sermon_ids);
		$services = mbsb_get_sermon_count_by ('service', $sermon_ids);
		$tags = mbsb_get_sermon_count_by ('tag', $sermon_ids);
		$years = mbsb_get_sermon_count_by ('year', $sermon_ids);
		$books = mbsb_get_sermon_count_by ('book', $sermon_ids);
		$output = '<form id="sermon_filter_form">';
		$output .= '<div id="sermon_control">';
		$dropdown = array (array('id' => 'recent', 'name' => __('Most recent', MBSB)), array('id' => 'oldest', 'name' => __('Oldest', MBSB)), array ('id' => 'title', 'name' => __('Title', MBSB)), array('id' => 'preacher', 'name' => __('Preacher', MBSB)), array('id' => 'series', 'name' => __('Series', MBSB)), array('id' => 'service', 'name' => __('Service', MBSB)), array('id' => 'book', 'name' => __('Passage', MBSB)));
		$output .= '<label for="sort_by">'.__('Sort:', MBSB).'</label> '.mbsb_do_filter_dropdown('sort_by', $dropdown);
		$dropdown = array (5, 10, 20, 50);
		if (!in_array(get_option('posts_per_page'), $dropdown)) {
			$dropdown[] = get_option('posts_per_page');
			sort ($dropdown);
		}
		$dropdown[] = 'all';
		foreach ($dropdown as $k => $v)
			$dropdown[$k] = array ('id' => $v, 'name' => $v);
		$output .= '<label for="per_page">'.__('Num:', MBSB).'</label> '.mbsb_do_filter_dropdown('per_page', $dropdown, '', get_option('posts_per_page'));
		$output .= '</div>';
		$output .= '<div id="sermon_filter">';
		$dropdown = array (array('id' => '', 'name' => ''), array('id' => 'preacher', 'name' => __('Preacher')), array('id' => 'year', 'name' => __('Date')), array('id' => 'series', 'name' => __('Series')), array('id' => 'service', 'name' => __('Service')), array('id' => 'tag', 'name' => __('Tags')), array('id' => 'book', 'name' => __('Passage')));
		$output .= '<label for="filter_by">'.__('Filter:', MBSB).'</label> '.mbsb_do_filter_dropdown('filter_by', $dropdown);
		$output .= mbsb_do_filter_dropdown ('preacher', $preachers, 'mbsb_hide');
		$output .= mbsb_do_filter_dropdown ('series', $series, 'mbsb_hide');
		$output .= mbsb_do_filter_dropdown ('tag', $tags, 'mbsb_hide');
		$output .= mbsb_do_filter_dropdown ('service', $services, 'mbsb_hide');
		$output .= mbsb_do_filter_dropdown ('book', $books, 'mbsb_hide');
		$output .= mbsb_do_filter_dropdown ('year', $years, 'mbsb_hide');
		$output .= '</div>';
		$output .= '</form>';
		return $output;
	}
}

function mbsb_do_filter_dropdown ($field_name, $values, $class='', $selected = null) {
	if ($values) {
		if ($class != '')
			$class = " class=\"{$class}\"";
		if (isset($_POST[$field_name]))
			$selected = $_POST[$field_name];
		$output = "<select id=\"filter_{$field_name}_dropdown\" name=\"{$field_name}\"{$class}>";
		foreach ($values as $v) {
			$v = (object)$v;
			if ($field_name == 'book')
				$v->name = mbsb_get_bible_book_name($v->name);
			$insert = ($v->id == $selected) ? ' selected="selected"' : '';
			$output .= '<option value="'.esc_html($v->id).'"'.$insert.'>'.esc_html($v->name).(isset($v->count) ? ' ('.$v->count.')' : '').'</option>';
		}
		return $output .'</select>';
	}
}

/**
* Filters posts_join_paged when the sermons page is displayed in the frontend
* 
* Adds SQL to WP_Query to ensure the correct metadata is added to the query.
* 
* @param string $join
* @return string
*/
function mbsb_frontend_sermons_standard_join ($join) {
	if (isset($_POST['preacher']))
		$join .= mbsb_join_preacher ('');
	if (isset($_POST['series']))
		$join .= mbsb_join_service ('');
	if (isset($_POST['service']))
		$join .= mbsb_join_series ('');
	if (isset($_POST['book']))
		$join .= mbsb_join_book ('');
	return $join;
}

/**
* Filters posts_where_paged when the sermons page is displayed in the frontend
* 
* Adds SQL to WP_Query to ensure the correct 'WHERE' data is added to the query.
* 
* @param string $where
* @return string
*/
function mbsb_frontend_sermons_standard_where($where) {
	global $wpdb;
	if (isset($_POST['preacher']))
		$where .= " AND preachers.ID=".$wpdb->escape($_POST["preacher"]);
	if (isset($_POST['series']))
		$where .= " AND series.ID=".$wpdb->escape($_POST["series"]);
	if (isset($_POST['service']))
		$where .= " AND services.ID=".$wpdb->escape($_POST["service"]);
	if (isset($_POST['book']))
		$where .= " AND CONVERT(LEFT(book_postmeta.meta_value,2), UNSIGNED)=".$wpdb->escape($_POST["book"]);
	if (isset($_POST['year']))
		$where .= " AND YEAR(post_date) =".$wpdb->escape($_POST["year"]);
	return $where;
}
?>