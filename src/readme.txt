

ESSENTIAL GRID

In order for this plugin function with terms it's necessary to hack some files.

	ADD POST DATA


	- include the following change in the essential-grid/public/essential-grid.class.php get_post_media_source_data method.

		replace this line (2685):

			$post_media_source_data = $base->get_post_media_source_data($post['ID'], $post_media_source_type, $media_sources, $image_sizes);
		
		with:
		
			$post_media_source_data = $base->get_post_media_source_data($post['ID'], $post_media_source_type, $media_sources, $image_sizes, $post);

	

	- include the following change in the essential-grid/includes/base.class.php get_post_media_source_data method.

		
		replace this line (2138):

			public function get_post_media_source_data($post_id, $image_type, $media_sources, $image_size = array())
		
		with:

			public function get_post_media_source_data($post_id, $image_type, $media_sources, $image_size = array(), $post)

		
		replace this line (2267):
			
			return apply_filters('essgrid_modify_media_sources', $ret, $post_id);
		
		with:
			
			return apply_filters('essgrid_modify_media_sources', $ret, $post_id, $post);



	ALLOW FOR TERM LINK

	- include the following change in the essential-grid/includes/item-skin.class.php output_item_skin method.

		replace this line (1555):

			$link_wrapper = '<a href="' . get_permalink($this->post['ID']) . '"' . $link_target . $link_rel_nofollow . '>%REPLACE%</a>';

		with:

			$link_wrapper = '<a href="' . ( !empty( $this->post['term_id'] ) ? get_term_link( $this->post['term_id'] ) : get_permalink($this->post['ID']) ) . '"' . $link_target . $link_rel_nofollow . '>%REPLACE%</a>';

 
