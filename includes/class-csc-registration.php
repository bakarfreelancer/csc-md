<?php
/**
 * Handles CSC Member Login and Registration shortcodes and AJAX actions.
 *
 * Shortcodes:
 *   [csc_login]  — renders the styled members login form
 *   [csc_join]   — renders the multi-step join/registration form
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Registration {

	public function register_hooks( $loader ) {
		$loader->add_action( 'init', $this, 'register_shortcodes' );
		$loader->add_action( 'wp_ajax_nopriv_csc_login', $this, 'handle_login' );
		$loader->add_action( 'wp_ajax_nopriv_csc_register', $this, 'handle_register' );
		// Block wp default login redirect for CSC members
		$loader->add_filter( 'login_url', $this, 'custom_login_url', 10, 3 );
		$loader->add_action( 'template_redirect', $this, 'redirect_logged_in_from_login' );
	}

	public function register_shortcodes() {
		add_shortcode( 'csc_login', array( $this, 'render_login' ) );
		add_shortcode( 'csc_join', array( $this, 'render_join' ) );
	}

	/* -----------------------------------------------------------------------
	 * Login URL filter — point "forgot password" and redirects to our page
	 * --------------------------------------------------------------------- */
	public function custom_login_url( $login_url, $redirect, $force_reauth ) {
		$page = get_page_by_path( 'members-login' );
		if ( $page ) {
			$url = get_permalink( $page->ID );
			if ( $redirect ) {
				$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
			}
			return $url;
		}
		return $login_url;
	}

	/* Redirect already-logged-in users away from the login page */
	public function redirect_logged_in_from_login() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$login_page = get_page_by_path( 'members-login' );
		if ( $login_page && is_page( $login_page->ID ) ) {
			wp_safe_redirect( $this->get_dashboard_url() );
			exit;
		}
	}

	/* -----------------------------------------------------------------------
	 * [csc_login] shortcode
	 * --------------------------------------------------------------------- */
	public function render_login( $atts ) {
		// If already logged in, check status
		if ( is_user_logged_in() ) {
			$user   = wp_get_current_user();
			$status = get_user_meta( $user->ID, '_csc_status', true );

			if ( $status === 'pending' ) {
				return $this->render_pending_message();
			}
			// Approved or no CSC status — redirect to dashboard
			wp_safe_redirect( $this->get_dashboard_url() );
			exit;
		}

		$join_page = get_page_by_path( 'join-csc' );
		$join_url  = $join_page ? get_permalink( $join_page->ID ) : home_url( '/join-csc/' );
		$logo_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/csc-logo.png';

		ob_start();
		?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-login-card">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Celtic Sea Cluster" class="csc-logo">
        <h1 class="csc-title">CSC Members<br>Portal</h1>
        <p class="csc-subtitle">Access your account to connect with members, explore opportunities, and stay updated on
            sector insights.</p>

        <div id="csc-login-message" class="csc-alert" style="display:none;" role="alert"></div>

        <form id="csc-login-form" class="csc-form" novalidate>
            <div class="csc-form-group">
                <label for="csc-email">Email Address</label>
                <input type="email" id="csc-email" name="email" placeholder="you@company.com" required
                    autocomplete="email">
            </div>
            <div class="csc-form-group">
                <label for="csc-password">Password</label>
                <input type="password" id="csc-password" name="password" placeholder="••••••••••••" required
                    autocomplete="current-password">
            </div>
            <div class="csc-form-row csc-form-remember">
                <label class="csc-checkbox-label">
                    <input type="checkbox" name="remember_me"> Remember me
                </label>
                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="csc-link">Forgot Password</a>
            </div>
            <button type="submit" class="csc-btn-primary">Login</button>
        </form>

        <p class="csc-join-prompt">Not a member yet? <a href="<?php echo esc_url( $join_url ); ?>"
                class="csc-link">Apply to Join</a></p>
    </div>
</div>
<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * [csc_join] shortcode
	 * --------------------------------------------------------------------- */
	public function render_join( $atts ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->get_dashboard_url() );
			exit;
		}

		$login_page = get_page_by_path( 'members-login' );
		$login_url  = $login_page ? get_permalink( $login_page->ID ) : wp_login_url();
		$logo_url   = plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/csc-logo.png';

		ob_start();
		?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-join-card">

        <a href="<?php echo esc_url( $login_url ); ?>" class="csc-back-link" id="csc-back-btn">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
            Back
        </a>

        <!-- ====================================================
				     STEP 1 — Select Organisation + (for existing org) Personal Data
				     ==================================================== -->
        <div id="csc-step-1" class="csc-step">

            <h1 class="csc-title csc-title-left">Join The Celtic Sea Cluster</h1>

            <div id="csc-join-message" class="csc-alert" style="display:none;" role="alert"></div>

            <form id="csc-join-form" class="csc-form" novalidate>

                <!-- Organisation typeahead -->
                <div class="csc-form-group" id="csc-org-field">
                    <label for="csc-org-search">Select Organisation</label>
                    <div class="csc-typeahead-wrap">
                        <input type="text" id="csc-org-search" class="csc-typeahead-input"
                            placeholder="Select Organisation" autocomplete="off" aria-autocomplete="list"
                            aria-controls="csc-org-dropdown">
                        <input type="hidden" id="csc-org-id" name="organisation_id">
                        <ul id="csc-org-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;">
                        </ul>
                    </div>
                    <label class="csc-checkbox-label csc-register-org-toggle">
                        <input type="checkbox" id="csc-register-org-check"> Can't find? Register a new one
                    </label>
                </div>

                <!-- New org inline form (shown when checkbox checked) -->
                <div id="csc-new-org-section" style="display:none;">
                    <h3 class="csc-subtitle-sm">Register your organisation</h3>
                    <div class="csc-form-group">
                        <label for="csc-org-name">Organisation Name</label>
                        <input type="text" id="csc-org-name" name="org_name" placeholder="e.g Acme Energy Ltd">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-location">Location</label>
                        <input type="text" id="csc-org-location" name="org_location" placeholder="e.g Swansea, Wales">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-postcode">Postcode</label>
                        <input type="text" id="csc-org-postcode" name="org_postcode" placeholder="e.g. SA1 1AA">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-sector">Sector / Services</label>
                        <div class="csc-select-wrap">
                            <select id="csc-org-sector" name="org_sector" class="csc-select">
                                <option value="">Select Sector</option>
                                <?php foreach ( Csc_Organisations::get_company_types() as $type ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" id="csc-next-btn" class="csc-btn-primary">Next</button>
                </div>

                <!-- Personal data (visible in step-1 for existing org flow) -->
                <div id="csc-personal-inline">
                    <div class="csc-form-row">
                        <div class="csc-form-group csc-col">
                            <label for="csc-first-name-1">First Name</label>
                            <input type="text" id="csc-first-name-1" name="first_name" placeholder="e.g. John" required
                                autocomplete="given-name">
                        </div>
                        <div class="csc-form-group csc-col">
                            <label for="csc-last-name-1">Last Name</label>
                            <input type="text" id="csc-last-name-1" name="last_name" placeholder="e.g. Davies" required
                                autocomplete="family-name">
                        </div>
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-job-title-1">Job Title</label>
                        <input type="text" id="csc-job-title-1" name="job_title" placeholder="e.g. Project Manager"
                            required>
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-email-1">Email</label>
                        <input type="email" id="csc-email-1" name="email" placeholder="you@company.com" required
                            autocomplete="email">
                    </div>
                    <button type="submit" class="csc-btn-primary">Submit Application</button>
                </div>

            </form>
        </div><!-- /step-1 -->

        <!-- ====================================================
				     STEP 2 — Personal Data (new org flow only, screen 04)
				     ==================================================== -->
        <div id="csc-step-2" class="csc-step" style="display:none;">
            <h1 class="csc-title">Personal Data</h1>

            <div id="csc-step2-message" class="csc-alert" style="display:none;" role="alert"></div>

            <form id="csc-personal-form" class="csc-form" novalidate>
                <div class="csc-form-row">
                    <div class="csc-form-group csc-col">
                        <label for="csc-first-name-2">First Name</label>
                        <input type="text" id="csc-first-name-2" name="first_name_2" placeholder="e.g. John" required
                            autocomplete="given-name">
                    </div>
                    <div class="csc-form-group csc-col">
                        <label for="csc-last-name-2">Surname</label>
                        <input type="text" id="csc-last-name-2" name="last_name_2" placeholder="e.g. Davies" required
                            autocomplete="family-name">
                    </div>
                </div>
                <div class="csc-form-group">
                    <label for="csc-job-title-2">Job Title</label>
                    <input type="text" id="csc-job-title-2" name="job_title_2" placeholder="e.g. Project Manager"
                        required>
                </div>
                <div class="csc-form-group">
                    <label for="csc-email-2">Email</label>
                    <input type="email" id="csc-email-2" name="email_2" placeholder="you@company.com" required
                        autocomplete="email">
                </div>
                <button type="submit" class="csc-btn-primary">Submit Application</button>
            </form>
        </div><!-- /step-2 -->

    </div><!-- /.csc-card -->
</div><!-- /.csc-portal-wrap -->

<!-- Application Under Review Modal (Screen 05) -->
<div id="csc-modal-overlay" class="csc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="csc-modal-title"
    style="display:none;">
    <div class="csc-modal">
        <button class="csc-modal-close" id="csc-modal-close-btn" aria-label="Close">&times;</button>
        <div class="csc-modal-icon" aria-hidden="true">
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="40" cy="40" r="40" fill="#E8F5EE" />
                <path d="M28 40c3 3 5 7 8 10 5-8 10-15 16-20" stroke="#44BD70" stroke-width="3.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                <circle cx="56" cy="28" r="10" fill="#1F2D57" />
                <path d="M52 28l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
        </div>
        <h2 id="csc-modal-title">Application Under Review</h2>
        <p>Your account is currently pending approval. You will be notified once access is granted. Thank you for your
            patience.</p>
    </div>
</div>
<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * AJAX: handle login
	 * --------------------------------------------------------------------- */
	public function handle_login() {
		check_ajax_referer( 'csc_login_action', 'nonce' );

		$email    = sanitize_email( $_POST['email'] ?? '' );
		$password = $_POST['password'] ?? '';
		$remember = ! empty( $_POST['remember_me'] );

		if ( ! $email || ! $password ) {
			wp_send_json_error( array( 'message' => 'Please enter your email and password.' ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'Invalid email address or password.' ) );
		}

		$result = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'      => $remember,
		), false );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email address or password.' ) );
		}

		// Check CSC membership status
		$status = get_user_meta( $result->ID, '_csc_status', true );
		if ( $status === 'pending' ) {
			wp_logout();
			wp_send_json_error( array(
				'message' => 'Your account is currently pending approval. You will be notified once access is granted.',
			) );
		}

		wp_send_json_success( array( 'redirect' => $this->get_dashboard_url() ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: handle registration
	 * --------------------------------------------------------------------- */
	public function handle_register() {
		check_ajax_referer( 'csc_register_action', 'nonce' );

		$first_name      = sanitize_text_field( $_POST['first_name'] ?? '' );
		$last_name       = sanitize_text_field( $_POST['last_name'] ?? '' );
		$job_title       = sanitize_text_field( $_POST['job_title'] ?? '' );
		$email           = sanitize_email( $_POST['email'] ?? '' );
		$org_id          = intval( $_POST['organisation_id'] ?? 0 );
		$register_new    = ! empty( $_POST['register_new_org'] );

		// Validate required fields
		if ( ! $first_name || ! $last_name || ! $job_title || ! $email ) {
			wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => 'An account with this email address already exists. Please log in.' ) );
		}

		// Handle new organisation registration
		if ( $register_new ) {
			$org_name     = sanitize_text_field( $_POST['org_name']     ?? '' );
			$org_location = sanitize_text_field( $_POST['org_location'] ?? '' );
			$org_sector   = sanitize_text_field( $_POST['org_sector']   ?? '' );
			$org_postcode = sanitize_text_field( $_POST['org_postcode'] ?? '' );

			if ( ! $org_name ) {
				wp_send_json_error( array( 'message' => 'Please enter your organisation name.' ) );
			}

			$org_id = wp_insert_post( array(
				'post_title'  => $org_name,
				'post_type'   => 'csc_organisation',
				'post_status' => 'publish',
			) );

			if ( is_wp_error( $org_id ) || ! $org_id ) {
				wp_send_json_error( array( 'message' => 'Could not register your organisation. Please try again.' ) );
			}

			update_post_meta( $org_id, '_csc_org_location', $org_location );
			update_post_meta( $org_id, '_csc_org_sector',   $org_sector );
			if ( $org_postcode ) {
				update_post_meta( $org_id, '_csc_org_postcode', $org_postcode );
			}

		} elseif ( ! $org_id ) {
			wp_send_json_error( array( 'message' => 'Please select an organisation or register a new one.' ) );
		}

		// Create WordPress user (subscriber, pending approval)
		$username = sanitize_user( explode( '@', $email )[0] . '_' . wp_generate_password( 4, false ) );
		$password = wp_generate_password( 16, true );

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Could not create your account. Please try again.' ) );
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $first_name . ' ' . $last_name,
			'role'         => 'subscriber',
		) );

		update_user_meta( $user_id, '_csc_status', 'pending' );
		update_user_meta( $user_id, '_csc_job_title', $job_title );
		update_user_meta( $user_id, '_csc_organisation_id', $org_id );

		// Notify site admin
		$this->notify_admin_new_application( $user_id, $org_id );

		wp_send_json_success( array( 'message' => 'Application submitted successfully.' ) );
	}

	/* -----------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------- */
	private function render_pending_message() {
		$logo_url = plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/csc-logo.png';
		ob_start();
		?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-pending-card">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Celtic Sea Cluster" class="csc-logo">
        <div class="csc-modal-icon" aria-hidden="true">
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="40" cy="40" r="40" fill="#E8F5EE" />
                <path d="M28 40c3 3 5 7 8 10 5-8 10-15 16-20" stroke="#44BD70" stroke-width="3.5" stroke-linecap="round"
                    stroke-linejoin="round" />
                <circle cx="56" cy="28" r="10" fill="#1F2D57" />
                <path d="M52 28l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
        </div>
        <h2>Application Under Review</h2>
        <p>Your account is currently pending approval. You will be notified once access is granted. Thank you for your
            patience.</p>
        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="csc-btn-outline">Logout</a>
    </div>
</div>
<?php
		return ob_get_clean();
	}

	private function get_dashboard_url() {
		$page = get_page_by_path( 'member-dashboard' );
		return $page ? get_permalink( $page->ID ) : home_url( '/member-dashboard/' );
	}

	private function notify_admin_new_application( $user_id, $org_id ) {
		$user    = get_user_by( 'ID', $user_id );
		$org     = get_post( $org_id );
		$subject = 'New CSC Member Application';

		$body  = "A new member has applied to join the Celtic Sea Cluster.\n\n";
		$body .= 'Name: ' . $user->first_name . ' ' . $user->last_name . "\n";
		$body .= 'Email: ' . $user->user_email . "\n";
		$body .= 'Job Title: ' . get_user_meta( $user_id, '_csc_job_title', true ) . "\n";
		$body .= 'Organisation: ' . ( $org ? $org->post_title : 'N/A' ) . "\n\n";
		$body .= 'Review applications: ' . admin_url( 'admin.php?page=csc-members' ) . "\n";

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}
}