<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save Watch Provider data to post meta
 */
function ktn_save_watch_providers($post_id, $data) {
    if (empty($data['watch/providers']['results'])) {
        update_post_meta($post_id, '_ktn_watch_providers', []);
        update_post_meta($post_id, '_ktn_watch_providers_fetched', time());
        return;
    }

    $providers = $data['watch/providers']['results'];
    update_post_meta($post_id, '_ktn_watch_providers', $providers);
    update_post_meta($post_id, '_ktn_watch_providers_fetched', time());
}

/**
 * Get human readable country name from ISO 3166-1 code
 */
function ktn_get_country_name($code) {
    $countries = array(
        'AF' => 'Afghanistan', 'AX' => 'Aland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
        'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria',
        'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
        'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
        'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BQ' => 'Bonaire, Sint Eustatius and Saba', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
        'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
        'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
        'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia', 'KM' => 'Comoros',
        'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic of the', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire',
        'HR' => 'Croatia', 'CU' => 'Cuba', 'CW' => 'Curacao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
        'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador',
        'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia',
        'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland',
        'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon',
        'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar',
        'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam',
        'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
        'HT' => 'Haiti', 'HM' => 'Heard Island and McDonald Islands', 'VA' => 'Holy See (Vatican City State)', 'HN' => 'Honduras', 'HK' => 'Hong Kong',
        'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic of',
        'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy',
        'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
        'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic of', 'KR' => 'Korea, Republic of', 'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho',
        'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
        'MO' => 'Macao', 'MK' => 'Macedonia, the former Yugoslav Republic of', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia',
        'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique',
        'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States of',
        'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat',
        'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru',
        'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
        'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestinian Territory, Occupied',
        'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
        'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar',
        'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia',
        'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
        'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia',
        'ZA' => 'South Africa', 'GS' => 'South Georgia and the South Sandwich Islands', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
        'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Swaziland', 'SE' => 'Sweden',
        'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan, Province of China', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of',
        'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu',
        'VE' => 'Venezuela', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    );

    return isset($countries[$code]) ? $countries[$code] : $code;
}

/**
 * AJAX handler for refreshing watch providers
 */
add_action('wp_ajax_ktn_refresh_watch_providers', 'ktn_ajax_refresh_watch_providers');
function ktn_ajax_refresh_watch_providers() {
    check_ajax_referer('ktn_import_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    $tmdb_id = get_post_meta($post_id, '_movie_tmdb_id', true);
    $post_type = get_post_type($post_id);
    $type = ($post_type === 'tv_show') ? 'tv' : 'movie';

    if (!$tmdb_id) {
        wp_send_json_error(['message' => 'TMDB ID missing.']);
    }

    $token = get_option('ktn_tmdb_bearer_token');
    $default_language = get_option('ktn_default_language', 'en-US');

    $details = ktn_get_tmdb_media_details($tmdb_id, $type, $token, $default_language);
    if (is_wp_error($details)) {
        wp_send_json_error(['message' => $details->get_error_message()]);
    }

    ktn_save_watch_providers($post_id, $details);

    wp_send_json_success([
        'message' => 'Watch providers updated successfully!',
        'time' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Frontend Renderer for Watch Providers
 */
function ktn_render_watch_providers($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    
    $providers = get_post_meta($post_id, '_ktn_watch_providers', true);
    if (empty($providers)) {
        echo '<div class="ktn-wp-empty">' . esc_html__('No legal streaming options currently available.', 'kontentainment') . '</div>';
        return;
    }

    $default_region = get_option('ktn_default_region', 'EG');
    $available_regions = array_keys($providers);
    
    // Sort regions so default is first, then alphabetically
    usort($available_regions, function($a, $b) use ($default_region) {
        if ($a === $default_region) return -1;
        if ($b === $default_region) return 1;
        return strcmp(ktn_get_country_name($a), ktn_get_country_name($b));
    });

    wp_enqueue_style('ktn-watch-providers', KTN_PLUGIN_URL . 'assets/css/kontentainment-watch-providers.css', [], KTN_PLUGIN_VERSION);
    wp_enqueue_script('ktn-watch-providers', KTN_PLUGIN_URL . 'assets/js/kontentainment-watch-providers.js', ['jquery'], KTN_PLUGIN_VERSION, true);

    ?>
    <div class="ktn-watch-providers-section" id="ktn-wp-<?php echo $post_id; ?>">
        <div class="ktn-wp-header">
            <h2 class="ktn-wp-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>
                <?php esc_html_e('Where to Watch', 'kontentainment'); ?>
            </h2>

            <div class="ktn-wp-region-selector">
                <label for="ktn-region-select-<?php echo $post_id; ?>"><?php esc_html_e('Region:', 'kontentainment'); ?></label>
                <select id="ktn-region-select-<?php echo $post_id; ?>" class="ktn-wp-select">
                    <?php foreach ($available_regions as $code): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $default_region); ?>>
                            <?php echo esc_html(ktn_get_country_name($code)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ktn-wp-content">
            <?php foreach ($providers as $region_code => $data): 
                $active_class = ($region_code === $default_region || ($region_code === reset($available_regions) && !in_array($default_region, $available_regions))) ? 'active' : '';
                ?>
                <div class="ktn-wp-region-panel <?php echo esc_attr($active_class); ?>" data-region="<?php echo esc_attr($region_code); ?>">
                    <?php
                    $categories = [
                        'flatrate' => __('Stream', 'kontentainment'),
                        'free'     => __('Free', 'kontentainment'),
                        'ads'      => __('Ads', 'kontentainment'),
                        'rent'     => __('Rent', 'kontentainment'),
                        'buy'      => __('Buy', 'kontentainment'),
                    ];

                    $found_any = false;
                    foreach ($categories as $key => $label):
                        if (empty($data[$key])) continue;
                        $found_any = true;
                        ?>
                        <div class="ktn-wp-category">
                            <h4 class="ktn-wp-cat-title"><?php echo esc_html($label); ?></h4>
                            <div class="ktn-wp-list">
                                <?php foreach ($data[$key] as $provider): ?>
                                    <div class="ktn-wp-item" title="<?php echo esc_attr($provider['provider_name']); ?>">
                                        <div class="ktn-wp-logo">
                                            <img src="https://image.tmdb.org/t/p/original<?php echo esc_attr($provider['logo_path']); ?>" alt="<?php echo esc_attr($provider['provider_name']); ?>">
                                        </div>
                                        <span class="ktn-wp-name"><?php echo esc_html($provider['provider_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; 

                    if (!$found_any): ?>
                        <p class="ktn-wp-none"><?php esc_html_e('No providers available for this region.', 'kontentainment'); ?></p>
                    <?php endif; 
                    
                    if (!empty($data['link'])): ?>
                        <div class="ktn-wp-footer">
                            <a href="<?php echo esc_url($data['link']); ?>" target="_blank" rel="nofollow" class="ktn-wp-tmdb-link">
                                <?php esc_html_e('View on TMDB', 'kontentainment'); ?> &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
