<?php
/**
 * Главный класс плагина. Синглтон, инициализация сервисов.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Plugin
 */
final class MZEN_Plugin {

    /**
     * @var MZEN_Plugin|null
     */
    private static $instance = null;

    /**
     * @var MZEN_Channels|null
     */
    public $channels = null;

    /**
     * @var MZEN_Migrator|null
     */
    public $migrator = null;

    /**
     * @var MZEN_Feed|null
     */
    public $feed = null;

    /**
     * @var MZEN_Metabox|null
     */
    public $metabox = null;

    /**
     * @var MZEN_Admin_Settings|null
     */
    public $settings = null;

    /**
     * Синглтон.
     *
     * @return MZEN_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Точка входа — вызывается на plugins_loaded.
     */
    public static function init() {
        self::instance()->boot();
    }

    /**
     * Инициализация сервисов.
     */
    private function boot() {
        $this->migrator = new MZEN_Migrator();
        $this->channels = new MZEN_Channels();
        $this->feed     = new MZEN_Feed( $this->channels );

        if ( is_admin() ) {
            $this->metabox  = new MZEN_Metabox( $this->channels );
            $this->settings = new MZEN_Admin_Settings( $this->channels );

            if ( ! class_exists( 'MZEN_Admin_Columns' ) ) {
                require_once MZEN_DIR . 'includes/admin/class-mzen-admin-columns.php';
            }
            new MZEN_Admin_Columns( $this->channels );
        }
    }

    /**
     * Хук активации.
     */
    public static function on_activation() {
        if ( ! class_exists( 'MZEN_Channels' ) ) {
            require_once MZEN_DIR . 'includes/class-mzen-channels.php';
        }
        if ( ! class_exists( 'MZEN_Migrator' ) ) {
            require_once MZEN_DIR . 'includes/class-mzen-migrator.php';
        }

        // 1. Миграция старых опций (mzen_options → mzen_channels).
        MZEN_Migrator::migrate();

        // 2. Если каналов нет — создаём дефолтный.
        $channels = new MZEN_Channels();
        if ( ! $channels->all() ) {
            $channels->create_default();
        }

        // 3. Отложенная пересборка rewrite rules (сработает на следующем init).
        update_option( 'mzen_flush_needed', 1 );

        // 4. Версия БД.
        update_option( 'mzen_db_version', MZEN_VERSION );
    }

    /**
     * Хук деактивации.
     */
    public static function on_deactivation() {
        flush_rewrite_rules();
    }

    /**
     * Запрет клонирования.
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Singleton — cloning is not allowed.', 'multi-rss-for-zen' ), '2.0.0' );
    }

    /**
     * Запрет десериализации.
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Singleton — unserializing is not allowed.', 'multi-rss-for-zen' ), '2.0.0' );
    }
}