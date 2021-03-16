<?php
/**
 * Term Name
 *
 * @package Wpinc Plex
 * @author Takuto Yanagida
 * @version 2021-03-16
 */

namespace wpinc\plex\term_name;

require_once __DIR__ . '/custom-rewrite.php';
require_once __DIR__ . '/slug-key.php';

/**
 * Add taxonomy
 *
 * @param string|string[] $taxonomy_s Taxonomy slugs.
 * @param array           $args {
 *     Add taxonomy.
 *
 *     @type bool $has_singular_name         Whether the terms has singular names.
 *     @type bool $has_default_singular_name Whether the default name of the terms has singular form.
 *     @type bool $has_description           Whether the terms has custom descriptions.
 * }
 */
function add_taxonomy( $taxonomy_s, array $args = array() ) {
	$txs = is_array( $taxonomy_s ) ? $taxonomy_s : array( $taxonomy_s );

	$args += array(
		'has_singular_name'         => false,
		'has_default_singular_name' => false,
		'has_description'           => false,
	);
	foreach ( $txs as $tx ) {
		if ( ! is_admin() || ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) ) {
			add_filter( "get_{$tx}", '\wpinc\plex\term_name\_cb_get_taxonomy', 10 );
		}
		if ( is_admin() ) {
			add_action( "{$tx}_edit_form_fields", '\wpinc\plex\term_name\_cb_taxonomy_edit_form_fields', 10, 2 );
			add_action( "edited_$tx", '\wpinc\plex\term_name\_cb_edited_taxonomy', 10 );
		}
	}
	$inst = _get_instance();

	$inst->taxonomies = array_merge( $inst->taxonomies, $txs );
	if ( $args['has_singular_name'] ) {
		$inst->taxonomies_singular_name = array_merge( $inst->taxonomies_singular_name, $txs );
	}
	if ( $args['has_default_singular_name'] ) {
		$inst->taxonomies_default_singular_name = array_merge( $inst->taxonomies_default_singular_name, $txs );
	}
	if ( $args['has_description'] ) {
		$inst->taxonomies_description = array_merge( $inst->taxonomies_description, $txs );
		if ( ! is_admin() ) {
			foreach ( $txs as $tx ) {
				add_filter( "{$tx}_description", '\wpinc\plex\term_name\_cb_taxonomy_description', 10, 4 );
			}
		}
	}
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
 * Initialize the term name.
 *
 * @param array $args {
 *     Configuration arguments.
 *
 *     @type array  $vars                     Query variable names.
 *     @type string $name_key_prefix          (Optional) Key prefix of term metadata for custom names.
 *     @type string $singular_name_key_prefix (Optional) Key prefix of term metadata for custom singular names.
 *     @type string $description_key_prefix   (Optional) Key prefix of term metadata for custom description.
 * }
 */
function initialize( array $args = array() ) {
	$inst = _get_instance();

	$args += array(
		'vars'                     => array(),
		'name_key_prefix'          => '_name_',
		'singular_name_key_prefix' => '_singular_name_',
		'description_key_prefix'   => '_description_',
	);

	$inst->vars                  = $args['vars'];
	$inst->key_pre_name          = $args['name_key_prefix'];
	$inst->key_pre_singular_name = $args['singular_name_key_prefix'];
	$inst->key_pre_description   = $args['description_key_prefix'];

	global $pagenow;
	if ( ! is_admin() || ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) ) {
		add_filter( 'get_object_terms', '\wpinc\plex\term_name\_cb_get_terms', 10 );
		add_filter( 'get_terms', '\wpinc\plex\term_name\_cb_get_terms', 10 );
	}
}

/**
 * Retrieves term name.
 *
 * @param int   $term_id  Term ID.
 * @param bool  $singular (Optional) Whether the name is singular.
 * @param mixed $args     (Optional) An array of variable name to slugs.
 * @return string Term name.
 */
function get_term_name( int $term_id, bool $singular = false, $args = null ) {
	$inst = _get_instance();
	$key  = \wpinc\plex\get_argument_key( $inst->vars );
	$ret  = '';

	if ( \wpinc\plex\get_default_key( $inst->vars ) === $key ) {
		if ( $singular ) {
			$sn = get_term_meta( $term_id, $inst->key_pre_singular_name . $key, true );
			if ( ! empty( $sn ) ) {
				$ret = $sn;
			}
		}
	} else {
		$name = get_term_meta( $term_id, $inst->key_pre_name . $key, true );
		$sn   = get_term_meta( $term_id, $inst->key_pre_singular_name . $key, true );

		if ( $singular ) {
			$ret = empty( $sn ) ? $name : $sn;
		} else {
			$ret = empty( $name ) ? $sn : $name;
		}
	}
	return empty( $ret ) ? _get_term_field( 'name', $term_id ) : $ret;
}

/**
 * Retrieves term description.
 *
 * @param int   $term_id Term ID. Defaults to the current term ID.
 * @param mixed $args    (Optional) An array of variable name to slugs.
 * @return string Term description, if available.
 */
function term_description( int $term_id = 0, $args = null ) {
	if ( ! $term_id && ( is_tax() || is_tag() || is_category() ) ) {
		$t = get_queried_object();
		if ( $t ) {
			$term_id = $t->term_id;
		}
	}
	$inst = _get_instance();
	$key  = \wpinc\plex\get_argument_key( $inst->vars );
	$ret  = '';

	if ( \wpinc\plex\get_default_key( $inst->vars ) !== $key ) {
		$ret = get_term_meta( $term_id, $inst->key_pre_description . $key, true );
	}
	if ( empty( $ret ) ) {
		$ret = _get_term_field( 'description', $term_id );
	}
	return $ret;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'get_terms' filter.
 *
 * @access private
 *
 * @param \WP_Term[] $terms Array of found terms.
 * @return \WP_Term[] The filtered terms.
 */
function _cb_get_terms( array $terms ) {
	$inst = _get_instance();
	$key  = \wpinc\plex\get_query_key( $inst->vars );

	if ( \wpinc\plex\get_default_key( $inst->vars ) !== $key ) {
		foreach ( $terms as $t ) {
			if ( in_array( $t->taxonomy, $inst->taxonomies, true ) ) {
				_replace_term_name( $t, $t->taxonomy, $inst, $key );
			}
		}
	}
	return $terms;
}

/**
 * Callback function for 'get_{$taxonomy}' filter.
 *
 * @access private
 *
 * @param \WP_Term $term Term object.
 * @return \WP_Term The filtered term.
 */
function _cb_get_taxonomy( \WP_Term $term ): \WP_Term {
	$inst = _get_instance();
	$key  = \wpinc\plex\get_query_key( $inst->vars );

	if ( \wpinc\plex\get_default_key( $inst->vars ) !== $key ) {
		_replace_term_name( $term, $term->taxonomy, $inst, $key );
	}
	return $term;
}

/**
 * Replace the name field of terms.
 *
 * @access private
 *
 * @param \WP_Term $term     Term object.
 * @param string   $taxonomy The taxonomy slug.
 * @param object   $inst     The instance of plex\term_name.
 * @param string   $key      The key of term metadata.
 */
function _replace_term_name( \WP_Term $term, string $taxonomy, object $inst, string $key ) {
	if ( isset( $term->orig_name ) ) {
		return;
	}
	$name = get_term_meta( $term->term_id, $inst->key_pre_name . $key, true );
	$sn   = '';
	if ( in_array( $taxonomy, $inst->taxonomies_singular_name, true ) ) {
		$sn = get_term_meta( $term->term_id, $inst->key_pre_singular_name . $key, true );
	}
	$ret = empty( $name ) ? $sn : $name;
	if ( ! empty( $ret ) ) {
		$term->orig_name = $term->name;
		$term->name      = $ret;
	}
}


// -----------------------------------------------------------------------------


/**
 * Callback function for '{$taxonomy}_{$field}' filter.
 *
 * @access private
 *
 * @param mixed  $value    Value of the term field.
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @param string $context  Context to retrieve the term field value.
 * @return mixed Filtered value.
 */
function _cb_taxonomy_description( $value, int $term_id, string $taxonomy, string $context ) {
	if ( 'display' !== $context ) {
		return $value;
	}
	$inst = _get_instance();
	$key  = \wpinc\plex\get_query_key( $inst->vars );
	$ret  = '';

	if ( \wpinc\plex\get_default_key( $inst->vars ) !== $key ) {
		$ret = get_term_meta( $term_id, $inst->key_pre_description . $key, true );
	}
	if ( empty( $ret ) ) {
		$ret = $value;
	}
	return $ret;
}

/**
 * Get Term field.
 *
 * @access private
 *
 * @param string $field   Term field to fetch.
 * @param int    $term_id Term ID.
 */
function _get_term_field( string $field, int $term_id ) {
	$term = WP_Term::get_instance( $term_id );
	if ( is_wp_error( $term ) || ! is_object( $term ) || ! isset( $term->$field ) ) {
		return '';
	}
	return $term->$field;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for '{$taxonomy}_edit_form_fields' hook.
 *
 * @access private
 *
 * @param \WP_Term $term     Current taxonomy term object.
 * @param string   $taxonomy Current taxonomy slug.
 */
function _cb_taxonomy_edit_form_fields( \WP_Term $term, string $taxonomy ) {
	$inst    = _get_instance();
	$def_key = \wpinc\plex\get_default_key( $inst->vars );
	$t_meta  = get_term_meta( $term->term_id );

	$def_slugs  = \wpinc\plex\custom_rewrite\get_structures( 'default_slug', $inst->vars );
	$lab_base_n = esc_html_x( 'Name', 'term name', 'default' );

	$has_sn     = in_array( $taxonomy, $inst->taxonomies_singular_name, true );
	$has_def_sn = in_array( $taxonomy, $inst->taxonomies_default_singular_name, true );
	$has_desc   = in_array( $taxonomy, $inst->taxonomies_description, true );

	if ( $has_def_sn ) {
		$lab_pf = _get_admin_label( $def_slugs );
		$lab_n  = "$lab_base_n $lab_pf";

		$id_sn   = $inst->key_pre_singular_name . $def_key;
		$name_sn = $inst->key_pre_singular_name . "array[$def_key]";
		$val_sn  = isset( $t_meta[ $id_sn ] ) ? $t_meta[ $id_sn ][0] : '';
		_echo_name_field( $lab_n . __( ' (Singular Form)' ), $id_sn, $name_sn, $val_sn );
	}
	if ( $has_desc ) {
		$lab_base_d = esc_html__( 'Description' );
	}
	foreach ( \wpinc\plex\get_slug_combination( $inst->vars ) as $slugs ) {
		$key = implode( '_', $slugs );
		if ( $key === $def_key ) {
			continue;
		}
		$lab_pf = _get_admin_label( $slugs );
		$lab_n  = "$lab_base_n $lab_pf";

		$id_n   = $inst->key_pre_name . $key;
		$name_n = $inst->key_pre_name . "array[$key]";
		$val_n  = isset( $t_meta[ $id_n ] ) ? $t_meta[ $id_n ][0] : '';
		_echo_name_field( $lab_n, $id_n, $name_n, $val_n, 'padding-bottom: 6px;' );

		if ( $has_sn ) {
			$id_sn   = $inst->key_pre_singular_name . $key;
			$name_sn = $inst->key_pre_singular_name . "array[$key]";
			$val_sn  = isset( $t_meta[ $id_sn ] ) ? $t_meta[ $id_sn ][0] : '';
			_echo_name_field( $lab_n . __( ' (Singular Form)' ), $id_sn, $name_sn, $val_sn, 'padding-top: 6px;' );
		}
		if ( $has_desc ) {
			$lab_d  = "$lab_base_d $lab_pf";
			$id_d   = $inst->key_pre_description . $key;
			$name_d = $inst->key_pre_description . "array[$key]";
			$val_d  = isset( $t_meta[ $id_d ] ) ? $t_meta[ $id_d ][0] : '';
			_echo_description_field( $lab_d, $id_d, $name_d, $val_d );
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

/**
 * Function that echos the field of name.
 *
 * @access private
 *
 * @param string $label The label of the field.
 * @param string $id    The id of the field.
 * @param string $name  The name of the field.
 * @param string $val   The value of the field.
 * @param string $style The style of the field.
 */
function _echo_name_field( string $label, string $id, string $name, string $val, string $style = '' ) {
	?>
<tr class="form-field">
	<th style="<?php echo esc_attr( $style ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
	</th>
	<td style="<?php echo esc_attr( $style ); ?>">
		<input type="text" size="40" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>" />
	</td>
</tr>
	<?php
}

/**
 * Function that echos the field of description.
 *
 * @access private
 *
 * @param string $label The label of the field.
 * @param string $id    The id of the field.
 * @param string $name  The name of the field.
 * @param string $val   The value of the field.
 */
function _echo_description_field( string $label, string $id, string $name, string $val ) {
	?>
<tr class="form-field term-description-wrap">
	<th scope="row">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
	</th>
	<td>
		<textarea class="large-text" rows="5" cols="50" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $val ); ?></textarea>
	</td>
</tr>
	<?php
}

/**
 * Callback function for 'edited_{$taxonomy}' hook.
 *
 * @access private
 *
 * @param int $term_id Term ID.
 */
function _cb_edited_taxonomy( int $term_id ) {
	$inst     = _get_instance();
	$key_name = $inst->key_pre_name . 'array';
	$key_sn   = $inst->key_pre_singular_name . 'array';
	$key_desc = $inst->key_pre_description . 'array';

	// phpcs:disable
	if ( isset( $_POST[ $key_name ] ) ) {
		foreach ( $_POST[ $key_name ] as $key => $val ) {
			_modify_term_meta( $term_id, $inst->key_pre_name . $key, wp_unslash( $val ) );
		}
	}
	if ( isset( $_POST[ $key_sn ] ) ) {
		foreach ( $_POST[ $key_sn ] as $key => $val ) {
			_modify_term_meta( $term_id, $inst->key_pre_singular_name . $key, wp_unslash( $val ) );
		}
	}
	if ( isset( $_POST[ $key_desc ] ) ) {
		foreach ( $_POST[ $key_desc ] as $key => $val ) {
			_modify_term_meta( $term_id, $inst->key_pre_description . $key, wp_unslash( $val ) );
		}
	}
	// phpcs:enable
}

/**
 * Updates or removes term metadata.
 *
 * @access private
 *
 * @param int    $term_id Term ID.
 * @param string $key     Metadata name.
 * @param mixed  $val     Metadata value. Must be serializable if non-scalar.
 */
function _modify_term_meta( int $term_id, string $key, $val ) {
	if ( empty( $val ) ) {
		delete_term_meta( $term_id, $key );
	} else {
		update_term_meta( $term_id, $key, $val );
	}
}


// -----------------------------------------------------------------------------


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
		 * The key prefix of term metadata of a custom name.
		 *
		 * @var string
		 */
		public $key_pre_name = '';

		/**
		 * The key prefix of term metadata of a custom singular name.
		 *
		 * @var string
		 */
		public $key_pre_singular_name = '';

		/**
		 * The key prefix of term metadata of a custom description.
		 *
		 * @var string
		 */
		public $key_pre_description = '';

		/**
		 * The taxonomies with custom names.
		 *
		 * @var array
		 */
		public $taxonomies = array();

		/**
		 * The taxonomies with custom singular names.
		 *
		 * @var array
		 */
		public $taxonomies_singular_name = array();

		/**
		 * The taxonomies with a custom singular name for default name.
		 *
		 * @var array
		 */
		public $taxonomies_default_singular_name = array();

		/**
		 * The taxonomies with custom descriptions.
		 *
		 * @var array
		 */
		public $taxonomies_description = array();
	};
	return $values;
}
