<?php
/**
 * Settings shortcode.
 * Tabs: Password | Login & Security | Notification Settings | Member Directory Settings
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Settings {

	public function register_hooks() {
		add_shortcode( 'csc_settings', array( $this, 'render' ) );
		add_action( 'wp_ajax_csc_save_password',           array( $this, 'ajax_save_password' ) );
		add_action( 'wp_ajax_csc_save_security',           array( $this, 'ajax_save_security' ) );
		add_action( 'wp_ajax_csc_save_notifications',      array( $this, 'ajax_save_notifications' ) );
		add_action( 'wp_ajax_csc_save_dir_settings',       array( $this, 'ajax_save_dir_settings' ) );
		add_action( 'wp_ajax_csc_sign_out_all_devices',    array( $this, 'ajax_sign_out_all' ) );
	}

	/* -----------------------------------------------------------------------
	 * Main entry
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

		$tab      = sanitize_key( $_GET['tab'] ?? 'password' );
		$page_url = Csc_Dashboard::portal_url( 'member-settings' );

		$tabs = array(
			'password'      => 'Password',
			'security'      => 'Login &amp; Security',
			'notifications' => 'Notification Settings',
			'directory'     => 'Member Directory Settings',
		);

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'settings', $user ); ?>

			<main class="csc-portal-main">

				<!-- Page header -->
				<div class="csc-page-header">
					<div></div>
					<button type="button" class="csc-btn-primary" id="csc-settings-save-btn">Save Changes</button>
				</div>

				<div id="csc-settings-message" class="csc-alert" style="display:none;margin-bottom:16px;"></div>

				<!-- Tabs -->
				<div class="csc-settings-tabs">
					<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $page_url ) ); ?>"
					   class="csc-settings-tab <?php echo $tab === $key ? 'is-active' : ''; ?>"><?php echo $label; ?></a>
					<?php endforeach; ?>
				</div>

				<!-- Tab content -->
				<div class="csc-settings-body">
					<?php
					switch ( $tab ) {
						case 'security':
							echo $this->render_security_tab( $user );
							break;
						case 'notifications':
							echo $this->render_notifications_tab( $user );
							break;
						case 'directory':
							echo $this->render_directory_tab( $user );
							break;
						default:
							echo $this->render_password_tab( $user );
							break;
					}
					?>
				</div>

			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Password tab
	 * --------------------------------------------------------------------- */
	private function render_password_tab( $user ) {
		ob_start();
		?>
		<form class="csc-settings-form"
		      id="csc-settings-form"
		      data-action="csc_save_password"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_password' ) ); ?>">

			<div class="csc-settings-section">
				<h2 class="csc-settings-section-title">Update Password</h2>

				<div class="csc-settings-fields">

					<div class="csc-form-group csc-form-group--md">
						<label class="csc-label" for="pw-current">Current Password</label>
						<input type="password" id="pw-current" name="current_password" class="csc-input" autocomplete="current-password">
					</div>

					<div class="csc-form-group csc-form-group--md">
						<label class="csc-label" for="pw-new">New Password</label>
						<div class="csc-password-wrap">
							<input type="password" id="pw-new" name="new_password" class="csc-input" autocomplete="new-password" id="pw-new">
							<button type="button" class="csc-pw-toggle" data-target="pw-new" aria-label="Show/hide password">
								<svg class="csc-pw-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 10s3.6-7 9-7 9 7 9 7-3.6 7-9 7-9-7-9-7z"/><circle cx="10" cy="10" r="3"/></svg>
							</button>
						</div>
						<div class="csc-pw-strength" id="csc-pw-strength" aria-live="polite">
							<div class="csc-pw-strength-bar">
								<span></span><span></span><span></span><span></span>
							</div>
							<span class="csc-pw-strength-label" id="csc-pw-strength-label"></span>
						</div>
					</div>

					<div class="csc-form-group csc-form-group--md">
						<label class="csc-label" for="pw-confirm">Confirm New Password</label>
						<div class="csc-password-wrap">
							<input type="password" id="pw-confirm" name="confirm_password" class="csc-input" autocomplete="new-password">
							<button type="button" class="csc-pw-toggle" data-target="pw-confirm" aria-label="Show/hide password">
								<svg class="csc-pw-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 10s3.6-7 9-7 9 7 9 7-3.6 7-9 7-9-7-9-7z"/><circle cx="10" cy="10" r="3"/></svg>
							</button>
						</div>
					</div>

				</div>
			</div>

			<?php echo $this->support_box(); ?>

		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Login & Security tab
	 * --------------------------------------------------------------------- */
	private function render_security_tab( $user ) {
		$two_fa       = get_user_meta( $user->ID, '_csc_2fa_enabled',    true ) === '1';
		$login_alerts = get_user_meta( $user->ID, '_csc_login_alerts',   true );
		$login_alerts = $login_alerts === '' ? true : $login_alerts === '1';

		// Count active sessions
		$tokens        = WP_Session_Tokens::get_instance( $user->ID );
		$sessions      = $tokens->get_all();
		$session_count = count( $sessions );

		ob_start();
		?>
		<form class="csc-settings-form"
		      id="csc-settings-form"
		      data-action="csc_save_security"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_security' ) ); ?>">

			<div class="csc-settings-section">
				<h2 class="csc-settings-section-title">Login &amp; Security</h2>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Two-factor authentication</div>
						<div class="csc-settings-toggle-desc">Get notified by email every time someone uses a new device to sign into your account at login.</div>
					</div>
					<label class="csc-toggle" aria-label="Two-factor authentication">
						<input type="checkbox" name="two_fa" value="1" <?php checked( $two_fa ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Login alerts</div>
						<div class="csc-settings-toggle-desc">Get notified whenever there is a new login to your account.</div>
					</div>
					<label class="csc-toggle" aria-label="Login alerts">
						<input type="checkbox" name="login_alerts" value="1" <?php checked( $login_alerts ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>

				<div class="csc-settings-sessions">
					<div class="csc-settings-sessions-title">Active sessions</div>
					<div class="csc-settings-sessions-info">
						You're currently signed in on <?php echo $session_count; ?> device<?php echo $session_count !== 1 ? 's' : ''; ?>.
					</div>
					<button type="button" class="csc-link-btn" id="csc-sign-out-all"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_sign_out_all_devices' ) ); ?>">
						Sign out of all other devices
					</button>
				</div>
			</div>

			<?php echo $this->support_box(); ?>

		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Notification Settings tab
	 * --------------------------------------------------------------------- */
	private function render_notifications_tab( $user ) {
		$newsletter = get_user_meta( $user->ID, '_csc_notif_newsletter', true );
		$forum      = get_user_meta( $user->ID, '_csc_notif_forum',      true );
		$events     = get_user_meta( $user->ID, '_csc_notif_events',     true );

		// Default on for forum & events if never set
		$forum  = $forum  === '' ? true : $forum  === '1';
		$events = $events === '' ? true : $events === '1';

		ob_start();
		?>
		<form class="csc-settings-form"
		      id="csc-settings-form"
		      data-action="csc_save_notifications"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_notifications' ) ); ?>">

			<div class="csc-settings-section">
				<h2 class="csc-settings-section-title">Account Preferences</h2>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Newsletter emails</div>
						<div class="csc-settings-toggle-desc">Receive monthly CSC newsletters from Shore.</div>
					</div>
					<label class="csc-toggle" aria-label="Newsletter emails">
						<input type="checkbox" name="notif_newsletter" value="1" <?php checked( $newsletter === '1' ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Forum notifications</div>
						<div class="csc-settings-toggle-desc">Email me when someone replies to a topic I created.</div>
					</div>
					<label class="csc-toggle" aria-label="Forum notifications">
						<input type="checkbox" name="notif_forum" value="1" <?php checked( $forum ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Event invitations</div>
						<div class="csc-settings-toggle-desc">Get invitations to CSC quarterly networking events.</div>
					</div>
					<label class="csc-toggle" aria-label="Event invitations">
						<input type="checkbox" name="notif_events" value="1" <?php checked( $events ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>
			</div>

			<?php echo $this->support_box(); ?>

		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Member Directory Settings tab
	 * --------------------------------------------------------------------- */
	private function render_directory_tab( $user ) {
		$org_visible  = get_user_meta( $user->ID, '_csc_dir_org_visible',     true );
		$prof_visible = get_user_meta( $user->ID, '_csc_dir_profile_visible',  true );

		// Default profile visible = on, org visible = on
		$org_visible  = $org_visible  === '' ? true : $org_visible  === '1';
		$prof_visible = $prof_visible === '' ? true : $prof_visible === '1';

		ob_start();
		?>
		<form class="csc-settings-form"
		      id="csc-settings-form"
		      data-action="csc_save_dir_settings"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_dir_settings' ) ); ?>">

			<div class="csc-settings-section">
				<h2 class="csc-settings-section-title">Member Directory Settings</h2>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Organisation visibility</div>
						<div class="csc-settings-toggle-desc">Your consent to my organisation's details being included in the Celtic Sea Cluster Member Directory, visible to other members.</div>
					</div>
					<label class="csc-toggle" aria-label="Organisation visibility">
						<input type="checkbox" name="dir_org_visible" value="1" <?php checked( $org_visible ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>

				<div class="csc-settings-toggle-row">
					<div class="csc-settings-toggle-info">
						<div class="csc-settings-toggle-title">Personal profile visibility</div>
						<div class="csc-settings-toggle-desc">You consent to my profile, bio, and contact details being included in the Celtic Sea Cluster Member Directory, visible to other members.</div>
					</div>
					<label class="csc-toggle" aria-label="Personal profile visibility">
						<input type="checkbox" name="dir_profile_visible" value="1" <?php checked( $prof_visible ); ?>>
						<span class="csc-toggle-slider"></span>
					</label>
				</div>
			</div>

			<?php echo $this->support_box(); ?>

		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Shared support box
	 * --------------------------------------------------------------------- */
	private function support_box() {
		$admin_email = get_option( 'admin_email' );
		ob_start();
		?>
		<div class="csc-support-box">
			<div class="csc-support-box__icon">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><line x1="10" y1="7" x2="10" y2="10"/><circle cx="10" cy="13" r="0.5" fill="currentColor"/></svg>
			</div>
			<div>
				<div class="csc-support-box__title">Need extra support?</div>
				<div class="csc-support-box__text">For any extra support, please email it to <a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save password
	 * --------------------------------------------------------------------- */
	public function ajax_save_password() {
		check_ajax_referer( 'csc_save_password', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user    = wp_get_current_user();
		$current = $_POST['current_password'] ?? '';
		$new     = $_POST['new_password']     ?? '';
		$confirm = $_POST['confirm_password'] ?? '';

		if ( ! $current || ! $new || ! $confirm ) {
			wp_send_json_error( array( 'message' => 'Please fill in all password fields.' ) );
		}

		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			wp_send_json_error( array( 'message' => 'Current password is incorrect.' ) );
		}

		if ( strlen( $new ) < 8 ) {
			wp_send_json_error( array( 'message' => 'New password must be at least 8 characters.' ) );
		}

		if ( $new !== $confirm ) {
			wp_send_json_error( array( 'message' => 'New passwords do not match.' ) );
		}

		wp_set_password( $new, $user->ID );

		// Re-authenticate so session isn't lost
		wp_set_auth_cookie( $user->ID, true );

		wp_send_json_success( array( 'message' => 'Password updated successfully.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save security settings
	 * --------------------------------------------------------------------- */
	public function ajax_save_security() {
		check_ajax_referer( 'csc_save_security', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user = wp_get_current_user();
		update_user_meta( $user->ID, '_csc_2fa_enabled',  isset( $_POST['two_fa'] )       ? '1' : '0' );
		update_user_meta( $user->ID, '_csc_login_alerts', isset( $_POST['login_alerts'] ) ? '1' : '0' );

		wp_send_json_success( array( 'message' => 'Security settings saved.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save notification settings
	 * --------------------------------------------------------------------- */
	public function ajax_save_notifications() {
		check_ajax_referer( 'csc_save_notifications', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user = wp_get_current_user();
		update_user_meta( $user->ID, '_csc_notif_newsletter', isset( $_POST['notif_newsletter'] ) ? '1' : '0' );
		update_user_meta( $user->ID, '_csc_notif_forum',      isset( $_POST['notif_forum'] )      ? '1' : '0' );
		update_user_meta( $user->ID, '_csc_notif_events',     isset( $_POST['notif_events'] )     ? '1' : '0' );

		wp_send_json_success( array( 'message' => 'Notification preferences saved.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save directory settings
	 * --------------------------------------------------------------------- */
	public function ajax_save_dir_settings() {
		check_ajax_referer( 'csc_save_dir_settings', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user = wp_get_current_user();
		update_user_meta( $user->ID, '_csc_dir_org_visible',     isset( $_POST['dir_org_visible'] )     ? '1' : '0' );
		update_user_meta( $user->ID, '_csc_dir_profile_visible', isset( $_POST['dir_profile_visible'] ) ? '1' : '0' );

		wp_send_json_success( array( 'message' => 'Directory settings saved.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Sign out all other devices
	 * --------------------------------------------------------------------- */
	public function ajax_sign_out_all() {
		check_ajax_referer( 'csc_sign_out_all_devices', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user   = wp_get_current_user();
		$tokens = WP_Session_Tokens::get_instance( $user->ID );
		$tokens->destroy_others( wp_get_session_token() );

		wp_send_json_success( array( 'message' => 'Signed out of all other devices.' ) );
	}
}
