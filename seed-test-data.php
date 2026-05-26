<?php
/**
 * Seed 90 test companies + 90 representatives (one per company).
 *
 * Run:    php wp-content/plugins/csc-md/seed-test-data.php
 * Delete: php wp-content/plugins/csc-md/seed-test-data.php --delete
 *
 * Must be run from the WordPress root directory.
 */

// Bootstrap WordPress
// __DIR__ = .../wp-content/plugins/csc-md  → 3 levels up = WP root
$wp_root = dirname( dirname( dirname( __DIR__ ) ) );
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
    die( "Could not find wp-load.php. Expected at: {$wp_root}/wp-load.php\n" );
}

// Suppress output buffering issues in CLI
define( 'DOING_CRON', true ); // prevents redirect loops
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

require_once $wp_root . '/wp-load.php';

// -------------------------------------------------------------------------
// Sample data pools
// -------------------------------------------------------------------------
$industries = [
    'Offshore Wind', 'Marine Energy', 'Tidal Power', 'Wave Energy',
    'Grid Infrastructure', 'Port & Logistics', 'Environmental Services',
    'Engineering & Fabrication', 'Digital & Data', 'Finance & Investment',
];
$igp_cats = [
    'Supply Chain Development', 'Skills & Workforce', 'Innovation & R&D',
    'Infrastructure Investment', 'Export & Trade',
];
$sectors = [
    'Private Company', 'Public Sector', 'Academic Institution',
    'Charity / NGO', 'Partnership',
];
$capabilities = [
    'Turbine Installation', 'Cable Laying', 'Subsea Engineering',
    'Environmental Impact Assessment', 'Project Finance',
    'Operations & Maintenance', 'Digital Twin', 'Blade Manufacturing',
    'Port Services', 'Community Engagement',
];
$expertise_pool = [
    'Wind', 'Marine', 'Tidal', 'Wave', 'Grid', 'Logistics',
    'Environmental', 'Engineering', 'Data', 'Finance', 'Policy',
    'Innovation', 'Offshore', 'Subsea', 'Construction',
];
$locations = [
    'Swansea', 'Cardiff', 'Bristol', 'Milford Haven', 'Pembroke',
    'Cork', 'Dublin', 'Brest', 'Nantes', 'Plymouth',
];
$counties = [
    'West Wales', 'South Wales', 'Devon', 'Cornwall', 'Pembrokeshire',
    'County Cork', 'County Dublin', 'Finistère', 'Loire-Atlantique', '',
];
$countries = [
    'Wales', 'England', 'Ireland', 'France', 'Wales',
    'Wales', 'England', 'Ireland', 'France', 'Scotland',
];
$job_titles = [
    'Chief Executive Officer', 'Managing Director', 'Head of Operations',
    'Business Development Manager', 'Technical Director',
    'Project Manager', 'Commercial Director', 'Engineering Lead',
    'Policy Advisor', 'Innovation Manager',
];
$company_names = [
    'AquaWind Technologies', 'Celtic Marine Solutions', 'Atlantic Power Systems',
    'WaveForce Engineering', 'TidalBridge Consulting', 'SeaGrid Networks',
    'Pembroke Offshore Ltd', 'BretonWave Energy', 'Swansea Bay Renewables',
    'IrishSea Dynamics', 'MerWind Partners', 'BristolChannel Power',
    'Deepwater Fabrications', 'OceanData Analytics', 'Shoreline Finance Group',
    'CelticEdge Engineering', 'NorthWave Logistics', 'GreenMarine Services',
    'TidalFlow Systems', 'WindBridge Wales', 'Severn Offshore', 'BlueTide Energy',
    'MarineGrid Solutions', 'CoastalLink Technologies', 'WestWave Consulting',
    'Atlantic Subsea', 'PembrokePower', 'CorkHarbour Energy', 'BretagneWind',
    'IrishAtlantic Renewables', 'SwanseaPower Co', 'CelticSubsea', 'TidalPeak Ltd',
    'OceanVenture Group', 'WindHarvest UK', 'MarineEdge Analytics',
    'BlueSea Engineering', 'GulfStream Power', 'PortalWave Systems', 'ArcticBlue Marine',
    'CelticShoreline Ltd', 'WestCoast Renewables', 'MarineQuest Analytics',
    'TidalCraft Engineering', 'SeaBreeze Consulting', 'HarbourLink Power',
    'OceanPath Systems', 'WindRidge Wales', 'CoastalForce Energy', 'DeepCurrent Ltd',
    'PembrokeBay Power', 'BretonMarine Solutions', 'IrishWave Energy', 'CeltSea Tech',
    'AtlanticGrid Partners', 'SouthWales Offshore', 'BristonWave Ltd',
    'CelticCurrent Systems', 'MarineForge Ltd', 'TidalSpark Energy',
    'WindQuest Analytics', 'OceanFrame Engineering', 'CoastalPulse Ltd',
    'BayBridge Renewables', 'SeaPath Consulting', 'WaveLink Wales',
    'GreatBritain Marine', 'CelticPeak Energy', 'TidalStorm Systems',
    'BlueMarine Networks', 'OceanCraft Consulting', 'WindHaven Ltd',
    'CelticBlue Engineering', 'SeaForge Technologies', 'TidalCrest Partners',
    'AtlanticEdge Ltd', 'WavePeak Systems', 'CoastalCraft Energy',
    'MarineVault Analytics', 'TidalMast Consulting', 'CelticFlow Ltd',
    'WindSprint Energy', 'OceanSpark Technologies', 'SeaCrest Engineering',
    'BayWindSolutions', 'PembrokeGrid Ltd', 'WaveHarbour Systems',
    'CelticStride Energy', 'TidalPower Wales', 'SeaLane Renewables',
];
$first_names = [
    'James', 'Emma', 'Oliver', 'Sophia', 'Liam', 'Ava', 'Noah', 'Isabella',
    'William', 'Mia', 'Ethan', 'Charlotte', 'Lucas', 'Amelia', 'Mason',
    'Harper', 'Logan', 'Evelyn', 'Aiden', 'Abigail', 'Caden', 'Emily',
    'Jackson', 'Elizabeth', 'Grayson', 'Avery', 'Sebastian', 'Sofia',
    'Mateo', 'Ella', 'Jack', 'Madison', 'Owen', 'Scarlett', 'Theodore',
    'Victoria', 'Asher', 'Aria', 'Samuel', 'Grace', 'Henry', 'Chloe',
    'Alexander', 'Penelope', 'Wyatt', 'Layla', 'Carter', 'Riley', 'Julian', 'Zoey',
];
$last_names = [
    'Jones', 'Williams', 'Davies', 'Evans', 'Thomas', 'Roberts', 'Lewis',
    'Hughes', 'Morgan', 'Price', 'Edwards', 'James', 'Jenkins', 'Owen',
    'Phillips', 'Wood', 'Thompson', 'Martin', 'Garcia', 'Martinez',
    'Robinson', 'Clark', 'Rodriguez', 'Lee', 'Walker', 'Hall',
    'Allen', 'Young', 'Hernandez', 'King', 'Wright', 'Lopez', 'Hill',
    'Scott', 'Green', 'Adams', 'Baker', 'Gonzalez', 'Nelson',
    'Carter', 'Mitchell', 'Perez', 'Turner', 'Campbell', 'Parker',
];

// -------------------------------------------------------------------------
// Delete mode
// -------------------------------------------------------------------------
$delete_mode = in_array( '--delete', $argv, true );

if ( $delete_mode ) {
    $deleted_companies = 0;
    $deleted_users     = 0;

    $posts = get_posts( [
        'post_type'      => 'csc_organisation',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [ [ 'key' => '_csc_test_seed', 'value' => '1' ] ],
        'fields'         => 'ids',
    ] );
    foreach ( $posts as $pid ) {
        wp_delete_post( $pid, true );
        $deleted_companies++;
    }

    $users = get_users( [
        'meta_query' => [ [ 'key' => '_csc_test_seed', 'value' => '1' ] ],
        'fields'     => 'ID',
        'number'     => -1,
    ] );
    foreach ( $users as $uid ) {
        wp_delete_user( $uid );
        $deleted_users++;
    }

    echo "Deleted {$deleted_companies} companies and {$deleted_users} users.\n";
    return;
}

// -------------------------------------------------------------------------
// Create companies + representatives
// -------------------------------------------------------------------------
$created_companies = 0;
$created_users     = 0;
$skipped           = 0;

for ( $i = 0; $i < 90; $i++ ) {
    $name       = $company_names[ $i ] ?? "Test Company {$i}";
    $slug       = 'test-co-' . sanitize_title( $name );
    $industry   = $industries[ $i % count( $industries ) ];
    $igp        = $igp_cats[ $i % count( $igp_cats ) ];
    $sector     = $sectors[ $i % count( $sectors ) ];
    $capability = $capabilities[ $i % count( $capabilities ) ];
    $location   = $locations[ $i % count( $locations ) ];
    $county     = $counties[ $i % count( $counties ) ];
    $country    = $countries[ $i % count( $countries ) ];
    $postcode   = 'SA' . ( ( $i % 9 ) + 1 ) . ' ' . ( ( $i % 9 ) + 1 ) . chr( 65 + ( $i % 26 ) ) . chr( 65 + ( ( $i + 3 ) % 26 ) );

    // Org: skip if slug already exists
    $existing = get_page_by_path( $slug, OBJECT, 'csc_organisation' );
    if ( $existing ) {
        $org_id = $existing->ID;
        $skipped++;
        echo "  [skip] Company already exists: {$name}\n";
    } else {
        $org_id = wp_insert_post( [
            'post_type'   => 'csc_organisation',
            'post_title'  => $name,
            'post_name'   => $slug,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $org_id ) ) {
            echo "  [error] Company insert failed: {$name} — " . $org_id->get_error_message() . "\n";
            continue;
        }

        update_post_meta( $org_id, '_csc_org_industry',      $industry );
        update_post_meta( $org_id, '_csc_org_igp_category',  $igp );
        update_post_meta( $org_id, '_csc_org_sector',        $sector );
        update_post_meta( $org_id, '_csc_org_capability',    $capability );
        update_post_meta( $org_id, '_csc_org_location',      $location );
        update_post_meta( $org_id, '_csc_org_county',        $county );
        update_post_meta( $org_id, '_csc_org_country',       $country );
        update_post_meta( $org_id, '_csc_org_postcode',      $postcode );
        update_post_meta( $org_id, '_csc_org_description',   "A leading {$industry} organisation based in {$location}." );
        update_post_meta( $org_id, '_csc_org_website',       'https://example.com/' . sanitize_title( $name ) );
        update_post_meta( $org_id, '_csc_org_phone',         '+44 ' . rand( 1000, 9999 ) . ' ' . rand( 100000, 999999 ) );
        update_post_meta( $org_id, '_csc_test_seed',         '1' );

        $created_companies++;
        echo "  [ok] Company #{$org_id}: {$name}\n";
    }

    // Rep: skip if email already exists
    $email = 'test.rep.' . $i . '@csc-seed.test';
    if ( get_user_by( 'email', $email ) ) {
        $skipped++;
        echo "  [skip] User already exists: {$email}\n";
        continue;
    }

    $fn    = $first_names[ $i % count( $first_names ) ];
    $ln    = $last_names[ $i % count( $last_names ) ];

    // 3 rotating expertise tags
    $exp = array_unique( [
        $expertise_pool[ $i % count( $expertise_pool ) ],
        $expertise_pool[ ( $i + 4 ) % count( $expertise_pool ) ],
        $expertise_pool[ ( $i + 9 ) % count( $expertise_pool ) ],
    ] );

    $user_id = wp_insert_user( [
        'user_login'   => 'testrep' . $i,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password( 16 ),
        'first_name'   => $fn,
        'last_name'    => $ln,
        'display_name' => "{$fn} {$ln}",
        'role'         => 'subscriber',
    ] );

    if ( is_wp_error( $user_id ) ) {
        echo "  [error] User insert failed for company {$name}: " . $user_id->get_error_message() . "\n";
        continue;
    }

    update_user_meta( $user_id, '_csc_status',              'approved' );
    update_user_meta( $user_id, '_csc_organisation_id',     $org_id );
    update_user_meta( $user_id, '_csc_job_title',           $job_titles[ $i % count( $job_titles ) ] );
    update_user_meta( $user_id, '_csc_expertise',           implode( ', ', $exp ) );
    update_user_meta( $user_id, '_csc_phone',               '+44 ' . rand( 7000, 7999 ) . rand( 100000, 999999 ) );
    update_user_meta( $user_id, '_csc_dir_org_visible',     '1' );
    update_user_meta( $user_id, '_csc_dir_profile_visible', '1' );
    update_user_meta( $user_id, '_csc_test_seed',           '1' );

    $created_users++;
    echo "  [ok] Rep #{$user_id}: {$fn} {$ln} → {$name}\n";
}

echo "\n";
echo "Done.\n";
echo "  Created : {$created_companies} companies, {$created_users} representatives\n";
echo "  Skipped : {$skipped} already existing\n";
echo "\nTo remove all test data, run:\n";
echo "  php wp-content/plugins/csc-md/seed-test-data.php --delete\n";
