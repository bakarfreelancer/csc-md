<?php
/**
 * HubSpot API integration — creates/updates contacts via Private App token.
 *
 * Usage:
 *   $hs = new Csc_Hubspot();
 *   $hs->sync_contact( $user_id );
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Hubspot {

	const API_BASE    = 'https://api.hubapi.com';
	const ERROR_LIMIT = 50; // max errors stored in the log option

	/* -----------------------------------------------------------------------
	 * Public API
	 * --------------------------------------------------------------------- */

	/**
	 * Sync a WordPress user to HubSpot (create or update contact).
	 * Returns true on success, WP_Error on failure.
	 *
	 * @param int $user_id
	 * @return true|WP_Error
	 */
	public function sync_contact( $user_id ) {
		$token = $this->get_token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'HubSpot token not configured.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', 'User not found: ' . $user_id );
		}

		$properties = $this->build_properties( $user );

		// Search for existing contact by email
		$existing_id = $this->search_by_email( $user->user_email, $token );

		if ( is_wp_error( $existing_id ) ) {
			$this->log_error( $user_id, $existing_id->get_error_message() );
			return $existing_id;
		}

		if ( $existing_id ) {
			$result = $this->update_contact( $existing_id, $properties, $token );
		} else {
			$result = $this->create_contact( $properties, $token );
		}

		if ( is_wp_error( $result ) ) {
			$this->log_error( $user_id, $result->get_error_message() );
			return $result;
		}

		return true;
	}

	/**
	 * Test whether the configured token is valid.
	 * Returns array with 'ok' (bool) and 'message' (string).
	 */
	public function test_connection() {
		$token = $this->get_token();
		if ( ! $token ) {
			return array( 'ok' => false, 'message' => 'No API token saved.' );
		}

		$response = wp_remote_get( self::API_BASE . '/crm/v3/objects/contacts?limit=1', array(
			'headers' => $this->headers( $token ),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			return array( 'ok' => true, 'message' => 'Connected successfully.' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$msg  = $body['message'] ?? ( 'HTTP ' . $code );
		return array( 'ok' => false, 'message' => $msg );
	}

	/**
	 * Update the member_status custom property for a user (e.g. to 'revoked').
	 */
	public function update_status( $user_id, $status ) {
		$token = $this->get_token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'HubSpot token not configured.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', 'User not found.' );
		}

		$existing_id = $this->search_by_email( $user->user_email, $token );
		if ( is_wp_error( $existing_id ) ) {
			return $existing_id;
		}
		if ( ! $existing_id ) {
			return new WP_Error( 'not_found', 'Contact not found in HubSpot.' );
		}

		return $this->update_contact( $existing_id, array( 'csc_member_status' => $status ), $token );
	}

	/**
	 * Return the last N error log entries.
	 */
	public function get_errors( $limit = 20 ) {
		$errors = get_option( 'csc_hubspot_sync_errors', array() );
		return array_slice( $errors, -$limit );
	}

	/**
	 * Clear the error log.
	 */
	public function clear_errors() {
		update_option( 'csc_hubspot_sync_errors', array() );
	}

	/**
	 * Retry all users listed in the error log.
	 * Returns array of results keyed by user_id.
	 */
	public function retry_failed() {
		$errors  = get_option( 'csc_hubspot_sync_errors', array() );
		$results = array();

		// Get unique user IDs from error log
		$user_ids = array_unique( array_column( $errors, 'user_id' ) );

		foreach ( $user_ids as $uid ) {
			$r = $this->sync_contact( intval( $uid ) );
			$results[ $uid ] = is_wp_error( $r ) ? $r->get_error_message() : 'ok';
		}

		return $results;
	}

	/* -----------------------------------------------------------------------
	 * Private helpers
	 * --------------------------------------------------------------------- */

	private function get_token() {
		return get_option( 'csc_hubspot_token', '' );
	}

	private function headers( $token ) {
		return array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Build the HubSpot properties array for a given WP user.
	 */
	private function build_properties( $user ) {
		$uid = $user->ID;

		$org_id    = get_user_meta( $uid, '_csc_organisation_id', true );
		$org_title = '';
		$org_site  = '';
		if ( $org_id ) {
			$org_title = get_the_title( $org_id );
			$org_site  = get_post_meta( $org_id, '_csc_org_website', true );
		}

		$dir_visible = get_user_meta( $uid, '_csc_dir_profile_visible', true );
		$dir_visible = ( $dir_visible !== '0' ) ? 'true' : 'false';

		return array(
			// Standard HubSpot properties
			'firstname' => $user->first_name,
			'lastname'  => $user->last_name,
			'email'     => $user->user_email,
			'phone'     => get_user_meta( $uid, '_csc_phone', true ),
			'jobtitle'  => get_user_meta( $uid, '_csc_job_title', true ),
			'company'   => $org_title,
			'website'   => $org_site,

			// Custom CSC properties
			'csc_member_status'    => get_user_meta( $uid, '_csc_status', true ),
			'csc_portal_user_id'   => (string) $uid,
			'csc_organisation_id'  => (string) $org_id,
			'csc_directory_visible' => $dir_visible,
		);
	}

	/**
	 * Search HubSpot for a contact by email.
	 * Returns contact ID (string) or null if not found, or WP_Error.
	 */
	private function search_by_email( $email, $token ) {
		$body = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => 'email',
							'operator'     => 'EQ',
							'value'        => $email,
						),
					),
				),
			),
			'limit' => 1,
		);

		$response = wp_remote_post( self::API_BASE . '/crm/v3/objects/contacts/search', array(
			'headers' => $this->headers( $token ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error( 'hs_search_failed', $data['message'] ?? 'Search failed (HTTP ' . $code . ')' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $data['results'] ) ) {
			return $data['results'][0]['id'];
		}

		return null;
	}

	/**
	 * Create a new HubSpot contact. Returns contact ID or WP_Error.
	 */
	private function create_contact( $properties, $token ) {
		$body = array( 'properties' => $properties );

		$response = wp_remote_post( self::API_BASE . '/crm/v3/objects/contacts', array(
			'headers' => $this->headers( $token ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 201 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			return $data['id'];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_Error( 'hs_create_failed', $data['message'] ?? 'Create failed (HTTP ' . $code . ')' );
	}

	/**
	 * Update an existing HubSpot contact. Returns true or WP_Error.
	 */
	private function update_contact( $hs_id, $properties, $token ) {
		$body = array( 'properties' => $properties );

		$response = wp_remote_request( self::API_BASE . '/crm/v3/objects/contacts/' . $hs_id, array(
			'method'  => 'PATCH',
			'headers' => $this->headers( $token ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			return true;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_Error( 'hs_update_failed', $data['message'] ?? 'Update failed (HTTP ' . $code . ')' );
	}

	/**
	 * Append an error to the persistent error log.
	 */
	private function log_error( $user_id, $message ) {
		$errors   = get_option( 'csc_hubspot_sync_errors', array() );
		$errors[] = array(
			'user_id'   => $user_id,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		);

		// Cap the log
		if ( count( $errors ) > self::ERROR_LIMIT ) {
			$errors = array_slice( $errors, - self::ERROR_LIMIT );
		}

		update_option( 'csc_hubspot_sync_errors', $errors );
	}
}
