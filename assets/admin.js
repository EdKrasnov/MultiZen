/**
 * Multi Zen — скрипты админки.
 *
 * На странице редактирования записи:
 *  - при смене канала подставляем тематику и прочие поля;
 *  - авто-галочка «Исключить из RSS» по excludedefault канала;
 *  - валидация отложенной публикации: не раньше post_date + delay_minutes.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var $channel  = $('#mzen_channel');
        var $category = $('#mzen_category');
        var $exclude  = $('#mzen_rss_enabled');
        var $publish  = $('#mzen_publish_at');
        var $minLabel = $('#mzen_publish_at_min');
        var $wrapper  = $('.mzen-metabox');

        if (!$channel.length || typeof mzenChannels === 'undefined') {
            return;
        }

        var userChangedCategory = false;

        $category.on('change', function () {
            userChangedCategory = true;
        });

        // Получаем delay_minutes выбранного канала.
        function getDelay() {
            var slug = $channel.val();
            if (!slug || !mzenChannels[slug]) {
                return 0;
            }
            return parseInt(mzenChannels[slug].delay_minutes, 10) || 0;
        }

        // Минимально допустимое время = post_date + delay_minutes.
        function getMinDate() {
            var postDate = $wrapper.data('post-date');
            if (!postDate) {
                return null;
            }
            // 'YYYY-MM-DD HH:MM:SS' → Date
            var dt = new Date(String(postDate).replace(' ', 'T'));
            if (isNaN(dt.getTime())) {
                return null;
            }
            return new Date(dt.getTime() + getDelay() * 60000);
        }

        function formatDateTime(d) {
            var pad = function (n) { return n < 10 ? '0' + n : String(n); };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function updateMinHint() {
            if (!$minLabel.length) {
                return;
            }
            var min = getMinDate();
            if (!min) {
                $minLabel.text('—');
                return;
            }
            $minLabel.text(formatDateTime(min));
            // Опционально: проставить min-атрибут у input (не все браузеры уважают).
            $publish.attr('min', formatDateTime(min));
        }

        // Обновляем подсказку при загрузке и при смене канала.
        updateMinHint();

        $channel.on('change', function () {
            var slug = $(this).val();
            if (!slug || !mzenChannels[slug]) {
                updateMinHint();
                return;
            }

            var channel = mzenChannels[slug];

            // Тематика.
            if (!userChangedCategory && channel.category) {
                $category.val(channel.category);
            }

            // Остальные селекты.
            if ($('#mzen_type_platform').length && channel.typeplatform) {
                $('#mzen_type_platform').val(channel.typeplatform);
            }
            if ($('#mzen_type_article').length && channel.typearticle) {
                $('#mzen_type_article').val(channel.typearticle);
            }
            if ($('#mzen_index').length && channel.index) {
                $('#mzen_index').val(channel.index);
            }

            // Авто-галочка «Исключить из RSS».
            if ($exclude.length) {
                if (channel.excludedefault === 'enabled') {
                    $exclude.prop('checked', true);
                } else {
                    $exclude.prop('checked', false);
                }
            }

            updateMinHint();
        });

        // Кнопка «Очистить» у отложенной публикации.
        $('#mzen_publish_at_clear').on('click', function () {
            $publish.val('');
        });

        // Валидация при отправке формы.
        $('form#post').on('submit', function (e) {
            if (!$publish.length) {
                return;
            }
            var val = $publish.val();
            if (!val) {
                return;
            }
            var min = getMinDate();
            if (!min) {
                return;
            }
            var chosen = new Date(val);
            if (isNaN(chosen.getTime())) {
                return;
            }
            if (chosen.getTime() < min.getTime()) {
                e.preventDefault();
                alert(
                    'Отложенная публикация не может быть раньше, чем '
                    + formatDateTime(min).replace('T', ' ')
                    + ' (время публикации + задержка канала).'
                );
                $publish.focus();
                return false;
            }
        });
    });
})(jQuery);