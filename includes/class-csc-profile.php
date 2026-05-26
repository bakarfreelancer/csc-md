<?php
/**
 * Update Account / My Profile shortcode.
 * Shortcode: [csc_update_account]
 * Tabs: Personal Details | Company Information
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Profile {

	public function register_hooks() {
		add_shortcode( 'csc_update_account', array( $this, 'render' ) );
		add_action( 'wp_ajax_csc_save_personal',    array( $this, 'ajax_save_personal' ) );
		add_action( 'wp_ajax_csc_save_company',     array( $this, 'ajax_save_company' ) );
		add_action( 'wp_ajax_csc_upload_user_photo', array( $this, 'ajax_upload_user_photo' ) );
		add_action( 'wp_ajax_csc_upload_org_logo',   array( $this, 'ajax_upload_org_logo' ) );
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

		$tab      = sanitize_key( $_GET['tab'] ?? 'personal' );
		$page_url = Csc_Dashboard::portal_url( 'update-account' );

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'account', $user ); ?>

			<main class="csc-portal-main">

				<!-- Page header -->
				<div class="csc-page-header">
					<h1 class="csc-page-title">My Profile</h1>
					<button type="button" class="csc-btn-primary" id="csc-profile-save-btn">Save Changes</button>
				</div>

				<div id="csc-profile-message" class="csc-alert" style="display:none;margin-bottom:16px;"></div>

				<!-- Tabs -->
				<div class="csc-settings-tabs">
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'personal', $page_url ) ); ?>"
					   class="csc-settings-tab <?php echo $tab === 'personal' ? 'is-active' : ''; ?>">Personal Details</a>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'company', $page_url ) ); ?>"
					   class="csc-settings-tab <?php echo $tab === 'company' ? 'is-active' : ''; ?>">Company Information</a>
				</div>

				<!-- Tab content -->
				<div class="csc-settings-body">
					<?php if ( $tab === 'company' ) : ?>
						<?php echo $this->render_company_tab( $user ); ?>
					<?php else : ?>
						<?php echo $this->render_personal_tab( $user ); ?>
					<?php endif; ?>
				</div>

			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Personal Details tab
	 * --------------------------------------------------------------------- */
	private function render_personal_tab( $user ) {
		$first_name = get_user_meta( $user->ID, 'first_name',     true );
		$last_name  = get_user_meta( $user->ID, 'last_name',      true );
		$job_title  = get_user_meta( $user->ID, '_csc_job_title', true );
		$phone      = get_user_meta( $user->ID, '_csc_phone',     true );
		$linkedin   = get_user_meta( $user->ID, '_csc_linkedin',  true );
		$about      = get_user_meta( $user->ID, '_csc_about',     true );
		$expertise  = get_user_meta( $user->ID, '_csc_expertise', true );

		$org_id   = get_user_meta( $user->ID, '_csc_organisation_id', true );
		$org_name = $org_id ? get_the_title( $org_id ) : '';

		$words      = explode( ' ', $user->display_name );
		$initials   = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );
		$skills     = $expertise ? array_filter( array_map( 'trim', explode( ',', $expertise ) ) ) : array();
		$photo_id   = (int) get_user_meta( $user->ID, '_csc_profile_photo_id', true );
		$photo_url  = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';

		ob_start();
		?>
		<form class="csc-profile-form"
		      id="csc-profile-form"
		      data-action="csc_save_personal"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_personal' ) ); ?>">

			<!-- Avatar / identity block -->
			<div class="csc-profile-identity">
				<div class="csc-profile-avatar-wrap">
					<?php if ( $photo_url ) : ?>
					<img src="<?php echo esc_url( $photo_url ); ?>" alt=""
					     class="csc-avatar csc-avatar--xl csc-avatar--photo" id="csc-user-avatar-photo">
					<div class="csc-avatar csc-avatar--xl" id="csc-user-avatar-initials"
					     style="display:none;" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<?php else : ?>
					<img src="" alt=""
					     class="csc-avatar csc-avatar--xl csc-avatar--photo" id="csc-user-avatar-photo"
					     style="display:none;">
					<div class="csc-avatar csc-avatar--xl" id="csc-user-avatar-initials"
					     aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<?php endif; ?>
					<input type="file" id="csc-user-photo-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
				</div>
				<div class="csc-profile-identity-info">
					<div class="csc-profile-identity-name"><?php echo esc_html( $user->display_name ); ?></div>
					<?php if ( $org_name ) : ?>
					<div class="csc-profile-identity-org"><?php echo esc_html( $org_name ); ?></div>
					<?php endif; ?>
					<button type="button" class="csc-avatar-add-btn" id="csc-user-photo-btn"
					        data-action="csc_upload_user_photo"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_upload_user_photo' ) ); ?>">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><line x1="8" y1="5" x2="8" y2="11"/><line x1="5" y1="8" x2="11" y2="8"/></svg>
						<?php echo $photo_url ? 'Change image' : 'Add image'; ?>
					</button>
				</div>
			</div>

			<!-- Two-column form fields -->
			<div class="csc-profile-fields">

				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="pf-first-name">First Name</label>
						<input type="text" id="pf-first-name" name="first_name" class="csc-input"
						       value="<?php echo esc_attr( $first_name ); ?>">
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="pf-last-name">Last Name</label>
						<input type="text" id="pf-last-name" name="last_name" class="csc-input"
						       value="<?php echo esc_attr( $last_name ); ?>">
					</div>
				</div>

				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="pf-job-title">Job Title</label>
						<input type="text" id="pf-job-title" name="job_title" class="csc-input"
						       placeholder="Operations Manager" value="<?php echo esc_attr( $job_title ); ?>">
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="pf-email">Email</label>
						<input type="email" id="pf-email" name="email" class="csc-input"
						       value="<?php echo esc_attr( $user->user_email ); ?>">
					</div>
				</div>

				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="pf-phone">Contact number</label>
						<input type="tel" id="pf-phone" name="phone" class="csc-input"
						       placeholder="Enter Contact Number" value="<?php echo esc_attr( $phone ); ?>">
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="pf-linkedin">Add LinkedIn profile link</label>
						<input type="url" id="pf-linkedin" name="linkedin" class="csc-input"
						       placeholder="Paste LinkedIn link here…" value="<?php echo esc_attr( $linkedin ); ?>">
					</div>
				</div>

				<div class="csc-form-group csc-form-group--full">
					<label class="csc-label" for="pf-about">About me</label>
					<textarea id="pf-about" name="about" class="csc-input csc-textarea" rows="5"
					          placeholder="15 years of experience in offshore energy operations management."><?php echo esc_textarea( $about ); ?></textarea>
				</div>

				<div class="csc-form-group csc-form-group--full">
					<label class="csc-label">Skills</label>
					<div class="csc-tag-input-wrap" id="csc-skills-wrap">
						<div class="csc-tag-chips" id="csc-skills-chips">
							<?php foreach ( $skills as $tag ) : ?>
							<span class="csc-tag-chip">
								<?php echo esc_html( $tag ); ?>
								<button type="button" class="csc-tag-chip-remove"
								        data-tag="<?php echo esc_attr( $tag ); ?>"
								        aria-label="Remove <?php echo esc_attr( $tag ); ?>">×</button>
							</span>
							<?php endforeach; ?>
						</div>
						<input type="text" id="csc-skills-input" class="csc-tag-input"
						       placeholder="Type a skill and press Enter or comma…" autocomplete="off">
						<input type="hidden" name="expertise" id="csc-skills-hidden"
						       value="<?php echo esc_attr( $expertise ); ?>">
					</div>
				</div>

			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Company Information tab — two-column layout with typeahead
	 * --------------------------------------------------------------------- */
	private function render_company_tab( $user ) {
		$org_id = get_user_meta( $user->ID, '_csc_organisation_id', true );

		if ( ! $org_id ) {
			return '<div class="csc-settings-notice"><p>You are not currently associated with an organisation.</p></div>';
		}

		$org = get_post( $org_id );
		if ( ! $org ) {
			return '<div class="csc-settings-notice"><p>Organisation not found.</p></div>';
		}

		$address     = get_post_meta( $org_id, '_csc_org_address',      true );
		$city        = get_post_meta( $org_id, '_csc_org_city',         true )
		            ?: get_post_meta( $org_id, '_csc_org_location',     true );
		$county      = get_post_meta( $org_id, '_csc_org_county',       true );
		$country     = get_post_meta( $org_id, '_csc_org_country',      true );
		$postcode    = get_post_meta( $org_id, '_csc_org_postcode',     true );
		$industry    = get_post_meta( $org_id, '_csc_org_industry',     true );
		$igp         = get_post_meta( $org_id, '_csc_org_igp_category', true );
		$co_type     = get_post_meta( $org_id, '_csc_org_sector',       true );
		$org_phone   = get_post_meta( $org_id, '_csc_org_phone',        true );
		$website     = get_post_meta( $org_id, '_csc_org_website',      true );
		$description = get_post_meta( $org_id, '_csc_org_description',  true );

		$words    = explode( ' ', $org->post_title );
		$initials = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );
		$logo_id  = (int) get_post_meta( $org_id, '_csc_org_logo_id', true );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';

		$industries    = Csc_Organisations::get_primary_industries();
		$igp_cats      = Csc_Organisations::get_igp_categories();
		$company_types = Csc_Organisations::get_company_types();

		// County row: visible only when country is United Kingdom
		$is_uk         = ( $country === 'United Kingdom' );
		$county_hidden = $is_uk ? '' : 'display:none;';
		$city_col      = $is_uk ? '1fr 1fr' : '1fr';

		ob_start();
		?>
		<script>
		window.cscCountries  = <?php echo wp_json_encode( csc_get_countries() ); ?>;
		window.cscUkCounties = <?php echo wp_json_encode( csc_get_uk_counties_flat() ); ?>;
		</script>

		<form class="csc-profile-form"
		      id="csc-profile-form"
		      data-action="csc_save_company"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_save_company' ) ); ?>"
		      data-org-id="<?php echo esc_attr( $org_id ); ?>">

			<!-- Company logo block -->
			<div class="csc-profile-identity csc-profile-identity--company">
				<div class="csc-profile-avatar-wrap">
					<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt=""
					     class="csc-avatar csc-avatar--xl csc-avatar--photo" id="csc-org-avatar-photo">
					<div class="csc-avatar csc-avatar--xl" id="csc-org-avatar-initials"
					     style="display:none;" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<?php else : ?>
					<img src="" alt=""
					     class="csc-avatar csc-avatar--xl csc-avatar--photo" id="csc-org-avatar-photo"
					     style="display:none;">
					<div class="csc-avatar csc-avatar--xl" id="csc-org-avatar-initials"
					     aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<?php endif; ?>
					<input type="file" id="csc-org-logo-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
				</div>
				<div class="csc-profile-identity-info">
					<div class="csc-profile-identity-name"><?php echo esc_html( $org->post_title ); ?></div>
					<button type="button" class="csc-avatar-add-btn" id="csc-org-logo-btn"
					        data-action="csc_upload_org_logo"
					        data-nonce="<?php echo esc_attr( wp_create_nonce( 'csc_upload_org_logo' ) ); ?>">
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><line x1="8" y1="5" x2="8" y2="11"/><line x1="5" y1="8" x2="11" y2="8"/></svg>
						<?php echo $logo_url ? 'Change image' : 'Add image'; ?>
					</button>
				</div>
			</div>

			<!-- Two-column fields -->
			<div class="csc-profile-fields">

				<!-- Organisation Name — full width -->
				<div class="csc-form-group">
					<label class="csc-label" for="co-name">Organisation Name</label>
					<input type="text" id="co-name" name="org_name" class="csc-input"
					       value="<?php echo esc_attr( $org->post_title ); ?>">
				</div>

				<!-- Address — full width -->
				<div class="csc-form-group">
					<label class="csc-label" for="co-address">Company Address</label>
					<input type="text" id="co-address" name="org_address" class="csc-input"
					       placeholder="Cork Innovation Centre" value="<?php echo esc_attr( $address ); ?>">
				</div>

				<!-- Country | Postcode -->
				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="co-country-input">Country</label>
						<div class="csc-typeahead-wrap" id="csc-country-wrap">
							<input type="text" id="co-country-input" class="csc-input"
							       placeholder="Select country…" autocomplete="off"
							       value="<?php echo esc_attr( $country ); ?>">
							<input type="hidden" name="org_country" id="co-country-hidden"
							       value="<?php echo esc_attr( $country ); ?>">
							<ul class="csc-typeahead-dropdown" id="csc-country-dropdown" role="listbox" style="display:none;"></ul>
						</div>
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="co-postcode">Postcode</label>
						<input type="text" id="co-postcode" name="org_postcode" class="csc-input"
						       placeholder="T12 XTA6" value="<?php echo esc_attr( $postcode ); ?>">
					</div>
				</div>

				<!-- City | County (county only visible when UK selected) -->
				<div class="csc-profile-row" id="csc-city-county-row"
				     style="grid-template-columns:<?php echo esc_attr( $city_col ); ?>;">
					<div class="csc-form-group">
						<label class="csc-label" for="co-city">City / Town</label>
						<input type="text" id="co-city" name="org_city" class="csc-input"
						       placeholder="Cork" value="<?php echo esc_attr( $city ); ?>">
					</div>
					<div class="csc-form-group" id="csc-county-group" style="<?php echo esc_attr( $county_hidden ); ?>">
						<label class="csc-label" for="co-county-input">County</label>
						<div class="csc-typeahead-wrap" id="csc-county-wrap">
							<input type="text" id="co-county-input" class="csc-input"
							       placeholder="Select county…" autocomplete="off"
							       value="<?php echo esc_attr( $county ); ?>">
							<input type="hidden" name="org_county" id="co-county-hidden"
							       value="<?php echo esc_attr( $county ); ?>">
							<ul class="csc-typeahead-dropdown" id="csc-county-dropdown" role="listbox" style="display:none;"></ul>
						</div>
					</div>
				</div>

				<!-- Primary Industry | Company Type -->
				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="co-industry">Primary Industry</label>
						<select id="co-industry" name="org_industry" class="csc-input csc-select">
							<option value="">Select industry…</option>
							<?php foreach ( $industries as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $industry, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="co-type">Company Type</label>
						<select id="co-type" name="org_company_type" class="csc-input csc-select">
							<option value="">Select type…</option>
							<?php foreach ( $company_types as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $co_type, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- IGP Category | Contact number -->
				<div class="csc-profile-row">
					<div class="csc-form-group">
						<label class="csc-label" for="co-igp">
							<abbr title="Industrial Growth Plan Category" style="text-decoration:underline dotted;cursor:help;">IGP Category</abbr>
						</label>
						<select id="co-igp" name="org_igp" class="csc-input csc-select">
							<option value="">Select category…</option>
							<?php foreach ( $igp_cats as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $igp, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="co-phone">Contact Number</label>
						<input type="tel" id="co-phone" name="org_phone" class="csc-input"
						       placeholder="Type number" value="<?php echo esc_attr( $org_phone ); ?>">
					</div>
				</div>

				<!-- Website — full width -->
				<div class="csc-form-group">
					<label class="csc-label" for="co-website">Website</label>
					<div class="csc-input-with-icon">
						<input type="url" id="co-website" name="org_website" class="csc-input"
						       placeholder="https://example.com" value="<?php echo esc_attr( $website ); ?>">
						<button type="button" class="csc-input-icon-btn" id="csc-copy-website"
						        title="Copy URL" aria-label="Copy website URL">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M3 13V3h10"/></svg>
						</button>
					</div>
				</div>

				<!-- Description — full width -->
				<div class="csc-form-group">
					<label class="csc-label" for="co-desc">Description</label>
					<textarea id="co-desc" name="org_description" class="csc-input csc-textarea" rows="4"
					          placeholder="Leading provider of floating wind substructure designs."><?php echo esc_textarea( $description ); ?></textarea>
				</div>

			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save personal details
	 * --------------------------------------------------------------------- */
	public function ajax_save_personal() {
		check_ajax_referer( 'csc_save_personal', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user  = wp_get_current_user();
		$first = sanitize_text_field( $_POST['first_name'] ?? '' );
		$last  = sanitize_text_field( $_POST['last_name']  ?? '' );

		update_user_meta( $user->ID, 'first_name', $first );
		update_user_meta( $user->ID, 'last_name',  $last );

		$userdata = array(
			'ID'           => $user->ID,
			'display_name' => trim( "$first $last" ) ?: $user->display_name,
		);

		if ( ! empty( $_POST['email'] ) ) {
			$new_email = sanitize_email( $_POST['email'] );
			if ( $new_email !== $user->user_email ) {
				$existing = email_exists( $new_email );
				if ( $existing && intval( $existing ) !== $user->ID ) {
					wp_send_json_error( array( 'message' => 'That email address is already in use.' ) );
				}
				$userdata['user_email'] = $new_email;
			}
		}

		wp_update_user( $userdata );

		update_user_meta( $user->ID, '_csc_job_title', sanitize_text_field( $_POST['job_title']   ?? '' ) );
		update_user_meta( $user->ID, '_csc_phone',     sanitize_text_field( $_POST['phone']       ?? '' ) );
		update_user_meta( $user->ID, '_csc_linkedin',  esc_url_raw(          $_POST['linkedin']   ?? '' ) );
		update_user_meta( $user->ID, '_csc_about',     sanitize_textarea_field( $_POST['about']   ?? '' ) );
		update_user_meta( $user->ID, '_csc_expertise', sanitize_text_field( $_POST['expertise']   ?? '' ) );

		wp_send_json_success( array( 'message' => 'Personal details saved successfully.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save company information
	 * --------------------------------------------------------------------- */
	public function ajax_save_company() {
		check_ajax_referer( 'csc_save_company', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user     = wp_get_current_user();
		$org_id   = absint( $_POST['org_id'] ?? 0 );
		$user_org = absint( get_user_meta( $user->ID, '_csc_organisation_id', true ) );

		if ( ! $org_id || $user_org !== $org_id ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( ! empty( $_POST['org_name'] ) ) {
			wp_update_post( array(
				'ID'         => $org_id,
				'post_title' => sanitize_text_field( $_POST['org_name'] ),
			) );
		}

		$text_fields = array(
			'_csc_org_address'      => 'org_address',
			'_csc_org_city'         => 'org_city',
			'_csc_org_location'     => 'org_city',     // keep directory in sync
			'_csc_org_county'       => 'org_county',
			'_csc_org_country'      => 'org_country',
			'_csc_org_postcode'     => 'org_postcode',
			'_csc_org_industry'     => 'org_industry',
			'_csc_org_igp_category' => 'org_igp',
			'_csc_org_sector'       => 'org_company_type',
			'_csc_org_phone'        => 'org_phone',
		);

		foreach ( $text_fields as $meta_key => $post_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $org_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
			}
		}

		if ( isset( $_POST['org_website'] ) ) {
			update_post_meta( $org_id, '_csc_org_website', esc_url_raw( $_POST['org_website'] ) );
		}
		if ( isset( $_POST['org_description'] ) ) {
			update_post_meta( $org_id, '_csc_org_description', sanitize_textarea_field( $_POST['org_description'] ) );
		}

		wp_send_json_success( array( 'message' => 'Company information saved successfully.' ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Upload user profile photo
	 * --------------------------------------------------------------------- */
	public function ajax_upload_user_photo() {
		check_ajax_referer( 'csc_upload_user_photo', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		if ( empty( $_FILES['photo'] ) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => 'No valid file received.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$user = wp_get_current_user();
		update_user_meta( $user->ID, '_csc_profile_photo_id', $attachment_id );

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		wp_send_json_success( array( 'url' => $url ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Upload organisation logo
	 * --------------------------------------------------------------------- */
	public function ajax_upload_org_logo() {
		check_ajax_referer( 'csc_upload_org_logo', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in.' ) );
		}

		$user   = wp_get_current_user();
		$org_id = absint( get_user_meta( $user->ID, '_csc_organisation_id', true ) );

		if ( ! $org_id ) {
			wp_send_json_error( array( 'message' => 'No organisation found.' ) );
		}

		if ( empty( $_FILES['photo'] ) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => 'No valid file received.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', $org_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		update_post_meta( $org_id, '_csc_org_logo_id', $attachment_id );

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		wp_send_json_success( array( 'url' => $url ) );
	}
}
