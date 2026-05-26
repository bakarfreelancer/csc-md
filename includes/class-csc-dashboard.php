<?php
/**
 * CSC Member Portal — Dashboard and shared portal helpers.
 *
 * Provides [csc_dashboard] shortcode and the sidebar nav used across all
 * member portal pages.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Dashboard {

	public function register_hooks( $loader ) {
		$loader->add_action( 'init', $this, 'register_shortcodes' );
		$loader->add_action( 'template_redirect', $this, 'protect_portal_pages' );
	}

	public function register_shortcodes() {
		add_shortcode( 'csc_dashboard', array( $this, 'render_dashboard' ) );
	}

	/* -----------------------------------------------------------------------
	 * Access guard — redirect unapproved/logged-out users away from portal pages
	 * --------------------------------------------------------------------- */
	public function protect_portal_pages() {
		$portal_slugs = array(
			'member-dashboard',
			'member-directory',
			'member-forum',
			'member-newsletters',
			'update-account',
			'member-settings',
			'terms-of-use',
		);

		if ( ! is_page() ) {
			return;
		}

		global $post;
		if ( ! $post || ! in_array( $post->post_name, $portal_slugs, true ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}

		$status = get_user_meta( get_current_user_id(), '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}
	}

	/* -----------------------------------------------------------------------
	 * [csc_dashboard] shortcode
	 * --------------------------------------------------------------------- */
	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}

		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, '_csc_status', true );

		if ( $status !== 'approved' ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}

		$cards = array(
			array(
				'title' => 'Member Directory',
				'desc'  => 'Access and browse members of the Celtic Sea Cluster, and discover organisations across the network.',
				'url'   => self::portal_url( 'member-directory' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
			),
			array(
				'title' => 'Forum',
				'desc'  => 'Connect with other members, ask questions, and explore the latest discussions within the industry.',
				'url'   => self::portal_url( 'member-forum' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
			),
			array(
				'title' => 'Newsletters &amp; Resources',
				'desc'  => 'Stay informed with the latest updates, newsletters and resources. Your media resources are waiting for you.',
				'url'   => self::portal_url( 'member-newsletters' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
			),
			array(
				'title' => 'Update Account',
				'desc'  => 'Keep your profile current, update your contact details, and business information.',
				'url'   => self::portal_url( 'update-account' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			),
			array(
				'title' => 'Settings',
				'desc'  => 'Manage your password, login details, and account preferences.',
				'url'   => self::portal_url( 'member-settings' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
			),
			array(
				'title' => 'Terms of Use',
				'desc'  => 'Stay in the loop. Review the policies and guidelines that guide our community.',
				'url'   => self::portal_url( 'terms-of-use' ),
				'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
			),
		);

		ob_start();
		?>
		<div class="csc-member-portal">

			<?php echo self::render_sidebar( 'dashboard', $user ); ?>

			<main class="csc-portal-main">
				<div class="csc-dashboard-header">
					<h1 class="csc-welcome-title">Welcome, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?></h1>
					<p class="csc-welcome-sub">Your hub for the Celtic Sea Cluster member network. Please choose a section below to get started.</p>
				</div>

				<div class="csc-cards-grid">
					<?php foreach ( $cards as $card ) : ?>
					<a href="<?php echo esc_url( $card['url'] ); ?>" class="csc-dash-card">
						<div class="csc-dash-card__top">
							<div class="csc-dash-card__icon"><?php echo $card['icon']; ?></div>
							<div class="csc-dash-card__arrow">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
							</div>
						</div>
						<h3 class="csc-dash-card__title"><?php echo $card['title']; ?></h3>
						<p class="csc-dash-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
					</a>
					<?php endforeach; ?>
				</div>
			</main>

		</div><!-- /.csc-member-portal -->
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Shared sidebar — used on all portal pages
	 *
	 * @param string   $active  Current section slug (dashboard, directory, forum, newsletters, account, settings, terms)
	 * @param WP_User  $user    Current user object
	 * --------------------------------------------------------------------- */
	public static function render_sidebar( $active, $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		$initials = strtoupper(
			substr( $user->first_name, 0, 1 ) .
			substr( $user->last_name, 0, 1 )
		);
		if ( ! $initials ) {
			$initials = strtoupper( substr( $user->display_name, 0, 2 ) );
		}

		$nav = array(
			'dashboard'   => array( 'label' => 'Dashboard',        'url' => self::portal_url( 'member-dashboard' ),  'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>' ),
			'directory'   => array( 'label' => 'Member Directory', 'url' => self::portal_url( 'member-directory' ),  'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' ),
			'forum'       => array( 'label' => 'Forum',            'url' => self::portal_url( 'member-forum' ),      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' ),
			'newsletters' => array( 'label' => 'Newsletters',      'url' => self::portal_url( 'member-newsletters' ),'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' ),
			'account'     => array( 'label' => 'Update Account',   'url' => self::portal_url( 'update-account' ),   'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' ),
			'settings'    => array( 'label' => 'Settings',         'url' => self::portal_url( 'member-settings' ),  'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>' ),
			'terms'       => array( 'label' => 'Terms of Use',     'url' => self::portal_url( 'terms-of-use' ),     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' ),
		);

		// Get organisation name for profile subtitle
		$org_id   = get_user_meta( $user->ID, '_csc_organisation_id', true );
		$org_name = $org_id ? get_the_title( $org_id ) : '';

		$active_label = $nav[ $active ]['label'] ?? 'Menu';

		ob_start();
		?>
		<!-- Mobile top bar (visible only on small screens) -->
		<div class="csc-mobile-topbar">
			<button class="csc-sidebar-toggle" id="csc-sidebar-toggle" aria-label="Open navigation" aria-expanded="false" aria-controls="csc-sidebar">
				<span></span><span></span><span></span>
			</button>
			<span class="csc-mobile-section-label"><?php echo esc_html( $active_label ); ?></span>
			<div class="csc-mobile-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
		</div>

		<!-- Sidebar overlay (mobile only) -->
		<div class="csc-sidebar-overlay" id="csc-sidebar-overlay" aria-hidden="true"></div>

		<aside class="csc-sidebar" id="csc-sidebar" role="navigation" aria-label="Member Portal Navigation">

			<!-- Close button (mobile only) -->
			<button class="csc-sidebar-close" id="csc-sidebar-close" aria-label="Close navigation">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>

			<!-- Profile -->
			<div class="csc-sidebar__profile">
				<div class="csc-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
				<div class="csc-sidebar__profile-info">
					<div class="csc-sidebar__name"><?php echo esc_html( $user->display_name ); ?></div>
					<?php if ( $org_name ) : ?>
					<div class="csc-sidebar__org"><?php echo esc_html( $org_name ); ?></div>
					<?php endif; ?>
				</div>
				<button class="csc-sidebar-collapse-btn" id="csc-sidebar-collapse-btn" aria-label="Collapse sidebar" title="Collapse sidebar">
					<svg viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M5.303 5.303L1.06 9.546 0 8.486 3.713 4.773 0 1.06 1.06 0l4.243 4.243A.75.75 0 0 1 5.303 5.303Z" fill="currentColor"/>
					</svg>
				</button>
			</div>

			<!-- Nav -->
			<nav class="csc-sidebar__nav">
				<ul>
					<?php foreach ( $nav as $key => $item ) : ?>
					<li>
						<a href="<?php echo esc_url( $item['url'] ); ?>"
						   class="csc-sidebar__link <?php echo $active === $key ? 'is-active' : ''; ?>"
						   title="<?php echo esc_attr( $item['label'] ); ?>"
						   <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
							<span class="csc-sidebar__link-icon" aria-hidden="true"><?php echo $item['icon']; ?></span>
							<span><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<!-- Logout -->
			<div class="csc-sidebar__footer">
				<a href="<?php echo esc_url( wp_logout_url( self::login_url() ) ); ?>" class="csc-sidebar__logout" title="Logout">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
					Logout
				</a>
			</div>
		</aside>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Static helpers
	 * --------------------------------------------------------------------- */
	public static function portal_url( $slug ) {
		$page = get_page_by_path( $slug );
		return $page ? get_permalink( $page->ID ) : home_url( '/' . $slug . '/' );
	}

	public static function login_url() {
		$page = get_page_by_path( 'members-login' );
		return $page ? get_permalink( $page->ID ) : wp_login_url();
	}

	/**
	 * Quick access guard for use at the top of other portal shortcodes.
	 * Returns false if access OK, or outputs a redirect and exits if not.
	 */
	public static function guard() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}
		$status = get_user_meta( get_current_user_id(), '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_safe_redirect( self::login_url() );
			exit;
		}
		return wp_get_current_user();
	}
}
