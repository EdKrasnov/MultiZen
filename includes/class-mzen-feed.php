<?php
/**
 * Регистрация фидов и рендер ленты для каждого канала.
 *
 * Логика:
 *  - На init регистрируем add_feed() для всех каналов.
 *  - Если установлен флаг mzen_flush_needed (после активации) — пересобираем
 *    правила перезаписи, чтобы URL фидов сразу заработали.
 *  - На do_feed_<slug> вызывается render(), который:
 *      1. Находит канал по get_query_var('feed').
 *      2. Строит WP_Query с учётом:
 *          — мета-поля _mzen_channel = slug канала;
 *          — задержки публикации (delay_minutes);
 *          — исключения записей с _mzen_rss_enabled = yes;
 *          — таксономий (если включено).
 *      3. Отдаёт Content-Type: application/rss+xml.
 *      4. Подключает XML-шаблон из templates/feed-rss2.php.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Feed
 */
class MZEN_Feed {

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
     * Регистрация хуков.
     */
    private function hooks() {
        add_action( 'init', array( $this, 'add_feed_rewrite_rules' ), 5 );
        add_action( 'init', array( $this, 'register_feeds' ) );
        add_filter( 'feed_content_type', array( $this, 'fix_content_type' ), 10, 2 );
        add_action( 'template_redirect', array( $this, 'send_robots_header' ), 999999 );    }

    /**
     * Регистрируем add_feed() для каждого канала.
     * Если установлен флаг mzen_flush_needed (после активации) — пересобираем
     * правила перезаписи, чтобы URL фидов сразу заработали.
     */
    public function register_feeds() {
        foreach ( $this->channels->all() as $slug => $channel ) {
            add_feed( $slug, array( $this, 'render' ) );
        }

        if ( get_option( 'mzen_flush_needed' ) ) {
            flush_rewrite_rules();
            delete_option( 'mzen_flush_needed' );
        }
    }

    /**
     * Добавляем rewrite-правило для фида канала с высоким приоритетом.
     * Гарантирует, что /feed/<slug>/ не перехватывается правилами страниц.
     */
    public function add_feed_rewrite_rules() {
        foreach ( $this->channels->all() as $slug => $channel ) {
            add_rewrite_rule(
                '^feed/' . preg_quote( $slug, '/' ) . '/?$',
                'index.php?feed=' . $slug,
                'top'
            );
        }
    }


    /**
     * Принудительно выставляем content type для наших фидов.
     *
     * @param string $content_type Тип контента.
     * @param string $type         Тип фида.
     * @return string
     */
    public function fix_content_type( $content_type, $type ) {
        if ( $this->channels->get( $type ) ) {
            $content_type = 'application/rss+xml';
        }
        return $content_type;
    }

    /**
     * X-Robots-Tag: index, follow для наших фидов.
     */
    public function send_robots_header() {
        $feed = get_query_var( 'feed' );
        if ( $feed && $this->channels->get( $feed ) ) {
            header( 'X-Robots-Tag: index, follow', true );
        }
    }

    /**
     * Точка входа рендера ленты.
     * Вызывается через do_feed_<slug>.
     */
    public function render() {
        $slug    = get_query_var( 'feed' );
        $channel = $slug ? $this->channels->get( $slug ) : null;

        if ( ! $channel ) {
            status_header( 404 );
            nocache_headers();
            wp_die( esc_html__( 'Канал не найден.', 'multi-rss-for-zen' ), '', array( 'response' => 404 ) );
        }

        $query = $this->build_query( $channel );

        // Заголовки.
        header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );

        // Шаблон получает переменные $channel и $query.
        $template = MZEN_DIR . 'templates/feed-rss2.php';
        if ( ! file_exists( $template ) ) {
            status_header( 500 );
            wp_die( esc_html__( 'Шаблон ленты не найден.', 'multi-rss-for-zen' ), '', array( 'response' => 500 ) );
        }

        include $template;
    }

    /**
     * Строит WP_Query для канала.
     *
     * @param array $channel Параметры канала.
     * @return WP_Query
     */
    public function build_query( $channel ) {
        $slug  = isset( $channel['slug'] ) ? $channel['slug'] : '';
        $types = array_filter( array_map( 'trim', explode( ',', (string) MZEN_Helpers::opt( $channel, 'type', 'post' ) ) ) );
        if ( empty( $types ) ) {
            $types = array( 'post' );
        }

        $number = max( 1, (int) MZEN_Helpers::opt( $channel, 'number', 20 ) );

        $args = array(
            'post_type'           => $types,
            'post_status'         => 'publish',
            'posts_per_page'      => $number,
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
        );

        // -------- Фильтр по каналу: запись относится к этому каналу. --------
        $meta_query = array( 'relation' => 'AND' );

        $meta_query[] = array(
            'key'     => '_mzen_channel',
            'value'   => $slug,
            'compare' => '=',
        );

        // Исключаем записи с галочкой "Исключить из RSS".
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_mzen_rss_enabled',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_mzen_rss_enabled',
                'value'   => 'yes',
                'compare' => '!=',
            ),
        );

        // Отложенная публикация: запись попадёт в фид, только если _mzen_publish_at
        // не установлено ИЛИ уже наступило (сравнение локальное время → локальное время).
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_mzen_publish_at',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_mzen_publish_at',
                'value'   => current_time( 'mysql' ),
                'compare' => '<=',
                'type'    => 'DATETIME',
            ),
        );

        // -------- Задержка публикации (п.4 ТЗ). --------
        $delay = max( 0, (int) MZEN_Helpers::opt( $channel, 'delay_minutes', 0 ) );
        if ( $delay > 0 ) {
            $cutoff             = gmdate( 'Y-m-d H:i:s', time() - $delay * MINUTE_IN_SECONDS );
            $args['date_query'] = array(
                array(
                    'column'    => 'post_date_gmt',
                    'before'    => $cutoff,
                    'inclusive' => true,
                ),
            );
        }

        // -------- Таксономии. --------
        $tax_query = $this->build_tax_query( $channel );
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $args['meta_query'] = $meta_query;

        // Даём возможность другим плагинам вмешаться.
        $args = apply_filters( 'mzen_query_args', $args, $channel );

        return new WP_Query( $args );
    }

    /**
     * Строит tax_query по параметрам канала.
     *
     * @param array $channel Канал.
     * @return array
     */
    private function build_tax_query( $channel ) {
        $query_select = MZEN_Helpers::opt( $channel, 'queryselect', 'Все таксономии, кроме исключенных' );
        $taxlist      = MZEN_Helpers::opt( $channel, 'taxlist', '' );
        $addtaxlist   = MZEN_Helpers::opt( $channel, 'addtaxlist', '' );

        $tax_query = array();

        if ( 'Все таксономии, кроме исключенных' === $query_select && $taxlist ) {
            $tax_query = array( 'relation' => 'AND' );
            foreach ( $this->parse_tax_lines( $taxlist ) as $line ) {
                $tax_query[] = array(
                    'taxonomy' => $line['taxonomy'],
                    'field'    => 'id',
                    'terms'    => $line['terms'],
                    'operator' => 'NOT IN',
                );
            }
        }

        if ( 'Только указанные таксономии' === $query_select ) {
            if ( ! $addtaxlist ) {
                // Заглушка: ни одна запись не попадёт — лента пустая.
                $addtaxlist = 'category:10000000';
            }
            $tax_query = array( 'relation' => 'OR' );
            foreach ( $this->parse_tax_lines( $addtaxlist ) as $line ) {
                $tax_query[] = array(
                    'taxonomy' => $line['taxonomy'],
                    'field'    => 'id',
                    'terms'    => $line['terms'],
                    'operator' => 'IN',
                );
            }
        }

        return $tax_query;
    }

    /**
     * Разбирает строки вида "taxonomy:1,2,3" в массив.
     *
     * @param string $text Многострочный текст.
     * @return array
     */
    private function parse_tax_lines( $text ) {
        $result = array();
        $lines  = array_filter( array_map( 'trim', explode( "\n", (string) $text ) ) );
        foreach ( $lines as $line ) {
            $parts = explode( ':', $line, 2 );
            if ( count( $parts ) < 2 ) {
                continue;
            }
            $taxonomy = trim( $parts[0] );
            $terms    = array_filter( array_map( 'intval', explode( ',', $parts[1] ) ) );
            if ( '' === $taxonomy || empty( $terms ) ) {
                continue;
            }
            $result[] = array(
                'taxonomy' => $taxonomy,
                'terms'    => $terms,
            );
        }
        return $result;
    }
}