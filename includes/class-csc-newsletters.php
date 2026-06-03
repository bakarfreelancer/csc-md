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

	/** Emails sent per cron batch (keep low to avoid SMTP rate limits). */
	const BATCH_SIZE = 20;

	/** Seconds between batches. */
	const BATCH_INTERVAL = 120;

	public function register_hooks() {
		add_shortcode( 'csc_newsletters', array( $this, 'render' ) );
		add_action( 'init',              array( $this, 'register_cpt' ) );
		add_action( 'admin_menu',        array( $this, 'admin_settings_page' ) );
		add_action( 'admin_init',        array( $this, 'register_settings' ) );
		add_filter( 'query_vars',        array( $this, 'add_query_vars' ) );

		// Resource CPT meta box
		add_action( 'add_meta_boxes',          array( $this, 'add_resource_meta_box' ) );
		add_action( 'save_post_csc_resource',  array( $this, 'save_resource_meta' ) );

		// Newsletter publish → queue emails
		add_action( 'transition_post_status', array( $this, 'on_newsletter_publish' ), 10, 3 );
		// Cron batch processor
		add_action( 'csc_send_nl_batch', array( $this, 'process_newsletter_batch' ) );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'nl_page';
		$vars[] = 'nl_id';
		return $vars;
	}

	/* -----------------------------------------------------------------------
	 * CPT registrations
	 * --------------------------------------------------------------------- */
	public function register_cpt() {
		register_post_type( 'csc_newsletter', array(
			'labels'             => array(
				'name'          => 'Newsletters',
				'singular_name' => 'Newsletter',
				'add_new_item'  => 'Add Newsletter',
				'edit_item'     => 'Edit Newsletter',
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'supports'           => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'          => 'dashicons-email-alt',
			'menu_position'      => 27,
		) );

		register_post_type( 'csc_resource', array(
			'labels'             => array(
				'name'               => 'Resources',
				'singular_name'      => 'Resource',
				'add_new_item'       => 'Add Resource',
				'edit_item'          => 'Edit Resource',
				'all_items'          => 'All Resources',
				'search_items'       => 'Search Resources',
				'not_found'          => 'No resources found.',
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'supports'           => array( 'title', 'page-attributes' ), // page-attributes gives the Order field
			'menu_icon'          => 'dashicons-admin-links',
			'menu_position'      => 28,
		) );
	}

	/* -----------------------------------------------------------------------
	 * Resource CPT — URL meta box
	 * --------------------------------------------------------------------- */
	public function add_resource_meta_box() {
		add_meta_box(
			'csc_resource_url',
			'Resource URL',
			array( $this, 'render_resource_meta_box' ),
			'csc_resource',
			'normal',
			'high'
		);
	}

	public function render_resource_meta_box( $post ) {
		wp_nonce_field( 'csc_save_resource_url', 'csc_resource_url_nonce' );
		$url = get_post_meta( $post->ID, '_csc_resource_url', true );
		echo '<p><label for="csc_resource_url_field"><strong>URL</strong></label></p>';
		echo '<input type="url" id="csc_resource_url_field" name="csc_resource_url"
			value="' . esc_attr( $url ) . '"
			placeholder="https://…"
			style="width:100%;max-width:600px;">';
		echo '<p class="description">Link the resource label will point to. Leave blank to display as plain text.</p>';
	}

	public function save_resource_meta( $post_id ) {
		if ( ! isset( $_POST['csc_resource_url_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['csc_resource_url_nonce'], 'csc_save_resource_url' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_csc_resource_url', esc_url_raw( $_POST['csc_resource_url'] ?? '' ) );
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
		register_setting( 'csc_resource_group', 'csc_resource_heading', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function render_settings_page() {
		?>
<div class="wrap">
    <h1>CSC Newsletters &amp; Resources Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'csc_resource_group' ); ?>
        <h2 class="title">Resources Section</h2>
        <table class="form-table">
            <tr>
                <th><label for="csc_resource_heading">Section Heading</label></th>
                <td>
                    <input type="text" id="csc_resource_heading" name="csc_resource_heading"
                        value="<?php echo esc_attr( get_option( 'csc_resource_heading', 'Resources' ) ); ?>"
                        class="regular-text">
                    <p class="description">Heading shown above the resources list. Defaults to "Resources".</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Save Settings' ); ?>
    </form>
    <hr>
    <h2 class="title">Managing Resources</h2>
    <p>Add and manage individual resources via <a
            href="<?php echo esc_url( admin_url( 'edit.php?post_type=csc_resource' ) ); ?>"><strong>Resources</strong></a>
        in the admin menu.</p>
    <p>Each resource has a <strong>title</strong> (the link label) and a <strong>URL</strong> field. Use the
        <strong>Order</strong> field (bottom of the edit screen) to control the display order — lower numbers appear
        first.</p>
    <hr>
    <h2 class="title">Managing Newsletters</h2>
    <p>Add newsletters via <a
            href="<?php echo esc_url( admin_url( 'edit.php?post_type=csc_newsletter' ) ); ?>"><strong>Newsletters</strong></a>
        in the admin menu. Each newsletter supports the following custom fields:</p>
    <ul style="list-style:disc;padding-left:20px;">
        <li><code>csc_newsletter_tags</code> — comma-separated tags shown as pills on the card and single view.</li>
        <li><code>csc_newsletter_date_label</code> — date label shown on the front end (e.g. "January 2025"). Defaults
            to the post publish month if left blank.</li>
    </ul>
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

		$nl_id = isset( $_GET['nl_id'] ) ? absint( $_GET['nl_id'] ) : 0;
		if ( $nl_id ) {
			return $this->render_single( $nl_id, $user );
		}

		$resource_heading = get_option( 'csc_resource_heading', 'Resources' );

		$resources = get_posts( array(
			'post_type'      => 'csc_resource',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		$per_page = 12;
		$paged    = max( 1, absint( $_GET['nl_page'] ?? 1 ) );
		$nl_url   = Csc_Dashboard::portal_url( 'member-newsletters' );

		$nl_query = new WP_Query( array(
			'post_type'      => 'csc_newsletter',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$newsletters = $nl_query->posts;
		$nl_pages    = $nl_query->max_num_pages;

		ob_start();
		?>
<div class="csc-member-portal">
    <?php echo Csc_Dashboard::render_sidebar( 'newsletters', $user ); ?>

    <main class="csc-portal-main">

        <!-- Resources section -->
        <div class="csc-nl-section csc-nl-resources">
            <h2 class="csc-nl-section-title"><?php echo esc_html( $resource_heading ); ?></h2>
            <?php if ( empty( $resources ) ) : ?>
            <p class="csc-nl-resources-empty">No resources have been added yet. Check back soon.</p>
            <?php else : ?>
            <div class="csc-nl-resource-list">
                <?php foreach ( $resources as $resource ) :
							$res_url = get_post_meta( $resource->ID, '_csc_resource_url', true );
						?>
                <div class="csc-nl-resource-item">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z" />
                        <polyline points="14,2 14,8 20,8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10,9 9,9 8,9" />
                    </svg>
                    <?php if ( $res_url ) : ?>
                    <a href="<?php echo esc_url( $res_url ); ?>" target="_blank" rel="noopener noreferrer"
                        class="csc-nl-resource-link"><?php echo esc_html( $resource->post_title ); ?></a>
                    <?php else : ?>
                    <span class="csc-nl-resource-label"><?php echo esc_html( $resource->post_title ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- News Letters section -->
        <div class="csc-nl-section csc-nl-letters">
            <h2 class="csc-nl-section-title">Newsletters</h2>
            <?php if ( empty( $newsletters ) ) : ?>
            <div class="csc-dir-empty" style="padding: 40px 0;">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <rect x="6" y="10" width="36" height="28" rx="3" />
                    <polyline points="6,10 24,28 42,10" />
                </svg>
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
							$single_url = add_query_arg( 'nl_id', $nl->ID, $nl_url );
						?>
                <a href="<?php echo esc_url( $single_url ); ?>" class="csc-nl-item">
                    <div class="csc-nl-item__date">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="4" width="16" height="14" rx="1" />
                            <line x1="2" y1="8" x2="18" y2="8" />
                            <line x1="6" y1="2" x2="6" y2="6" />
                            <line x1="14" y1="2" x2="14" y2="6" />
                        </svg>
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
                    <span class="csc-nl-item__arrow" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="7 4 13 10 7 16" />
                        </svg>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ( $nl_pages > 1 ) : ?>
            <div class="csc-dir-pagination">
                <?php echo paginate_links( array(
							'base'      => add_query_arg( 'nl_page', '%#%', $nl_url ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $nl_pages,
							'prev_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="10 4 6 8 10 12"/></svg>',
							'next_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 4 10 8 6 12"/></svg>',
							'type'      => 'list',
						) ); ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

    </main>
</div>
<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Single newsletter view
	 * --------------------------------------------------------------------- */
	private function render_single( $nl_id, $user ) {
		$nl = get_post( $nl_id );

		if ( ! $nl || $nl->post_type !== 'csc_newsletter' || $nl->post_status !== 'publish' ) {
			return $this->render_not_found( $user );
		}

		$nl_url     = Csc_Dashboard::portal_url( 'member-newsletters' );
		$date_label = get_post_meta( $nl->ID, 'csc_newsletter_date_label', true )
			?: wp_date( 'F Y', strtotime( $nl->post_date ) );
		$tags_raw   = get_post_meta( $nl->ID, 'csc_newsletter_tags', true );
		$tags       = $tags_raw ? array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) : array();

		ob_start();
		?>
<div class="csc-member-portal">
    <?php echo Csc_Dashboard::render_sidebar( 'newsletters', $user ); ?>

    <main class="csc-portal-main">

        <!-- Back navigation -->
        <nav class="csc-co-back" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( $nl_url ); ?>" class="csc-co-back__link">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" aria-hidden="true">
                    <polyline points="13 4 7 10 13 16" />
                </svg>
                Newsletters &amp; Resources
            </a>
        </nav>

        <!-- Newsletter article -->
        <div class="csc-nl-single">
            <div class="csc-nl-single__meta">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="16" height="14" rx="1" />
                    <line x1="2" y1="8" x2="18" y2="8" />
                    <line x1="6" y1="2" x2="6" y2="6" />
                    <line x1="14" y1="2" x2="14" y2="6" />
                </svg>
                <?php echo esc_html( $date_label ); ?>
            </div>
            <h1 class="csc-nl-single__title"><?php echo esc_html( $nl->post_title ); ?></h1>
            <?php if ( ! empty( $tags ) ) : ?>
            <div class="csc-nl-single__tags">
                <?php foreach ( $tags as $tag ) : ?>
                <span class="csc-dir-tag csc-dir-tag--sector"><?php echo esc_html( $tag ); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="csc-nl-single__body">
                <?php echo wp_kses_post( wpautop( $nl->post_content ) ); ?>
            </div>
        </div>

    </main>
</div>
<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Not-found fallback
	 * --------------------------------------------------------------------- */
	private function render_not_found( $user ) {
		$nl_url = Csc_Dashboard::portal_url( 'member-newsletters' );
		ob_start();
		?>
<div class="csc-member-portal">
    <?php echo Csc_Dashboard::render_sidebar( 'newsletters', $user ); ?>
    <main class="csc-portal-main">
        <div class="csc-not-found-wrap">
            <div class="csc-not-found-card">
                <div class="csc-not-found-icon">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <rect x="8" y="12" width="48" height="40" rx="3" />
                        <polyline points="8,12 32,36 56,12" />
                        <line x1="38" y1="38" x2="50" y2="50" />
                        <line x1="50" y1="38" x2="38" y2="50" />
                    </svg>
                </div>
                <h2 class="csc-not-found-title">Newsletter not found</h2>
                <p class="csc-not-found-message">This newsletter is unavailable or may have been removed.</p>
                <a href="<?php echo esc_url( $nl_url ); ?>" class="csc-btn-primary csc-not-found-btn">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <polyline points="13 4 7 10 13 16" />
                    </svg>
                    Back to Newsletters
                </a>
            </div>
        </div>
    </main>
</div>
<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Newsletter publish hook — build email queue
	 * --------------------------------------------------------------------- */
	public function on_newsletter_publish( $new_status, $old_status, $post ) {
		if ( $post->post_type !== 'csc_newsletter' ) {
			return;
		}
		// Only trigger on the first publish (not on subsequent saves)
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}
		// Guard against duplicate queuing
		if ( get_post_meta( $post->ID, '_csc_nl_email_queued', true ) === '1' ) {
			return;
		}

		// Collect approved users who opted in to newsletter emails
		$user_ids = get_users( array(
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => '_csc_status',           'value' => 'approved', 'compare' => '=' ),
				array( 'key' => '_csc_notif_newsletter', 'value' => '1',        'compare' => '=' ),
			),
			'fields' => 'ID',
			'number' => -1,
		) );

		if ( empty( $user_ids ) ) {
			return;
		}

		update_post_meta( $post->ID, '_csc_nl_email_queue',  wp_json_encode( array_values( $user_ids ) ) );
		update_post_meta( $post->ID, '_csc_nl_email_queued', '1' );

		// Kick off the first batch after a short delay
		wp_schedule_single_event( time() + 60, 'csc_send_nl_batch', array( $post->ID ) );
	}

	/* -----------------------------------------------------------------------
	 * Cron: send one batch, re-schedule if more remain
	 * --------------------------------------------------------------------- */
	public function process_newsletter_batch( $post_id ) {
		$raw = get_post_meta( $post_id, '_csc_nl_email_queue', true );
		if ( ! $raw ) {
			return;
		}

		$queue = json_decode( $raw, true );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			delete_post_meta( $post_id, '_csc_nl_email_queue' );
			return;
		}

		$nl = get_post( $post_id );
		if ( ! $nl || $nl->post_status !== 'publish' ) {
			return; // newsletter was unpublished/deleted — abort silently
		}

		// Take one batch
		$batch = array_splice( $queue, 0, self::BATCH_SIZE );

		foreach ( $batch as $user_id ) {
			$user = get_user_by( 'ID', (int) $user_id );
			if ( $user ) {
				$this->send_newsletter_email( $user, $nl );
			}
		}

		if ( ! empty( $queue ) ) {
			// Persist remaining queue and schedule next batch
			update_post_meta( $post_id, '_csc_nl_email_queue', wp_json_encode( array_values( $queue ) ) );
			wp_schedule_single_event( time() + self::BATCH_INTERVAL, 'csc_send_nl_batch', array( $post_id ) );
		} else {
			delete_post_meta( $post_id, '_csc_nl_email_queue' );
		}
	}

	/* -----------------------------------------------------------------------
	 * Build and send a single newsletter notification email
	 * --------------------------------------------------------------------- */
	private function send_newsletter_email( $user, $nl ) {
		$site       = get_bloginfo( 'name' );
		$fname      = get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name;
		$date_label = get_post_meta( $nl->ID, 'csc_newsletter_date_label', true )
			?: wp_date( 'F Y', strtotime( $nl->post_date ) );
		$excerpt    = wp_trim_words( wp_strip_all_tags( $nl->post_content ), 40, '…' );
		$view_url   = add_query_arg( 'nl_id', $nl->ID, Csc_Dashboard::portal_url( 'member-newsletters' ) );
		$unsub_url  = add_query_arg( 'tab', 'notifications', Csc_Dashboard::portal_url( 'member-settings' ) );

		$subject = "[{$site}] {$nl->post_title}";

		$body  = "Hi {$fname},\n\n";
		$body .= "A new newsletter is available: {$nl->post_title} ({$date_label})\n\n";
		if ( $excerpt ) {
			$body .= "{$excerpt}\n\n";
		}
		$body .= "Read the full newsletter:\n{$view_url}\n\n";
		$body .= "— The {$site} Team\n\n";
		$body .= "---\n";
		$body .= "You're receiving this because newsletter emails are enabled in your account.\n";
		$body .= "To unsubscribe: {$unsub_url}";

		wp_mail( $user->user_email, $subject, $body );
	}
}