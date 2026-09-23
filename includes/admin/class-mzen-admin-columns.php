<?php
/**
 * Колонка «Канал Мульти.Дзен», фильтр по каналу и массовые действия
 * в списке записей админки.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Admin_Columns
 */
class MZEN_Admin_Columns {

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
        add_action( 'admin_init', array( $this, 'register_columns' ) );
        add_action( 'restrict_manage_posts', array( $this, 'render_filter' ) );
        add_action( 'pre_get_posts', array( $this, 'apply_filter' ) );

        // Bulk actions (массовые действия).
        $this->register_bulk_actions();
    }

    /**
     * Возвращает список типов записей, используемых хотя бы в одном канале.
     *
     * @return array
     */
    private function get_post_types() {
        $types = array();
        foreach ( $this->channels->all() as $channel ) {
            $list  = array_filter( array_map( 'trim', explode( ',', isset( $channel['type'] ) ? $channel['type'] : 'post' ) ) );
            $types = array_merge( $types, $list );
        }
        $types = array_values( array_unique( $types ) );
        if ( empty( $types ) ) {
            $types = array( 'post' );
        }
        return $types;
    }

    // -------------------------------------------------------------------------
    // Колонка «Канал».
    // -------------------------------------------------------------------------

    /**
     * Подписываемся на фильтры и экшены для каждого типа записи.
     */
    public function register_columns() {
        foreach ( $this->get_post_types() as $pt ) {
            add_filter( "manage_{$pt}_posts_columns", array( $this, 'add_column' ) );
            add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
        }
    }

    /**
     * Добавляем колонку «Канал» после колонки «Заголовок».
     *
     * @param array $columns Существующие колонки.
     * @return array
     */
    public function add_column( $columns ) {
        $new      = array();
        $inserted = false;

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['mzen_channel'] = __( 'Канал', 'multi-rss-for-zen' );
                $inserted            = true;
            }
        }
        if ( ! $inserted ) {
            $new['mzen_channel'] = __( 'Канал', 'multi-rss-for-zen' );
        }

        return $new;
    }

    /**
     * Рендер содержимого колонки.
     *
     * @param string $column  Имя колонки.
     * @param int    $post_id ID записи.
     */
    public function render_column( $column, $post_id ) {
        if ( 'mzen_channel' !== $column ) {
            return;
        }

        $slug = (string) get_post_meta( $post_id, '_mzen_channel', true );

        if ( '' === $slug ) {
            echo '<span style="color:#999;">— ' . esc_html__( 'не привязано', 'multi-rss-for-zen' ) . ' —</span>';
            return;
        }

        $channel = $this->channels->get( $slug );
        if ( ! $channel ) {
            echo '<span style="color:#b32d2e;">' . esc_html__( 'канал удалён', 'multi-rss-for-zen' ) . '</span>';
            return;
        }

        $edit_url = add_query_arg(
            array(
                'page'    => MZEN_Admin_Settings::PAGE,
                'action'  => 'edit',
                'channel' => $slug,
            ),
            admin_url( 'options-general.php' )
        );

        $title = ! empty( $channel['title'] ) ? $channel['title'] : $slug;
        echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $title ) . '</a>';
    }

    // -------------------------------------------------------------------------
    // Фильтр по каналу.
    // -------------------------------------------------------------------------

    /**
     * Рисуем выпадающий фильтр рядом с другими фильтрами.
     *
     * @param string $post_type Текущий тип записи.
     */
    public function render_filter( $post_type ) {
        if ( ! in_array( $post_type, $this->get_post_types(), true ) ) {
            return;
        }

        $channels = $this->channels->all();
        if ( empty( $channels ) ) {
            return;
        }

        $selected = isset( $_GET['mzen_channel_filter'] ) ? sanitize_key( wp_unslash( $_GET['mzen_channel_filter'] ) ) : '';
        ?>
        <select name="mzen_channel_filter" id="mzen_channel_filter">
            <option value=""><?php esc_html_e( 'Все каналы', 'multi-rss-for-zen' ); ?></option>
            <option value="__none__" <?php selected( $selected, '__none__' ); ?>>
                <?php esc_html_e( '— Без канала —', 'multi-rss-for-zen' ); ?>
            </option>
            <?php foreach ( $channels as $slug => $channel ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>>
                    <?php echo esc_html( ! empty( $channel['title'] ) ? $channel['title'] : $slug ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Применяем фильтр к запросу списка записей.
     *
     * @param WP_Query $query Запрос.
     */
    public function apply_filter( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        global $pagenow;
        if ( 'edit.php' !== $pagenow ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( ! in_array( $post_type, $this->get_post_types(), true ) ) {
            return;
        }

        if ( empty( $_GET['mzen_channel_filter'] ) ) {
            return;
        }

        $filter = sanitize_key( wp_unslash( $_GET['mzen_channel_filter'] ) );

        if ( '__none__' === $filter ) {
            $query->set( 'meta_query', array(
                array(
                    'key'     => '_mzen_channel',
                    'compare' => 'NOT EXISTS',
                ),
            ) );
            return;
        }

        $query->set( 'meta_query', array(
            array(
                'key'     => '_mzen_channel',
                'value'   => $filter,
                'compare' => '=',
            ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Массовые действия (bulk actions).
    // -------------------------------------------------------------------------

    /**
     * Регистрируем хуки для bulk actions для всех наших типов записей.
     */
    private function register_bulk_actions() {
        foreach ( $this->get_post_types() as $pt ) {
            add_filter( "bulk_actions-edit-{$pt}", array( $this, 'add_bulk_actions' ) );
            add_filter( "handle_bulk_actions-edit-{$pt}", array( $this, 'handle_bulk_actions' ), 10, 3 );
        }
        add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );
    }

    /**
     * Добавляем пункты в выпадающий список массовых действий.
     *
     * @param array $actions Существующие действия.
     * @return array
     */
    public function add_bulk_actions( $actions ) {
        $channels = $this->channels->all();

        if ( ! empty( $channels ) ) {
            foreach ( $channels as $slug => $channel ) {
                $title                               = ! empty( $channel['title'] ) ? $channel['title'] : $slug;
                $actions[ 'mzen_assign_' . $slug ] = sprintf(
                    /* translators: %s — название канала */
                    __( 'Мульти.Дзен: Назначить канал «%s»', 'multi-rss-for-zen' ),
                    $title
                );
            }
        }

        $actions['mzen_unassign']          = __( 'Мульти.Дзен: Снять привязку к каналу', 'multi-rss-for-zen' );
        $actions['mzen_exclude_from_rss']  = __( 'Мульти.Дзен: Исключить из RSS', 'multi-rss-for-zen' );
        $actions['mzen_include_to_rss']    = __( 'Мульти.Дзен: Включить в RSS', 'multi-rss-for-zen' );

        return $actions;
    }

    /**
     * Обрабатываем bulk action.
     *
     * @param string $redirect_to URL, куда редиректить после.
     * @param string $action      Выбранное действие.
     * @param array  $post_ids    ID выделенных записей.
     * @return string
     */
    public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
            return $redirect_to;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return $redirect_to;
        }

        $processed = 0;

        // --- Назначить канал ---
        // При массовом назначении канала записи «начинают следовать»
        // настройкам канала: персональные мета-поля удаляются, чтобы
        // значения брались из настроек канала.
        if ( strpos( $action, 'mzen_assign_' ) === 0 ) {
            $slug = substr( $action, strlen( 'mzen_assign_' ) );
            $channel = $this->channels->get( $slug );
            if ( ! $channel ) {
                return $redirect_to;
            }

            $excludedefault = ! empty( $channel['excludedefault'] ) && 'enabled' === $channel['excludedefault'];

            // Мета-поля, зависящие от канала. Удаляем, чтобы записи
            // взяли значения из настроек канала.
            $value_metas = array(
                '_mzen_category',
                '_mzen_type_article',
                '_mzen_type_platform',
                '_mzen_index',
            );

            foreach ( $post_ids as $post_id ) {
                $post_id = (int) $post_id;
                update_post_meta( $post_id, '_mzen_channel', $slug );

                foreach ( $value_metas as $meta_key ) {
                    delete_post_meta( $post_id, $meta_key );
                }

                if ( $excludedefault ) {
                    update_post_meta( $post_id, '_mzen_rss_enabled', 'yes' );
                } else {
                    delete_post_meta( $post_id, '_mzen_rss_enabled' );
                }
                $processed++;
            }

            $redirect_to = add_query_arg( 'mzen_bulk_assigned', $processed, $redirect_to );
            $redirect_to = add_query_arg( 'mzen_bulk_channel', $slug, $redirect_to );
            return $redirect_to;
        }

        // --- Снять привязку ---
        if ( 'mzen_unassign' === $action ) {
            foreach ( $post_ids as $post_id ) {
                delete_post_meta( (int) $post_id, '_mzen_channel' );
                $processed++;
            }
            return add_query_arg( 'mzen_bulk_unassigned', $processed, $redirect_to );
        }

        // --- Исключить из RSS ---
        if ( 'mzen_exclude_from_rss' === $action ) {
            foreach ( $post_ids as $post_id ) {
                update_post_meta( (int) $post_id, '_mzen_rss_enabled', 'yes' );
                $processed++;
            }
            return add_query_arg( 'mzen_bulk_excluded', $processed, $redirect_to );
        }

        // --- Включить в RSS ---
        if ( 'mzen_include_to_rss' === $action ) {
            foreach ( $post_ids as $post_id ) {
                delete_post_meta( (int) $post_id, '_mzen_rss_enabled' );
                $processed++;
            }
            return add_query_arg( 'mzen_bulk_included', $processed, $redirect_to );
        }

        return $redirect_to;
    }

    /**
     * Уведомление после выполнения bulk action.
     */
    public function bulk_action_notice() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit' !== $screen->base ) {
            return;
        }

        // --- Назначено ---
        if ( ! empty( $_GET['mzen_bulk_assigned'] ) ) {
            $count   = (int) $_GET['mzen_bulk_assigned'];
            $slug    = isset( $_GET['mzen_bulk_channel'] ) ? sanitize_key( wp_unslash( $_GET['mzen_bulk_channel'] ) ) : '';
            $channel = $slug ? $this->channels->get( $slug ) : null;
            $title   = $channel && ! empty( $channel['title'] ) ? $channel['title'] : $slug;

            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( sprintf(
                    /* translators: 1: кол-во, 2: название канала */
                    _n(
                        'Запись назначена на канал «%2$s».',
                        'Записей назначено на канал «%2$s»: %1$d.',
                        $count,
                        'multi-rss-for-zen'
                    ),
                    $count,
                    $title
                ) )
            );
        }

        // --- Снята привязка ---
        if ( ! empty( $_GET['mzen_bulk_unassigned'] ) ) {
            $count = (int) $_GET['mzen_bulk_unassigned'];
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( sprintf(
                    _n( 'Привязка снята у %d записи.', 'Привязка снята у %d записей.', $count, 'multi-rss-for-zen' ),
                    $count
                ) )
            );
        }

        // --- Исключено ---
        if ( ! empty( $_GET['mzen_bulk_excluded'] ) ) {
            $count = (int) $_GET['mzen_bulk_excluded'];
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( sprintf(
                    _n( '%d запись исключена из RSS.', '%d записей исключено из RSS.', $count, 'multi-rss-for-zen' ),
                    $count
                ) )
            );
        }

        // --- Включено ---
        if ( ! empty( $_GET['mzen_bulk_included'] ) ) {
            $count = (int) $_GET['mzen_bulk_included'];
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( sprintf(
                    _n( '%d запись включена в RSS.', '%d записей включено в RSS.', $count, 'multi-rss-for-zen' ),
                    $count
                ) )
            );
        }
    }
}