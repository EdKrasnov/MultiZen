<?php
/**
 * Миграция данных со старого формата (v1) на новый (v2, мультиканальный).
 *
 * Что делает:
 *  1. Превращает опцию mzen_options (одиночный массив настроек) в mzen_channels.
 *  2. Переносит мета-поля постов со старых ключей на новые. Старые НЕ удаляет.
 *  3. Ставит флаг mzen_db_version, чтобы повторно не запускаться.
 *
 * ВАЖНО: этот класс НЕ назначает записи на каналы массово.
 * Запись попадает в ленту только если редактор явно выбрал канал в метабоксе.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Migrator
 */
class MZEN_Migrator {

    const OLD_OPTION        = 'mzen_options';
    const DB_VERSION_OPTION = 'mzen_db_version';

    /**
     * Карта старых мета-ключей → новых.
     *
     * @return array
     */
    public static function meta_map() {
        return array(
            'mzencategory_meta_value'     => '_mzen_category',
            'mzenrating_meta_value'       => '_mzen_rating',
            'mzentypearticle_meta_value'  => '_mzen_type_article',
            'mzentypeplatform_meta_value' => '_mzen_type_platform',
            'mzenindex_meta_value'        => '_mzen_index',
            'mzenrssenabled_meta_value'   => '_mzen_rss_enabled',
        );
    }

    /**
     * Запустить миграцию. Многоразовый вызов безопасен.
     */
    public static function migrate() {
        $done = get_option( self::DB_VERSION_OPTION );
        if ( $done && version_compare( $done, MZEN_VERSION, '>=' ) ) {
            return;
        }

        self::migrate_options_to_channels();
        self::migrate_post_meta();

        update_option( self::DB_VERSION_OPTION, MZEN_VERSION );
    }

    /**
     * Шаг 1. mzen_options → mzen_channels.
     */
    private static function migrate_options_to_channels() {
        $channels = get_option( 'mzen_channels' );
        if ( is_array( $channels ) && ! empty( $channels ) ) {
            return;
        }

        $old = get_option( self::OLD_OPTION );
        if ( ! is_array( $old ) || empty( $old ) ) {
            return;
        }

        $slug = 'multizen';
        if ( ! empty( $old['yzrssname'] ) ) {
            $slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $old['yzrssname'] ) );
        }
        if ( '' === $slug ) {
            $slug = 'multizen';
        }

        $field_map       = self::old_to_new_field_map();
        $channel         = MZEN_Channels::default_channel();
        $channel['slug'] = $slug;

        foreach ( $field_map as $old_key => $new_key ) {
            if ( array_key_exists( $old_key, $old ) && '' !== $old[ $old_key ] ) {
                $channel[ $new_key ] = $old[ $old_key ];
            }
        }
        $channel['delay_minutes'] = 0;

        $channels = new MZEN_Channels();
        $channel  = $channels->normalize( $channel );

        update_option( 'mzen_channels', array( $slug => $channel ) );
        update_option( 'mzen_default_channel', $slug );

        $channels->flush_cache();
    }

    /**
     * Шаг 2. Перенос мета-полей постов: старые ключи → новые.
     * Старые ключи остаются в БД (backup на 2-3 месяца).
     */
    private static function migrate_post_meta() {
        global $wpdb;

        foreach ( self::meta_map() as $old_key => $new_key ) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $old_key
                )
            );
            if ( $count < 1 ) {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                     SELECT pm.post_id, %s, pm.meta_value
                     FROM {$wpdb->postmeta} pm
                     LEFT JOIN {$wpdb->postmeta} new_pm
                        ON new_pm.post_id = pm.post_id
                       AND new_pm.meta_key = %s
                     WHERE pm.meta_key = %s
                       AND new_pm.meta_id IS NULL",
                    $new_key,
                    $new_key,
                    $old_key
                )
            );
        }
    }

    /**
     * Карта старых полей mzen_options → новых полей канала.
     *
     * @return array
     */
    private static function old_to_new_field_map() {
        return array(
            'yztitle'              => 'title',
            'yzlink'               => 'link',
            'yzdescription'        => 'description',
            'yzlanguage'           => 'language',
            'yzcategory'           => 'category',
            'yzrating'             => 'rating',
            'yznumber'             => 'number',
            'yztype'               => 'type',
            'yzauthor'             => 'author',
            'yzfigcaption'         => 'figcaption',
            'yzimgauthorselect'    => 'imgauthorselect',
            'yzimgauthor'          => 'imgauthor',
            'yzthumbnail'          => 'thumbnail',
            'yzselectthumb'        => 'selectthumb',
            'yzseodesc'            => 'seodesc',
            'yzseoplugin'          => 'seoplugin',
            'yzexcludetags'        => 'excludetags',
            'yzexcludetagslist'    => 'excludetagslist',
            'yzexcludetags2'       => 'excludetags2',
            'yzexcludetagslist2'   => 'excludetagslist2',
            'yzexcludecontent'     => 'excludecontent',
            'yzexcludecontentlist' => 'excludecontentlist',
            'yzqueryselect'        => 'queryselect',
            'yztaxlist'            => 'taxlist',
            'yzaddtaxlist'         => 'addtaxlist',
            'yzexcerpt'            => 'excerpt',
            'yzexcludedefault'     => 'excludedefault',
            'yztypearticle'        => 'typearticle',
            'yztypeplatform'       => 'typeplatform',
            'yzindex'              => 'index',
        );
    }

    /**
     * Ручной запуск миграции: сбрасывает флаг и запускает заново.
     */
    public static function force_migrate() {
        delete_option( self::DB_VERSION_OPTION );
        self::migrate();
    }

    /**
     * Какая версия зафиксирована в mzen_db_version.
     *
     * @return string
     */
    public static function current_version() {
        return (string) get_option( self::DB_VERSION_OPTION, '' );
    }

    /**
     * Сколько старых мета-ключей ещё осталось в БД.
     *
     * @return int
     */
    public static function count_legacy_meta() {
        global $wpdb;
        $total = 0;
        foreach ( array_keys( self::meta_map() ) as $key ) {
            $total += (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $key
                )
            );
        }
        return $total;
    }

    /**
     * Удалить старые мета-ключи (кнопка «Очистить backup»).
     *
     * @return int Сколько записей удалено.
     */
    public static function cleanup_legacy_meta() {
        global $wpdb;
        $total = 0;
        foreach ( array_keys( self::meta_map() ) as $key ) {
            $total += (int) $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ) );
        }
        return $total;
    }
}