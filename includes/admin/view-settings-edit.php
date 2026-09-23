<?php
/**
 * Форма редактирования канала.
 *
 * @var array       $channel
 * @var string      $slug
 * @var bool        $is_default
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_new       = ( '' === $slug );
$list_url     = admin_url( 'options-general.php?page=' . MZEN_Admin_Settings::PAGE );
$form_action  = admin_url( 'admin-post.php' );

$image_sizes  = get_intermediate_image_sizes();
$categories   = array(
    'Происшествия','Политика','Война','Общество','Экономика','Спорт','Технологии',
    'Наука','Игры','Музыка','Литература','Кино','Культура','Мода','Знаменитости',
    'Психология','Здоровье','Авто','Дом','Хобби','Еда','Дизайн','Фотографии',
    'Юмор','Природа','Путешествия',
);

/** Helper для checkbox. */
$checked = function ( $value, $yes = 'enabled' ) {
    return ( (string) $value === (string) $yes ) ? 'checked="checked"' : '';
};
?>
<div class="wrap mzen-wrap">

    <h1>
        <?php echo $is_new ? esc_html__( 'Новый канал', 'multi-rss-for-zen' ) : esc_html__( 'Редактирование канала', 'multi-rss-for-zen' ); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'К списку каналов', 'multi-rss-for-zen' ); ?></a>
    </p>

    <?php if ( ! $is_new ) : ?>
        <?php
        $feed_url = home_url( '/feed/' . $slug . '/' );
        if ( ! get_option( 'permalink_structure' ) ) {
            $feed_url = home_url( '/?feed=' . $slug );
        }
        ?>
        <p>
            <strong><?php esc_html_e( 'URL ленты:', 'multi-rss-for-zen' ); ?></strong>
            <a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $feed_url ); ?></a>
        </p>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mzen-edit-form">
        <?php wp_nonce_field( 'mzen_save_channel' ); ?>
        <input type="hidden" name="action" value="mzen_save_channel" />
        <input type="hidden" name="channel_slug" value="<?php echo esc_attr( $slug ); ?>" />

        <table class="form-table">
            <?php if ( $is_new ) : ?>
                <tr>
                    <th><label for="channel_slug_input"><?php esc_html_e( 'Slug канала', 'multi-rss-for-zen' ); ?></label></th>
                    <td>
                        <input type="text" name="channel_slug_input" id="channel_slug_input" value="multizen" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Только a-z, 0-9, дефис. Используется в URL ленты: /feed/&lt;slug&gt;/.', 'multi-rss-for-zen' ); ?></p>
                    </td>
                </tr>
            <?php else : ?>
                <tr>
                    <th><?php esc_html_e( 'Slug канала', 'multi-rss-for-zen' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $slug ); ?></code>
                        <p class="description"><?php esc_html_e( 'Slug менять нельзя (для сохранения существующих URL лент).', 'multi-rss-for-zen' ); ?></p>
                    </td>
                </tr>
            <?php endif; ?>

            <tr>
                <th><label for="mzen_title"><?php esc_html_e( 'Заголовок канала', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <input type="text" name="mzen_title" id="mzen_title" value="<?php echo esc_attr( $channel['title'] ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Название канала. Оно же — <title> в ленте и метка в метабоксе записи.', 'multi-rss-for-zen' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_link"><?php esc_html_e( 'Ссылка на сайт', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="url" name="mzen_link" id="mzen_link" value="<?php echo esc_attr( $channel['link'] ); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><label for="mzen_description"><?php esc_html_e( 'Описание', 'multi-rss-for-zen' ); ?></label></th>
                <td><textarea name="mzen_description" id="mzen_description" rows="3" cols="60"><?php echo esc_textarea( $channel['description'] ); ?></textarea></td>
            </tr>

            <tr>
                <th><label for="mzen_language"><?php esc_html_e( 'Язык', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="text" name="mzen_language" id="mzen_language" value="<?php echo esc_attr( $channel['language'] ); ?>" size="5" /></td>
            </tr>

            <tr>
                <th><label for="mzen_delay_minutes"><?php esc_html_e( 'Задержка публикации (мин)', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <input type="number" name="mzen_delay_minutes" id="mzen_delay_minutes" value="<?php echo esc_attr( (int) $channel['delay_minutes'] ); ?>" min="0" step="10" />
                    <p class="description"><?php esc_html_e( 'Через сколько минут после публикации запись попадёт в ленту. Шаг — 10 минут. 0 = сразу.', 'multi-rss-for-zen' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_order"><?php esc_html_e( 'Порядок сортировки', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="number" name="mzen_order" id="mzen_order" value="<?php echo esc_attr( (int) $channel['order'] ); ?>" step="1" /></td>
            </tr>

            <tr>
                <th><label for="mzen_number"><?php esc_html_e( 'Количество записей', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="number" name="mzen_number" id="mzen_number" value="<?php echo esc_attr( (int) $channel['number'] ); ?>" min="1" max="500" /></td>
            </tr>

            <tr>
                <th><label for="mzen_type"><?php esc_html_e( 'Типы записей', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <input type="text" name="mzen_type" id="mzen_type" value="<?php echo esc_attr( $channel['type'] ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Через запятую: post, page и т.д.', 'multi-rss-for-zen' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_category"><?php esc_html_e( 'Тематика по умолчанию', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_category" id="mzen_category">
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $channel['category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_typearticle"><?php esc_html_e( 'Тип статей по умолчанию', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_typearticle" id="mzen_typearticle">
                        <option value="true" <?php selected( $channel['typearticle'], 'true' ); ?>><?php esc_html_e( 'Новости', 'multi-rss-for-zen' ); ?></option>
                        <option value="false" <?php selected( $channel['typearticle'], 'false' ); ?>><?php esc_html_e( 'Материалы', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_typeplatform"><?php esc_html_e( 'Публикация по умолчанию', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_typeplatform" id="mzen_typeplatform">
                        <option value="native-yes" <?php selected( $channel['typeplatform'], 'native-yes' ); ?>><?php esc_html_e( 'Опубликовать в Дзене', 'multi-rss-for-zen' ); ?></option>
                        <option value="native-draft" <?php selected( $channel['typeplatform'], 'native-draft' ); ?>><?php esc_html_e( 'Сохранить как черновик', 'multi-rss-for-zen' ); ?></option>
                        <option value="native-no" <?php selected( $channel['typeplatform'], 'native-no' ); ?>><?php esc_html_e( 'Публикация с сайта', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_index"><?php esc_html_e( 'Индексация по умолчанию', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_index" id="mzen_index">
                        <option value="index" <?php selected( $channel['index'], 'index' ); ?>><?php esc_html_e( 'Индексировать', 'multi-rss-for-zen' ); ?></option>
                        <option value="noindex" <?php selected( $channel['index'], 'noindex' ); ?>><?php esc_html_e( 'Не индексировать', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_rating"><?php esc_html_e( 'Контент для взрослых', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_rating" id="mzen_rating">
                        <option value="Нет (не для взрослых)" <?php selected( $channel['rating'], 'Нет (не для взрослых)' ); ?>><?php esc_html_e( 'Нет (не для взрослых)', 'multi-rss-for-zen' ); ?></option>
                        <option value="Да (для взрослых)" <?php selected( $channel['rating'], 'Да (для взрослых)' ); ?>><?php esc_html_e( 'Да (для взрослых)', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_author"><?php esc_html_e( 'Автор записей', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="text" name="mzen_author" id="mzen_author" value="<?php echo esc_attr( $channel['author'] ); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><label for="mzen_figcaption"><?php esc_html_e( 'Описания изображений', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_figcaption" id="mzen_figcaption">
                        <option value="Использовать подписи" <?php selected( $channel['figcaption'], 'Использовать подписи' ); ?>><?php esc_html_e( 'Использовать подписи', 'multi-rss-for-zen' ); ?></option>
                        <option value="Отключить описания" <?php selected( $channel['figcaption'], 'Отключить описания' ); ?>><?php esc_html_e( 'Отключить описания', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_imgauthorselect"><?php esc_html_e( 'Автор изображений', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_imgauthorselect" id="mzen_imgauthorselect">
                        <option value="Автор записи" <?php selected( $channel['imgauthorselect'], 'Автор записи' ); ?>><?php esc_html_e( 'Автор записи', 'multi-rss-for-zen' ); ?></option>
                        <option value="Указать автора" <?php selected( $channel['imgauthorselect'], 'Указать автора' ); ?>><?php esc_html_e( 'Указать автора', 'multi-rss-for-zen' ); ?></option>
                        <option value="Отключить указание автора" <?php selected( $channel['imgauthorselect'], 'Отключить указание автора' ); ?>><?php esc_html_e( 'Отключить', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_imgauthor"><?php esc_html_e( 'Имя автора изображений', 'multi-rss-for-zen' ); ?></label></th>
                <td><input type="text" name="mzen_imgauthor" id="mzen_imgauthor" value="<?php echo esc_attr( $channel['imgauthor'] ); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Миниатюра в RSS', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_thumbnail" value="enabled" <?php echo $checked( $channel['thumbnail'] ); ?> />
                        <?php esc_html_e( 'Добавить миниатюру к записи', 'multi-rss-for-zen' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_selectthumb"><?php esc_html_e( 'Размер миниатюры', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_selectthumb" id="mzen_selectthumb">
                        <option value=""><?php esc_html_e( '— не выбрано —', 'multi-rss-for-zen' ); ?></option>
                        <?php foreach ( $image_sizes as $size ) : ?>
                            <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $channel['selectthumb'], $size ); ?>><?php echo esc_html( $size ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Описания из SEO', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_seodesc" value="enabled" <?php echo $checked( $channel['seodesc'] ); ?> />
                        <?php esc_html_e( 'Использовать данные из SEO-плагинов', 'multi-rss-for-zen' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_seoplugin"><?php esc_html_e( 'SEO-плагин', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_seoplugin" id="mzen_seoplugin">
                        <option value="Yoast SEO" <?php selected( $channel['seoplugin'], 'Yoast SEO' ); ?>>Yoast SEO</option>
                        <option value="All in One SEO Pack" <?php selected( $channel['seoplugin'], 'All in One SEO Pack' ); ?>>All in One SEO Pack</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Отрывок записей', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_excerpt" value="enabled" <?php echo $checked( $channel['excerpt'] ); ?> />
                        <?php esc_html_e( 'Добавить "отрывок" в начало записей', 'multi-rss-for-zen' ); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Фильтр тегов (без контента)', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_excludetags" value="enabled" <?php echo $checked( $channel['excludetags'] ); ?> />
                        <?php esc_html_e( 'Удалять указанные теги (содержимое остаётся)', 'multi-rss-for-zen' ); ?>
                    </label>
                    <br />
                    <textarea name="mzen_excludetagslist" rows="3" cols="60"><?php echo esc_textarea( $channel['excludetagslist'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Фильтр тегов (с контентом)', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_excludetags2" value="enabled" <?php echo $checked( $channel['excludetags2'] ); ?> />
                        <?php esc_html_e( 'Удалять указанные теги вместе с содержимым', 'multi-rss-for-zen' ); ?>
                    </label>
                    <br />
                    <textarea name="mzen_excludetagslist2" rows="3" cols="60"><?php echo esc_textarea( $channel['excludetagslist2'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Удаление контента', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_excludecontent" value="enabled" <?php echo $checked( $channel['excludecontent'] ); ?> />
                        <?php esc_html_e( 'Удалять указанный контент (построчно)', 'multi-rss-for-zen' ); ?>
                    </label>
                    <br />
                    <textarea name="mzen_excludecontentlist" rows="5" cols="60"><?php echo esc_textarea( $channel['excludecontentlist'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_queryselect"><?php esc_html_e( 'Что включать в RSS', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <select name="mzen_queryselect" id="mzen_queryselect">
                        <option value="Все таксономии, кроме исключенных" <?php selected( $channel['queryselect'], 'Все таксономии, кроме исключенных' ); ?>><?php esc_html_e( 'Все таксономии, кроме исключенных', 'multi-rss-for-zen' ); ?></option>
                        <option value="Только указанные таксономии" <?php selected( $channel['queryselect'], 'Только указанные таксономии' ); ?>><?php esc_html_e( 'Только указанные таксономии', 'multi-rss-for-zen' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_taxlist"><?php esc_html_e( 'Таксономии для исключения', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <textarea name="mzen_taxlist" id="mzen_taxlist" rows="3" cols="60"><?php echo esc_textarea( $channel['taxlist'] ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Формат: taxonomy_name:id1,id2,id3 (каждая с новой строки).', 'multi-rss-for-zen' ); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="mzen_addtaxlist"><?php esc_html_e( 'Таксономии для добавления', 'multi-rss-for-zen' ); ?></label></th>
                <td>
                    <textarea name="mzen_addtaxlist" id="mzen_addtaxlist" rows="3" cols="60"><?php echo esc_textarea( $channel['addtaxlist'] ); ?></textarea>
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e( 'Исключать по умолчанию', 'multi-rss-for-zen' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mzen_excludedefault" value="enabled" <?php echo $checked( $channel['excludedefault'] ); ?> />
                        <?php esc_html_e( 'Новые записи по умолчанию исключаются из RSS', 'multi-rss-for-zen' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $is_new ? esc_html__( 'Создать канал', 'multi-rss-for-zen' ) : esc_html__( 'Сохранить канал', 'multi-rss-for-zen' ); ?>
            </button>
            <a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Отмена', 'multi-rss-for-zen' ); ?></a>
        </p>
    </form>

    <?php if ( ! $is_new && ! $is_default ) : ?>
        <hr />
        <form method="post" action="<?php echo esc_url( $form_action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Удалить канал? Это действие необратимо.', 'multi-rss-for-zen' ) ); ?>');">
            <?php wp_nonce_field( 'mzen_delete_channel' ); ?>
            <input type="hidden" name="action" value="mzen_delete_channel" />
            <input type="hidden" name="channel_slug" value="<?php echo esc_attr( $slug ); ?>" />
            <button type="submit" class="button button-link-delete"><?php esc_html_e( 'Удалить канал', 'multi-rss-for-zen' ); ?></button>
        </form>
    <?php endif; ?>

</div>