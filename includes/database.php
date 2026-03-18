<?php
if (!defined('ABSPATH')) {
    exit;
}

function ktn_create_database_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'ktn_showtimes';

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        cinema_id bigint(20) NOT NULL,
        cinema_name varchar(255) NOT NULL,
        source_url text NOT NULL,
        movie_title_scraped varchar(255) NOT NULL,
        matched_movie_id bigint(20) DEFAULT NULL,
        show_date varchar(100) NOT NULL,
        show_time varchar(100) NOT NULL,
        experience varchar(100) DEFAULT 'Standard',
        price_text varchar(100) DEFAULT '',
        source_type varchar(100) DEFAULT 'elcinema_theater',
        scraped_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY cinema_id (cinema_id),
        KEY matched_movie_id (matched_movie_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}