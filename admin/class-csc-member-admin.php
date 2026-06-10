<?php
/**
 * Admin panel for managing CSC member applications (approve / reject).
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/admin
 */
class Csc_Member_Admin {

	public function register_hooks( $loader ) {
		$loader->add_action( 'admin_menu', $this, 'add_admin_menu' );
		$loader->add_action( 'wp_ajax_csc_approve_member', $this, 'approve_member' );
		$loader->add_action( 'wp_ajax_csc_reject_member', $this, 'reject_member' );
		$loader->add_filter( 'manage_users_columns', $this, 'add_status_column' );
		$loader->add_filter( 'manage_users_custom_column', $this, 'render_status_column', 10, 3 );
		// Company edit permission on user profile screen
		$loader->add_action( 'edit_user_profile',        $this, 'render_company_permission_field' );
		$loader->add_action( 'edit_user_profile_update', $this, 'save_company_permission_field' );
	}

	/* -----------------------------------------------------------------------
	 * Admin menu
	 * --------------------------------------------------------------------- */
	public function add_admin_menu() {
		add_menu_page(
			'CSC Members',
			'CSC Members',
			'manage_options',
			'csc-members',
			array( $this, 'render_members_page' ),
			'dashicons-groups',
			26
		);
	}

	/* -----------------------------------------------------------------------
	 * Members admin page
	 * --------------------------------------------------------------------- */
	public function render_members_page() {
		$filter  = sanitize_text_field( $_GET['csc_filter'] ?? 'pending' );
		$search  = sanitize_text_field( $_GET['s'] ?? '' );
		$paged   = max( 1, intval( $_GET['paged'] ?? 1 ) );
		$per     = 20;

		$query_args = array(
			'meta_key'    => '_csc_status',
			'meta_value'  => $filter,
			'number'      => $per,
			'offset'      => ( $paged - 1 ) * $per,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'count_total' => true,
		);

		if ( $search ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'display_name', 'user_email' );
		}

		$query = new WP_User_Query( $query_args );
		$users = $query->get_results();
		$total = $query->get_total();
		$pages = ceil( $total / $per );

		// Count totals for tab badges (without search filter to always show full counts)
		$counts = array();
		foreach ( array( 'pending', 'approved', 'rejected' ) as $s ) {
			$q          = new WP_User_Query( array( 'meta_key' => '_csc_status', 'meta_value' => $s, 'count_total' => true, 'number' => 0 ) );
			$counts[$s] = $q->get_total();
		}

		$admin_nonce = wp_create_nonce( 'csc_admin_action' );
		$base_url    = admin_url( 'admin.php?page=csc-members&csc_filter=' . $filter . ( $search ? '&s=' . urlencode( $search ) : '' ) );

		$pagination_args = array(
			'base'      => add_query_arg( 'paged', '%#%', $base_url ),
			'format'    => '',
			'current'   => $paged,
			'total'     => $pages,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'type'      => 'plain',
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">CSC Members</h1>
			<hr class="wp-header-end">

			<!-- Status filter tabs -->
			<ul class="subsubsub">
				<?php
				$statuses = array( 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected' );
				$keys     = array_keys( $statuses );
				foreach ( $statuses as $s => $label ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=csc-members&csc_filter=' . $s ) ); ?>"
					   class="<?php echo $filter === $s ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo intval( $counts[$s] ); ?>)</span>
					</a>
					<?php echo end( $keys ) !== $s ? ' | ' : ''; ?>
				</li>
				<?php endforeach; ?>
			</ul>

			<!-- Search form -->
			<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-top:8px;">
				<input type="hidden" name="page" value="csc-members">
				<input type="hidden" name="csc_filter" value="<?php echo esc_attr( $filter ); ?>">
				<p class="search-box">
					<label class="screen-reader-text" for="csc-member-search">Search Members</label>
					<input type="search" id="csc-member-search" name="s"
					       value="<?php echo esc_attr( $search ); ?>"
					       placeholder="Search by name or email&hellip;">
					<input type="submit" class="button" value="Search Members">
					<?php if ( $search ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=csc-members&csc_filter=' . $filter ) ); ?>"
					   class="button" style="margin-left:4px;">Clear</a>
					<?php endif; ?>
				</p>
			</form>

			<!-- Top tablenav -->
			<div class="tablenav top">
				<div class="tablenav-pages <?php echo $pages <= 1 ? 'one-page' : ''; ?>">
					<span class="displaying-num">
						<?php echo number_format_i18n( $total ); ?> item<?php echo $total !== 1 ? 's' : ''; ?>
					</span>
					<?php if ( $pages > 1 ) : ?>
					<span class="pagination-links">
						<?php echo paginate_links( $pagination_args ); ?>
					</span>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>

			<!-- Table -->
			<table class="wp-list-table widefat fixed striped users">
				<thead>
					<tr>
						<th scope="col" class="column-name manage-column">Name</th>
						<th scope="col" class="manage-column">Email</th>
						<th scope="col" class="manage-column">Organisation</th>
						<th scope="col" class="manage-column">Job Title</th>
						<th scope="col" class="manage-column">Registered</th>
						<th scope="col" class="manage-column">Actions</th>
					</tr>
				</thead>
				<tbody id="csc-members-list">
				<?php if ( empty( $users ) ) : ?>
					<tr>
						<td colspan="6" style="text-align:center;padding:24px;">
							<?php if ( $search ) : ?>
								No members found matching <strong><?php echo esc_html( $search ); ?></strong>
								with status <strong><?php echo esc_html( $filter ); ?></strong>.
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=csc-members&csc_filter=' . $filter ) ); ?>">Clear search</a>
							<?php else : ?>
								No members found with status: <strong><?php echo esc_html( $filter ); ?></strong>.
							<?php endif; ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $users as $user ) :
						$org_id    = get_user_meta( $user->ID, '_csc_organisation_id', true );
						$org       = $org_id ? get_post( $org_id ) : null;
						$job_title = get_user_meta( $user->ID, '_csc_job_title', true );
						$status    = get_user_meta( $user->ID, '_csc_status', true );
						$reg_date  = date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) );
					?>
					<tr id="csc-row-<?php echo $user->ID; ?>">
						<td class="column-name has-row-actions">
							<strong>
								<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
									<?php echo esc_html( $user->display_name ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td>
							<?php if ( $org ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $org->ID ) ); ?>">
									<?php echo esc_html( $org->post_title ); ?>
								</a>
							<?php else : ?>
								<em>—</em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $job_title ?: '—' ); ?></td>
						<td><?php echo esc_html( $reg_date ); ?></td>
						<td class="csc-action-cell">
							<?php if ( $status === 'pending' ) : ?>
								<button class="button button-primary csc-approve-btn"
								        data-user="<?php echo esc_attr( $user->ID ); ?>"
								        data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Approve</button>
								<button class="button csc-reject-btn"
								        data-user="<?php echo esc_attr( $user->ID ); ?>"
								        data-nonce="<?php echo esc_attr( $admin_nonce ); ?>"
								        style="margin-left:4px;">Reject</button>
							<?php elseif ( $status === 'rejected' ) : ?>
								<button class="button button-primary csc-approve-btn"
								        data-user="<?php echo esc_attr( $user->ID ); ?>"
								        data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Approve</button>
							<?php elseif ( $status === 'approved' ) : ?>
								<button class="button csc-reject-btn"
								        data-user="<?php echo esc_attr( $user->ID ); ?>"
								        data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Revoke Access</button>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Email</th>
						<th scope="col">Organisation</th>
						<th scope="col">Job Title</th>
						<th scope="col">Registered</th>
						<th scope="col">Actions</th>
					</tr>
				</tfoot>
			</table>

			<!-- Bottom tablenav -->
			<div class="tablenav bottom">
				<div class="tablenav-pages <?php echo $pages <= 1 ? 'one-page' : ''; ?>">
					<span class="displaying-num">
						<?php echo number_format_i18n( $total ); ?> item<?php echo $total !== 1 ? 's' : ''; ?>
					</span>
					<?php if ( $pages > 1 ) : ?>
					<span class="pagination-links">
						<?php echo paginate_links( $pagination_args ); ?>
					</span>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>

		</div>

		<script>
		jQuery(function($){
			function cscAdminAction(action, userId, nonce, $row) {
				$.post(ajaxurl, {
					action: action,
					user_id: userId,
					nonce: nonce
				}, function(res) {
					if (res.success) {
						$row.fadeOut(300, function(){ $(this).remove(); });
					} else {
						alert(res.data ? res.data.message : 'An error occurred.');
					}
				}).fail(function(){
					alert('Network error. Please try again.');
				});
			}

			$(document).on('click', '.csc-approve-btn', function(){
				var $btn = $(this);
				if (!confirm('Approve this member? They will receive an email with a link to set their password.')) return;
				cscAdminAction('csc_approve_member', $btn.data('user'), $btn.data('nonce'), $btn.closest('tr'));
			});

			$(document).on('click', '.csc-reject-btn', function(){
				var $btn = $(this);
				if (!confirm('Reject / revoke access for this member?')) return;
				cscAdminAction('csc_reject_member', $btn.data('user'), $btn.data('nonce'), $btn.closest('tr'));
			});
		});
		</script>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * AJAX: approve member
	 * --------------------------------------------------------------------- */
	public function approve_member() {
		check_ajax_referer( 'csc_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$user_id = intval( $_POST['user_id'] );
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user.' ) );
		}

		update_user_meta( $user_id, '_csc_status', 'approved' );

		// Sync to HubSpot if auto-sync is enabled
		if ( get_option( 'csc_hubspot_auto_sync', '1' ) === '1' && get_option( 'csc_hubspot_token', '' ) ) {
			$hs = new Csc_Hubspot();
			$hs->sync_contact( $user_id );
		}

		// Send approval email with password-reset link
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$key        = get_password_reset_key( $user );
			$setpw_page = get_page_by_path( 'members-set-password' );
			$reset_url  = add_query_arg( array(
				'key'   => $key,
				'login' => rawurlencode( $user->user_login ),
			), $setpw_page ? get_permalink( $setpw_page->ID ) : home_url( '/members-set-password/' ) );

			$login_page = get_page_by_path( 'members-login' );
			$login_url  = $login_page ? get_permalink( $login_page->ID ) : wp_login_url();

			$subject  = 'Your Celtic Sea Cluster Membership Has Been Approved';
			$body     = 'Dear ' . $user->first_name . ",\n\n";
			$body    .= "We are pleased to confirm that your application to join the Celtic Sea Cluster has been approved.\n\n";
			$body    .= "You can now set your password and access the Members Portal using the link below:\n";
			$body    .= $reset_url . "\n\n";
			$body    .= "Once your password has been created, you will be able to log in here:\n";
			$body    .= $login_url . "\n\n";
			$body    .= "Within the portal, you can create and manage your member profile, access the Member Directory, connect with other members through the forum, and view the latest newsletters and resources.\n\n";
			$body    .= "Welcome to the Celtic Sea Cluster. We are delighted to have you as part of the network.\n\n";
			$body    .= "Kind regards,\n\n";
			$body    .= "The Celtic Sea Cluster Team\n";

			wp_mail( $user->user_email, $subject, $body );
		}

		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	 * AJAX: reject member
	 * --------------------------------------------------------------------- */
	public function reject_member() {
		check_ajax_referer( 'csc_admin_action', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$user_id = intval( $_POST['user_id'] );
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user.' ) );
		}

		update_user_meta( $user_id, '_csc_status', 'rejected' );

		// Sync updated status to HubSpot
		if ( get_option( 'csc_hubspot_auto_sync', '1' ) === '1' && get_option( 'csc_hubspot_token', '' ) ) {
			$hs = new Csc_Hubspot();
			$hs->sync_contact( $user_id );
		}

		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	 * Users list column
	 * --------------------------------------------------------------------- */
	public function add_status_column( $columns ) {
		$columns['csc_status'] = 'CSC Status';
		return $columns;
	}

	public function render_status_column( $value, $column, $user_id ) {
		if ( $column !== 'csc_status' ) {
			return $value;
		}
		$status = get_user_meta( $user_id, '_csc_status', true );
		if ( ! $status ) {
			return '—';
		}
		$colors = array(
			'pending'  => '#d97706',
			'approved' => '#16a34a',
			'rejected' => '#dc2626',
		);
		$color = $colors[ $status ] ?? '#666';
		return '<span style="color:' . $color . ';font-weight:600;">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/* -----------------------------------------------------------------------
	 * Company edit permission — shown on WP Admin > Users > Edit User
	 * --------------------------------------------------------------------- */
	public function render_company_permission_field( $profile_user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$org_id   = get_user_meta( $profile_user->ID, '_csc_organisation_id', true );
		$org_name = $org_id ? get_the_title( $org_id ) : '';
		$enabled  = get_user_meta( $profile_user->ID, '_csc_can_edit_company', true ) === '1';
		?>
		<h2>CSC Member Portal</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Company Edit Access</th>
				<td>
					<?php if ( $org_id ) : ?>
						<label>
							<input type="checkbox" name="csc_can_edit_company" value="1"
							       <?php checked( $enabled ); ?>>
							Allow this member to edit <strong><?php echo esc_html( $org_name ?: "Organisation #{$org_id}" ); ?></strong> company details
						</label>
						<p class="description">
							When enabled, the <em>Company Information</em> tab appears on the member's Update Account page.
						</p>
						<?php wp_nonce_field( 'csc_company_permission_' . $profile_user->ID, '_csc_company_perm_nonce' ); ?>
					<?php else : ?>
						<p class="description">This user is not linked to a CSC organisation.</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_company_permission_field( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['_csc_company_perm_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['_csc_company_perm_nonce'], 'csc_company_permission_' . $user_id ) ) {
			return;
		}

		$value = ! empty( $_POST['csc_can_edit_company'] ) ? '1' : '0';
		update_user_meta( $user_id, '_csc_can_edit_company', $value );
	}
}
