<?php
/**
 * Метабокс «Мульти Дзен» на странице редактирования записи.
 *
 * Отвечает за:
 *  - отображение селекта канала + полей тематики, типа статьи, публикации, индексации;
 *  - сохранение мета-полей поста (_mzen_*);
 *  - подключение admin.js для авто-подстановки полей при смене канала.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Metabox
 */
class MZEN_Metabox {

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
        add_action( 'add_meta_boxes', array( $this, 'register' ) );
        add_action( 'save_post', array( $this, 'save' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Подключаем admin.js на страницах редактирования.
     *
     * @param string $hook_suffix Текущий экран админки.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_register_script(
            'mzen-metabox',
            MZEN_URL . 'assets/admin.js',
            array( 'jquery' ),
            MZEN_VERSION,
            true
        );

        // Передаём карту: slug канала → его настройки.
        $map = array();
        foreach ( $this->channels->all() as $slug => $channel ) {
            $map[ $slug ] = array(
                'title'          => isset( $channel['title'] ) ? $channel['title'] : $slug,
                'category'       => isset( $channel['category'] ) ? $channel['category'] : '',
                'excludedefault' => isset( $channel['excludedefault'] ) ? $channel['excludedefault'] : 'disabled',
                'typeplatform'   => isset( $channel['typeplatform'] ) ? $channel['typeplatform'] : 'native-no',
                'typearticle'    => isset( $channel['typearticle'] ) ? $channel['typearticle'] : 'false',
                'index'          => isset( $channel['index'] ) ? $channel['index'] : 'index',
                'delay_minutes'  => isset( $channel['delay_minutes'] ) ? (int) $channel['delay_minutes'] : 0,
            );
        }
        wp_localize_script( 'mzen-metabox', 'mzenChannels', $map );

        wp_enqueue_script( 'mzen-metabox' );

        wp_register_style(
            'mzen-metabox',
            MZEN_URL . 'assets/admin.css',
            array(),
            MZEN_VERSION
        );
        wp_enqueue_style( 'mzen-metabox' );
    }

    /**
     * Регистрация метабокса для всех типов записей,
     * которые используются хотя бы в одном канале.
     */
    public function register() {
        $post_types = array();
        foreach ( $this->channels->all() as $channel ) {
            $types = array_filter( array_map( 'trim', explode( ',', isset( $channel['type'] ) ? $channel['type'] : 'post' ) ) );
            $post_types = array_merge( $post_types, $types );
        }
        $post_types = array_values( array_unique( $post_types ) );
        if ( empty( $post_types ) ) {
            $post_types = array( 'post' );
        }

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'mzen_meta_box',
                __( 'Мульти Дзен', 'multi-rss-for-zen' ),
                array( $this, 'render' ),
                $pt,
                'normal',
                'high'
            );
        }
    }

    /**
     * Рендер метабокса — подключает шаблон.
     *
     * @param WP_Post $post Текущая запись.
     */
    public function render( $post ) {
        $channels = $this->channels->all();
        include MZEN_DIR . 'includes/admin/view-metabox.php';
    }

    /**
     * Сохранение мета-полей.
     *
     * @param int     $post_id ID записи.
     * @param WP_Post $post    Объект записи.
     */
    public function save( $post_id, $post ) {
        // Проверки безопасности.
        if ( ! isset( $_POST['mzen_meta_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mzen_meta_nonce'] ) ), 'mzen_save_metabox_' . $post_id ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // -------- Канал --------
        $channel_slug = isset( $_POST['mzen_channel'] ) ? sanitize_key( wp_unslash( $_POST['mzen_channel'] ) ) : '';
        $channel      = null;

        if ( '' === $channel_slug || ! $this->channels->get( $channel_slug ) ) {
            delete_post_meta( $post_id, '_mzen_channel' );
        } else {
            update_post_meta( $post_id, '_mzen_channel', $channel_slug );
            $channel = $this->channels->get( $channel_slug );
        }

        // -------- Тематика --------
        $default_category = $channel && ! empty( $channel['category'] ) ? $channel['category'] : '';
        $category         = isset( $_POST['mzen_category'] ) ? sanitize_text_field( wp_unslash( $_POST['mzen_category'] ) ) : '';

        // Если выбранная тематика совпадает с тематикой канала — не сохраняем override.
        if ( '' === $category || $category === $default_category ) {
            delete_post_meta( $post_id, '_mzen_category' );
        } else {
            update_post_meta( $post_id, '_mzen_category', $category );
        }

        // -------- Исключить из RSS --------
        // Логика (Вариант A):
        //  JS автоматически ставит галочку при выборе канала с excludedefault=enabled.
        //  PHP просто сохраняет фактическое состояние чекбокса.
        if ( isset( $_POST['mzen_rss_enabled'] ) ) {
            update_post_meta( $post_id, '_mzen_rss_enabled', 'yes' );
        } else {
            delete_post_meta( $post_id, '_mzen_rss_enabled' );
        }

        // -------- Чекбокс "для взрослых" --------
        if ( isset( $_POST['mzen_rating'] ) ) {
            update_post_meta( $post_id, '_mzen_rating', 'Да (для взрослых)' );
        } else {
            update_post_meta( $post_id, '_mzen_rating', 'Нет (не для взрослых)' );
        }

        // Значения по умолчанию из выбранного канала.
        $channel_article  = $channel && ! empty( $channel['typearticle'] )  ? $channel['typearticle']  : 'false';
        $channel_platform = $channel && ! empty( $channel['typeplatform'] ) ? $channel['typeplatform'] : 'native-no';
        $channel_index    = $channel && ! empty( $channel['index'] )        ? $channel['index']        : 'index';

        // -------- Тип статьи --------
        if ( isset( $_POST['mzen_type_article'] ) ) {
            $val = sanitize_text_field( wp_unslash( $_POST['mzen_type_article'] ) );
            if ( in_array( $val, array( 'true', 'false' ), true ) ) {
                if ( $val === $channel_article ) {
                    delete_post_meta( $post_id, '_mzen_type_article' );
                } else {
                    update_post_meta( $post_id, '_mzen_type_article', $val );
                }
            }
        }

        // -------- Публикация --------
        if ( isset( $_POST['mzen_type_platform'] ) ) {
            $val = sanitize_text_field( wp_unslash( $_POST['mzen_type_platform'] ) );
            if ( in_array( $val, array( 'native-yes', 'native-draft', 'native-no' ), true ) ) {
                if ( $val === $channel_platform ) {
                    delete_post_meta( $post_id, '_mzen_type_platform' );
                } else {
                    update_post_meta( $post_id, '_mzen_type_platform', $val );
                }
            }
        }

        // -------- Индексация --------
        if ( isset( $_POST['mzen_index'] ) ) {
            $val = sanitize_text_field( wp_unslash( $_POST['mzen_index'] ) );
            if ( in_array( $val, array( 'index', 'noindex' ), true ) ) {
                if ( $val === $channel_index ) {
                    delete_post_meta( $post_id, '_mzen_index' );
                } else {
                    update_post_meta( $post_id, '_mzen_index', $val );
                }
            }
        }

        // -------- Отложенная публикация в RSS --------
        // Формат от input: 'Y-m-dTH:i'. Храним как 'Y-m-d H:i:s' (local time).
        // Минимум: post_date + delay_minutes канала.
        $publish_at_raw = isset( $_POST['mzen_publish_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mzen_publish_at'] ) ) : '';
        $publish_at_raw = trim( str_replace( 'T', ' ', $publish_at_raw ) );

        if ( '' === $publish_at_raw ) {
            delete_post_meta( $post_id, '_mzen_publish_at' );
        } else {
            $ts = strtotime( $publish_at_raw );
            if ( $ts ) {
                $post_date_ts = strtotime( (string) get_post_field( 'post_date', $post_id ) );
                $channel_delay = $channel && isset( $channel['delay_minutes'] ) ? (int) $channel['delay_minutes'] : 0;
                $min_ts        = $post_date_ts + ( $channel_delay * 60 );

                if ( $ts >= $min_ts ) {
                    update_post_meta( $post_id, '_mzen_publish_at', date( 'Y-m-d H:i:s', $ts ) );
                } else {
                    // Слишком рано — удаляем, чтобы запись ушла по общим правилам канала.
                    delete_post_meta( $post_id, '_mzen_publish_at' );
                }
            } else {
                delete_post_meta( $post_id, '_mzen_publish_at' );
            }
        }

    }
}