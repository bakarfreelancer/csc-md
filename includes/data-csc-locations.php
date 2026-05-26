<?php
/**
 * Static location data: world countries and UK postal counties.
 *
 * @package Csc_Md
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns a flat, alphabetically sorted array of world country names.
 */
function csc_get_countries() {
    return array(
        'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda',
        'Argentina','Armenia','Australia','Austria','Azerbaijan',
        'Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize',
        'Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil',
        'Brunei','Bulgaria','Burkina Faso','Burundi',
        'Cabo Verde','Cambodia','Cameroon','Canada','Central African Republic','Chad',
        'Chile','China','Colombia','Comoros','Congo (Brazzaville)','Congo (Kinshasa)',
        'Costa Rica','Croatia','Cuba','Cyprus','Czech Republic',
        'Denmark','Djibouti','Dominica','Dominican Republic',
        'Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia',
        'Eswatini','Ethiopia',
        'Fiji','Finland','France',
        'Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala',
        'Guinea','Guinea-Bissau','Guyana',
        'Haiti','Honduras','Hungary',
        'Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy',
        'Jamaica','Japan','Jordan',
        'Kazakhstan','Kenya','Kiribati','Kuwait','Kyrgyzstan',
        'Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein',
        'Lithuania','Luxembourg',
        'Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands',
        'Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia',
        'Montenegro','Morocco','Mozambique','Myanmar',
        'Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger',
        'Nigeria','North Korea','North Macedonia','Norway',
        'Oman',
        'Pakistan','Palau','Palestine','Panama','Papua New Guinea','Paraguay','Peru',
        'Philippines','Poland','Portugal',
        'Qatar',
        'Romania','Russia','Rwanda',
        'Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines',
        'Samoa','San Marino','Sao Tome and Principe','Saudi Arabia','Senegal','Serbia',
        'Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands',
        'Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka',
        'Sudan','Suriname','Sweden','Switzerland','Syria',
        'Taiwan','Tajikistan','Tanzania','Thailand','Timor-Leste','Togo','Tonga',
        'Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu',
        'Uganda','Ukraine','United Arab Emirates','United Kingdom','United States',
        'Uruguay','Uzbekistan',
        'Vanuatu','Vatican City','Venezuela','Vietnam',
        'Yemen',
        'Zambia','Zimbabwe',
    );
}

/**
 * Returns UK postal counties grouped by region.
 * Based on: https://github.com/markinns/postal-counties
 *
 * Format: array( 'Region Name' => array( 'County', ... ) )
 */
function csc_get_uk_counties() {
    return array(
        'Greater London' => array(
            'Greater London',
        ),
        'England — Home Counties' => array(
            'Bedfordshire','Berkshire','Buckinghamshire','East Sussex','Essex',
            'Hertfordshire','Kent','Middlesex','Surrey','West Sussex',
        ),
        'England — East' => array(
            'Cambridgeshire','Lincolnshire','Norfolk','Suffolk',
        ),
        'England — East Midlands' => array(
            'Derbyshire','Leicestershire','Northamptonshire','Nottinghamshire','Rutland',
        ),
        'England — West Midlands' => array(
            'Herefordshire','Shropshire','Staffordshire','Warwickshire',
            'West Midlands','Worcestershire',
        ),
        'England — North West' => array(
            'Cheshire','Cumbria','Greater Manchester','Lancashire','Merseyside',
        ),
        'England — Yorkshire' => array(
            'East Riding of Yorkshire','North Yorkshire','South Yorkshire','West Yorkshire',
        ),
        'England — North East' => array(
            'County Durham','Northumberland','Tyne and Wear',
        ),
        'England — South West' => array(
            'Avon','Bristol','Cornwall','Devon','Dorset','Gloucestershire',
            'Isle of Wight','Somerset','Wiltshire',
        ),
        'England — South East' => array(
            'Hampshire','Oxfordshire',
        ),
        'England — Other' => array(
            'Cleveland','Humberside',
        ),
        'Scotland' => array(
            'Aberdeenshire','Angus','Argyll and Bute','Clackmannanshire',
            'Dumfries and Galloway','East Ayrshire','East Dunbartonshire',
            'East Lothian','East Renfrewshire','City of Edinburgh','Eilean Siar',
            'Falkirk','Fife','City of Glasgow','Highland','Inverclyde','Midlothian',
            'Moray','North Ayrshire','North Lanarkshire','Orkney Islands',
            'Perth and Kinross','Renfrewshire','Scottish Borders','Shetland Islands',
            'South Ayrshire','South Lanarkshire','Stirling',
            'West Dunbartonshire','West Lothian',
        ),
        'Wales' => array(
            'Anglesey','Blaenau Gwent','Bridgend','Caerphilly','Cardiff',
            'Carmarthenshire','Ceredigion','Conwy','Denbighshire','Flintshire',
            'Gwynedd','Merthyr Tydfil','Monmouthshire','Neath Port Talbot',
            'Newport','Pembrokeshire','Powys','Rhondda Cynon Taf',
            'Swansea','Torfaen','Vale of Glamorgan','Wrexham',
        ),
        'Northern Ireland' => array(
            'Antrim','Armagh','Down','Fermanagh','Londonderry','Tyrone',
        ),
    );
}

/**
 * Returns UK counties as a flat array of {name, region} objects (for JS output).
 */
function csc_get_uk_counties_flat() {
    $flat = array();
    foreach ( csc_get_uk_counties() as $region => $counties ) {
        foreach ( $counties as $county ) {
            $flat[] = array( 'name' => $county, 'region' => $region );
        }
    }
    return $flat;
}
