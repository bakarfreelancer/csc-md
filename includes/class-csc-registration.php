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
		$loader->add_action( 'wp_ajax_nopriv_csc_login',            $this, 'handle_login' );
		$loader->add_action( 'wp_ajax_nopriv_csc_verify_2fa',       $this, 'handle_verify_2fa' );
		$loader->add_action( 'wp_ajax_nopriv_csc_resend_2fa',       $this, 'handle_resend_2fa' );
		$loader->add_action( 'wp_ajax_nopriv_csc_register',         $this, 'handle_register' );
		$loader->add_action( 'wp_ajax_nopriv_csc_forgot_password',  $this, 'handle_forgot_password' );
		$loader->add_action( 'wp_ajax_nopriv_csc_set_password',     $this, 'handle_set_password' );
		// Redirect wp-login.php password flows to our custom pages
		$loader->add_filter( 'lostpassword_url',   $this, 'custom_lostpassword_url', 10, 2 );
		$loader->add_action( 'login_init',         $this, 'redirect_default_password_pages' );
		// Block wp default login redirect for CSC members
		$loader->add_filter( 'login_url', $this, 'custom_login_url', 10, 3 );
		$loader->add_action( 'template_redirect', $this, 'redirect_logged_in_from_login' );
	}

	public function register_shortcodes() {
		add_shortcode( 'csc_login',           array( $this, 'render_login' ) );
		add_shortcode( 'csc_join',            array( $this, 'render_join' ) );
		add_shortcode( 'csc_forgot_password', array( $this, 'render_forgot_password' ) );
		add_shortcode( 'csc_set_password',    array( $this, 'render_set_password' ) );
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
	 * Filter: lostpassword_url → our custom forgot-password page
	 * --------------------------------------------------------------------- */
	public function custom_lostpassword_url( $url, $redirect ) {
		$page = get_page_by_path( 'members-forgot-password' );
		return $page ? get_permalink( $page->ID ) : $url;
	}

	/* -----------------------------------------------------------------------
	 * Redirect wp-login.php?action=lostpassword|rp to our custom pages
	 * --------------------------------------------------------------------- */
	public function redirect_default_password_pages() {
		$action = sanitize_key( $_GET['action'] ?? '' );

		if ( $action === 'lostpassword' ) {
			$page = get_page_by_path( 'members-forgot-password' );
			if ( $page ) {
				wp_safe_redirect( get_permalink( $page->ID ) );
				exit;
			}
		} elseif ( $action === 'rp' || $action === 'resetpass' ) {
			$key   = sanitize_text_field( $_GET['key']   ?? '' );
			$login = sanitize_text_field( $_GET['login'] ?? '' );
			$page  = get_page_by_path( 'members-set-password' );
			if ( $page && $key && $login ) {
				wp_safe_redirect( add_query_arg(
					array( 'key' => $key, 'login' => rawurlencode( $login ) ),
					get_permalink( $page->ID )
				) );
				exit;
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * [csc_forgot_password] shortcode
	 * --------------------------------------------------------------------- */
	public function render_forgot_password( $atts ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->get_dashboard_url() );
			exit;
		}

		$logo_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/csc-logo.png';
		$login_url = Csc_Dashboard::login_url();
		$nonce     = wp_create_nonce( 'csc_forgot_password' );

		ob_start();
		?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-login-card">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Celtic Sea Cluster" class="csc-logo">
        <h1 class="csc-title">Reset Password</h1>
        <p class="csc-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

        <div id="csc-forgot-message" class="csc-alert" style="display:none;" role="alert"></div>

        <form id="csc-forgot-form" class="csc-form" novalidate
              data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <div class="csc-form-group">
                <label for="csc-forgot-email">Email Address</label>
                <input type="email" id="csc-forgot-email" name="email"
                       placeholder="you@company.com" required autocomplete="email">
            </div>
            <button type="submit" class="csc-btn-primary">Send Reset Link</button>
        </form>

        <p class="csc-join-prompt">
            <a href="<?php echo esc_url( $login_url ); ?>" class="csc-link">&larr; Back to Login</a>
        </p>
    </div>
</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * [csc_set_password] shortcode
	 * --------------------------------------------------------------------- */
	public function render_set_password( $atts ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->get_dashboard_url() );
			exit;
		}

		$key   = sanitize_text_field( $_GET['key']   ?? '' );
		$login = sanitize_text_field( $_GET['login'] ?? '' );

		$logo_url   = plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/csc-logo.png';
		$login_url  = Csc_Dashboard::login_url();
		$forgot_page = get_page_by_path( 'members-forgot-password' );
		$forgot_url  = $forgot_page ? get_permalink( $forgot_page->ID ) : $login_url;

		// Validate key upfront so we show an error immediately if it's expired/invalid
		$valid_user = false;
		if ( $key && $login ) {
			$check = check_password_reset_key( $key, $login );
			if ( ! is_wp_error( $check ) ) {
				$valid_user = true;
			}
		}

		if ( ! $valid_user ) {
			ob_start();
			?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-login-card">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Celtic Sea Cluster" class="csc-logo">
        <div class="csc-setpw-error-icon" aria-hidden="true">
            <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="32" r="28"/><line x1="22" y1="22" x2="42" y2="42"/><line x1="42" y1="22" x2="22" y2="42"/></svg>
        </div>
        <h1 class="csc-title">Link Expired</h1>
        <p class="csc-subtitle">This password reset link is invalid or has already been used. Please request a new one.</p>
        <a href="<?php echo esc_url( $forgot_url ); ?>" class="csc-btn-primary" style="display:block;text-align:center;">Request New Link</a>
        <p class="csc-join-prompt"><a href="<?php echo esc_url( $login_url ); ?>" class="csc-link">&larr; Back to Login</a></p>
    </div>
</div>
			<?php
			return ob_get_clean();
		}

		$nonce    = wp_create_nonce( 'csc_set_password' );
		$eye_icon = '<svg class="csc-pw-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 10s3.6-7 9-7 9 7 9 7-3.6 7-9 7-9-7-9-7z"/><circle cx="10" cy="10" r="3"/></svg>';

		ob_start();
		?>
<div class="csc-portal-wrap">
    <div class="csc-card csc-login-card">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Celtic Sea Cluster" class="csc-logo">
        <h1 class="csc-title">Set Your Password</h1>
        <p class="csc-subtitle">Choose a strong password for your account.</p>

        <div id="csc-setpw-message" class="csc-alert" style="display:none;" role="alert"></div>

        <form id="csc-setpw-form" class="csc-form" novalidate
              data-nonce="<?php echo esc_attr( $nonce ); ?>"
              data-key="<?php echo esc_attr( $key ); ?>"
              data-login="<?php echo esc_attr( $login ); ?>">

            <div class="csc-form-group">
                <label for="csc-setpw-new">New Password</label>
                <div class="csc-password-wrap">
                    <input type="password" id="csc-setpw-new" name="new_password"
                           class="csc-input" autocomplete="new-password">
                    <button type="button" class="csc-pw-toggle" data-target="csc-setpw-new" aria-label="Show/hide password">
                        <?php echo $eye_icon; ?>
                    </button>
                </div>
                <div class="csc-pw-strength" id="csc-setpw-strength" aria-live="polite">
                    <div class="csc-pw-strength-bar"><span></span><span></span><span></span><span></span></div>
                    <span class="csc-pw-strength-label" id="csc-setpw-strength-label"></span>
                </div>
            </div>

            <div class="csc-form-group">
                <label for="csc-setpw-confirm">Confirm Password</label>
                <div class="csc-password-wrap">
                    <input type="password" id="csc-setpw-confirm" name="confirm_password"
                           class="csc-input" autocomplete="new-password">
                    <button type="button" class="csc-pw-toggle" data-target="csc-setpw-confirm" aria-label="Show/hide password">
                        <?php echo $eye_icon; ?>
                    </button>
                </div>
            </div>

            <button type="submit" class="csc-btn-primary">Set Password &amp; Sign In</button>
        </form>
    </div>
</div>
		<?php
		return ob_get_clean();
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
        <h1 class="csc-title">Celtic Sea Cluster Members' Portal</h1>
        <p class="csc-subtitle">Sign in to connect with other members, join discussions in the forum, explore opportunities, and stay informed with the latest sector updates.</p>

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

        <!-- 2FA verification panel (shown by JS when required) -->
        <div id="csc-2fa-panel" style="display:none;" aria-live="polite">
            <p class="csc-subtitle" style="margin-bottom:20px;">We've sent a 6-digit verification code to your email address. Enter it below to complete sign in.</p>

            <div id="csc-2fa-message" class="csc-alert" style="display:none;" role="alert"></div>

            <form id="csc-2fa-form" novalidate>
                <input type="hidden" id="csc-2fa-token" name="token" value="">
                <input type="hidden" id="csc-2fa-nonce" name="nonce" value="">

                <div class="csc-form-group">
                    <label for="csc-2fa-code">Verification Code</label>
                    <input type="text" id="csc-2fa-code" name="code" placeholder="000000"
                        maxlength="6" autocomplete="one-time-code" inputmode="numeric"
                        class="csc-input csc-2fa-code-input">
                </div>

                <button type="submit" class="csc-btn-primary">Verify &amp; Sign In</button>
            </form>

            <div class="csc-2fa-footer">
                <button type="button" class="csc-link-btn" id="csc-2fa-resend">Resend code</button>
                <span class="csc-2fa-footer-sep">·</span>
                <button type="button" class="csc-link-btn" id="csc-2fa-back">Back to login</button>
            </div>
        </div>
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
                        <label for="csc-org-address">Street Address <span class="csc-label-optional">(optional)</span></label>
                        <input type="text" id="csc-org-address" name="org_address" placeholder="e.g. 1 Harbour Way">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-city">City / Town</label>
                        <input type="text" id="csc-org-city" name="org_city" placeholder="e.g. Swansea">
                    </div>
                    <div class="csc-form-group">
                        <label for="reg-country-input">Country</label>
                        <div class="csc-typeahead-wrap">
                            <input type="text" id="reg-country-input" class="csc-typeahead-input"
                                   placeholder="Select country" autocomplete="off"
                                   aria-autocomplete="list" aria-controls="reg-country-dropdown">
                            <input type="hidden" id="reg-country-hidden" name="org_country">
                            <ul id="reg-country-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;"></ul>
                        </div>
                    </div>
                    <div class="csc-form-group" id="reg-county-group" style="display:none;">
                        <label for="reg-county-input">County</label>
                        <div class="csc-typeahead-wrap">
                            <input type="text" id="reg-county-input" class="csc-typeahead-input"
                                   placeholder="Select county" autocomplete="off"
                                   aria-autocomplete="list" aria-controls="reg-county-dropdown">
                            <input type="hidden" id="reg-county-hidden" name="org_county">
                            <ul id="reg-county-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;"></ul>
                        </div>
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-postcode">Postcode</label>
                        <input type="text" id="csc-org-postcode" name="org_postcode" placeholder="e.g. SA1 1AA">
                    </div>
                    <div class="csc-form-group">
                        <label for="reg-sector-input">Company Type</label>
                        <div class="csc-typeahead-wrap">
                            <input type="text" id="reg-sector-input" class="csc-typeahead-input"
                                   placeholder="Select company type" autocomplete="off"
                                   aria-autocomplete="list" aria-controls="reg-sector-dropdown">
                            <input type="hidden" id="reg-sector-hidden" name="org_sector">
                            <ul id="reg-sector-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;"></ul>
                        </div>
                    </div>
                    <div class="csc-form-group">
                        <label for="reg-industry-input">Primary Industry <span class="csc-label-optional">(optional)</span></label>
                        <div class="csc-typeahead-wrap">
                            <input type="text" id="reg-industry-input" class="csc-typeahead-input"
                                   placeholder="Select primary industry" autocomplete="off"
                                   aria-autocomplete="list" aria-controls="reg-industry-dropdown">
                            <input type="hidden" id="reg-industry-hidden" name="org_industry">
                            <ul id="reg-industry-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;"></ul>
                        </div>
                    </div>
                    <div class="csc-form-group">
                        <label for="reg-igp-input">Industrial Growth Plan Category <span class="csc-label-optional">(optional)</span></label>
                        <div class="csc-typeahead-wrap">
                            <input type="text" id="reg-igp-input" class="csc-typeahead-input"
                                   placeholder="Select IGP category" autocomplete="off"
                                   aria-autocomplete="list" aria-controls="reg-igp-dropdown">
                            <input type="hidden" id="reg-igp-hidden" name="org_igp">
                            <ul id="reg-igp-dropdown" class="csc-typeahead-dropdown" role="listbox" style="display:none;"></ul>
                        </div>
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-phone">Phone <span class="csc-label-optional">(optional)</span></label>
                        <input type="tel" id="csc-org-phone" name="org_phone" placeholder="e.g. +44 1792 000000">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-website">Website <span class="csc-label-optional">(optional)</span></label>
                        <input type="url" id="csc-org-website" name="org_website" placeholder="https://example.com">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-org-description">Organisation Description <span class="csc-label-optional">(optional)</span></label>
                        <textarea id="csc-org-description" name="org_description" rows="4"
                            placeholder="Briefly describe your organisation's work and focus…"></textarea>
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
                    <div class="csc-form-group">
                        <label for="csc-linkedin-1">LinkedIn Profile URL <span class="csc-label-optional">(optional)</span></label>
                        <input type="url" id="csc-linkedin-1" name="linkedin"
                               placeholder="https://linkedin.com/in/yourname" autocomplete="url">
                    </div>
                    <div class="csc-form-group">
                        <label for="csc-bio-1">Bio <span class="csc-label-optional">(optional)</span></label>
                        <textarea id="csc-bio-1" name="bio" rows="3"
                                  placeholder="Tell us a little about yourself…"></textarea>
                    </div>

                    <div class="csc-consent-group">
                        <label class="csc-checkbox-label csc-consent-label">
                            <input type="checkbox" name="consent_marketing" id="consent-marketing-1">
                            Yes, I would like to receive marketing communications and details of forthcoming events from the Celtic Sea Cluster.
                        </label>
                        <label class="csc-checkbox-label csc-consent-label">
                            <input type="checkbox" name="consent_sharing" id="consent-sharing-1">
                            Yes, I consent to my contact details being shared with the Celtic Sea Cluster Board Members (Celtic Sea Power, Offshore Renewable Energy (ORE) Catapult, Pembrokeshire Coastal Forum, and Welsh Government), so they can contact me with relevant information such as funding programmes and events.
                        </label>
                        <label class="csc-checkbox-label csc-consent-label">
                            <input type="checkbox" name="consent_directory" id="consent-directory-1">
                            Yes, I consent to my organisation's details being included in the Celtic Sea Cluster Member Directory, visible to other members. I understand that any content I choose to post within the members' forum will also be visible to other members.
                        </label>
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
                <div class="csc-form-group">
                    <label for="csc-linkedin-2">LinkedIn Profile URL <span class="csc-label-optional">(optional)</span></label>
                    <input type="url" id="csc-linkedin-2" name="linkedin_2"
                           placeholder="https://linkedin.com/in/yourname" autocomplete="url">
                </div>
                <div class="csc-form-group">
                    <label for="csc-bio-2">Bio <span class="csc-label-optional">(optional)</span></label>
                    <textarea id="csc-bio-2" name="bio_2" rows="3"
                              placeholder="Tell us a little about yourself…"></textarea>
                </div>

                <div class="csc-consent-group">
                    <label class="csc-checkbox-label csc-consent-label">
                        <input type="checkbox" name="consent_marketing" id="consent-marketing-2">
                        Yes, I would like to receive marketing communications and details of forthcoming events from the Celtic Sea Cluster.
                    </label>
                    <label class="csc-checkbox-label csc-consent-label">
                        <input type="checkbox" name="consent_sharing" id="consent-sharing-2">
                        Yes, I consent to my contact details being shared with the Celtic Sea Cluster Board Members (Celtic Sea Power, Offshore Renewable Energy (ORE) Catapult, Pembrokeshire Coastal Forum, and Welsh Government), so they can contact me with relevant information such as funding programmes and events.
                    </label>
                    <label class="csc-checkbox-label csc-consent-label">
                        <input type="checkbox" name="consent_directory" id="consent-directory-2">
                        Yes, I consent to my organisation's details being included in the Celtic Sea Cluster Member Directory, visible to other members. I understand that any content I choose to post within the members' forum will also be visible to other members.
                    </label>
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
<script>
window.cscCountries     = <?php echo wp_json_encode( csc_get_countries() ); ?>;
window.cscUkCounties    = <?php echo wp_json_encode( csc_get_uk_counties_flat() ); ?>;
window.cscRegSectors    = <?php echo wp_json_encode( Csc_Organisations::get_company_types() ); ?>;
window.cscRegIndustries = <?php echo wp_json_encode( Csc_Organisations::get_primary_industries() ); ?>;
window.cscRegIgp        = <?php echo wp_json_encode( Csc_Organisations::get_igp_categories() ); ?>;
</script>
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

		// Verify credentials WITHOUT creating a session (no cookies, no redirects)
		$auth = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $auth ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email address or password.' ) );
		}

		// Check CSC membership status
		$status = get_user_meta( $auth->ID, '_csc_status', true );
		if ( $status === 'pending' ) {
			wp_send_json_error( array(
				'message' => 'Your account is currently pending approval. You will be notified once access is granted.',
			) );
		}

		// Two-factor authentication: send code, do NOT create session yet
		if ( get_user_meta( $auth->ID, '_csc_2fa_enabled', true ) === '1' ) {
			$code  = (string) random_int( 100000, 999999 );
			$token = wp_generate_password( 32, false );
			set_transient( 'csc_2fa_' . $token, array(
				'user_id'  => $auth->ID,
				'code'     => $code,
				'remember' => $remember,
			), 600 ); // 10 minutes

			$this->send_2fa_email( $auth, $code );

			wp_send_json_success( array(
				'require_2fa' => true,
				'token'       => $token,
				'nonce'       => wp_create_nonce( 'csc_verify_2fa' ),
			) );
		}

		// No 2FA — create session now and redirect
		wp_set_auth_cookie( $auth->ID, $remember );
		wp_set_current_user( $auth->ID );
		do_action( 'wp_login', $auth->user_login, $auth );

		$this->maybe_send_login_alert( $auth );

		wp_send_json_success( array( 'redirect' => $this->get_dashboard_url() ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Verify 2FA code
	 * --------------------------------------------------------------------- */
	public function handle_verify_2fa() {
		check_ajax_referer( 'csc_verify_2fa', 'nonce' );

		$token = sanitize_text_field( $_POST['token'] ?? '' );
		$code  = preg_replace( '/\D/', '', $_POST['code'] ?? '' );

		if ( ! $token || ! $code ) {
			wp_send_json_error( array( 'message' => 'Please enter the verification code.' ) );
		}

		$data = get_transient( 'csc_2fa_' . $token );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => 'Code has expired. Please go back and log in again.' ) );
		}

		if ( ! hash_equals( $data['code'], $code ) ) {
			wp_send_json_error( array( 'message' => 'Invalid code. Please try again.' ) );
		}

		delete_transient( 'csc_2fa_' . $token );

		$user = get_user_by( 'ID', $data['user_id'] );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'Something went wrong. Please log in again.' ) );
		}

		wp_set_auth_cookie( $user->ID, $data['remember'] );

		$this->maybe_send_login_alert( $user );

		wp_send_json_success( array( 'redirect' => $this->get_dashboard_url() ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Resend 2FA code
	 * --------------------------------------------------------------------- */
	public function handle_resend_2fa() {
		check_ajax_referer( 'csc_verify_2fa', 'nonce' );

		$token = sanitize_text_field( $_POST['token'] ?? '' );
		if ( ! $token ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ) );
		}

		$data = get_transient( 'csc_2fa_' . $token );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => 'Session expired. Please log in again.' ) );
		}

		$user = get_user_by( 'ID', $data['user_id'] );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'Something went wrong. Please log in again.' ) );
		}

		// Generate a new code and reset the TTL
		$new_code = (string) random_int( 100000, 999999 );
		$data['code'] = $new_code;
		set_transient( 'csc_2fa_' . $token, $data, 600 );

		$this->send_2fa_email( $user, $new_code );

		wp_send_json_success( array( 'message' => 'A new code has been sent to your email.' ) );
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
		$linkedin        = esc_url_raw( $_POST['linkedin'] ?? '' );
		$bio             = sanitize_textarea_field( $_POST['bio'] ?? '' );
		$org_id          = intval( $_POST['organisation_id'] ?? 0 );
		$register_new    = ! empty( $_POST['register_new_org'] );

		// Consent fields
		$consent_marketing  = ! empty( $_POST['consent_marketing'] );
		$consent_sharing    = ! empty( $_POST['consent_sharing'] );
		$consent_directory  = ! empty( $_POST['consent_directory'] );

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
		if ( ! $consent_sharing || ! $consent_directory ) {
			wp_send_json_error( array( 'message' => 'Please accept the required consent statements to proceed.' ) );
		}

		// Handle new organisation registration
		if ( $register_new ) {
			$org_name        = sanitize_text_field( $_POST['org_name']        ?? '' );
			$org_address     = sanitize_text_field( $_POST['org_address']     ?? '' );
			$org_city        = sanitize_text_field( $_POST['org_city']        ?? '' );
			$org_country     = sanitize_text_field( $_POST['org_country']     ?? '' );
			$org_county      = sanitize_text_field( $_POST['org_county']      ?? '' );
			$org_postcode    = sanitize_text_field( $_POST['org_postcode']    ?? '' );
			$org_sector      = sanitize_text_field( $_POST['org_sector']      ?? '' );
			$org_industry    = sanitize_text_field( $_POST['org_industry']    ?? '' );
			$org_igp         = sanitize_text_field( $_POST['org_igp']         ?? '' );
			$org_phone       = sanitize_text_field( $_POST['org_phone']       ?? '' );
			$org_website     = esc_url_raw(         $_POST['org_website']     ?? '' );
			$org_description = sanitize_textarea_field( $_POST['org_description'] ?? '' );

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

			update_post_meta( $org_id, '_csc_org_sector',  $org_sector );
			if ( $org_address )     update_post_meta( $org_id, '_csc_org_address',      $org_address );
			if ( $org_city )        update_post_meta( $org_id, '_csc_org_city',          $org_city );
			if ( $org_city )        update_post_meta( $org_id, '_csc_org_location',      $org_city );
			if ( $org_country )     update_post_meta( $org_id, '_csc_org_country',       $org_country );
			if ( $org_county )      update_post_meta( $org_id, '_csc_org_county',        $org_county );
			if ( $org_postcode )    update_post_meta( $org_id, '_csc_org_postcode',      $org_postcode );
			if ( $org_industry )    update_post_meta( $org_id, '_csc_org_industry',      $org_industry );
			if ( $org_igp )         update_post_meta( $org_id, '_csc_org_igp_category',  $org_igp );
			if ( $org_phone )       update_post_meta( $org_id, '_csc_org_phone',         $org_phone );
			if ( $org_website )     update_post_meta( $org_id, '_csc_org_website',       $org_website );
			if ( $org_description ) update_post_meta( $org_id, '_csc_org_description',   $org_description );

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

		update_user_meta( $user_id, '_csc_status',          'pending' );
		update_user_meta( $user_id, '_csc_job_title',       $job_title );
		update_user_meta( $user_id, '_csc_organisation_id', $org_id );

		// Grant company edit permission to whoever registered the organisation
		if ( $register_new ) {
			update_user_meta( $user_id, '_csc_can_edit_company', '1' );
		}

		// Optional profile fields
		if ( $linkedin ) update_user_meta( $user_id, '_csc_linkedin', $linkedin );
		if ( $bio )      update_user_meta( $user_id, '_csc_about',    $bio );

		// Consent preferences
		update_user_meta( $user_id, '_csc_consent_marketing',  $consent_marketing  ? '1' : '0' );
		update_user_meta( $user_id, '_csc_consent_sharing',    $consent_sharing    ? '1' : '0' );
		update_user_meta( $user_id, '_csc_consent_directory',  $consent_directory  ? '1' : '0' );

		// Directory visibility — initialised from consent_directory so the profile
		// toggles reflect the user's choice from the moment the account is created.
		$dir_vis = $consent_directory ? '1' : '0';
		update_user_meta( $user_id, '_csc_dir_org_visible',     $dir_vis );
		update_user_meta( $user_id, '_csc_dir_profile_visible', $dir_vis );

		// Security defaults — both off until the user opts in via Settings.
		update_user_meta( $user_id, '_csc_2fa_enabled',  '0' );
		update_user_meta( $user_id, '_csc_login_alerts', '0' );

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

	/* -----------------------------------------------------------------------
	 * AJAX: send password reset email
	 * --------------------------------------------------------------------- */
	public function handle_forgot_password() {
		check_ajax_referer( 'csc_forgot_password', 'nonce' );

		$email = sanitize_email( $_POST['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}

		// Generic response to prevent email enumeration
		$generic = array( 'message' => 'If an account exists for that email address, you will receive a reset link shortly.' );

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_success( $generic );
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			wp_send_json_error( array( 'message' => 'Unable to generate a reset link. Please try again.' ) );
		}

		$reset_page = get_page_by_path( 'members-set-password' );
		$reset_url  = add_query_arg(
			array( 'key' => $key, 'login' => rawurlencode( $user->user_login ) ),
			$reset_page ? get_permalink( $reset_page->ID ) : home_url( '/members-set-password/' )
		);

		$site  = get_bloginfo( 'name' );
		$fname = get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name;

		$subject = "[{$site}] Reset your password";
		$body    = "Hi {$fname},\n\n";
		$body   .= "We received a request to reset the password for your {$site} account.\n\n";
		$body   .= "Click the link below to choose a new password:\n";
		$body   .= $reset_url . "\n\n";
		$body   .= "This link expires in 24 hours. If you didn't request this, you can safely ignore this email — your password won't change.\n\n";
		$body   .= "— The {$site} Team";

		wp_mail( $user->user_email, $subject, $body );

		wp_send_json_success( $generic );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: set new password via reset key
	 * --------------------------------------------------------------------- */
	public function handle_set_password() {
		check_ajax_referer( 'csc_set_password', 'nonce' );

		$key     = sanitize_text_field( $_POST['key']             ?? '' );
		$login   = sanitize_text_field( $_POST['login']           ?? '' );
		$new_pw  =                      $_POST['new_password']     ?? '';
		$confirm =                      $_POST['confirm_password'] ?? '';

		if ( ! $new_pw || ! $confirm ) {
			wp_send_json_error( array( 'message' => 'Please fill in both password fields.' ) );
		}
		if ( strlen( $new_pw ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters.' ) );
		}
		if ( $new_pw !== $confirm ) {
			wp_send_json_error( array( 'message' => 'Passwords do not match.' ) );
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => 'This link has expired or is invalid. Please request a new one.' ) );
		}

		reset_password( $user, $new_pw );
		wp_set_auth_cookie( $user->ID, false );

		wp_send_json_success( array(
			'message'  => 'Password set successfully! Redirecting…',
			'redirect' => $this->get_dashboard_url(),
		) );
	}

	private function send_2fa_email( $user, $code ) {
		$site  = get_bloginfo( 'name' );
		$fname = get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name;

		$subject = "[{$site}] Your verification code";
		$body    = "Hi {$fname},\n\n";
		$body   .= "Your two-factor authentication code is:\n\n";
		$body   .= "    {$code}\n\n";
		$body   .= "This code expires in 10 minutes. Do not share it with anyone.\n\n";
		$body   .= "If you did not attempt to sign in, please change your password immediately.\n\n";
		$body   .= "— The {$site} Team";

		wp_mail( $user->user_email, $subject, $body );
	}

	private function maybe_send_login_alert( $user ) {
		$pref = get_user_meta( $user->ID, '_csc_login_alerts', true );
		// Default is ON; only skip if explicitly set to '0'
		if ( $pref === '0' ) {
			return;
		}

		$site  = get_bloginfo( 'name' );
		$fname = get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name;
		$time  = wp_date( 'j M Y, H:i T' );
		$ip    = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'Unknown' );
		$ua    = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' );

		$subject = "[{$site}] New sign-in to your account";
		$body    = "Hi {$fname},\n\n";
		$body   .= "A new sign-in to your {$site} account was detected.\n\n";
		$body   .= "Time:       {$time}\n";
		$body   .= "IP address: {$ip}\n";
		$body   .= "Browser:    {$ua}\n\n";
		$body   .= "If this was you, no action is needed.\n";
		$body   .= "If you did not sign in, please change your password immediately.\n\n";
		$body   .= "— The {$site} Team";

		wp_mail( $user->user_email, $subject, $body );
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