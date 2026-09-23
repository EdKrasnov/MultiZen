<?php
/**
 * Шаблон метабокса «Мульти Дзен».
 *
 * Переменные, доступные из MZEN_Metabox::render():
 *   @var WP_Post $post
 *   @var array   $channels [ slug => channel_data ]
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var WP_Post $post */
/** @var array   $channels */

$current_channel = (string) get_post_meta( $post->ID, '_mzen_channel', true );
$current_category   = (string) get_post_meta( $post->ID, '_mzen_category', true );
$current_rating     = (string) get_post_meta( $post->ID, '_mzen_rating', true );
$current_article    = (string) get_post_meta( $post->ID, '_mzen_type_article', true );
$current_platform   = (string) get_post_meta( $post->ID, '_mzen_type_platform', true );
$current_index      = (string) get_post_meta( $post->ID, '_mzen_index', true );
$current_excluded   = get_post_meta( $post->ID, '_mzen_rss_enabled', true );

// Отложенная публикация: raw хранится как 'Y-m-d H:i:s', для input нужен 'Y-m-dTH:i'.
$current_publish_at_raw = (string) get_post_meta( $post->ID, '_mzen_publish_at', true );
$current_publish_at     = '';
if ( '' !== $current_publish_at_raw ) {
    $ts = strtotime( $current_publish_at_raw );
    if ( $ts ) {
        $current_publish_at = date( 'Y-m-d\TH:i', $ts );
    }
}

$selected_channel = $current_channel ? ( isset( $channels[ $current_channel ] ) ? $channels[ $current_channel ] : null ) : null;

$fallback_category = $selected_channel && ! empty( $selected_channel['category'] ) ? $selected_channel['category'] : '';
$fallback_article  = $selected_channel && ! empty( $selected_channel['typearticle'] ) ? $selected_channel['typearticle'] : 'false';
$fallback_platform = $selected_channel && ! empty( $selected_channel['typeplatform'] ) ? $selected_channel['typeplatform'] : 'native-no';
$fallback_index    = $selected_channel && ! empty( $selected_channel['index'] ) ? $selected_channel['index'] : 'index';
$fallback_rating   = $selected_channel && ! empty( $selected_channel['rating'] ) ? $selected_channel['rating'] : 'Нет (не для взрослых)';

$display_category = $current_category ?: $fallback_category;
$display_article  = $current_article ?: $fallback_article;
$display_platform = $current_platform ?: $fallback_platform;
$display_index    = $current_index ?: $fallback_index;
$display_rating   = $current_rating ?: $fallback_rating;

$categories = array(
    'Происшествия', 'Политика', 'Война', 'Общество', 'Экономика', 'Спорт',
    'Технологии', 'Наука', 'Игры', 'Музыка', 'Литература', 'Кино', 'Культура',
    'Мода', 'Знаменитости', 'Психология', 'Здоровье', 'Авто', 'Дом', 'Хобби',
    'Еда', 'Дизайн', 'Фотографии', 'Юмор', 'Природа', 'Путешествия',
);

$post_date_local = get_post_field( 'post_date', $post->ID );

wp_nonce_field( 'mzen_save_metabox_' . $post->ID, 'mzen_meta_nonce' );
?>

<div class="mzen-metabox" data-post-date="<?php echo esc_attr( $post_date_local ); ?>">

    <p class="mzen-field mzen-field-channel">
        <label for="mzen_channel"><strong><?php esc_html_e( 'Канал:', 'multi-rss-for-zen' ); ?></strong></label>
        <select name="mzen_channel" id="mzen_channel" class="widefat" style="max-width: 350px;">
            <option value=""><?php esc_html_e( '— Канал НЕ выбран —', 'multi-rss-for-zen' ); ?></option>
            <?php foreach ( $channels as $slug => $channel ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_channel, $slug ); ?>>
                    <?php echo esc_html( ! empty( $channel['title'] ) ? $channel['title'] : $slug ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="description"><?php esc_html_e( 'Запись попадёт только в выбранный канал. Если ничего не выбрано — в лентах не появится.', 'multi-rss-for-zen' ); ?></span>
    </p>

    <table class="form-table mzen-fields">
        <tr>
            <th><label for="mzen_category"><?php esc_html_e( 'Тематика:', 'multi-rss-for-zen' ); ?></label></th>
            <td>
                <select name="mzen_category" id="mzen_category" class="mzen-category">
                    <option value=""><?php esc_html_e( '— по умолчанию канала —', 'multi-rss-for-zen' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $display_category, $cat ); ?>>
                            <?php echo esc_html( $cat ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Подставляется автоматически при выборе канала. Можно переопределить.', 'multi-rss-for-zen' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="mzen_type_article"><?php esc_html_e( 'Тип статьи:', 'multi-rss-for-zen' ); ?></label></th>
            <td>
                <select name="mzen_type_article" id="mzen_type_article">
                    <option value="true" <?php selected( $display_article, 'true' ); ?>><?php esc_html_e( 'Новость', 'multi-rss-for-zen' ); ?></option>
                    <option value="false" <?php selected( $display_article, 'false' ); ?>><?php esc_html_e( 'Материал', 'multi-rss-for-zen' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="mzen_type_platform"><?php esc_html_e( 'Публикация:', 'multi-rss-for-zen' ); ?></label></th>
            <td>
                <select name="mzen_type_platform" id="mzen_type_platform">
                    <option value="native-yes" <?php selected( $display_platform, 'native-yes' ); ?>><?php esc_html_e( 'Опубликовать в Дзене', 'multi-rss-for-zen' ); ?></option>
                    <option value="native-draft" <?php selected( $display_platform, 'native-draft' ); ?>><?php esc_html_e( 'Сохранить как черновик в Дзене', 'multi-rss-for-zen' ); ?></option>
                    <option value="native-no" <?php selected( $display_platform, 'native-no' ); ?>><?php esc_html_e( 'Публикация с сайта', 'multi-rss-for-zen' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="mzen_index"><?php esc_html_e( 'Индексация:', 'multi-rss-for-zen' ); ?></label></th>
            <td>
                <select name="mzen_index" id="mzen_index">
                    <option value="index" <?php selected( $display_index, 'index' ); ?>><?php esc_html_e( 'Индексировать', 'multi-rss-for-zen' ); ?></option>
                    <option value="noindex" <?php selected( $display_index, 'noindex' ); ?>><?php esc_html_e( 'Не индексировать', 'multi-rss-for-zen' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="mzen_publish_at"><?php esc_html_e( 'Отложенная публикация в RSS:', 'multi-rss-for-zen' ); ?></label></th>
            <td>
                <input type="datetime-local" name="mzen_publish_at" id="mzen_publish_at" value="<?php echo esc_attr( $current_publish_at ); ?>" />
                <button type="button" class="button button-small" id="mzen_publish_at_clear"><?php esc_html_e( 'Очистить', 'multi-rss-for-zen' ); ?></button>
                <p class="description">
                    <?php esc_html_e( 'Когда запись должна появиться в RSS. Оставьте пусто — запись уйдёт по общим правилам канала (post_date + задержка).', 'multi-rss-for-zen' ); ?>
                </p>
                <p class="description" style="color:#666;">
                    <?php esc_html_e( 'Минимальное допустимое время:', 'multi-rss-for-zen' ); ?>
                    <strong id="mzen_publish_at_min">—</strong>
                </p>
            </td>
        </tr>
    </table>

    <p class="mzen-checks">
        <label>
            <input type="checkbox" name="mzen_rating" id="mzen_rating" value="enabled" <?php checked( $display_rating, 'Да (для взрослых)' ); ?> />
            <?php esc_html_e( 'Запись с контентом для взрослых', 'multi-rss-for-zen' ); ?>
        </label>
        <br />
        <label>
            <input type="checkbox" name="mzen_rss_enabled" id="mzen_rss_enabled" value="yes" <?php checked( $current_excluded, 'yes' ); ?> />
            <?php esc_html_e( 'Исключить эту запись из RSS', 'multi-rss-for-zen' ); ?>
        </label>
    </p>

</div>