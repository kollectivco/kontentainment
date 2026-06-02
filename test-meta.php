<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

$post_id = 1; // Any existing post ID, we'll just create a dummy meta key
$cast = [
    [
        'name' => 'زيندايا',
        'character' => 'Emma'
    ]
];

$json1 = wp_json_encode($cast);
$json2 = wp_json_encode($cast, JSON_UNESCAPED_UNICODE);

update_post_meta($post_id, '_test_cast1', $json1);
update_post_meta($post_id, '_test_cast2', wp_slash($json2));

echo "Test 1 (no slash, no unescaped):\n";
echo get_post_meta($post_id, '_test_cast1', true) . "\n\n";

echo "Test 2 (slash + unescaped):\n";
echo get_post_meta($post_id, '_test_cast2', true) . "\n\n";
