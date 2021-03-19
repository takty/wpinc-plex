<?php
/**
 * Post Content
 *
 * @package Wpinc Plex
 * @author Takuto Yanagida
 * @version 2021-03-16
 */

namespace wpinc\plex\post_content;

require_once __DIR__ . '/custom-rewrite.php';

/**
 * Add post type
 *
 * @param string|string[] $post_type_s Post types.
 */
function add_post_type( $post_type_s ) {
	$pts  = is_array( $post_type_s ) ? $post_type_s : array( $post_type_s );
	$inst = _get_instance();

	foreach ( $pts as $pt ) {
		add_action( "save_post_$pt", '\wpinc\plex\post_content\_cb_save_post', 10, 2 );
	}
	$inst->post_types = array_merge( $inst->post_types, $pts );
}

/**
 * Add an array of slug to label.
 *
 * @param array $slug_to_label An array of slug to label.
 */
function add_admin_labels( array $slug_to_label ) {
	$inst = _get_instance();

	$inst->slug_to_label = array_merge( $inst->slug_to_label, $slug_to_label );
}

/**
 * Assign a format for displaying admin labels.
 *
 * @param string $format A format to assign.
 */
function set_admin_label_format( string $format ) {
	_get_instance()->label_format = $format;
}

/**
 * Initialize the post content.
 *
 * @param array $args {
 *     Configuration arguments.
 *
 *     @type array  $vars               Query variable names.
 *     @type string $title_key_prefix   (Optional) Key prefix of post metadata for custom title.
 *     @type string $content_key_prefix (Optional) Key prefix of post metadata for custom content.
 * }
 */
function initialize( array $args = array() ) {
	$inst = _get_instance();

	$args += array(
		'vars'               => array(),
		'title_key_prefix'   => '_post_title_',
		'content_key_prefix' => '_post_content_',
	);

	$inst->vars            = $args['vars'];
	$inst->key_pre_title   = $args['title_key_prefix'];
	$inst->key_pre_content = $args['content_key_prefix'];

	if ( is_admin() ) {
		add_action( 'admin_head', '\wpinc\plex\post_content\_cb_admin_head' );
		add_action( 'admin_menu', '\wpinc\plex\post_content\_cb_admin_menu' );
	} else {
		add_filter( 'single_post_title', '\wpinc\plex\post_content\_cb_single_post_title', 10, 2 );
		add_filter( 'the_title', '\wpinc\plex\post_content\_cb_the_title', 10, 2 );
		add_filter( 'the_content', '\wpinc\plex\post_content\_cb_the_content' );
	}
}


// -------------------------------------------------------------------------


/**
 * Callback function for 'single_post_title' filter.
 *
 * @access private
 *
 * @param string   $title The single post page title.
 * @param \WP_Post $post  The current post.
 * @return string Filtered title.
 */
function _cb_single_post_title( string $title, \WP_Post $post ): string {
	return _get_title( $title, $post->ID, $post );
}

/**
 * Callback function for 'the_title' filter.
 *
 * @access private
 *
 * @param string $title The post title.
 * @param int    $id    The post ID.
 * @return string Filtered title.
 */
function _cb_the_title( string $title, int $id ): string {
	return _get_title( $title, $id, get_post( $id ) );
}

/**
 * Get post title.
 *
 * @access private
 *
 * @param string    $title The post title.
 * @param int       $id    The post ID.
 * @param ?\WP_Post $post  The post.
 * @return string Filtered title.
 */
function _get_title( string $title, int $id, ?\WP_Post $post ): string {
	if ( null === $post ) {
		return $title;  // When $id is 0.
	}
	$inst = _get_instance();
	if ( ! in_array( $post->post_type, $inst->post_types, true ) ) {
		return $title;
	}
	$key = \wpinc\plex\get_query_key( $inst->vars );
	if ( \wpinc\plex\get_default_key( $inst->vars ) === $key ) {
		return $title;
	}
	$t = get_post_meta( $id, $inst->key_pre_title . $key, true );
	if ( empty( $t ) ) {
		return $title;
	}
	$basic_title = $post->post_title;
	$basic_title = \capital_P_dangit( $basic_title );
	$basic_title = \wptexturize( $basic_title );
	$basic_title = \convert_chars( $basic_title );
	$basic_title = \trim( $basic_title );
	if ( empty( $basic_title ) ) {
		return "$title $t";
	}
	return preg_replace( '/' . preg_quote( $basic_title, '/' ) . '/u', $t, $title );
}

/**
 * Callback function for 'the_content' filter.
 *
 * @access private
 *
 * @param string $content Content of the current post.
 * @return string Filtered content.
 */
function _cb_the_content( string $content ): string {
	$inst = _get_instance();
	$p    = get_post();
	if ( post_password_required( $p ) ) {
		return get_the_password_form( $p );
	}
	if ( ! in_array( $p->post_type, $inst->post_types, true ) ) {
		return $content;
	}
	$key = \wpinc\plex\get_query_key( $inst->vars );
	if ( \wpinc\plex\get_default_key( $inst->vars ) === $key ) {
		return $content;
	}
	$c = get_post_meta( $p->ID, $inst->key_pre_content . $key, true );
	if ( empty( $c ) ) {
		return $content;
	}
	remove_filter( 'the_content', '\wpinc\plex\post_content\_cb_the_content' );
	$c = apply_filters( 'the_content', $c );
	add_filter( 'the_content', '\wpinc\plex\post_content\_cb_the_content' );
	return str_replace( ']]>', ']]&gt;', $c );
}


// -----------------------------------------------------------------------------


function _cb_save_post( $pid, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	$inst    = _get_instance();
	$def_key = \wpinc\plex\get_default_key( $inst->vars );
	$scs     = \wpinc\plex\get_slug_combination( $inst->vars );

	foreach ( $scs as $slugs ) {
		$key = implode( '_', $slugs );
		if ( $key === $def_key ) {
			continue;
		}
		if ( ! isset( $_POST[ "post_{$key}_nonce" ] ) ) {
			continue;
		}
		// phpcs:disable
		if ( ! wp_verify_nonce( $_POST[ "post_{$key}_nonce" ], "post_$key" ) ) {
			continue;
		}
		$title   = $_POST[ $inst->key_pre_title . $key ];
		$content = $_POST[ $inst->key_pre_content . $key ];
		// phpcs:enable
		$title   = apply_filters( 'title_save_pre', $title );
		$content = apply_filters( 'content_save_pre', $content );
		update_post_meta( $pid, $inst->key_pre_title . $key, $title );
		update_post_meta( $pid, $inst->key_pre_content . $key, $content );
	}
}

function _cb_admin_head() {
	?>
<style>
	.st-multilang-title input {
		margin: 0 0 6px; padding: 3px 8px;
		width: 100%; height: 1.7em;
		font-size: 1.7em; line-height: 100%;
		background-color: #fff; outline: none;
	}
</style>
	<?php
}

function _cb_admin_menu() {
	$inst    = _get_instance();
	$def_key = \wpinc\plex\get_default_key( $inst->vars );
	$scs     = \wpinc\plex\get_slug_combination( $inst->vars );

	foreach ( $inst->post_types as $pt ) {
		$pto = get_post_type_object( $pt );
		if ( null === $pto ) {
			continue;
		}
		$post_type_name = $pto->labels->name;
		foreach ( $scs as $slugs ) {
			$key = implode( '_', $slugs );
			if ( $key === $def_key ) {
				continue;
			}
			$lab_pf = _get_admin_label( $slugs );
			add_meta_box(
				"post_$lang",
				"$post_type_name $lab_pf",
				function () use ( $key ) {
					\wpinc\plex\post_content\_output_html( $key );
				},
				$pt,
				'advanced',
				'high'
			);
		}
	}
}

/**
 * Retrieve the label of current query variables.
 *
 * @access private
 *
 * @param string[] $slugs The slug combination.
 * @return string The label string.
 */
function _get_admin_label( array $slugs ): string {
	$inst = _get_instance();
	$ls   = array_map(
		function ( $s ) use ( $inst ) {
			return $inst->slug_to_label[ $s ] ?? $s;
		},
		$slugs
	);
	if ( $inst->label_format ) {
		return sprintf( $inst->label_format, ...$ls );
	}
	return implode( ' ', $ls );
}

function _output_html( $key ) {
	global $post;
	$name        = $inst->key_pre_title . $key;
	$value       = get_post_meta( $post->ID, $inst->key_pre_title . $key, true );
	$placeholder = apply_filters( 'enter_title_here', __( 'Add title' ), $post );
	wp_nonce_field( "post_$key", "post_{$key}_nonce" );
	?>
<div class="st-multilang-title" id="titlewrap_<?php echo esc_attr( $key ); ?>">
	<input
		id="<?php echo esc_attr( $name ); ?>"
		name="<?php echo esc_attr( $name ); ?>"
		value="<?php echo esc_attr( $value ); ?>"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		size="30"
		type="text"
		spellcheck="true"
		autocomplete="off"
	>
</div>
	<?php
	$content = get_post_meta( $post->ID, $name, true );
	wp_editor( $content, $name );
}


// -------------------------------------------------------------------------


/**
 * Get instance.
 *
 * @access private
 *
 * @return object Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {
		/**
		 * The array of slug to label.
		 *
		 * @var array
		 */
		public $slug_to_label = array();

		/**
		 * The label format.
		 *
		 * @var string
		 */
		public $label_format = '';

		/**
		 * The array of variable names.
		 *
		 * @var array
		 */
		public $vars = array();

		/**
		 * The post types.
		 *
		 * @var array
		 */
		public $post_types = array();

		/**
		 * The key prefix of term metadata of a custom name.
		 *
		 * @var string
		 */
		public $key_pre_title = '';

		/**
		 * The key prefix of term metadata of a custom singular name.
		 *
		 * @var string
		 */
		public $key_pre_content = '';
	};
	return $values;
}