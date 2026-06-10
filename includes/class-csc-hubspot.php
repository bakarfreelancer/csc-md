<?php
/**
 * HubSpot API integration — syncs contacts and companies via Private App token.
 *
 * HubSpot IDs are cached in meta after first sync:
 *   User meta  _csc_hs_contact_id  — HubSpot contact ID
 *   Post meta  _csc_hs_company_id  — HubSpot company ID
 *
 * This avoids brittle name/email searches on every sync.
 *
 * Debug log: wp-content/uploads/csc-hs-debug.log
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Hubspot {

	const API_BASE    = 'https://api.hubapi.com';
	const ERROR_LIMIT = 100;

	/* -----------------------------------------------------------------------
	 * Public API — Contacts
	 * --------------------------------------------------------------------- */

	/**
	 * Sync a WP user to HubSpot as a contact (create or update).
	 * Also syncs their company and links contact ↔ company.
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

		$properties    = $this->build_contact_properties( $user );
		$hs_contact_id = get_user_meta( $user_id, '_csc_hs_contact_id', true );

		if ( ! $hs_contact_id ) {
			$found = $this->search_contact_by_email( $user->user_email, $token );
			if ( is_wp_error( $found ) ) {
				$this->log_error( $user_id, 'contact', 'search by email: ' . $found->get_error_message() );
				return $found;
			}
			if ( $found ) {
				$hs_contact_id = $found;
				update_user_meta( $user_id, '_csc_hs_contact_id', $hs_contact_id );
			}
		}

		if ( $hs_contact_id ) {
			$result = $this->patch( '/crm/v3/objects/contacts/' . $hs_contact_id, $properties, $token );
		} else {
			$result = $this->post( '/crm/v3/objects/contacts', array( 'properties' => $properties ), $token );
			if ( ! is_wp_error( $result ) ) {
				$hs_contact_id = $result['id'] ?? null;
				if ( $hs_contact_id ) {
					update_user_meta( $user_id, '_csc_hs_contact_id', $hs_contact_id );
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			$this->log_error( $user_id, 'contact', 'upsert: ' . $result->get_error_message() );
			return $result;
		}

		$org_id = get_user_meta( $user_id, '_csc_organisation_id', true );
		if ( $org_id && $hs_contact_id ) {
			$hs_company_id = $this->sync_company( intval( $org_id ) );
			if ( ! is_wp_error( $hs_company_id ) && $hs_company_id ) {
				$this->associate_contact_company( $hs_contact_id, $hs_company_id, $token );
			}
		}

		return true;
	}

	/* -----------------------------------------------------------------------
	 * Public API — Companies
	 * --------------------------------------------------------------------- */

	/**
	 * Sync a WP organisation post to HubSpot as a company (create or update).
	 * Returns HubSpot company ID (string) or WP_Error.
	 *
	 * @param int $org_id  WP post ID of the csc_organisation
	 * @return string|WP_Error
	 */
	public function sync_company( $org_id ) {
		$token = $this->get_token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'HubSpot token not configured.' );
		}

		$name = get_the_title( $org_id );
		if ( ! $name ) {
			return new WP_Error( 'invalid_org', 'Organisation post not found: ' . $org_id );
		}

		$properties    = $this->build_company_properties( $org_id );
		$hs_company_id = get_post_meta( $org_id, '_csc_hs_company_id', true );

		if ( ! $hs_company_id ) {
			$found = $this->search_company_by_name( $name, $token );
			if ( is_wp_error( $found ) ) {
				$found = null;
			}
			if ( $found ) {
				$hs_company_id = $found;
				update_post_meta( $org_id, '_csc_hs_company_id', $hs_company_id );
			}
		}

		if ( $hs_company_id ) {
			$result = $this->patch( '/crm/v3/objects/companies/' . $hs_company_id, $properties, $token );
			if ( is_wp_error( $result ) ) {
				$this->log_error( $org_id, 'company', 'update: ' . $result->get_error_message() );
				return $result;
			}
			return $hs_company_id;
		}

		$result = $this->post( '/crm/v3/objects/companies', array( 'properties' => $properties ), $token );
		if ( is_wp_error( $result ) ) {
			$this->log_error( $org_id, 'company', 'create: ' . $result->get_error_message() );
			return $result;
		}

		$hs_company_id = $result['id'] ?? null;
		if ( $hs_company_id ) {
			update_post_meta( $org_id, '_csc_hs_company_id', $hs_company_id );
		}

		return $hs_company_id ?: new WP_Error( 'hs_no_id', 'Company created but no ID returned.' );
	}

	/* -----------------------------------------------------------------------
	 * Public API — Utilities
	 * --------------------------------------------------------------------- */

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
		return array( 'ok' => false, 'message' => $body['message'] ?? 'HTTP ' . $code );
	}

	public function get_errors( $limit = 20 ) {
		return array_slice( get_option( 'csc_hubspot_sync_errors', array() ), -$limit );
	}

	public function clear_errors() {
		update_option( 'csc_hubspot_sync_errors', array() );
	}

	public function retry_failed() {
		$errors   = get_option( 'csc_hubspot_sync_errors', array() );
		$user_ids = array_unique( array_filter( array_column( $errors, 'user_id' ), function( $id ) {
			return get_userdata( intval( $id ) ) !== false;
		} ) );
		$results = array();
		foreach ( $user_ids as $uid ) {
			$r = $this->sync_contact( intval( $uid ) );
			$results[ $uid ] = is_wp_error( $r ) ? $r->get_error_message() : 'ok';
		}
		return $results;
	}

	/* -----------------------------------------------------------------------
	 * Property builders — standard HubSpot properties only
	 * --------------------------------------------------------------------- */

	private function build_contact_properties( $user ) {
		$uid    = $user->ID;
		$org_id = get_user_meta( $uid, '_csc_organisation_id', true );

		$props = array(
			'firstname' => $user->first_name,
			'lastname'  => $user->last_name,
			'email'     => $user->user_email,
			'phone'     => get_user_meta( $uid, '_csc_phone', true ),
			'jobtitle'  => get_user_meta( $uid, '_csc_job_title', true ),
			'company'   => $org_id ? get_the_title( $org_id ) : '',
		);

		// Remove blank values
		return array_filter( $props, fn( $v ) => $v !== '' && $v !== false && $v !== null );
	}

	private function build_company_properties( $org_id ) {
		$props = array(
			'name'        => get_the_title( $org_id ),
			'phone'       => get_post_meta( $org_id, '_csc_org_phone', true ),
			'website'     => get_post_meta( $org_id, '_csc_org_website', true ),
			'description' => get_post_meta( $org_id, '_csc_org_description', true ),
			'city'        => get_post_meta( $org_id, '_csc_org_city', true ),
			'state'       => get_post_meta( $org_id, '_csc_org_county', true ),
			'country'     => get_post_meta( $org_id, '_csc_org_country', true ),
			'zip'         => get_post_meta( $org_id, '_csc_org_postcode', true ),
		);

		return array_filter( $props, fn( $v ) => $v !== '' && $v !== false && $v !== null );
	}

	/* -----------------------------------------------------------------------
	 * Search — used only when no cached HubSpot ID exists
	 * --------------------------------------------------------------------- */

	private function search_contact_by_email( $email, $token ) {
		$body = array(
			'filterGroups' => array( array(
				'filters' => array( array(
					'propertyName' => 'email',
					'operator'     => 'EQ',
					'value'        => $email,
				) ),
			) ),
			'limit' => 1,
		);

		$result = $this->post( '/crm/v3/objects/contacts/search', $body, $token );

		if ( is_wp_error( $result ) ) return $result;
		return ! empty( $result['results'] ) ? $result['results'][0]['id'] : null;
	}

	private function search_company_by_name( $name, $token ) {
		$body = array(
			'filterGroups' => array( array(
				'filters' => array( array(
					'propertyName' => 'name',
					'operator'     => 'EQ',
					'value'        => $name,
				) ),
			) ),
			'limit' => 1,
		);

		$result = $this->post( '/crm/v3/objects/companies/search', $body, $token );

		if ( is_wp_error( $result ) ) return $result;
		return ! empty( $result['results'] ) ? $result['results'][0]['id'] : null;
	}

	/* -----------------------------------------------------------------------
	 * Association: contact ↔ company
	 * --------------------------------------------------------------------- */

	private function associate_contact_company( $hs_contact_id, $hs_company_id, $token ) {
		$url = self::API_BASE . '/crm/v4/objects/contacts/' . $hs_contact_id
			. '/associations/default/companies/' . $hs_company_id;

		wp_remote_request( $url, array(
			'method'  => 'PUT',
			'headers' => $this->headers( $token ),
			'timeout' => 10,
		) );
	}

	/* -----------------------------------------------------------------------
	 * HTTP helpers
	 * --------------------------------------------------------------------- */

	private function post( $path, $body, $token ) {
		$url      = self::API_BASE . $path;
		$json     = wp_json_encode( $body );

		$response = wp_remote_post( $url, array(
			'headers' => $this->headers( $token ),
			'body'    => $json,
			'timeout' => 15,
		) );

		return $this->handle_response( $response, $path );
	}

	private function patch( $path, $properties, $token ) {
		$url  = self::API_BASE . $path;
		$body = wp_json_encode( array( 'properties' => $properties ) );

		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => $this->headers( $token ),
			'body'    => $body,
			'timeout' => 15,
		) );

		return $this->handle_response( $response, $path );
	}

	private function handle_response( $response, $path ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return $data ?: array();
		}

		$msg = $data['message'] ?? "HTTP {$code}";
		return new WP_Error( 'hs_api_error', $msg );
	}

	private function log_error( $entity_id, $type, $message ) {
		$errors   = get_option( 'csc_hubspot_sync_errors', array() );
		$errors[] = array(
			'user_id'   => $entity_id,
			'type'      => $type,
			'message'   => "[{$type}] {$message}",
			'timestamp' => current_time( 'mysql' ),
		);
		if ( count( $errors ) > self::ERROR_LIMIT ) {
			$errors = array_slice( $errors, -self::ERROR_LIMIT );
		}
		update_option( 'csc_hubspot_sync_errors', $errors );
	}

	/* -----------------------------------------------------------------------
	 * Helpers
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
}
