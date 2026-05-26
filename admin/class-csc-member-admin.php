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
		$filter = sanitize_text_field( $_GET['csc_filter'] ?? 'pending' );
		$paged  = max( 1, intval( $_GET['paged'] ?? 1 ) );
		$per    = 20;

		$query = new WP_User_Query( array(
			'meta_key'   => '_csc_status',
			'meta_value' => $filter,
			'number'     => $per,
			'offset'     => ( $paged - 1 ) * $per,
			'orderby'    => 'display_name',
			'order'      => 'ASC',
		) );

		$users = $query->get_results();
		$total = $query->get_total();

		// Count totals for tab badges
		$counts = array();
		foreach ( array( 'pending', 'approved', 'rejected' ) as $s ) {
			$q           = new WP_User_Query( array( 'meta_key' => '_csc_status', 'meta_value' => $s, 'count_total' => true, 'number' => 0 ) );
			$counts[ $s ] = $q->get_total();
		}

		$admin_nonce = wp_create_nonce( 'csc_admin_action' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">CSC Members</h1>
			<hr class="wp-header-end">

			<ul class="subsubsub" style="margin-bottom:12px;">
				<?php foreach ( array( 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected' ) as $s => $label ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=csc-members&csc_filter=' . $s ) ); ?>"
					   class="<?php echo $filter === $s ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo intval( $counts[ $s ] ); ?>)</span>
					</a>
					<?php echo $s !== 'rejected' ? ' | ' : ''; ?>
				</li>
				<?php endforeach; ?>
			</ul>

			<table class="wp-list-table widefat fixed striped users">
				<thead>
					<tr>
						<th scope="col" class="column-name">Name</th>
						<th scope="col">Email</th>
						<th scope="col">Organisation</th>
						<th scope="col">Job Title</th>
						<th scope="col">Applied</th>
						<th scope="col">Actions</th>
					</tr>
				</thead>
				<tbody id="csc-members-list">
				<?php if ( empty( $users ) ) : ?>
					<tr>
						<td colspan="6" style="text-align:center;padding:24px;">
							No members found with status: <strong><?php echo esc_html( $filter ); ?></strong>
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
						<td>
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
								<button class="button button-primary csc-approve-btn" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Approve</button>
								<button class="button csc-reject-btn" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>" style="margin-left:4px;">Reject</button>
							<?php elseif ( $status === 'rejected' ) : ?>
								<button class="button button-primary csc-approve-btn" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Approve</button>
							<?php elseif ( $status === 'approved' ) : ?>
								<button class="button csc-reject-btn" data-user="<?php echo esc_attr( $user->ID ); ?>" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">Revoke Access</button>
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
						<th scope="col">Applied</th>
						<th scope="col">Actions</th>
					</tr>
				</tfoot>
			</table>

			<?php if ( $total > $per ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => ceil( $total / $per ),
					) );
					?>
				</div>
			</div>
			<?php endif; ?>
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

			$subject  = 'Your CSC Membership Has Been Approved';
			$body     = 'Dear ' . $user->first_name . ",\n\n";
			$body    .= "Great news — your application to join the Celtic Sea Cluster has been approved!\n\n";
			$body    .= "Please click the link below to set your password and access the Members Portal:\n";
			$body    .= $reset_url . "\n\n";
			$body    .= "Once you have set your password, you can log in at:\n";
			$body    .= $login_url . "\n\n";
			$body    .= "Welcome to the Celtic Sea Cluster!\n";

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
}
