<?php
/**
 * Handles the Organisation custom post type for CSC Member Directory.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Organisations {

	public function register_hooks( $loader ) {
		$loader->add_action( 'init', $this, 'register_post_type' );
		$loader->add_action( 'wp_ajax_nopriv_csc_search_orgs', $this, 'ajax_search_orgs' );
		$loader->add_action( 'wp_ajax_csc_search_orgs', $this, 'ajax_search_orgs' );
		$loader->add_action( 'add_meta_boxes', $this, 'add_meta_boxes' );
		$loader->add_action( 'save_post_csc_organisation', $this, 'save_meta', 10, 2 );
	}

	public function register_post_type() {
		register_post_type( 'csc_organisation', array(
			'labels' => array(
				'name'               => 'Organisations',
				'singular_name'      => 'Organisation',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Organisation',
				'edit_item'          => 'Edit Organisation',
				'view_item'          => 'View Organisation',
				'all_items'          => 'All Organisations',
				'search_items'       => 'Search Organisations',
				'not_found'          => 'No organisations found.',
			),
			'public'            => false,
			'publicly_queryable'=> false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => false,
			'supports'          => array( 'title' ),
			'capability_type'   => 'post',
			'menu_icon'         => 'dashicons-building',
			'menu_position'     => 25,
		) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'csc_org_details',
			'Organisation Details',
			array( $this, 'render_meta_box' ),
			'csc_organisation',
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'csc_org_meta_save', 'csc_org_meta_nonce' );
		$location     = get_post_meta( $post->ID, '_csc_org_location',    true );
		$sector       = get_post_meta( $post->ID, '_csc_org_sector',      true );
		$country      = get_post_meta( $post->ID, '_csc_org_country',     true );
		$county       = get_post_meta( $post->ID, '_csc_org_county',      true );
		$postcode     = get_post_meta( $post->ID, '_csc_org_postcode',    true );
		$industry     = get_post_meta( $post->ID, '_csc_org_industry',    true );
		$igp_category = get_post_meta( $post->ID, '_csc_org_igp_category', true );
		$description  = get_post_meta( $post->ID, '_csc_org_description', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="csc_org_description">Description</label></th>
				<td><textarea id="csc_org_description" name="csc_org_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="csc_org_location">Location / City</label></th>
				<td><input type="text" id="csc_org_location" name="csc_org_location" value="<?php echo esc_attr( $location ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="csc_org_sector">Company Type</label></th>
				<td>
					<select id="csc_org_sector" name="csc_org_sector">
						<option value="">Select Company Type</option>
						<?php foreach ( self::get_company_types() as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $sector, $type ); ?>><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="csc_org_industry">Primary Industry</label></th>
				<td>
					<select id="csc_org_industry" name="csc_org_industry">
						<option value="">Select Industry</option>
						<?php foreach ( self::get_primary_industries() as $ind ) : ?>
						<option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $industry, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="csc_org_igp">IGP Category</label></th>
				<td>
					<select id="csc_org_igp" name="csc_org_igp">
						<option value="">Select IGP Category</option>
						<?php foreach ( self::get_igp_categories() as $igp ) : ?>
						<option value="<?php echo esc_attr( $igp ); ?>" <?php selected( $igp_category, $igp ); ?>><?php echo esc_html( $igp ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="csc_org_country">Country</label></th>
				<td><input type="text" id="csc_org_country" name="csc_org_country" value="<?php echo esc_attr( $country ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="csc_org_county">County (UK only)</label></th>
				<td><input type="text" id="csc_org_county" name="csc_org_county" value="<?php echo esc_attr( $county ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="csc_org_postcode">Postcode</label></th>
				<td><input type="text" id="csc_org_postcode" name="csc_org_postcode" value="<?php echo esc_attr( $postcode ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="csc_org_phone">Contact Number</label></th>
				<td><input type="text" id="csc_org_phone" name="csc_org_phone" value="<?php echo esc_attr( get_post_meta( $post->ID, '_csc_org_phone', true ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="csc_org_website">Website</label></th>
				<td><input type="url" id="csc_org_website" name="csc_org_website" value="<?php echo esc_attr( get_post_meta( $post->ID, '_csc_org_website', true ) ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['csc_org_meta_nonce'] ) || ! wp_verify_nonce( $_POST['csc_org_meta_nonce'], 'csc_org_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'_csc_org_location'     => 'csc_org_location',
			'_csc_org_sector'       => 'csc_org_sector',
			'_csc_org_industry'     => 'csc_org_industry',
			'_csc_org_igp_category' => 'csc_org_igp',
			'_csc_org_country'      => 'csc_org_country',
			'_csc_org_phone'        => 'csc_org_phone',
			'_csc_org_county'       => 'csc_org_county',
			'_csc_org_postcode'     => 'csc_org_postcode',
		);

		foreach ( $text_fields as $meta_key => $post_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
			}
		}

		if ( isset( $_POST['csc_org_description'] ) ) {
			update_post_meta( $post_id, '_csc_org_description', sanitize_textarea_field( $_POST['csc_org_description'] ) );
		}
		if ( isset( $_POST['csc_org_website'] ) ) {
			update_post_meta( $post_id, '_csc_org_website', esc_url_raw( $_POST['csc_org_website'] ) );
		}
	}

	public function ajax_search_orgs() {
		check_ajax_referer( 'csc_public_nonce', 'nonce' );

		$search = sanitize_text_field( $_GET['q'] ?? '' );

		$args = array(
			'post_type'      => 'csc_organisation',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		$posts   = get_posts( $args );
		$results = array_map( function( $p ) {
			return array( 'id' => $p->ID, 'name' => $p->post_title );
		}, $posts );

		wp_send_json_success( $results );
	}

	public static function get_company_types() {
		return array(
			'Academia',
			'Developer / Operator / Major Contractor',
			'Engineering / Manufacturing',
			'Industrial Service Provider',
			'Material Supplier',
			'Professional Services',
			'Public Sector Body',
			'Trade Body',
		);
	}

	public static function get_primary_industries() {
		return array(
			'Advanced Turbine Technology',
			'Agriculture',
			'Aviation/Aerospace',
			'Civil Engineering',
			'Construction',
			'Defence',
			'Food & Beverage',
			'Government',
			'Hydro',
			'Marine',
			'Marine Energie',
			'Mining',
			'Nuclear',
			'Offshore Renewable Energy',
			'Oil & Gas',
			'Petrochemical',
			'Pharmaceuticals',
			'Ports and Terminals',
			'Solar',
			'Telecommunications',
			'Transport & Logistics',
			'Wind',
		);
	}

	public static function get_igp_categories() {
		return array(
			'Advanced Turbine Technology',
			'Future Electrical Systems & Cables',
			'Industrialised Foundations & Substructures',
			'Next Generation Installation, Operations & Maintenance',
			'Smart Environmental Services',
		);
	}
}
