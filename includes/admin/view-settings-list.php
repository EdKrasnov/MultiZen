<?php
/**
 * Список каналов.
 *
 * @var array  $channels Отфильтрованные каналы.
 * @var array  $all      Все каналы.
 * @var string $default  Slug канала по умолчанию.
 * @var string $search   Текущий поисковый запрос.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_url = admin_url( 'options-general.php?page=' . MZEN_Admin_Settings::PAGE );
?>
<div class="wrap mzen-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Мульти.Дзен — каналы', 'multi-rss-for-zen' ); ?></h1>
    <a href="<?php echo esc_url( add_query_arg( array( 'page' => MZEN_Admin_Settings::PAGE, 'action' => 'new' ), admin_url( 'options-general.php' ) ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Добавить канал', 'multi-rss-for-zen' ); ?>
    </a>
    <hr class="wp-header-end">

    <form method="get" class="mzen-search">
        <input type="hidden" name="page" value="<?php echo esc_attr( MZEN_Admin_Settings::PAGE ); ?>" />
        <p class="search-box">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Поиск по названию, slug, описанию…', 'multi-rss-for-zen' ); ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Найти', 'multi-rss-for-zen' ); ?></button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( $page_url ); ?>" class="button"><?php esc_html_e( 'Сбросить', 'multi-rss-for-zen' ); ?></a>
            <?php endif; ?>
        </p>
    </form>

    <?php if ( empty( $all ) ) : ?>
        <div class="notice notice-warning"><p>
            <?php esc_html_e( 'Каналов пока нет. Создайте первый канал, чтобы ленты начали работать.', 'multi-rss-for-zen' ); ?>
        </p></div>
    <?php else : ?>

        <table class="wp-list-table widefat fixed striped mzen-channels">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Название', 'multi-rss-for-zen' ); ?></th>
                    <th><?php esc_html_e( 'Slug', 'multi-rss-for-zen' ); ?></th>
                    <th><?php esc_html_e( 'URL ленты', 'multi-rss-for-zen' ); ?></th>
                    <th><?php esc_html_e( 'Задержка', 'multi-rss-for-zen' ); ?></th>
                    <th><?php esc_html_e( 'Порядок', 'multi-rss-for-zen' ); ?></th>
                    <th><?php esc_html_e( 'Действия', 'multi-rss-for-zen' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $channels ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'Ничего не найдено.', 'multi-rss-for-zen' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $channels as $slug => $channel ) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        array( 'page' => MZEN_Admin_Settings::PAGE, 'action' => 'edit', 'channel' => $slug ),
                        admin_url( 'options-general.php' )
                    );
                    $feed_url = home_url( '/feed/' . $slug . '/' );
                    if ( ! get_option( 'permalink_structure' ) ) {
                        $feed_url = home_url( '/?feed=' . $slug );
                    }
                    $is_default = ( $slug === $default );
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $channel['title'] ); ?></a></strong>
                            <?php if ( $is_default ) : ?>
                                <span class="mzen-badge mzen-badge-default"><?php esc_html_e( 'по умолчанию', 'multi-rss-for-zen' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $feed_url ); ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $delay = (int) $channel['delay_minutes'];
                            echo $delay > 0
                                ? esc_html( sprintf( _n( '%d мин', '%d мин', $delay, 'multi-rss-for-zen' ), $delay ) )
                                : esc_html__( 'сразу', 'multi-rss-for-zen' );
                            ?>
                        </td>
                        <td><?php echo (int) $channel['order']; ?></td>
                        <td class="mzen-actions">
                            <a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Изменить', 'multi-rss-for-zen' ); ?></a>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzen-inline-form">
                                <?php wp_nonce_field( 'mzen_duplicate_channel' ); ?>
                                <input type="hidden" name="action" value="mzen_duplicate_channel" />
                                <input type="hidden" name="channel_slug" value="<?php echo esc_attr( $slug ); ?>" />
                                <button type="submit" class="button button-small"><?php esc_html_e( 'Дублировать', 'multi-rss-for-zen' ); ?></button>
                            </form>

                            <?php if ( ! $is_default ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzen-inline-form">
                                    <?php wp_nonce_field( 'mzen_set_default' ); ?>
                                    <input type="hidden" name="action" value="mzen_set_default" />
                                    <input type="hidden" name="channel_slug" value="<?php echo esc_attr( $slug ); ?>" />
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Сделать по умолчанию', 'multi-rss-for-zen' ); ?></button>
                                </form>
                            <?php endif; ?>

                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzen-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Удалить канал? Это действие необратимо.', 'multi-rss-for-zen' ) ); ?>');">
                                <?php wp_nonce_field( 'mzen_delete_channel' ); ?>
                                <input type="hidden" name="action" value="mzen_delete_channel" />
                                <input type="hidden" name="channel_slug" value="<?php echo esc_attr( $slug ); ?>" />
                                <button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Удалить', 'multi-rss-for-zen' ); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr />

    <div class="mzen-import-export">
        <h2><?php esc_html_e( 'Экспорт / импорт', 'multi-rss-for-zen' ); ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzen-inline-form">
            <?php wp_nonce_field( 'mzen_export_json' ); ?>
            <input type="hidden" name="action" value="mzen_export_json" />
            <button type="submit" class="button"><?php esc_html_e( 'Скачать JSON со всеми каналами', 'multi-rss-for-zen' ); ?></button>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mzen-import-form" onsubmit="return confirm('<?php echo esc_js( __( 'Импортировать каналы? Существующие могут быть перезаписаны (в режиме «Заменить»).', 'multi-rss-for-zen' ) ); ?>');">
            <?php wp_nonce_field( 'mzen_import_json' ); ?>
            <input type="hidden" name="action" value="mzen_import_json" />
            <p>
                <label>
                    <input type="radio" name="mzen_mode" value="merge" checked />
                    <?php esc_html_e( 'Объединить (добавить новые, обновить существующие)', 'multi-rss-for-zen' ); ?>
                </label>
                <br />
                <label>
                    <input type="radio" name="mzen_mode" value="replace" />
                    <?php esc_html_e( 'Заменить (удалить все текущие каналы)', 'multi-rss-for-zen' ); ?>
                </label>
            </p>
            <p>
                <textarea name="mzen_json" rows="8" cols="80" placeholder='{"channels":{...}}'></textarea>
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Импортировать JSON', 'multi-rss-for-zen' ); ?></button>
            </p>
        </form>
    </div>

</div>