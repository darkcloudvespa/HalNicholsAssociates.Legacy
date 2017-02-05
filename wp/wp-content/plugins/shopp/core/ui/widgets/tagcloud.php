<?php
/**
 * ShoppTagCloudWidget class
 * A WordPress widget that shows a cloud of the most popular product tags
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, 8 June, 2009
 * @package shopp
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

if ( class_exists('WP_Widget') && ! class_exists('ShoppTagCloudWidget') ) {

	class ShoppTagCloudWidget extends WP_Widget {

	    function __construct() {
	        parent::__construct(false,
				$name = Shopp::__('Shopp Tag Cloud'),
				array('description' => Shopp::__('Popular product tags in a cloud format'))
			);
	    }

	    function widget($args, $options) {
			$Shopp = Shopp::object();
			if (!empty($args)) extract($args);

			if (empty($options['title'])) $options['title'] = "Product Tags";
			$title = $before_title.$options['title'].$after_title;

			$tagcloud = shopp('storefront.get-tagcloud', $options);
			echo $before_widget.$title.$tagcloud.$after_widget;
	    }

	    function update($new_instance, $old_instance) {
	        return $new_instance;
	    }

	    function form($options) {
	    	$defaults = array(
				'title'	    => '',
				'exclude'	=> '',
				);
	    	
			$options = array_merge($defaults, $options);	    	
			?>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
				<input type="text" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" class="widefat" value="<?php echo $options['title']; ?>">
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('exclude'); ?>"><?php _e( 'Exclude' ); ?></label> <input type="text" name="<?php echo $this->get_field_name('exclude'); ?>" id="<?php echo $this->get_field_id('exclude'); ?>" class="widefat" value="<?php echo $options['exclude']; ?>" />
				<br />
				<small><?php Shopp::_e( 'Tags, separated by commas.' ); ?></small>
			</p>
			<?php
	    }

	} // class ShoppTagCloudWidget

}
