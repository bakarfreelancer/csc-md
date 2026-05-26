<?php
/**
 * Test data seeder — WP Admin page only, remove after testing.
 *
 * Access: WP Admin → Tools → CSC Test Data
 */
class Csc_Seeder {

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_action' ) );
	}

	public function add_menu() {
		add_management_page(
			'CSC Test Data',
			'CSC Test Data',
			'manage_options',
			'csc-test-data',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		global $wpdb;
		$existing_companies = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_csc_test_seed' AND meta_value='1'"
		);
		$existing_users = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='_csc_test_seed' AND meta_value='1'"
		);
		?>
		<div class="wrap">
			<h1>CSC Test Data Seeder</h1>
			<p>Creates 90 test companies and 90 representatives (one per company) with all required meta fields set and directory visibility on.</p>

			<table class="widefat" style="max-width:400px;margin-bottom:20px;">
				<tr><th>Seeded companies</th><td><strong><?php echo (int) $existing_companies; ?></strong></td></tr>
				<tr><th>Seeded users</th><td><strong><?php echo (int) $existing_users; ?></strong></td></tr>
			</table>

			<form method="post">
				<?php wp_nonce_field( 'csc_seed_action', 'csc_seed_nonce' ); ?>
				<input type="hidden" name="csc_seed_action" value="seed">
				<button type="submit" class="button button-primary">Seed 90 Companies &amp; Representatives</button>
			</form>

			<br>

			<form method="post" onsubmit="return confirm('Delete all seeded test data?');">
				<?php wp_nonce_field( 'csc_seed_action', 'csc_seed_nonce' ); ?>
				<input type="hidden" name="csc_seed_action" value="delete">
				<button type="submit" class="button button-secondary" style="color:#b32d2e;border-color:#b32d2e;">Delete All Test Data</button>
			</form>
		</div>
		<?php
	}

	public function handle_action() {
		if ( ! isset( $_POST['csc_seed_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorised.' );
		}
		check_admin_referer( 'csc_seed_action', 'csc_seed_nonce' );

		$action = sanitize_key( $_POST['csc_seed_action'] );

		if ( $action === 'delete' ) {
			$this->delete_all();
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>All CSC test data deleted.</p></div>';
			} );
			return;
		}

		if ( $action === 'seed' ) {
			$result = $this->seed();
			add_action( 'admin_notices', function() use ( $result ) {
				echo '<div class="notice notice-success"><p>'
					. "Created <strong>{$result['companies']}</strong> companies and <strong>{$result['users']}</strong> representatives. "
					. "Skipped <strong>{$result['skipped']}</strong> already existing."
					. '</p></div>';
			} );
		}
	}

	/* -----------------------------------------------------------------------
	 * Data pools
	 * --------------------------------------------------------------------- */
	private static $company_names = array(
		'AquaWind Technologies','Celtic Marine Solutions','Atlantic Power Systems',
		'WaveForce Engineering','TidalBridge Consulting','SeaGrid Networks',
		'Pembroke Offshore Ltd','BretonWave Energy','Swansea Bay Renewables',
		'IrishSea Dynamics','MerWind Partners','BristolChannel Power',
		'Deepwater Fabrications','OceanData Analytics','Shoreline Finance Group',
		'CelticEdge Engineering','NorthWave Logistics','GreenMarine Services',
		'TidalFlow Systems','WindBridge Wales','Severn Offshore','BlueTide Energy',
		'MarineGrid Solutions','CoastalLink Technologies','WestWave Consulting',
		'Atlantic Subsea','PembrokePower','CorkHarbour Energy','BretagneWind',
		'IrishAtlantic Renewables','SwanseaPower Co','CelticSubsea','TidalPeak Ltd',
		'OceanVenture Group','WindHarvest UK','MarineEdge Analytics',
		'BlueSea Engineering','GulfStream Power','PortalWave Systems','ArcticBlue Marine',
		'CelticShoreline Ltd','WestCoast Renewables','MarineQuest Analytics',
		'TidalCraft Engineering','SeaBreeze Consulting','HarbourLink Power',
		'OceanPath Systems','WindRidge Wales','CoastalForce Energy','DeepCurrent Ltd',
		'PembrokeBay Power','BretonMarine Solutions','IrishWave Energy','CeltSea Tech',
		'AtlanticGrid Partners','SouthWales Offshore','BristonWave Ltd',
		'CelticCurrent Systems','MarineForge Ltd','TidalSpark Energy',
		'WindQuest Analytics','OceanFrame Engineering','CoastalPulse Ltd',
		'BayBridge Renewables','SeaPath Consulting','WaveLink Wales',
		'GreatBritain Marine','CelticPeak Energy','TidalStorm Systems',
		'BlueMarine Networks','OceanCraft Consulting','WindHaven Ltd',
		'CelticBlue Engineering','SeaForge Technologies','TidalCrest Partners',
		'AtlanticEdge Ltd','WavePeak Systems','CoastalCraft Energy',
		'MarineVault Analytics','TidalMast Consulting','CelticFlow Ltd',
		'WindSprint Energy','OceanSpark Technologies','SeaCrest Engineering',
		'BayWindSolutions','PembrokeGrid Ltd','WaveHarbour Systems',
		'CelticStride Energy','TidalPower Wales','SeaLane Renewables',
	);
	private static $industries   = array( 'Offshore Wind','Marine Energy','Tidal Power','Wave Energy','Grid Infrastructure','Port & Logistics','Environmental Services','Engineering & Fabrication','Digital & Data','Finance & Investment' );
	private static $igp_cats     = array( 'Supply Chain Development','Skills & Workforce','Innovation & R&D','Infrastructure Investment','Export & Trade' );
	private static $sectors      = array( 'Private Company','Public Sector','Academic Institution','Charity / NGO','Partnership' );
	private static $capabilities = array( 'Turbine Installation','Cable Laying','Subsea Engineering','Environmental Impact Assessment','Project Finance','Operations & Maintenance','Digital Twin','Blade Manufacturing','Port Services','Community Engagement' );
	private static $expertise    = array( 'Wind','Marine','Tidal','Wave','Grid','Logistics','Environmental','Engineering','Data','Finance','Policy','Innovation','Offshore','Subsea','Construction' );
	private static $locations    = array( 'Swansea','Cardiff','Bristol','Milford Haven','Pembroke','Cork','Dublin','Brest','Nantes','Plymouth' );
	private static $counties     = array( 'West Wales','South Wales','Devon','Cornwall','Pembrokeshire','County Cork','County Dublin','Finistère','Loire-Atlantique','' );
	private static $countries    = array( 'Wales','England','Ireland','France','Wales','Wales','England','Ireland','France','Scotland' );
	private static $job_titles   = array( 'Chief Executive Officer','Managing Director','Head of Operations','Business Development Manager','Technical Director','Project Manager','Commercial Director','Engineering Lead','Policy Advisor','Innovation Manager' );
	private static $first_names  = array( 'James','Emma','Oliver','Sophia','Liam','Ava','Noah','Isabella','William','Mia','Ethan','Charlotte','Lucas','Amelia','Mason','Harper','Logan','Evelyn','Aiden','Abigail','Caden','Emily','Jackson','Elizabeth','Grayson','Avery','Sebastian','Sofia','Mateo','Ella','Jack','Madison','Owen','Scarlett','Theodore','Victoria','Asher','Aria','Samuel','Grace','Henry','Chloe','Alexander','Penelope','Wyatt','Layla','Carter','Riley','Julian','Zoey' );
	private static $last_names   = array( 'Jones','Williams','Davies','Evans','Thomas','Roberts','Lewis','Hughes','Morgan','Price','Edwards','James','Jenkins','Owen','Phillips','Wood','Thompson','Martin','Garcia','Martinez','Robinson','Clark','Rodriguez','Lee','Walker','Hall','Allen','Young','Hernandez','King','Wright','Lopez','Hill','Scott','Green','Adams','Baker','Gonzalez','Nelson','Carter','Mitchell','Perez','Turner','Campbell','Parker' );

	/* -----------------------------------------------------------------------
	 * Seed
	 * --------------------------------------------------------------------- */
	private function seed() {
		$created_companies = 0;
		$created_users     = 0;
		$skipped           = 0;

		for ( $i = 0; $i < 90; $i++ ) {
			$name   = self::$company_names[ $i ] ?? "Test Company {$i}";
			$slug   = 'test-co-' . sanitize_title( $name );

			$existing = get_page_by_path( $slug, OBJECT, 'csc_organisation' );
			if ( $existing ) {
				$org_id = $existing->ID;
				$skipped++;
			} else {
				$org_id = wp_insert_post( array(
					'post_type'   => 'csc_organisation',
					'post_title'  => $name,
					'post_name'   => $slug,
					'post_status' => 'publish',
				) );

				if ( is_wp_error( $org_id ) ) {
					continue;
				}

				$industry = self::$industries[ $i % count( self::$industries ) ];
				update_post_meta( $org_id, '_csc_org_industry',     $industry );
				update_post_meta( $org_id, '_csc_org_igp_category', self::$igp_cats[ $i % count( self::$igp_cats ) ] );
				update_post_meta( $org_id, '_csc_org_sector',       self::$sectors[ $i % count( self::$sectors ) ] );
				update_post_meta( $org_id, '_csc_org_capability',   self::$capabilities[ $i % count( self::$capabilities ) ] );
				update_post_meta( $org_id, '_csc_org_location',     self::$locations[ $i % count( self::$locations ) ] );
				update_post_meta( $org_id, '_csc_org_county',       self::$counties[ $i % count( self::$counties ) ] );
				update_post_meta( $org_id, '_csc_org_country',      self::$countries[ $i % count( self::$countries ) ] );
				update_post_meta( $org_id, '_csc_org_postcode',     'SA' . ( ( $i % 9 ) + 1 ) . ' ' . ( $i % 9 + 1 ) . 'AB' );
				update_post_meta( $org_id, '_csc_org_description',  "A leading {$industry} organisation — test record #{$i}." );
				update_post_meta( $org_id, '_csc_org_website',      'https://example.com/' . sanitize_title( $name ) );
				update_post_meta( $org_id, '_csc_org_phone',        '+44 1234 ' . str_pad( $i, 6, '0', STR_PAD_LEFT ) );
				update_post_meta( $org_id, '_csc_test_seed',        '1' );
				$created_companies++;
			}

			// One rep per company
			$email = 'test.rep.' . $i . '@csc-seed.test';
			if ( get_user_by( 'email', $email ) ) {
				$skipped++;
				continue;
			}

			$fn      = self::$first_names[ $i % count( self::$first_names ) ];
			$ln      = self::$last_names[ $i % count( self::$last_names ) ];
			$exp     = array_unique( array(
				self::$expertise[ $i % count( self::$expertise ) ],
				self::$expertise[ ( $i + 4 ) % count( self::$expertise ) ],
				self::$expertise[ ( $i + 9 ) % count( self::$expertise ) ],
			) );

			$user_id = wp_insert_user( array(
				'user_login'   => 'testrep' . $i,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 16 ),
				'first_name'   => $fn,
				'last_name'    => $ln,
				'display_name' => "{$fn} {$ln}",
				'role'         => 'subscriber',
			) );

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			update_user_meta( $user_id, '_csc_status',              'approved' );
			update_user_meta( $user_id, '_csc_organisation_id',     $org_id );
			update_user_meta( $user_id, '_csc_job_title',           self::$job_titles[ $i % count( self::$job_titles ) ] );
			update_user_meta( $user_id, '_csc_expertise',           implode( ', ', $exp ) );
			update_user_meta( $user_id, '_csc_phone',               '+44 7' . str_pad( $i, 9, '0', STR_PAD_LEFT ) );
			update_user_meta( $user_id, '_csc_dir_org_visible',     '1' );
			update_user_meta( $user_id, '_csc_dir_profile_visible', '1' );
			update_user_meta( $user_id, '_csc_test_seed',           '1' );
			$created_users++;
		}

		return array( 'companies' => $created_companies, 'users' => $created_users, 'skipped' => $skipped );
	}

	/* -----------------------------------------------------------------------
	 * Delete
	 * --------------------------------------------------------------------- */
	private function delete_all() {
		$posts = get_posts( array(
			'post_type'      => 'csc_organisation',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => '_csc_test_seed', 'value' => '1' ) ),
			'fields'         => 'ids',
		) );
		foreach ( $posts as $pid ) {
			wp_delete_post( $pid, true );
		}

		$users = get_users( array(
			'meta_query' => array( array( 'key' => '_csc_test_seed', 'value' => '1' ) ),
			'fields'     => 'ID',
			'number'     => -1,
		) );
		foreach ( $users as $uid ) {
			wp_delete_user( $uid );
		}
	}
}
