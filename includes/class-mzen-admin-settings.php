<?php
/**
 * Страница настроек плагина: список каналов, редактирование, импорт/экспорт.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Admin_Settings
 */
class MZEN_Admin_Settings {

    const PAGE          = 'multi-rss-for-zen';
    const CAPABILITY    = 'manage_options';
    const NOTICE_KEY    = 'mzen_notice';

    /**
     * @var MZEN_Channels
     */
    private $channels;

    /**
     * Конструктор.
     *
     * @param MZEN_Channels $channels Менеджер каналов.
     */
    public function __construct( MZEN_Channels $channels ) {
        $this->channels = $channels;
        $this->hooks();
    }

    /**
     * Хуки.
     */
    private function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

        // Обработчики POST-форм.
        add_action( 'admin_post_mzen_save_channel',      array( $this, 'handle_save' ) );
        add_action( 'admin_post_mzen_delete_channel',    array( $this, 'handle_delete' ) );
        add_action( 'admin_post_mzen_duplicate_channel', array( $this, 'handle_duplicate' ) );
        add_action( 'admin_post_mzen_set_default',       array( $this, 'handle_set_default' ) );
        add_action( 'admin_post_mzen_import_json',       array( $this, 'handle_import' ) );
        add_action( 'admin_post_mzen_export_json',       array( $this, 'handle_export' ) );
    }

    /**
     * Пункт меню в «Настройки → Мульти.Дзен».
     */
    public function menu() {
        add_options_page(
            __( 'Мульти.Дзен', 'multi-rss-for-zen' ),
            __( 'Мульти.Дзен', 'multi-rss-for-zen' ),
            self::CAPABILITY,
            self::PAGE,
            array( $this, 'render' )
        );
    }

    /**
     * Подключаем ассеты на нашей странице.
     *
     * @param string $hook_suffix Текущий экран.
     */
    public function enqueue( $hook_suffix ) {
        if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
            return;
        }
        wp_register_style( 'mzen-settings', MZEN_URL . 'assets/admin.css', array(), MZEN_VERSION );
        wp_enqueue_style( 'mzen-settings' );

        wp_register_script( 'mzen-settings', MZEN_URL . 'assets/admin.js', array( 'jquery' ), MZEN_VERSION, true );
        wp_enqueue_script( 'mzen-settings' );
    }

    /**
     * Точка входа страницы — роутинг между списком и редактированием.
     */
    public function render() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Недостаточно прав.', 'multi-rss-for-zen' ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

        $this->render_notice();

        if ( 'edit' === $action ) {
            $this->render_edit();
            return;
        }
        if ( 'new' === $action ) {
            $this->render_edit( null, true );
            return;
        }
        $this->render_list();
    }

    /**
     * Показ уведомлений после redirect.
     */
    private function render_notice() {
        if ( empty( $_GET[ self::NOTICE_KEY ] ) ) {
            return;
        }
        $code = sanitize_key( wp_unslash( $_GET[ self::NOTICE_KEY ] ) );
        $map  = array(
            'saved'      => __( 'Канал сохранён.', 'multi-rss-for-zen' ),
            'created'    => __( 'Канал создан.', 'multi-rss-for-zen' ),
            'deleted'    => __( 'Канал удалён.', 'multi-rss-for-zen' ),
            'duplicated' => __( 'Канал продублирован.', 'multi-rss-for-zen' ),
            'default'    => __( 'Канал по умолчанию обновлён.', 'multi-rss-for-zen' ),
            'imported'   => __( 'Каналы импортированы.', 'multi-rss-for-zen' ),
            'error'      => __( 'Ошибка. Проверьте данные и попробуйте снова.', 'multi-rss-for-zen' ),
        );
        $message = isset( $map[ $code ] ) ? $map[ $code ] : '';
        if ( $message ) {
            $class = 'error' === $code ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

    // -------------------------------------------------------------------------
    // Рендер вьюх.
    // -------------------------------------------------------------------------

    /**
     * Список каналов.
     */
    private function render_list() {
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $channels = $search ? $this->channels->search( $search ) : $this->channels->all();
        $default  = $this->channels->get_default_slug();
        $all      = $this->channels->all();
        include MZEN_DIR . 'includes/admin/view-settings-list.php';
    }

    /**
     * Форма редактирования канала.
     *
     * @param string|null $slug       Slug (null при создании нового).
     * @param bool        $is_new     Создание нового.
     */
    private function render_edit( $slug = null, $is_new = false ) {
        if ( $is_new ) {
            $channel = MZEN_Channels::default_channel();
            $channel['slug'] = '';
            $slug = '';
        } else {
            $slug = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
            $channel = $slug ? $this->channels->get( $slug ) : null;
            if ( ! $channel ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Канал не найден.', 'multi-rss-for-zen' ) . '</p></div>';
                $this->render_list();
                return;
            }
        }
        $is_default = ( $slug && $slug === $this->channels->get_default_slug() );
        include MZEN_DIR . 'includes/admin/view-settings-edit.php';
    }

    // -------------------------------------------------------------------------
    // Обработчики форм.
    // -------------------------------------------------------------------------

    /**
     * Сохранение канала.
     */
    public function handle_save() {
        $this->check_caps();
        check_admin_referer( 'mzen_save_channel' );

        $slug_raw   = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
        $slug_input = isset( $_POST['channel_slug_input'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug_input'] ) ) : '';
        $old_slug   = $slug_raw;
        $new_slug   = $slug_raw ? $slug_raw : $slug_input;

        // Защита: при создании новый slug должен быть уникален.
        if ( ! $old_slug ) {
            $new_slug = $this->channels->unique_slug( $new_slug );
        }

        $data = array();
        foreach ( array_keys( MZEN_Channels::default_channel() ) as $key ) {
            if ( 'slug' === $key ) {
                continue;
            }
            $data[ $key ] = isset( $_POST[ 'mzen_' . $key ] )
                ? wp_unslash( $_POST[ 'mzen_' . $key ] )
                : '';
        }
        $data['slug'] = $new_slug;
        $data = $this->channels->normalize( $data );

        // Если slug изменили — удаляем старый канал.
        if ( $old_slug && $old_slug !== $new_slug ) {
            $this->channels->delete( $old_slug );
        }
        $this->channels->save( $new_slug, $data );

        // Пересобираем правила перезаписи, чтобы фид нового канала сразу заработал.
        flush_rewrite_rules();

        $this->redirect( $old_slug ? 'saved' : 'created', array( 'action' => 'edit', 'channel' => $new_slug ) );
    }

    /**
     * Удаление канала.
     */
    public function handle_delete() {
        $this->check_caps();
        check_admin_referer( 'mzen_delete_channel' );
        $slug = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
        if ( $slug ) {
            $this->channels->delete( $slug );
            // Пересобираем правила перезаписи, чтобы фид нового канала сразу заработал.
            flush_rewrite_rules();
        }
        $this->redirect( 'deleted' );
    }

    /**
     * Дублирование канала.
     */
    public function handle_duplicate() {
        $this->check_caps();
        check_admin_referer( 'mzen_duplicate_channel' );
        $slug = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
        $new  = $slug ? $this->channels->duplicate( $slug ) : false;
        // Пересобираем правила перезаписи, чтобы фид нового канала сразу заработал.
        flush_rewrite_rules();
        $this->redirect( $new ? 'duplicated' : 'error', $new ? array( 'action' => 'edit', 'channel' => $new ) : array() );
    }

    /**
     * Назначение канала по умолчанию.
     */
    public function handle_set_default() {
        $this->check_caps();
        check_admin_referer( 'mzen_set_default' );
        $slug = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
        if ( $slug ) {
            $this->channels->set_default( $slug );
        }
        $this->redirect( 'default' );
    }

    /**
     * Импорт JSON.
     */
    public function handle_import() {
        $this->check_caps();
        check_admin_referer( 'mzen_import_json' );

        $json = isset( $_POST['mzen_json'] ) ? wp_unslash( $_POST['mzen_json'] ) : '';
        $mode = isset( $_POST['mzen_mode'] ) ? sanitize_key( wp_unslash( $_POST['mzen_mode'] ) ) : 'merge';
        if ( ! in_array( $mode, array( 'replace', 'merge' ), true ) ) {
            $mode = 'merge';
        }

        $result = $this->channels->import_json( $json, $mode );
        if ( is_wp_error( $result ) ) {
            $this->redirect( 'error', array( 'mzen_error' => rawurlencode( $result->get_error_message() ) ) );
        }
        $this->redirect( 'imported' );
    }

    /**
     * Экспорт JSON — скачивание файла.
     */
    public function handle_export() {
        $this->check_caps();
        check_admin_referer( 'mzen_export_json' );

        $json     = $this->channels->export_json();
        $filename = 'multizen-channels-' . gmdate( 'Y-m-d-His' ) . '.json';

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json ) );
        echo $json;
        exit;
    }

    // -------------------------------------------------------------------------
    // Утилиты.
    // -------------------------------------------------------------------------

    /**
     * Проверка прав.
     */
    private function check_caps() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Недостаточно прав.', 'multi-rss-for-zen' ) );
        }
    }

    /**
     * Редирект с уведомлением.
     *
     * @param string $notice Код уведомления.
     * @param array  $extra  Доп. query-параметры.
     */
    private function redirect( $notice, $extra = array() ) {
        $args = array_merge(
            array( 'page' => self::PAGE, self::NOTICE_KEY => $notice ),
            $extra
        );
        $url = add_query_arg( $args, admin_url( 'options-general.php' ) );
        wp_safe_redirect( $url );
        exit;
    }
}