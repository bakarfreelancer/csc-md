<?php
/**
 * Newsletters & Resources page.
 *
 * Shortcode: [csc_newsletters]
 * Admin sets:
 *   - Resources link URL + label  → option csc_resource_url / csc_resource_label
 *   - News Letters                → CPT csc_newsletter (title, content, date, tags)
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Newsletters {

	public function register_hooks() {
		add_shortcode( 'csc_newsletters', array( $this, 'render' ) );
		add_action( 'init',              array( $this, 'register_cpt' ) );
		add_action( 'admin_menu',        array( $this, 'admin_settings_page' ) );
		add_action( 'admin_init',        array( $this, 'register_settings' ) );
	}

	/* -----------------------------------------------------------------------
	 * Newsletter CPT
	 * --------------------------------------------------------------------- */
	public function register_cpt() {
		register_post_type( 'csc_newsletter', array(
			'labels'            => array(
				'name'               => 'Newsletters',
				'singular_name'      => 'Newsletter',
				'add_new_item'       => 'Add Newsletter',
				'edit_item'          => 'Edit Newsletter',
			),
			'public'            => false,
			'publicly_queryable'=> false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'supports'          => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'         => 'dashicons-email-alt',
			'menu_position'     => 27,
		) );
	}

	/* -----------------------------------------------------------------------
	 * Admin settings page for Resource link
	 * --------------------------------------------------------------------- */
	public function admin_settings_page() {
		add_submenu_page(
			'options-general.php',
			'CSC Resource Settings',
			'CSC Resources',
			'manage_options',
			'csc-resource-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'csc_resource_group', 'csc_resource_url',   array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'csc_resource_group', 'csc_resource_label', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>CSC Resource Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'csc_resource_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="csc_resource_label">Resource Label</label></th>
						<td><input type="text" id="csc_resource_label" name="csc_resource_label" value="<?php echo esc_attr( get_option( 'csc_resource_label', 'Offshore Wind Industrial Growth' ) ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="csc_resource_url">Resource URL</label></th>
						<td><input type="url" id="csc_resource_url" name="csc_resource_url" value="<?php echo esc_attr( get_option( 'csc_resource_url', '' ) ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings' ); ?>
			</form>
			<hr>
			<p>Add newsletters via <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=csc_newsletter' ) ); ?>">Newsletters</a> in the menu. Each newsletter can have a <code>csc_newsletter_tags</code> custom field (comma-separated tags) and a <code>csc_newsletter_date_label</code> field for the date label shown on the front end.</p>
		</div>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * Shortcode
	 * --------------------------------------------------------------------- */
	public function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Csc_Dashboard::login_url() );
			exit;
		}
		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_safe_redirect( Csc_Dashboard::login_url() );
			exit;
		}

		$resource_label = get_option( 'csc_resource_label', 'Offshore Wind Industrial Growth' );
		$resource_url   = get_option( 'csc_resource_url', '' );

		$newsletters = get_posts( array(
			'post_type'      => 'csc_newsletter',
			'post_status'    => 'publish',
			'posts_per_page' => 40,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		ob_start();
		?>
		<div class="csc-member-portal">
			<?php echo Csc_Dashboard::render_sidebar( 'newsletters', $user ); ?>

			<main class="csc-portal-main">

				<!-- Resources section -->
				<div class="csc-nl-section csc-nl-resources">
					<div class="csc-nl-section-header">
						<h2 class="csc-nl-section-title">Resources</h2>
						<?php if ( $resource_url ) : ?>
						<a href="<?php echo esc_url( $resource_url ); ?>" target="_blank" rel="noopener noreferrer" class="csc-btn-primary csc-nl-visit-btn">Visit Resource</a>
						<?php endif; ?>
					</div>
					<?php if ( $resource_label ) : ?>
					<div class="csc-nl-resource-item">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
						<?php if ( $resource_url ) : ?>
						<a href="<?php echo esc_url( $resource_url ); ?>" target="_blank" rel="noopener noreferrer" class="csc-nl-resource-link"><?php echo esc_html( $resource_label ); ?></a>
						<?php else : ?>
						<span class="csc-nl-resource-label"><?php echo esc_html( $resource_label ); ?></span>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

				<!-- News Letters section -->
				<div class="csc-nl-section csc-nl-letters">
					<h2 class="csc-nl-section-title">News Letters</h2>
					<?php if ( empty( $newsletters ) ) : ?>
					<div class="csc-dir-empty" style="padding: 40px 0;">
						<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="6" y="10" width="36" height="28" rx="3"/><polyline points="6,10 24,28 42,10"/></svg>
						<p>No newsletters yet. Check back soon.</p>
					</div>
					<?php else : ?>
					<div class="csc-nl-list">
						<?php foreach ( $newsletters as $nl ) :
							$date_label = get_post_meta( $nl->ID, 'csc_newsletter_date_label', true )
								?: wp_date( 'F Y', strtotime( $nl->post_date ) );
							$tags_raw   = get_post_meta( $nl->ID, 'csc_newsletter_tags', true );
							$tags       = $tags_raw ? array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) : array();
							$excerpt    = wp_trim_words( wp_strip_all_tags( $nl->post_content ), 25, '…' );
						?>
						<div class="csc-nl-item">
							<div class="csc-nl-item__date">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="16" height="14" rx="1"/><line x1="2" y1="8" x2="18" y2="8"/><line x1="6" y1="2" x2="6" y2="6"/><line x1="14" y1="2" x2="14" y2="6"/></svg>
								<?php echo esc_html( $date_label ); ?>
							</div>
							<div class="csc-nl-item__body">
								<h3 class="csc-nl-item__title"><?php echo esc_html( $nl->post_title ); ?></h3>
								<?php if ( $excerpt ) : ?>
								<p class="csc-nl-item__excerpt"><?php echo esc_html( $excerpt ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $tags ) ) : ?>
								<div class="csc-nl-item__tags">
									<?php foreach ( $tags as $tag ) : ?>
									<span class="csc-dir-tag csc-dir-tag--sector"><?php echo esc_html( $tag ); ?></span>
									<?php endforeach; ?>
								</div>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>

			</main>
		</div>
		<?php
		return ob_get_clean();
	}
}
