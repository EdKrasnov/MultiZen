<?php
/**
 * Plugin Name: RSS for Multi Zen
 * Plugin URI:  https://giport.ru/
 * Description: Создание Multi RSS-ленты для сервиса Дзен с поддержкой нескольких каналов.
 * Version:     2.0.0
 * Author:      EdKrasnov
 * Author URI:  https://profiles.wordpress.org/edkrasnov
 * Text Domain: multi-rss-for-zen
 * Domain Path: /languages
 * Requires at least: 4.4
 * Requires PHP: 8.0
 *
 * @package MultiZen
 */

// Защита от прямого доступа.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Константы плагина.
// -----------------------------------------------------------------------------
define( 'MZEN_VERSION', '2.0.0' );
define( 'MZEN_FILE', __FILE__ );
define( 'MZEN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MZEN_URL', plugin_dir_url( __FILE__ ) );
define( 'MZEN_BASENAME', plugin_basename( __FILE__ ) );

// -----------------------------------------------------------------------------
// Автозагрузка классов.
// -----------------------------------------------------------------------------
spl_autoload_register( function ( $class ) {
    // Загружаем только классы с нашим префиксом.
    if ( strpos( $class, 'MZEN_' ) !== 0 ) {
        return;
    }

    // MZEN_Plugin          → class-mzen-plugin.php
    // MZEN_Admin_Settings  → class-mzen-admin-settings.php
    $class_lower = strtolower( str_replace( '_', '-', $class ) );
    $filename    = 'class-' . $class_lower . '.php';

    $paths = array(
        MZEN_DIR . 'includes/' . $filename,
        MZEN_DIR . 'includes/admin/' . $filename,
    );

    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );

// -----------------------------------------------------------------------------
// Хуки активации / деактивации / удаления.
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'MZEN_Plugin', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'MZEN_Plugin', 'on_deactivation' ) );

// -----------------------------------------------------------------------------
// Инициализация плагина.
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', array( 'MZEN_Plugin', 'init' ), 5 );

/**
 * Загрузка файла локализации.
 */
function mzen_load_textdomain() {
    load_plugin_textdomain( 'multi-rss-for-zen', false, dirname( MZEN_BASENAME ) . '/languages' );
}
add_action( 'init', 'mzen_load_textdomain' );