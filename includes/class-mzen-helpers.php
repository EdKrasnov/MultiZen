<?php
/**
 * Утилиты: mime-type, обработка тегов, атрибутов, подготовка контента для RSS.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MZEN_Helpers
 */
final class MZEN_Helpers {

    /**
     * Возвращает MIME-тип по расширению файла.
     *
     * @param string $file URL или путь.
     * @return string
     */
    public static function mime_type( $file ) {
        $mime_type = array(
            'bmp'  => 'image/bmp',
            'gif'  => 'image/gif',
            'ico'  => 'image/x-icon',
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
            'psd'  => 'image/vnd.adobe.photoshop',
            'svg'  => 'image/svg+xml',
            'tiff' => 'image/tiff',
            'webp' => 'image/webp',
        );
        $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        return isset( $mime_type[ $extension ] ) ? $mime_type[ $extension ] : 'Unknown file type';
    }

    /**
     * Удаляет указанные теги ВМЕСТЕ с их содержимым.
     *
     * @param string $text    HTML.
     * @param string $tags    Список тегов через запятую: "<iframe>,<script>".
     * @param bool   $invert  Если true — удаляет все теги, КРОМЕ указанных.
     * @return string
     */
    public static function strip_tags_with_content( $text, $tags = '', $invert = false ) {
        preg_match_all( '/<(.+?)[\s]*\/?[\s]*>/si', trim( $tags ), $tags_array );
        $tags_array = array_unique( $tags_array[1] );

        $regex = '';
        if ( count( $tags_array ) > 0 ) {
            if ( ! $invert ) {
                $regex = '@<(?!(?:' . implode( '|', $tags_array ) . ')\b)(\w+)\b[^>]*?(>((?!<\1\b).)*?<\/\1|\/)>@si';
                $text  = preg_replace( $regex, '', $text );
            } else {
                $regex = '@<(' . implode( '|', $tags_array ) . ')\b[^>]*?(>((?!<\1\b).)*?<\/\1|\/)>@si';
                $text  = preg_replace( $regex, '', $text );
            }
        } elseif ( ! $invert ) {
            $regex = '@<(\w+)\b[^>]*?(>((?!<\1\b).)*?<\/\1|\/)>@si';
            $text  = preg_replace( $regex, '', $text );
        }

        if ( $regex && preg_match( $regex, $text ) ) {
            $text = self::strip_tags_with_content( $text, $tags, $invert );
        }

        return $text;
    }

    /**
     * Удаляет указанные теги, но СОХРАНЯЕТ их содержимое.
     *
     * @param string $text HTML.
     * @param string $tags Список тегов через запятую.
     * @return string
     */
    public static function strip_tags_without_content( $text, $tags = '' ) {
        preg_match_all( '/<(.+?)[\s]*\/?[\s]*>/si', trim( $tags ), $tags );
        $tags = array_unique( $tags[1] );

        if ( is_array( $tags ) && count( $tags ) > 0 ) {
            foreach ( $tags as $tag ) {
                $text = preg_replace( '/<\/?' . preg_quote( $tag, '/' ) . '(.|\s)*?>/', '', $text );
            }
        }
        return $text;
    }

    /**
     * Оставляет у тега <img> только разрешённые атрибуты.
     *
     * @param string $s           HTML.
     * @param array  $allowedattr Например: ['src', 'width', 'height'].
     * @return string
     */
    public static function strip_attributes( $s, $allowedattr = array() ) {
        if ( preg_match_all( '/<img[^>]*\s([^>]*)\/*>/msiU', $s, $res, PREG_SET_ORDER ) ) {
            foreach ( $res as $r ) {
                $tag   = $r[0];
                $attrs = array();
                preg_match_all( '/\s.*=([\'"]).*\1/msiU', ' ' . $r[1], $split, PREG_SET_ORDER );
                foreach ( $split as $spl ) {
                    $attrs[] = $spl[0];
                }
                $newattrs = array();
                foreach ( $attrs as $a ) {
                    $tmp = explode( '=', $a );
                    if ( trim( $a ) !== '' && ( ! isset( $tmp[1] ) || ( trim( $tmp[0] ) !== '' && ! in_array( strtolower( trim( $tmp[0] ) ), $allowedattr, true ) ) ) ) {
                        continue;
                    }
                    $newattrs[] = $a;
                }

                sort( $newattrs );

                $attrs_str = implode( ' ', $newattrs );
                $rpl       = str_replace( $r[1], $attrs_str, $tag );
                $rpl       = str_replace( "'", '"', $rpl );
                $rpl       = str_replace( '">', '" />', $rpl );
                $rpl       = str_replace( '"/>', '" />', $rpl );
                $rpl       = str_replace( '  ', ' ', $rpl );

                $pattern     = '/<img(.*?) height="(.*?)" (.*?) \/>/i';
                $replacement = '<img$1 $3 height="$2" />';
                $rpl         = preg_replace( $pattern, $replacement, $rpl );

                $s = str_replace( $tag, $rpl, $s );
            }
        }
        return $s;
    }

    /**
     * Формирует <description> для ленты.
     *
     * @param array $channel Параметры канала.
     * @return string
     */
    public static function the_excerpt_rss( $channel ) {
        $content = get_the_excerpt();
        $content = apply_filters( 'mzen_the_excerpt', $content );
        $content = apply_filters( 'convert_chars', $content );
        $content = apply_filters( 'ent2ncr', $content, 8 );
        return $content;
    }

    /**
     * Формирует <content:encoded> для ленты.
     *
     * @param array $channel Параметры канала.
     * @return string
     */
    public static function the_content_feed( $channel ) {
        if ( ! empty( $channel['excerpt'] ) && 'enabled' === $channel['excerpt'] ) {
            $content = '';
            if ( has_excerpt( get_the_ID() ) ) {
                $content = '<p>' . get_the_excerpt( get_the_ID() ) . '</p>';
            }
            $content .= apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) );
        } else {
            $content = apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) );
        }
        $content = apply_filters( 'mzen_the_content', $content );
        $content = str_replace( ']]>', ']]&gt;', $content );
        $content = apply_filters( 'wp_staticize_emoji', $content );
        $content = apply_filters( '_oembed_filter_feed_content', $content );
        return $content;
    }

    /**
     * Получает опцию канала с fallback-значением.
     *
     * @param array  $channel Канал.
     * @param string $key     Ключ.
     * @param mixed  $default Значение по умолчанию.
     * @return mixed
     */
    public static function opt( $channel, $key, $default = '' ) {
        return isset( $channel[ $key ] ) && '' !== $channel[ $key ] ? $channel[ $key ] : $default;
    }
}