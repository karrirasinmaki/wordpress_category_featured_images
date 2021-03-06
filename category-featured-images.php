<?php
/**
 * Plugin Name: Category Featured Images (KR)
 * Plugin URI: https://github.com/karrirasinmaki/wordpress_category_featured_images/
 * Description: Allows to set featured images for categories, posts without a featured image will show the category's image (Posts \ Categories \ Edit category)
 * Author: Karri Rasinmäki
 * Author URI: http://karri.rasinmaki.fi
 * Version: 1.1.8.1
 * License: GPL3
 *
 * Original Author: Mattia Roccoberton
 * Original Author URI: http://blocknot.es
 * Original Plugin URI : https://github.com/blocknotes/wordpress_category_featured_images
 *
 */

class category_featured_images
{
	function __construct()
	{
	// --- Actions ------------------------------------------------------------- //
		add_action( 'admin_print_scripts', array( &$this, 'admin_print_scripts' ) );
		add_action( 'admin_print_styles', array( &$this, 'admin_print_styles' ) );
		add_action( 'category_edit_form', array( &$this, 'category_edit_form' ) );
		add_action( 'edited_category', array( &$this, 'edited_category' ) );

	// --- Filters ------------------------------------------------------------- //
		add_filter( 'get_post_metadata', array( &$this, 'get_post_metadata' ), 10, 4 );

	// --- Shortcodes ---------------------------------------------------------- //
		add_shortcode( 'cfi_featured_image', array( &$this, 'show_featured_image' ) );

	// --- Hooks --------------------------------------------------------------- //
		register_uninstall_hook( __FILE__, array( 'category_featured_images', 'uninstall' ) );
	}

	static function get_featured_image_url( $args )
	{
		$size = isset( $args['size'] ) ? $args['size'] : 'full';
		if( isset( $args['cat_id'] ) )
		{
			$cat_id = intval( $args['cat_id'] );
			$images = get_option( 'cfi_featured_images' );
			if( isset( $images[$cat_id] ) )
			{
				$attachment = wp_get_attachment_image_src( $images[$cat_id], $size );
				if( $attachment !== FALSE ) return $attachment[0];
			}
		}
		else if( is_single() )
		{
			$id = get_post_thumbnail_id();
			if( !empty( $id ) )
			{
				$attachment = wp_get_attachment_image_src( $id, $size );
				if( $attachment !== FALSE ) return $attachment[0];
			}
		}
		else if( is_category() )
		{
			$categories = get_the_category();
			if( $categories )
			{
				$images = get_option( 'cfi_featured_images' );
				$cat = NULL;
				foreach( $categories as $category )
				{
					if( isset( $images[$category->term_id] ) )
					{
						$attachment = wp_get_attachment_image_src( $images[$category->term_id], $size );
						if( $attachment !== FALSE ) return $attachment[0];
					}
					if( $cat === NULL ) $cat = $category;
				}
				if( $cat !== NULL )
				{
					$parent = intval( $cat->parent );
					if( $parent > 0 && isset( $images[$parent] ) )
					{
						$attachment = wp_get_attachment_image_src( $images[$parent], $size );
						if( $attachment !== FALSE ) return $attachment[0];
					}
				}
			}
		}
		return '';
	}

	static function show_featured_image( $args )
	{
		if( isset( $args['size'] ) )
		{
			$size = $args['size'];
			unset( $args['size'] );
		}
		else $size = 'thumbnail';
		if( isset( $args['cat_id'] ) )
		{
			$cat_id = intval( $args['cat_id'] );
			$images = get_option( 'cfi_featured_images' );
			if( isset( $images[$cat_id] ) )
			{
				$img = wp_get_attachment_image( $images[$cat_id], $size );
				if( !empty( $img ) ) return '<span class="cfi-featured-image">' . wp_get_attachment_image( $images[$cat_id], $size ) . '</span>';
			}
		}
		else if( is_single() )
		{
			$image = get_the_post_thumbnail( null, $size, $args );
			if( !empty( $image ) ) return '<span class="cfi-featured-image">' . $image . '</span>';
		}
		else if( is_category() )
		{
			$categories = get_the_category();
			if( $categories )
			{
				$images = get_option( 'cfi_featured_images' );
				$cat = NULL;
				foreach( $categories as $category )
				{
					if( isset( $images[$category->term_id] ) ) return '<span class="cfi-featured-image">' . wp_get_attachment_image( $images[$category->term_id], $size ) . '</span>';
					if( $cat === NULL ) $cat = $category;
				}
				if( $cat !== NULL )
				{
					$parent = intval( $cat->parent );
					if( $parent > 0 && isset( $images[$parent] ) ) return '<span class="cfi-featured-image">' . wp_get_attachment_image( $images[$parent], $size ) . '</span>';
				}
			}
		}
		return '';
	}

	static function uninstall()
	{
		delete_option( 'cfi_featured_images' );
	}

	function admin_print_scripts()
	{
		wp_enqueue_media();
		wp_register_script( 'cfi-scripts', plugins_url( 'category-featured-images.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'cfi-scripts' );
	}

	function admin_print_styles()
	{
		wp_register_style( 'cfi-styles', plugins_url( 'cfi-styles.css', __FILE__ ) );
		wp_enqueue_style( 'cfi-styles' );
	}

	function category_edit_form()
	{
		$tag_ID = $_GET['tag_ID'];
		$images = get_option( 'cfi_featured_images' );
		if( $images === FALSE ) $images = array();
		$image = isset( $images[$tag_ID] ) ? $images[$tag_ID] : '';
	?>
		<table class="form-table">
			<tr class="form-field">
				<th valign="top" scope="row">
					<label>Featured Image</label>
				</th>
				<td>
					<input id="cfi-featured-image" type="hidden" name="cfi_featured_image" readonly="readonly" value="<?php echo $image; ?>" />
					<input id="cfi-remove-image" class="button" type="button" value="Remove image" />
					<input id="cfi-change-image" class="button" type="button" value="Change image" />
					<div id="cfi-thumbnail"><?php if( !empty( $image ) ) echo wp_get_attachment_image( $image ); ?></div>
					<p class="description">Set a featured image for all the post of this category without a featured image.</p>
				</td>
			</tr>
		</table>
	<?php
	}

	function edited_category( $term_id )
	{
		if( isset( $_POST['cfi_featured_image'] ) )
		{
			$images = get_option( 'cfi_featured_images' );
			if( $images === FALSE ) $images = array();
			//$url = trim( $_POST['cfi_featured_image'] );	// URL alternative
			//$images[$term_id] = !empty( $url ) ? esc_url( $url ) : NULL;
			$img_id = trim( $_POST['cfi_featured_image'] );
			$images[$term_id] = !empty( $img_id ) ? $img_id : NULL;
			update_option( 'cfi_featured_images', $images );
		}
	}

	function get_post_metadata( $meta_value, $object_id, $meta_key, $single )
	{
		if( is_admin() || '_thumbnail_id' != $meta_key ) return $meta_value;
	// From: wp-includes/meta.php - get_metadata()
		$meta_type = 'post';
		$meta_cache = wp_cache_get($object_id, $meta_type . '_meta');
		if( !$meta_cache )
		{
			$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
			$meta_cache = $meta_cache[$object_id];
		}
		if( !$meta_key ) return $meta_cache;
		if( isset($meta_cache[$meta_key]) )
		{
			if( $single ) return maybe_unserialize( $meta_cache[$meta_key][0] );
			else return array_map('maybe_unserialize', $meta_cache[$meta_key]);
		}
		if( $single )
		{
		// Look for a category featured image
			$categories = wp_get_post_categories( $object_id );
			$images = get_option( 'cfi_featured_images' );
			$cat = NULL;
			foreach( $categories as $category )
			{
				if( isset( $images[$category] ) ) return $images[$category];
				if( $cat === NULL ) $cat = $category;
			}
			if( $cat !== NULL )
			{	// Look for the parent category image
				$category = get_category( $cat );
				if( !empty( $category ) && isset( $category->parent ) )
				{
					$parent = intval( $category->parent );
					if( $parent > 0 && isset( $images[$parent] ) ) return $images[$parent];
				}
			}

			return '';
		}
		else return array();
	}
}

$category_featured_images = new category_featured_images();

function cfi_featured_image( $args )
{
	echo category_featured_images::show_featured_image( $args );
}

function cfi_featured_image_url( $args )
{
	return category_featured_images::get_featured_image_url( $args );
}
