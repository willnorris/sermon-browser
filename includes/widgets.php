<?php
/**
* Register sermon widgets to be used in sidebars.
*
* @package SermonBrowser
* @subpackage Widgets
*/

add_action('widgets_init', 'mbsb_widgets_init');
function mbsb_widgets_init() {
	register_widget( 'Widget_Related_Sermons' );
	register_widget( 'Widget_Recent_Sermons' );
}

/**
* Related_Sermons widget class.  This widget lists all of the sermons that are
* related to the sermon, series, service, or preacher currently being viewed.
*
* @package SermonBrowser
* @subpackage Widgets
*/
class Widget_Related_Sermons extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname' => 'widget_related_sermons',
			'description' => __( 'Sermons related to the sermon, preacher, service, or sermon series page being viewed.', MBSB)
		);
		parent::__construct('related-sermons', __('Related Sermons', MBSB), $widget_ops);
		$this->alt_option_name = 'widget_related_sermons';
	}

	function widget($args, $instance) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		extract($args);

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Related Sermons', MBSB );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$order = isset( $instance['order'] ) ? $instance['order'] : 'desc';
		$number = ( ! empty( $instance['number'] ) ) ? $instance['number'] : 10;
		$show_date = isset( $instance['show_date'] ) ? $instance['show_date'] : false;
		$show_image = isset( $instance['show_image'] ) ? $instance['show_image'] : false;

		if ( is_singular( 'mbsb_sermon' ) ) {
			$meta_key = 'series';
			$sermon_id = get_queried_object_id();
			$meta_value = get_post_meta($sermon_id, 'series', true);
		} else if ( is_singular ( 'mbsb_series' ) ) {
			$meta_key = 'series';
			$meta_value = get_queried_object_id();
		} else if ( is_singular ( 'mbsb_service' ) ) {
			$meta_key = 'service';
			$meta_value = get_queried_object_id();
		} else if ( is_singular ( 'mbsb_preacher' ) ) {
			$meta_key = 'preacher';
			$meta_value = get_queried_object_id();
		}

		$title_sub = sprintf('<a href="%s">%s</a>', get_permalink($meta_value), get_the_title($meta_value));
		$title = sprintf($title, $title_sub);

		$r = new WP_Query( apply_filters( 'widget_posts_args', array( 'posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'post_type' => 'mbsb_sermon', 'meta_key' => $meta_key, 'meta_value' => $meta_value, 'orderby' => 'date', 'order' => $order ) ) );
		if ($r->have_posts()) :

			echo $before_widget;
			if ( $show_image && has_post_thumbnail($meta_value) ) {
				echo get_the_post_thumbnail($meta_value, 'medium');
			}
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}
?>
		<ul>
		<?php while ( $r->have_posts() ) : $r->the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>"><?php get_the_title() ? the_title() : the_ID(); ?></a>
				<?php if ( $show_date ) : ?>
					<span class="post-date"><?php echo get_the_date(); ?></span>
				<?php endif; ?>
			</li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['order'] = strip_tags($new_instance['order']);
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? (bool) $new_instance['show_date'] : false;
		$instance['show_image'] = isset( $new_instance['show_image'] ) ? (bool) $new_instance['show_image'] : false;

		return $instance;
	}

	function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    = isset( $instance['number'] ) ? $instance['number'] : 10;
		$order     = isset( $instance['order'] ) ? $instance['order'] : 'desc';
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$show_image = isset( $instance['show_image'] ) ? (bool) $instance['show_image'] : false;
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		<span class="description">"<em>%s</em>" will be replaced with the title of preacher, series, or service.</span></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of sermons to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'order' ); ?>"><?php _e( 'Sort Order:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'order' ); ?>" name="<?php echo $this->get_field_name( 'order' ); ?>">
			<option value="asc" <?php selected( 'asc', $order ) ?>><?php _e('Oldest First') ?></option>
			<option value="desc" <?php selected( 'desc', $order ) ?>><?php _e('Newest First') ?></option>
		</select></p>

		<p><input class="checkbox" type="checkbox" <?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display sermon date?' ); ?></label></p>

		<p><input class="checkbox" type="checkbox" <?php checked( $show_image ); ?> id="<?php echo $this->get_field_id( 'show_image' ); ?>" name="<?php echo $this->get_field_name( 'show_image' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'show_image' ); ?>"><?php _e( 'Display image?' ); ?></label></p>
<?php
	}
}

/**
* Recent_Sermons widget class.  This widget lists the most recently posted
* sermons.
*
* @package SermonBrowser
* @subpackage Widgets
*/
class Widget_Recent_Sermons extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname' => 'widget_recent_sermons',
			'description' => __( 'Your site&#8217;s most recent Sermons.', MBSB)
		);
		parent::__construct('recent-sermons', __('Recent Sermons', MBSB), $widget_ops);
		$this->alt_option_name = 'widget_recent_sermons';

		add_action( 'save_post', array($this, 'flush_widget_cache') );
		add_action( 'deleted_post', array($this, 'flush_widget_cache') );
		add_action( 'switch_theme', array($this, 'flush_widget_cache') );
	}

	function widget($args, $instance) {
		$cache = wp_cache_get('widget_recent_sermons', 'widget');

		if ( !is_array($cache) ) {
			$cache = array();
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}

		ob_start();
		extract($args);

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Sermons', MBSB );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 10;
		if ( ! $number ) {
			$number = 10;
		}
		$show_date = isset( $instance['show_date'] ) ? $instance['show_date'] : false;

		$r = new WP_Query( apply_filters( 'widget_posts_args', array( 'posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'post_type' => 'mbsb_sermon' ) ) );
		if ($r->have_posts()) :

			echo $before_widget;
			if ( $title ) {
				echo $before_title . $title . $after_title;
			}
?>
		<ul>
		<?php while ( $r->have_posts() ) : $r->the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>"><?php get_the_title() ? the_title() : the_ID(); ?></a>
				<?php if ( $show_date ) : ?>
					<span class="post-date"><?php echo get_the_date(); ?></span>
				<?php endif; ?>
			</li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_recent_posts', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? (bool) $new_instance['show_date'] : false;
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_recent_sermons']) ) {
			delete_option('widget_recent_sermons');
		}

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_recent_posts', 'widget');
	}

	function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of sermons to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p><input class="checkbox" type="checkbox" <?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display sermon date?' ); ?></label></p>
<?php
	}
}

