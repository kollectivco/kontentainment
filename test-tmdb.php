<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

$token = get_option('ktn_tmdb_bearer_token');
$tmdb_id = 937287; // Challengers
$language = 'ar';
$url = "https://api.themoviedb.org/3/movie/{$tmdb_id}?language={$language}&append_to_response=credits";

$args = array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ),
    'timeout' => 25
);

$response = wp_remote_get($url, $args);
$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

foreach($data['credits']['cast'] as $actor) {
    if (strpos($actor['name'], 'u06') !== false) {
        echo "TMDB RETURNED BAD NAME FOR: " . $actor['original_name'] . " -> " . $actor['name'] . "\n";
    }
}
echo "Done checking TMDB response.\n";
