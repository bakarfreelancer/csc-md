<?php

/**
 * Fired during plugin activation.
 *
 * Creates all required WordPress portal pages with their shortcodes
 * and assigns the CSC Portal page template.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Md_Activator {

	public static function activate() {
		self::create_pages();
		flush_rewrite_rules();
	}

	/**
	 * Create portal pages if they don't already exist.
	 * All pages use the "CSC Portal" page template (page-csc-portal.php in the theme).
	 */
	private static function create_pages() {
		$pages = array(
			array(
				'title'   => 'Members Login',
				'slug'    => 'members-login',
				'content' => '[csc_login]',
			),
			array(
				'title'   => 'Join CSC',
				'slug'    => 'join-csc',
				'content' => '[csc_join]',
			),
			array(
				'title'   => 'Member Dashboard',
				'slug'    => 'member-dashboard',
				'content' => '[csc_dashboard]',
			),
			array(
				'title'   => 'Member Directory',
				'slug'    => 'member-directory',
				'content' => '[csc_directory]',
			),
			array(
				'title'   => 'Member Forum',
				'slug'    => 'member-forum',
				'content' => '[csc_forum]',
			),
			array(
				'title'   => 'Newsletters & Resources',
				'slug'    => 'member-newsletters',
				'content' => '[csc_newsletters]',
			),
			array(
				'title'   => 'Update Account',
				'slug'    => 'update-account',
				'content' => '[csc_update_account]',
			),
			array(
				'title'   => 'Member Settings',
				'slug'    => 'member-settings',
				'content' => '[csc_settings]',
			),
			array(
				'title'   => 'Terms of Use',
				'slug'    => 'terms-of-use',
				'content' => '[csc_terms]',
			),
		);

		foreach ( $pages as $page ) {
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing ) {
				// Ensure the CSC Portal template is applied even if page already existed
				if ( get_post_meta( $existing->ID, '_wp_page_template', true ) !== 'page-csc-portal.php' ) {
					update_post_meta( $existing->ID, '_wp_page_template', 'page-csc-portal.php' );
				}
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			) );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_wp_page_template', 'page-csc-portal.php' );
			}
		}
	}
}
