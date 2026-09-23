<?php
/**
 * Удаление данных плагина при его удалении через админку WordPress.
 *
 * @package MultiZen
 */

// Защита: файл должен вызываться только WordPress'ом.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Удаляем основные опции.
delete_option( 'mzen_channels' );
delete_option( 'mzen_db_version' );
delete_option( 'mzen_default_channel' );

// Удаляем старые опции v1 (если остались).
delete_option( 'mzen_options' );

// Удаляем мета-ключи постов.
global $wpdb;

$meta_keys = array(
    '_mzen_channel',
    '_mzen_category',
    '_mzen_rating',
    '_mzen_type_article',
    '_mzen_type_platform',
    '_mzen_index',
    '_mzen_rss_enabled',
    // старые ключи v1 — удаляем только если пользователь уже мигрировал.
    'mzencategory_meta_value',
    'mzenrating_meta_value',
    'mzentypearticle_meta_value',
    'mzentypeplatform_meta_value',
    'mzenindex_meta_value',
    'mzenrssenabled_meta_value',
);

foreach ( $meta_keys as $key ) {
    $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ) );
}

// Удаляем транзиенты, если есть.
delete_transient( 'mzen_channels_cache' );