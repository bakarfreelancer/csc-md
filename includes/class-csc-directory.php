<?php
/**
 * Member Directory shortcode and company detail view.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Directory {

	public function register_hooks() {
		add_shortcode( 'csc_directory', array( $this, 'render' ) );
	}

	/**
	 * Returns true if a user's directory visibility is not explicitly off.
	 * Empty string (never saved) = default on; '1' = on; '0' = off.
	 *
	 * @param int    $user_id
	 * @param string $meta_key  '_csc_dir_org_visible' or '_csc_dir_profile_visible'
	 */
	private static function is_dir_visible( $user_id, $meta_key ) {
		return get_user_meta( $user_id, $meta_key, true ) !== '0';
	}

	/* -----------------------------------------------------------------------
	 * Main entry point
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

		$company_id = isset( $_GET['company'] ) ? absint( $_GET['company'] ) : 0;
		$member_id  = isset( $_GET['member'] )  ? absint( $_GET['member'] )  : 0;

		if ( $company_id ) {
			return $this->render_detail( $company_id, $user );
		}

		if ( $member_id ) {
			return $this->render_member( $member_id, $user );
		}

		return $this->render_listing( $user );
	}

	/* -----------------------------------------------------------------------
	 * Listing view — routes to Companies or Representatives tab
	 * --------------------------------------------------------------------- */
	private function render_listing( $user ) {
		$tab = sanitize_key( $_GET['tab'] ?? '' );
		if ( $tab === 'reps' ) {
			return $this->render_reps_listing( $user );
		}
		return $this->render_companies_listing( $user );
	}

	/* -----------------------------------------------------------------------
	 * All Companies tab
	 * --------------------------------------------------------------------- */
	private function render_companies_listing( $user ) {
		$search       = sanitize_text_field( $_GET['s']            ?? '' );
		$country      = sanitize_text_field( $_GET['country']      ?? '' );
		$county       = sanitize_text_field( $_GET['county']       ?? '' );
		$postcode     = sanitize_text_field( $_GET['postcode']     ?? '' );
		$industry     = sanitize_text_field( $_GET['industry']     ?? '' );
		$capability   = sanitize_text_field( $_GET['capability']   ?? '' );
		$igp          = sanitize_text_field( $_GET['igp']          ?? '' );
		$company_type = sanitize_text_field( $_GET['company_type'] ?? '' );
		$expertise_f  = sanitize_text_field( $_GET['expertise']    ?? '' );
		$paged        = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page     = 40;
		$has_filters  = $country || $county || $postcode || $industry || $capability || $igp || $company_type || $expertise_f;

		// Build WP_Query
		$args = array(
			'post_type'      => 'csc_organisation',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		// Expertise filter: find org IDs whose approved members have matching expertise
		$expertise_org_ids = null;
		if ( $expertise_f ) {
			$exp_users = get_users( array(
				'meta_query' => array(
					'relation' => 'AND',
					array( 'key' => '_csc_expertise', 'value' => $expertise_f, 'compare' => 'LIKE' ),
					array( 'key' => '_csc_status',    'value' => 'approved',   'compare' => '=' ),
				),
				'fields' => 'ID',
			) );
			$expertise_org_ids = array();
			foreach ( $exp_users as $uid ) {
				$oid = get_user_meta( $uid, '_csc_organisation_id', true );
				if ( $oid ) $expertise_org_ids[] = (int) $oid;
			}
			$expertise_org_ids = array_unique( $expertise_org_ids );
		}

		// If expertise filter returned no matches, force empty result
		if ( $expertise_org_ids !== null ) {
			if ( empty( $expertise_org_ids ) ) {
				$args['post__in'] = array( 0 ); // guaranteed no results
			} else {
				$args['post__in'] = $expertise_org_ids;
			}
		}

		$meta_query = array( 'relation' => 'AND' );

		if ( $country ) {
			$meta_query[] = array( 'key' => '_csc_org_country', 'value' => $country, 'compare' => 'LIKE' );
		}
		if ( $county ) {
			$meta_query[] = array( 'key' => '_csc_org_county', 'value' => $county, 'compare' => 'LIKE' );
		}
		if ( $postcode ) {
			$meta_query[] = array( 'key' => '_csc_org_postcode', 'value' => $postcode, 'compare' => 'LIKE' );
		}
		if ( $industry ) {
			$meta_query[] = array( 'key' => '_csc_org_industry', 'value' => $industry, 'compare' => '=' );
		}
		if ( $capability ) {
			$meta_query[] = array( 'key' => '_csc_org_capability', 'value' => $capability, 'compare' => 'LIKE' );
		}
		if ( $igp ) {
			$meta_query[] = array( 'key' => '_csc_org_igp_category', 'value' => $igp, 'compare' => '=' );
		}
		if ( $company_type ) {
			$meta_query[] = array( 'key' => '_csc_org_sector', 'value' => $company_type, 'compare' => '=' );
		}

		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		// Org visibility consent: only show orgs where at least one approved rep has org_visible != '0'
		$vis_approved = get_users( array(
			'meta_query' => array(
				array( 'key' => '_csc_status', 'value' => 'approved', 'compare' => '=' ),
			),
			'fields' => 'ID',
		) );
		$consented_org_ids = array();
		foreach ( $vis_approved as $uid ) {
			if ( self::is_dir_visible( $uid, '_csc_dir_org_visible' ) ) {
				$oid = (int) get_user_meta( $uid, '_csc_organisation_id', true );
				if ( $oid ) {
					$consented_org_ids[] = $oid;
				}
			}
		}
		$consented_org_ids = array_unique( $consented_org_ids );

		// Intersect with any existing post__in (e.g. from expertise filter)
		if ( isset( $args['post__in'] ) ) {
			$args['post__in'] = array_values( array_intersect( $args['post__in'], $consented_org_ids ) );
			if ( empty( $args['post__in'] ) ) {
				$args['post__in'] = array( 0 );
			}
		} else {
			$args['post__in'] = ! empty( $consented_org_ids ) ? $consented_org_ids : array( 0 );
		}

		$query = new WP_Query( $args );
		$orgs  = $query->posts;
		$pages = $query->max_num_pages;

		// Bulk-fetch expertise for all displayed orgs (1 query)
		$org_ids      = wp_list_pluck( $orgs, 'ID' );
		$expertise_map = array();

		if ( ! empty( $org_ids ) ) {
			$members = get_users( array(
				'meta_query' => array(
					'relation' => 'AND',
					array( 'key' => '_csc_organisation_id', 'value' => $org_ids, 'compare' => 'IN' ),
					array( 'key' => '_csc_status', 'value' => 'approved', 'compare' => '=' ),
				),
				'fields' => 'ID',
			) );

			foreach ( $members as $uid ) {
				$oid = get_user_meta( $uid, '_csc_organisation_id', true );
				$raw = get_user_meta( $uid, '_csc_expertise', true );
				if ( $raw && $oid ) {
					foreach ( array_map( 'trim', explode( ',', $raw ) ) as $tag ) {
						if ( $tag ) {
							$expertise_map[ $oid ][ $tag ] = true;
						}
					}
				}
			}
		}

		// Pagination base URL
		$dir_url = Csc_Dashboard::portal_url( 'member-directory' );

		// Filter values for dropdowns
		$industries    = Csc_Organisations::get_primary_industries();
		$company_types = Csc_Organisations::get_company_types();
		$igp_cats      = Csc_Organisations::get_igp_categories();

		// Distinct postcodes from all published organisations (submitted by members)
		global $wpdb;
		$postcodes = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_csc_org_postcode'
			   AND pm.meta_value != ''
			   AND p.post_type = 'csc_organisation'
			   AND p.post_status = 'publish'
			 ORDER BY pm.meta_value ASC"
		);

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'directory', $user ); ?>

			<main class="csc-portal-main">

				<!-- Tabs -->
				<div class="csc-dir-tabs-row">
					<a href="<?php echo esc_url( $dir_url ); ?>" class="csc-dir-tab is-active">All Companies</a>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'reps', $dir_url ) ); ?>" class="csc-dir-tab">Representatives</a>
				</div>

				<!-- Search & Filter -->
				<div class="csc-dir-section">
					<h2 class="csc-dir-section-title">Search &amp; Filter</h2>
					<p class="csc-dir-section-sub">Find organisations by name, location, sector, or expertise</p>

					<form method="GET" action="<?php echo esc_url( $dir_url ); ?>" class="csc-dir-form" id="csc-dir-form">
						<div class="csc-dir-search-row">
							<div class="csc-dir-search-wrap">
								<svg class="csc-dir-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><line x1="13" y1="13" x2="18" y2="18"/></svg>
								<input type="search" name="s" class="csc-dir-search-input" autocomplete="off"
									placeholder="Search by organisation, expertise, or keyword…"
									value="<?php echo esc_attr( $search ); ?>">
							</div>
							<button type="button" class="csc-dir-filter-btn <?php echo $has_filters ? 'is-active' : ''; ?>" id="csc-filter-toggle" aria-expanded="<?php echo $has_filters ? 'true' : 'false'; ?>" aria-controls="csc-filter-panel">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="5" x2="17" y2="5"/><line x1="5" y1="10" x2="15" y2="10"/><line x1="7" y1="15" x2="13" y2="15"/></svg>
								All Filters
							</button>
						</div>

						<!-- Pass filter data to JS -->
						<script>
						window.cscDirData = {
							countries:    <?php echo wp_json_encode( csc_get_countries() ); ?>,
							ukCounties:   <?php echo wp_json_encode( csc_get_uk_counties_flat() ); ?>,
							industries:   <?php echo wp_json_encode( $industries ); ?>,
							igpCats:      <?php echo wp_json_encode( $igp_cats ); ?>,
							companyTypes: <?php echo wp_json_encode( $company_types ); ?>,
							postcodes:    <?php echo wp_json_encode( array_values( $postcodes ) ); ?>
						};
						</script>

						<!-- Filter panel -->
						<div class="csc-dir-filter-panel" id="csc-filter-panel" <?php echo $has_filters ? '' : 'hidden'; ?>>
							<div class="csc-dir-filter-grid">

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">Country</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-country-vis"
										       placeholder="All" autocomplete="off"
										       value="<?php echo esc_attr( $country ); ?>">
										<input type="hidden" name="country" id="df-country-val"
										       value="<?php echo esc_attr( $country ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-country-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">County</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-county-vis"
										       placeholder="All" autocomplete="off"
										       value="<?php echo esc_attr( $county ); ?>">
										<input type="hidden" name="county" id="df-county-val"
										       value="<?php echo esc_attr( $county ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-county-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">Postcode</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-postcode-vis"
										       placeholder="e.g. SA1" autocomplete="off"
										       value="<?php echo esc_attr( $postcode ); ?>">
										<input type="hidden" name="postcode" id="df-postcode-val"
										       value="<?php echo esc_attr( $postcode ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-postcode-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">Primary Industry</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-industry-vis"
										       placeholder="All" autocomplete="off"
										       value="<?php echo esc_attr( $industry ); ?>">
										<input type="hidden" name="industry" id="df-industry-val"
										       value="<?php echo esc_attr( $industry ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-industry-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label" for="df-capability">Organisational Capability</label>
									<input type="text" id="df-capability" name="capability" class="csc-dir-filter-input" autocomplete="off"
									       placeholder="All" value="<?php echo esc_attr( $capability ); ?>">
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">
										<abbr title="Industrial Growth Plan Category" style="text-decoration:underline dotted;cursor:help;">IGP Category</abbr>
									</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-igp-vis"
										       placeholder="All" autocomplete="off"
										       value="<?php echo esc_attr( $igp ); ?>">
										<input type="hidden" name="igp" id="df-igp-val"
										       value="<?php echo esc_attr( $igp ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-igp-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label">Company Type</label>
									<div class="csc-typeahead-wrap csc-dir-ta-wrap">
										<input type="text" class="csc-dir-filter-input" id="df-type-vis"
										       placeholder="All" autocomplete="off"
										       value="<?php echo esc_attr( $company_type ); ?>">
										<input type="hidden" name="company_type" id="df-type-val"
										       value="<?php echo esc_attr( $company_type ); ?>">
										<ul class="csc-typeahead-dropdown" id="df-type-drop" role="listbox" style="display:none;"></ul>
									</div>
								</div>

								<div class="csc-dir-filter-field">
									<label class="csc-dir-filter-label" for="df-expertise">Expertise</label>
									<input type="text" id="df-expertise" name="expertise" class="csc-dir-filter-input" autocomplete="off"
									       placeholder="e.g. Wind, Marine" value="<?php echo esc_attr( $expertise_f ); ?>">
								</div>

							</div>
							<div class="csc-dir-filter-actions">
								<button type="button" class="csc-dir-clear-btn" id="csc-clear-filters">
									<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="2" y1="2" x2="14" y2="14"/><line x1="14" y1="2" x2="2" y2="14"/></svg>
									Clear Filters
								</button>
								<button type="submit" class="csc-dir-apply-btn">Apply Filters</button>
							</div>
						</div>
					</form>
				</div>

				<!-- Table -->
				<div class="csc-dir-table-wrap">
					<?php if ( empty( $orgs ) ) : ?>
					<div class="csc-dir-empty">
						<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="24" cy="24" r="20"/><path d="M16 24h16M24 16v16"/></svg>
						<p>No organisations found. Try adjusting your search or filters.</p>
					</div>
					<?php else : ?>
					<table class="csc-dir-table">
						<thead>
							<tr>
								<th>
									<a href="<?php echo esc_url( add_query_arg( array( 'orderby' => 'title', 'order' => 'asc' ), $dir_url ) ); ?>" class="csc-dir-th-link">
										Organisation <span class="csc-dir-sort-icon" aria-hidden="true">↕</span>
									</a>
								</th>
								<th>
									<span class="csc-dir-th-link">Location <span class="csc-dir-sort-icon" aria-hidden="true">↕</span></span>
								</th>
								<th>
									<span class="csc-dir-th-link">Sector <span class="csc-dir-sort-icon" aria-hidden="true">↕</span></span>
								</th>
								<th>Key Expertise</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $orgs as $org ) :
								$location = get_post_meta( $org->ID, '_csc_org_location', true );
								$country  = get_post_meta( $org->ID, '_csc_org_country', true );
								$industry = get_post_meta( $org->ID, '_csc_org_industry', true );
								$loc_str  = implode( ', ', array_filter( array( $location, $country ) ) );
								$expertise = array_keys( $expertise_map[ $org->ID ] ?? array() );
								$detail_url = add_query_arg( 'company', $org->ID, $dir_url );
							?>
							<tr class="csc-dir-row" data-href="<?php echo esc_url( $detail_url ); ?>">
								<td class="csc-dir-td-name">
									<a href="<?php echo esc_url( $detail_url ); ?>" class="csc-dir-org-link"><?php echo esc_html( $org->post_title ); ?></a>
								</td>
								<td class="csc-dir-td-location"><?php echo esc_html( $loc_str ?: '—' ); ?></td>
								<td class="csc-dir-td-sector">
									<?php if ( $industry ) : ?>
									<span class="csc-dir-tag csc-dir-tag--sector"><?php echo esc_html( $industry ); ?></span>
									<?php else : ?>
									<span class="csc-dir-td-empty">—</span>
									<?php endif; ?>
								</td>
								<td class="csc-dir-td-expertise">
									<?php
									$shown = array_slice( $expertise, 0, 3 );
									$extra = count( $expertise ) - count( $shown );
									foreach ( $shown as $i => $tag ) :
										echo '<span class="csc-dir-tag csc-dir-tag--exp csc-dir-tag--' . ( $i % 3 ) . '">' . esc_html( $tag ) . '</span>';
									endforeach;
									if ( $extra > 0 ) :
										echo '<span class="csc-dir-tag csc-dir-tag--more">+' . $extra . '</span>';
									endif;
									?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $pages > 1 ) : ?>
					<div class="csc-dir-pagination">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $dir_url ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="10 4 6 8 10 12"/></svg>',
							'next_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 4 10 8 6 12"/></svg>',
							'type'      => 'list',
						) );
						?>
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
	 * Company detail view
	 * --------------------------------------------------------------------- */
	private function render_detail( $org_id, $user ) {
		$org = get_post( $org_id );

		if ( ! $org || $org->post_type !== 'csc_organisation' || $org->post_status !== 'publish' ) {
			return $this->render_not_found( $user, 'company' );
		}

		// Check that at least one approved rep has consented to org visibility
		$org_rep_check = get_users( array(
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => '_csc_organisation_id', 'value' => $org_id,    'compare' => '=' ),
				array( 'key' => '_csc_status',          'value' => 'approved', 'compare' => '=' ),
			),
			'fields' => 'ID',
			'number' => -1,
		) );
		$org_consented = false;
		foreach ( $org_rep_check as $uid ) {
			if ( self::is_dir_visible( $uid, '_csc_dir_org_visible' ) ) {
				$org_consented = true;
				break;
			}
		}
		if ( ! $org_consented ) {
			return $this->render_not_found( $user, 'company' );
		}

		$location    = get_post_meta( $org_id, '_csc_org_location',     true );
		$country     = get_post_meta( $org_id, '_csc_org_country',      true );
		$county      = get_post_meta( $org_id, '_csc_org_county',       true );
		$postcode    = get_post_meta( $org_id, '_csc_org_postcode',     true );
		$industry    = get_post_meta( $org_id, '_csc_org_industry',     true );
		$sector      = get_post_meta( $org_id, '_csc_org_sector',       true );
		$igp         = get_post_meta( $org_id, '_csc_org_igp_category', true );
		$description = get_post_meta( $org_id, '_csc_org_description',  true );
		$website     = get_post_meta( $org_id, '_csc_org_website',      true );
		$org_phone   = get_post_meta( $org_id, '_csc_org_phone',        true );
		$logo_id     = get_post_meta( $org_id, '_csc_org_logo_id',      true );

		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		// Initials fallback
		$words    = explode( ' ', $org->post_title );
		$initials = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : '' ) );

		// Location string
		$loc_parts = array_filter( array( $location, $county, $country ) );
		$loc_str   = implode( ', ', $loc_parts );

		// Get approved members who have consented to personal profile visibility
		$all_org_members = get_users( array(
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => '_csc_organisation_id', 'value' => $org_id, 'compare' => '=' ),
				array( 'key' => '_csc_status',          'value' => 'approved', 'compare' => '=' ),
			),
		) );
		$members = array_filter( $all_org_members, function( $m ) {
			return self::is_dir_visible( $m->ID, '_csc_dir_profile_visible' );
		} );

		// Collect org-level expertise from all members
		$all_expertise = array();
		foreach ( $members as $m ) {
			$raw = get_user_meta( $m->ID, '_csc_expertise', true );
			if ( $raw ) {
				foreach ( array_map( 'trim', explode( ',', $raw ) ) as $tag ) {
					if ( $tag ) $all_expertise[ $tag ] = true;
				}
			}
		}
		$org_expertise = array_keys( $all_expertise );

		$dir_url    = Csc_Dashboard::portal_url( 'member-directory' );
		$member_cnt = count( $members );

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'directory', $user ); ?>

			<main class="csc-portal-main csc-portal-main--company">

				<!-- Back navigation -->
				<nav class="csc-co-back" aria-label="Breadcrumb">
					<a href="<?php echo esc_url( $dir_url ); ?>" class="csc-co-back__link">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 4 7 10 13 16"/></svg>
						Member Directory
					</a>
				</nav>

				<!-- Gradient banner -->
				<div class="csc-co-banner">
					<div class="csc-co-banner__left">
						<?php if ( $industry ) : ?>
						<div class="csc-co-banner__row">
							<span class="csc-co-banner__label">Supply Chain Area:</span>
							<span class="csc-co-banner-pill"><?php echo esc_html( $industry ); ?></span>
						</div>
						<?php endif; ?>
						<?php if ( $igp ) : ?>
						<div class="csc-co-banner__row">
							<span class="csc-co-banner__label">Offshore Wind Cluster:</span>
							<span class="csc-co-banner-pill"><?php echo esc_html( $igp ); ?></span>
						</div>
						<?php elseif ( $sector ) : ?>
						<div class="csc-co-banner__row">
							<span class="csc-co-banner__label">Company Type:</span>
							<span class="csc-co-banner-pill"><?php echo esc_html( $sector ); ?></span>
						</div>
						<?php endif; ?>
					</div>
					<div class="csc-co-banner__right">
						<?php if ( ! empty( $org_expertise ) ) : ?>
						<div class="csc-co-banner__row">
							<?php foreach ( array_slice( $org_expertise, 0, 4 ) as $tag ) : ?>
							<span class="csc-co-banner-pill"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
							<?php if ( count( $org_expertise ) > 4 ) : ?>
							<span class="csc-co-banner-pill">+<?php echo count( $org_expertise ) - 4; ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="csc-co-banner__row csc-co-banner__row--right">
							<span class="csc-co-banner__label">Total Member</span>
							<span class="csc-co-banner-pill csc-co-banner-pill--count"><?php echo $member_cnt; ?></span>
						</div>
					</div>
				</div>

				<!-- Company identity (logo overlaps banner) -->
				<div class="csc-co-identity">
					<div class="csc-co-logo">
						<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $org->post_title ); ?>" class="csc-co-logo__img">
						<?php else : ?>
						<span class="csc-co-logo__initials"><?php echo esc_html( $initials ); ?></span>
						<?php endif; ?>
					</div>
					<h1 class="csc-co-name"><?php echo esc_html( $org->post_title ); ?></h1>
					<?php if ( $description ) : ?>
					<p class="csc-co-tagline"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
					<?php if ( $loc_str || $website || $org_phone ) : ?>
					<div class="csc-co-meta">
						<?php if ( $loc_str ) : ?>
						<span class="csc-co-meta__item">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2a6 6 0 0 1 6 6c0 4-6 10-6 10S4 12 4 8a6 6 0 0 1 6-6z"/><circle cx="10" cy="8" r="2"/></svg>
							<?php echo esc_html( $loc_str ); ?>
						</span>
						<?php endif; ?>
						<?php if ( $website ) : ?>
						<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer" class="csc-co-meta__item csc-co-meta__item--link">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M2 10h16M10 2a14 14 0 0 1 0 16M10 2a14 14 0 0 0 0 16"/></svg>
							<?php echo esc_html( preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ) ); ?>
						</a>
						<?php endif; ?>
						<?php if ( $org_phone ) : ?>
						<a href="tel:<?php echo esc_attr( $org_phone ); ?>" class="csc-co-meta__item csc-co-meta__item--link">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.3 1a2 2 0 0 1 2 1.6l.5 2.5a2 2 0 0 1-.7 2L6 8.2a11 11 0 0 0 5.8 5.8l1.1-1.1a2 2 0 0 1 2-.7l2.5.5a2 2 0 0 1 1.6 2v2.1C19 18.5 18 19.5 16.7 19.5 8.3 19.5.5 11.7.5 3.3A2.3 2.3 0 0 1 3.2 1z"/></svg>
							<?php echo esc_html( $org_phone ); ?>
						</a>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

				<!-- Representatives -->
				<?php if ( ! empty( $members ) ) : ?>
				<div class="csc-reps-grid">
					<?php foreach ( $members as $m ) :
						$fname     = get_user_meta( $m->ID, 'first_name', true ) ?: $m->display_name;
						$lname     = get_user_meta( $m->ID, 'last_name',  true );
						$full_name = trim( "$fname $lname" );
						$job_title = get_user_meta( $m->ID, '_csc_job_title',        true );
						$phone     = get_user_meta( $m->ID, '_csc_phone',            true );
						$photo_id  = get_user_meta( $m->ID, '_csc_profile_photo_id', true );
						$photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
						$exp_raw   = get_user_meta( $m->ID, '_csc_expertise', true );
						$exp_tags  = $exp_raw ? array_filter( array_map( 'trim', explode( ',', $exp_raw ) ) ) : array();
						$av_words  = explode( ' ', $full_name );
						$av_init   = strtoupper( substr( $av_words[0], 0, 1 ) . ( isset( $av_words[1] ) ? substr( $av_words[1], 0, 1 ) : '' ) );
						$member_url = add_query_arg( 'member', $m->ID, $dir_url );
					?>
					<a href="<?php echo esc_url( $member_url ); ?>" class="csc-rep-card" aria-label="View profile of <?php echo esc_attr( $full_name ); ?>">
						<div class="csc-rep-card__top">
							<div class="csc-rep-avatar">
								<?php if ( $photo_url ) : ?>
								<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" class="csc-rep-avatar__img">
								<?php else : ?>
								<?php echo esc_html( $av_init ); ?>
								<?php endif; ?>
							</div>
							<div class="csc-rep-info">
								<div class="csc-rep-name"><?php echo esc_html( $full_name ); ?></div>
								<?php if ( $job_title ) : ?>
								<div class="csc-rep-title"><?php echo esc_html( $job_title ); ?></div>
								<?php endif; ?>
							</div>
						</div>
						<div class="csc-rep-contacts">
							<span class="csc-rep-contact-item">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h16c.55 0 1 .45 1 1v10c0 .55-.45 1-1 1H2c-.55 0-1-.45-1-1V5c0-.55.45-1 1-1z"/><polyline points="1,4 10,11 19,4"/></svg>
								<?php echo esc_html( $m->user_email ); ?>
							</span>
							<?php if ( $phone ) : ?>
							<span class="csc-rep-contact-item">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.3 1a2 2 0 0 1 2 1.6l.5 2.5a2 2 0 0 1-.7 2L6 8.2a11 11 0 0 0 5.8 5.8l1.1-1.1a2 2 0 0 1 2-.7l2.5.5a2 2 0 0 1 1.6 2v2.1C19 18.5 18 19.5 16.7 19.5 8.3 19.5.5 11.7.5 3.3A2.3 2.3 0 0 1 3.2 1z"/></svg>
								<?php echo esc_html( $phone ); ?>
							</span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $exp_tags ) ) : ?>
						<div class="csc-rep-tags">
							<?php foreach ( $exp_tags as $tag ) : ?>
							<span class="csc-rep-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Representatives tab
	 * --------------------------------------------------------------------- */
	private function render_reps_listing( $user ) {
		$search    = sanitize_text_field( $_GET['s']       ?? '' );
		$filter_co = sanitize_text_field( $_GET['company_filter'] ?? '' );
		$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page  = 40;

		$dir_url  = Csc_Dashboard::portal_url( 'member-directory' );
		$reps_url = add_query_arg( 'tab', 'reps', $dir_url );

		// Build user query args
		$user_args = array(
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => '_csc_status', 'value' => 'approved', 'compare' => '=' ),
			),
			'orderby' => 'display_name',
			'order'   => 'ASC',
		);

		if ( $search ) {
			$user_args['search']         = '*' . $search . '*';
			$user_args['search_columns'] = array( 'display_name', 'user_email' );
		}

		if ( $filter_co ) {
			// Try exact title first (typeahead selection), then fall back to search
			$exact = get_posts( array(
				'post_type'      => 'csc_organisation',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'title'          => $filter_co,
				'fields'         => 'ids',
			) );
			if ( ! empty( $exact ) ) {
				$orgs = $exact;
			} else {
				$orgs = get_posts( array(
					'post_type'      => 'csc_organisation',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					's'              => $filter_co,
					'fields'         => 'ids',
				) );
			}
			if ( ! empty( $orgs ) ) {
				$user_args['meta_query'][] = array(
					'key'     => '_csc_organisation_id',
					'value'   => $orgs,
					'compare' => 'IN',
				);
			} else {
				$user_args['include'] = array( 0 );
			}
		}

		$all_members = get_users( $user_args );

		// Filter by personal profile visibility consent
		$all_members = array_values( array_filter( $all_members, function( $m ) {
			return self::is_dir_visible( $m->ID, '_csc_dir_profile_visible' );
		} ) );

		$total   = count( $all_members );
		$pages   = (int) ceil( $total / $per_page );
		$members = array_slice( $all_members, ( $paged - 1 ) * $per_page, $per_page );

		// All published org names for typeahead datalist
		$all_orgs = get_posts( array(
			'post_type'      => 'csc_organisation',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'directory', $user ); ?>

			<main class="csc-portal-main">

				<!-- Tabs -->
				<div class="csc-dir-tabs-row">
					<a href="<?php echo esc_url( $dir_url ); ?>" class="csc-dir-tab">All Companies</a>
					<a href="<?php echo esc_url( $reps_url ); ?>" class="csc-dir-tab is-active">Representatives</a>
				</div>

				<!-- Search -->
				<div class="csc-dir-section">
					<h2 class="csc-dir-section-title">Search &amp; Filter</h2>
					<p class="csc-dir-section-sub">Find representatives by name, organisation, or expertise</p>
					<form method="GET" action="<?php echo esc_url( $reps_url ); ?>" class="csc-dir-form">
						<input type="hidden" name="tab" value="reps">
						<div class="csc-dir-search-row">
							<div class="csc-dir-search-wrap">
								<svg class="csc-dir-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><line x1="13" y1="13" x2="18" y2="18"/></svg>
								<input type="search" name="s" class="csc-dir-search-input" autocomplete="off"
									placeholder="Search by organisation, name, or keyword…"
									value="<?php echo esc_attr( $search ); ?>">
							</div>
							<div class="csc-dir-search-wrap csc-dir-typeahead-wrap" style="max-width:260px;">
								<svg class="csc-dir-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h14M3 10h10M3 14h6"/></svg>
								<input type="text" name="company_filter" id="csc-reps-company-filter"
									class="csc-dir-search-input" autocomplete="off"
									placeholder="Filter by company…"
									value="<?php echo esc_attr( $filter_co ); ?>"
									list="csc-reps-companies-list">
								<datalist id="csc-reps-companies-list">
									<?php foreach ( $all_orgs as $org_id ) : ?>
									<option value="<?php echo esc_attr( get_the_title( $org_id ) ); ?>">
									<?php endforeach; ?>
								</datalist>
							</div>
							<button type="submit" class="csc-dir-apply-btn">Search</button>
							<?php if ( $search || $filter_co ) : ?>
							<a href="<?php echo esc_url( $reps_url ); ?>" class="csc-dir-clear-btn">
								<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="2" y1="2" x2="14" y2="14"/><line x1="14" y1="2" x2="2" y2="14"/></svg>
								Clear
							</a>
							<?php endif; ?>
						</div>
					</form>
				</div>

				<!-- Table -->
				<div class="csc-dir-table-wrap">
					<?php if ( empty( $members ) ) : ?>
					<div class="csc-dir-empty">
						<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="24" cy="24" r="20"/><path d="M16 24h16M24 16v16"/></svg>
						<p>No representatives found. Try adjusting your search.</p>
					</div>
					<?php else : ?>
					<table class="csc-dir-table">
						<thead>
							<tr>
								<th>Representative</th>
								<th>Organisation</th>
								<th>Email</th>
								<th>Job Title</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $members as $m ) :
								$fname     = get_user_meta( $m->ID, 'first_name', true ) ?: $m->display_name;
								$lname     = get_user_meta( $m->ID, 'last_name',  true );
								$full_name = trim( "$fname $lname" );
								$job_title = get_user_meta( $m->ID, '_csc_job_title', true );
								$photo_id  = get_user_meta( $m->ID, '_csc_profile_photo_id', true );
								$photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
								$org_id    = get_user_meta( $m->ID, '_csc_organisation_id', true );
								$org_name  = $org_id ? get_the_title( $org_id ) : '—';
								$av_words  = explode( ' ', $full_name );
								$av_init   = strtoupper( substr( $av_words[0], 0, 1 ) . ( isset( $av_words[1] ) ? substr( $av_words[1], 0, 1 ) : '' ) );
								$member_url = add_query_arg( 'member', $m->ID, $dir_url );
							?>
							<tr class="csc-dir-row" data-href="<?php echo esc_url( $member_url ); ?>">
								<td class="csc-dir-td-name">
									<a href="<?php echo esc_url( $member_url ); ?>" class="csc-dir-rep-link">
										<span class="csc-dir-rep-avatar">
											<?php if ( $photo_url ) : ?>
											<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>">
											<?php else : ?>
											<?php echo esc_html( $av_init ); ?>
											<?php endif; ?>
										</span>
										<?php echo esc_html( $full_name ); ?>
									</a>
								</td>
								<td class="csc-dir-td-location">
									<?php if ( $org_id ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'company', $org_id, $dir_url ) ); ?>" class="csc-dir-org-link"><?php echo esc_html( $org_name ); ?></a>
									<?php else : ?>—<?php endif; ?>
								</td>
								<td class="csc-dir-td-location">
									<a href="mailto:<?php echo esc_attr( $m->user_email ); ?>" class="csc-dir-td-email"><?php echo esc_html( $m->user_email ); ?></a>
								</td>
								<td class="csc-dir-td-location"><?php echo esc_html( $job_title ?: '—' ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $pages > 1 ) : ?>
					<div class="csc-dir-pagination">
						<?php
						$page_links = paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $reps_url ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="10 4 6 8 10 12"/></svg>',
							'next_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 4 10 8 6 12"/></svg>',
							'type'      => 'list',
						) );
						echo $page_links;
						?>
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
	 * Single member profile view
	 * --------------------------------------------------------------------- */
	private function render_member( $member_id, $current_user ) {
		$m = get_userdata( $member_id );

		if ( ! $m ) {
			return $this->render_not_found( $current_user, 'member' );
		}

		// Only show approved members who have consented to profile visibility
		$status = get_user_meta( $member_id, '_csc_status', true );
		if ( $status !== 'approved' || ! self::is_dir_visible( $member_id, '_csc_dir_profile_visible' ) ) {
			return $this->render_not_found( $current_user, 'member' );
		}

		$fname     = get_user_meta( $member_id, 'first_name',            true ) ?: $m->display_name;
		$lname     = get_user_meta( $member_id, 'last_name',             true );
		$full_name = trim( "$fname $lname" );
		$job_title = get_user_meta( $member_id, '_csc_job_title',        true );
		$phone     = get_user_meta( $member_id, '_csc_phone',            true );
		$expertise = get_user_meta( $member_id, '_csc_expertise',        true );
		$photo_id  = get_user_meta( $member_id, '_csc_profile_photo_id', true );
		$photo_url = $photo_id ? wp_get_attachment_image_url( $photo_id, 'medium' ) : '';
		$org_id    = get_user_meta( $member_id, '_csc_organisation_id',  true );
		$org_name  = $org_id ? get_the_title( $org_id ) : '';
		$exp_tags  = $expertise ? array_filter( array_map( 'trim', explode( ',', $expertise ) ) ) : array();

		$av_words = explode( ' ', $full_name );
		$av_init  = strtoupper( substr( $av_words[0], 0, 1 ) . ( isset( $av_words[1] ) ? substr( $av_words[1], 0, 1 ) : '' ) );

		$dir_url    = Csc_Dashboard::portal_url( 'member-directory' );
		$reps_url   = add_query_arg( 'tab', 'reps', $dir_url );
		$co_url     = $org_id ? add_query_arg( 'company', $org_id, $dir_url ) : '';

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo Csc_Dashboard::render_sidebar( 'directory', $current_user ); ?>

			<main class="csc-portal-main csc-portal-main--company">

				<!-- Back navigation -->
				<nav class="csc-co-back" aria-label="Breadcrumb">
					<a href="<?php echo esc_url( $reps_url ); ?>" class="csc-co-back__link">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 4 7 10 13 16"/></svg>
						Representatives
					</a>
				</nav>

				<!-- Profile card -->
				<div class="csc-member-profile-card">

					<!-- Avatar -->
					<div class="csc-member-profile-avatar">
						<?php if ( $photo_url ) : ?>
						<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" class="csc-member-profile-avatar__img">
						<?php else : ?>
						<span class="csc-member-profile-avatar__initials"><?php echo esc_html( $av_init ); ?></span>
						<?php endif; ?>
					</div>

					<!-- Name & title -->
					<h1 class="csc-member-profile-name"><?php echo esc_html( $full_name ); ?></h1>
					<?php if ( $job_title ) : ?>
					<p class="csc-member-profile-jobtitle"><?php echo esc_html( $job_title ); ?></p>
					<?php endif; ?>

					<!-- Organisation link -->
					<?php if ( $org_name ) : ?>
					<p class="csc-member-profile-org">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="16" height="12" rx="1"/><path d="M6 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
						<?php if ( $co_url ) : ?>
						<a href="<?php echo esc_url( $co_url ); ?>" class="csc-member-profile-org__link"><?php echo esc_html( $org_name ); ?></a>
						<?php else : ?>
						<?php echo esc_html( $org_name ); ?>
						<?php endif; ?>
					</p>
					<?php endif; ?>

					<!-- Contact -->
					<div class="csc-member-profile-contacts">
						<a href="mailto:<?php echo esc_attr( $m->user_email ); ?>" class="csc-member-profile-contact">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h16c.55 0 1 .45 1 1v10c0 .55-.45 1-1 1H2c-.55 0-1-.45-1-1V5c0-.55.45-1 1-1z"/><polyline points="1,4 10,11 19,4"/></svg>
							<?php echo esc_html( $m->user_email ); ?>
						</a>
						<?php if ( $phone ) : ?>
						<a href="tel:<?php echo esc_attr( $phone ); ?>" class="csc-member-profile-contact">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.3 1a2 2 0 0 1 2 1.6l.5 2.5a2 2 0 0 1-.7 2L6 8.2a11 11 0 0 0 5.8 5.8l1.1-1.1a2 2 0 0 1 2-.7l2.5.5a2 2 0 0 1 1.6 2v2.1C19 18.5 18 19.5 16.7 19.5 8.3 19.5.5 11.7.5 3.3A2.3 2.3 0 0 1 3.2 1z"/></svg>
							<?php echo esc_html( $phone ); ?>
						</a>
						<?php endif; ?>
					</div>

					<!-- Expertise -->
					<?php if ( ! empty( $exp_tags ) ) : ?>
					<div class="csc-member-profile-expertise">
						<h2 class="csc-member-profile-section-title">Expertise</h2>
						<div class="csc-member-profile-tags">
							<?php foreach ( $exp_tags as $tag ) : ?>
							<span class="csc-rep-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<!-- Link back to company detail -->
					<?php if ( $co_url ) : ?>
					<a href="<?php echo esc_url( $co_url ); ?>" class="csc-member-profile-co-link">
						View <?php echo esc_html( $org_name ); ?> profile
						<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 4 10 8 6 12"/></svg>
					</a>
					<?php endif; ?>

				</div>

			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Not-found card — full portal layout with centered message
	 * $type: 'company' | 'member'
	 * --------------------------------------------------------------------- */
	private function render_not_found( $user, $type = 'company' ) {
		$dir_url  = Csc_Dashboard::portal_url( 'member-directory' );
		$reps_url = add_query_arg( 'tab', 'reps', $dir_url );

		if ( $type === 'member' ) {
			$icon    = '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="32" cy="22" r="12"/><path d="M8 56c0-13.3 10.7-24 24-24s24 10.7 24 24"/><line x1="44" y1="44" x2="56" y2="56"/><line x1="56" y1="44" x2="44" y2="56"/></svg>';
			$title   = 'Representative not found';
			$message = 'This profile is either unavailable or has been set to private by the member.';
			$back_url   = $reps_url;
			$back_label = 'Back to Representatives';
		} else {
			$icon    = '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="20" width="48" height="36" rx="4"/><path d="M20 20V16a12 12 0 0 1 24 0v4"/><line x1="40" y1="40" x2="52" y2="52"/><line x1="52" y1="40" x2="40" y2="52"/></svg>';
			$title   = 'Organisation not found';
			$message = 'This organisation is either unavailable or has been set to private.';
			$back_url   = $dir_url;
			$back_label = 'Back to Member Directory';
		}

		ob_start();
		?>
		<div class="csc-member-portal">
			<?php echo Csc_Dashboard::render_sidebar( 'directory', $user ); ?>
			<main class="csc-portal-main">
				<div class="csc-not-found-wrap">
					<div class="csc-not-found-card">
						<div class="csc-not-found-icon"><?php echo $icon; ?></div>
						<h2 class="csc-not-found-title"><?php echo esc_html( $title ); ?></h2>
						<p class="csc-not-found-message"><?php echo esc_html( $message ); ?></p>
						<a href="<?php echo esc_url( $back_url ); ?>" class="csc-btn-primary csc-not-found-btn">
							<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 4 7 10 13 16"/></svg>
							<?php echo esc_html( $back_label ); ?>
						</a>
					</div>
				</div>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}
}
