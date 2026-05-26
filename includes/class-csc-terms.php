<?php
/**
 * Terms of Use page.
 *
 * Shortcode: [csc_terms]
 * Admin sets terms content via Settings → CSC Terms of Use.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Terms {

	public function register_hooks() {
		add_shortcode( 'csc_terms', array( $this, 'render' ) );
		add_action( 'admin_menu',  array( $this, 'admin_settings_page' ) );
		add_action( 'admin_init',  array( $this, 'register_settings' ) );
	}

	/* -----------------------------------------------------------------------
	 * Admin settings page
	 * --------------------------------------------------------------------- */
	public function admin_settings_page() {
		add_submenu_page(
			'options-general.php',
			'CSC Terms of Use',
			'CSC Terms of Use',
			'manage_options',
			'csc-terms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'csc_terms_group', 'csc_terms_content', array( 'sanitize_callback' => 'wp_kses_post' ) );
	}

	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>CSC Terms of Use</h1>
			<p>Edit the terms of use content displayed to members on the portal.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'csc_terms_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="csc_terms_content">Terms Content</label></th>
						<td>
							<?php
							wp_editor(
								get_option( 'csc_terms_content', self::default_content() ),
								'csc_terms_content',
								array(
									'textarea_name' => 'csc_terms_content',
									'media_buttons' => false,
									'textarea_rows' => 25,
									'teeny'         => false,
								)
							);
							?>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Terms' ); ?>
			</form>
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

		$content = get_option( 'csc_terms_content', self::default_content() );

		ob_start();
		?>
		<div class="csc-member-portal">
			<?php echo Csc_Dashboard::render_sidebar( 'terms', $user ); ?>

			<main class="csc-portal-main">
				<div class="csc-page-header">
					<div>
						<h1 class="csc-page-title">Terms of Use</h1>
						<p class="csc-page-subtitle">Review the policies and guidelines that guide our community.</p>
					</div>
				</div>

				<div class="csc-terms-body">
					<?php echo wp_kses_post( $content ); ?>
				</div>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Default placeholder content
	 * --------------------------------------------------------------------- */
	private static function default_content() {
		return '<h2>Terms of Use</h2>
<p>Welcome to the Celtic Sea Cluster (CSC) Member Portal. By accessing and using this portal, you agree to be bound by these Terms of Use. Please read them carefully.</p>

<h3>1. Membership &amp; Access</h3>
<p>Access to the CSC Member Portal is restricted to approved members of the Celtic Sea Cluster. Your login credentials are personal and must not be shared with others. You are responsible for maintaining the confidentiality of your account details.</p>

<h3>2. Acceptable Use</h3>
<p>Members agree to use the portal in a respectful and professional manner. You must not post content that is defamatory, discriminatory, misleading, or otherwise harmful to other members or the organisation. The forum and other communication tools are intended for constructive professional exchange.</p>

<h3>3. Confidentiality</h3>
<p>Information shared within the Member Portal, including forum discussions, member contact details, and shared resources, is confidential and intended solely for use by CSC members. Members must not share, distribute, or publish any content or information from the portal without prior written consent from CSC.</p>

<h3>4. Intellectual Property</h3>
<p>All content, documents, and resources made available through the portal remain the intellectual property of the Celtic Sea Cluster or the respective contributing member unless otherwise stated. Reproduction or distribution of portal content requires explicit permission.</p>

<h3>5. Member Directory</h3>
<p>Your profile information in the member directory is visible to other approved CSC members. You may update your profile at any time via the Update Account section. You consent to your professional details being displayed to fellow members upon joining.</p>

<h3>6. Privacy &amp; Data</h3>
<p>CSC collects and processes your personal data in accordance with applicable data protection legislation including the UK GDPR. Your data is used solely for the purposes of managing your membership and facilitating communication within the cluster. Please contact CSC directly for a copy of the full Privacy Policy.</p>

<h3>7. Modifications</h3>
<p>CSC reserves the right to update these Terms of Use at any time. Members will be notified of material changes. Continued use of the portal following notification of changes constitutes acceptance of the revised terms.</p>

<h3>8. Termination</h3>
<p>CSC may suspend or terminate portal access for any member found to be in breach of these terms. Members may request account deletion by contacting CSC directly.</p>

<h3>9. Contact</h3>
<p>If you have questions about these Terms of Use, please contact the CSC team via the details provided on the main CSC website.</p>

<p><em>Last updated: May 2025</em></p>';
	}
}
