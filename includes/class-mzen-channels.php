<?php
/**
 * CRUD каналов, поиск, дублирование, экспорт/импорт.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Channels
 */
class MZEN_Channels {

    /**
     * Имя опции в БД.
     */
    const OPTION_KEY = 'mzen_channels';

    /**
     * Имя опции канала по умолчанию.
     */
    const DEFAULT_OPTION = 'mzen_default_channel';

    /**
     * @var array|null Кеш каналов в рамках запроса.
     */
    private $cache = null;

    /**
     * Получить все каналы.
     *
     * @return array Массив [ slug => [ параметры канала ] ].
     */
    public function all() {
        if ( null === $this->cache ) {
            $channels = get_option( self::OPTION_KEY, array() );
            if ( ! is_array( $channels ) ) {
                $channels = array();
            }

            // Сортируем по order, потом по title.
            uasort( $channels, function ( $a, $b ) {
                $oa = isset( $a['order'] ) ? (int) $a['order'] : 0;
                $ob = isset( $b['order'] ) ? (int) $b['order'] : 0;
                if ( $oa !== $ob ) {
                    return $oa - $ob;
                }
                return strcasecmp( isset( $a['title'] ) ? $a['title'] : '', isset( $b['title'] ) ? $b['title'] : '' );
            } );

            $this->cache = $channels;
        }
        return $this->cache;
    }

    /**
     * Получить один канал.
     *
     * @param string $slug Slug канала.
     * @return array|null
     */
    public function get( $slug ) {
        $all = $this->all();
        return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
    }

    /**
     * Сохранить канал (новый или существующий).
     *
     * @param string $slug Slug канала.
     * @param array  $data Параметры.
     * @return bool
     */
    public function save( $slug, $data ) {
        $slug = $this->sanitize_slug( $slug );
        if ( '' === $slug ) {
            return false;
        }

        $all        = $this->all();
        $data['slug'] = $slug;

        // Приводим data к канонической структуре.
        $data = $this->normalize( $data );

        $all[ $slug ] = $data;

        $this->cache = null;
        return update_option( self::OPTION_KEY, $all );
    }

    /**
     * Удалить канал.
     *
     * @param string $slug Slug.
     * @return bool
     */
    public function delete( $slug ) {
        $all = $this->all();
        if ( ! isset( $all[ $slug ] ) ) {
            return false;
        }
        unset( $all[ $slug ] );

        // Если удалили канал по умолчанию — сбрасываем опцию.
        if ( get_option( self::DEFAULT_OPTION ) === $slug ) {
            delete_option( self::DEFAULT_OPTION );
        }

        $this->cache = null;
        return update_option( self::OPTION_KEY, $all );
    }

    /**
     * Продублировать канал.
     *
     * @param string $source_slug Slug источника.
     * @param string $new_slug    Желаемый slug (если занят — добавится суффикс).
     * @return string|false Новый slug или false.
     */
    public function duplicate( $source_slug, $new_slug = '' ) {
        $source = $this->get( $source_slug );
        if ( ! $source ) {
            return false;
        }

        if ( '' === $new_slug ) {
            $new_slug = $source_slug . '-copy';
        }
        $new_slug = $this->unique_slug( $new_slug );

        $copy              = $source;
        $copy['slug']      = $new_slug;
        $copy['title']     = ( isset( $source['title'] ) ? $source['title'] : '' ) . ' (копия)';

        $all               = $this->all();
        $all[ $new_slug ]  = $copy;

        $this->cache = null;
        update_option( self::OPTION_KEY, $all );
        return $new_slug;
    }

    /**
     * Создать канал по умолчанию (при первой активации).
     *
     * @return string Slug созданного канала.
     */
    public function create_default() {
        $slug = $this->unique_slug( 'multizen' );
        $this->save( $slug, array(
            'title' => get_bloginfo( 'name' ),
            'slug'  => $slug,
            'order' => 10,
        ) );
        update_option( self::DEFAULT_OPTION, $slug );
        return $slug;
    }

    /**
     * Получить slug канала по умолчанию.
     *
     * @return string
     */
    public function get_default_slug() {
        $slug = get_option( self::DEFAULT_OPTION );
        if ( $slug && $this->get( $slug ) ) {
            return $slug;
        }
        // Если канал по умолчанию удалён или не задан — берём первый из списка.
        $all = $this->all();
        return $all ? key( $all ) : '';
    }

    /**
     * Установить канал по умолчанию.
     *
     * @param string $slug Slug.
     * @return bool
     */
    public function set_default( $slug ) {
        if ( ! $this->get( $slug ) ) {
            return false;
        }
        return update_option( self::DEFAULT_OPTION, $slug );
    }

    /**
     * Поиск по каналам.
     *
     * @param string $term Термин поиска (по title, slug, description).
     * @return array Отфильтрованный массив каналов.
     */
    public function search( $term ) {
        $term = trim( (string) $term );
        if ( '' === $term ) {
            return $this->all();
        }
        $needle = mb_strtolower( $term );
        $result = array();
        foreach ( $this->all() as $slug => $channel ) {
            $haystack = mb_strtolower(
                ( isset( $channel['title'] ) ? $channel['title'] : '' ) . ' ' .
                $slug . ' ' .
                ( isset( $channel['description'] ) ? $channel['description'] : '' )
            );
            if ( false !== mb_strpos( $haystack, $needle ) ) {
                $result[ $slug ] = $channel;
            }
        }
        return $result;
    }

    /**
     * Экспортировать все каналы в JSON.
     *
     * @return string
     */
    public function export_json() {
        return wp_json_encode(
            array(
                'version'  => MZEN_VERSION,
                'exported' => current_time( 'mysql' ),
                'channels' => $this->all(),
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Импортировать каналы из JSON.
     *
     * @param string $json JSON-строка.
     * @param string $mode 'replace' — полная замена, 'merge' — объединить.
     * @return array|WP_Error Массив [ imported => N, updated => M ] или WP_Error.
     */
    public function import_json( $json, $mode = 'merge' ) {
        $data = json_decode( (string) $json, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error( 'mzen_invalid_json', __( 'Некорректный JSON: ', 'multi-rss-for-zen' ) . json_last_error_msg() );
        }

        if ( ! is_array( $data ) || ! isset( $data['channels'] ) || ! is_array( $data['channels'] ) ) {
            return new WP_Error( 'mzen_invalid_structure', __( 'Неверная структура JSON: нет ключа "channels".', 'multi-rss-for-zen' ) );
        }

        $incoming = $data['channels'];
        $current  = 'replace' === $mode ? array() : $this->all();

        $imported = 0;
        $updated  = 0;

        foreach ( $incoming as $slug => $channel ) {
            $slug = $this->sanitize_slug( $slug );
            if ( '' === $slug ) {
                continue;
            }
            $channel = $this->normalize( $channel );
            $channel['slug'] = $slug;

            if ( isset( $current[ $slug ] ) ) {
                $updated++;
            } else {
                $imported++;
            }
            $current[ $slug ] = $channel;
        }

        $this->cache = null;
        update_option( self::OPTION_KEY, $current );

        return array(
            'imported' => $imported,
            'updated'  => $updated,
            'total'    => count( $current ),
        );
    }

    /**
     * Очистить кеш (для тестов и после мутаций).
     */
    public function flush_cache() {
        $this->cache = null;
    }

    // -------------------------------------------------------------------------
    // Внутренние методы.
    // -------------------------------------------------------------------------

    /**
     * Приводит slug к допустимому виду: только a-z, 0-9, дефис.
     *
     * @param string $slug Slug.
     * @return string
     */
    public function sanitize_slug( $slug ) {
        $slug = strtolower( (string) $slug );
        $slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
        return (string) $slug;
    }

    /**
     * Возвращает свободный slug, добавляя -2, -3 и т.д., если занят.
     *
     * @param string $slug Желаемый slug.
     * @return string
     */
    public function unique_slug( $slug ) {
        $slug = $this->sanitize_slug( $slug );
        if ( '' === $slug ) {
            $slug = 'channel';
        }
        $all   = $this->all();
        $base  = $slug;
        $i     = 2;
        while ( isset( $all[ $slug ] ) ) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /**
     * Приводит массив канала к канонической структуре.
     * Заполняет отсутствующие поля значениями по умолчанию.
     *
     * @param array $data Входные данные.
     * @return array
     */
    public function normalize( $data ) {
        $defaults = self::default_channel();

        foreach ( $defaults as $key => $value ) {
            if ( ! array_key_exists( $key, $data ) ) {
                $data[ $key ] = $value;
            }
        }

        // Приведение типов.
        $data['number']        = max( 1, (int) $data['number'] );
        $data['order']         = (int) $data['order'];
        $data['delay_minutes'] = max( 0, (int) $data['delay_minutes'] );

        // Slug всегда строка.
        $data['slug'] = $this->sanitize_slug( isset( $data['slug'] ) ? $data['slug'] : '' );

        return $data;
    }

    /**
     * Структура канала по умолчанию.
     *
     * @return array
     */
    public static function default_channel() {
        return array(
            'slug'               => '',
            'title'              => get_bloginfo( 'name' ),
            'link'               => get_bloginfo( 'url' ),
            'description'        => get_bloginfo( 'description' ),
            'language'           => 'ru',
            'category'           => 'Общество',
            'rating'             => 'Нет (не для взрослых)',
            'number'             => 20,
            'type'               => 'post',
            'author'             => '',
            'figcaption'         => 'Использовать подписи',
            'imgauthorselect'    => 'Автор записи',
            'imgauthor'          => '',
            'thumbnail'          => 'disabled',
            'selectthumb'        => '',
            'seodesc'            => 'disabled',
            'seoplugin'          => 'Yoast SEO',
            'excludetags'        => 'enabled',
            'excludetagslist'    => '<div>,<span>',
            'excludetags2'       => 'enabled',
            'excludetagslist2'   => '<iframe>,<script>,<ins>,<style>,<object>',
            'excludecontent'     => 'disabled',
            'excludecontentlist' => "<!--more-->\n<p></p>\n<p>&nbsp;</p>",
            'queryselect'        => 'Все таксономии, кроме исключенных',
            'taxlist'            => '',
            'addtaxlist'         => '',
            'excerpt'            => 'disabled',
            'excludedefault'     => 'disabled',
            'typearticle'        => 'false',
            'typeplatform'       => 'native-no',
            'index'              => 'index',
            'delay_minutes'      => 0,
            'order'              => 0,
        );
    }
}