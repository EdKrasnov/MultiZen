<?php
/*
Plugin Name: RSS for Multi Zen
Plugin URI: 
Description: Создание Multi RSS-ленты для сервиса Дзен.
Version: 1.0
Author: Flector
Author URI: https://profiles.wordpress.org/edkrasnov
Text Domain: multi-rss-for-zen
*/ 

//функция установки значений по умолчанию при активации плагина begin
function mzen_init() {
    $mzen_options = array();  
    $mzen_options['yzrssname'] = 'multizen';
    $mzen_options['yzcategory'] = "Общество";
    $mzen_options['yzrating'] = "Нет (не для взрослых)";
    $mzen_options['yztitle'] = get_bloginfo_rss('title');
    $mzen_options['yzlink'] = get_bloginfo_rss('url');
    $mzen_options['yzdescription'] = get_bloginfo_rss('description');
    $mzen_options['yzlanguage'] = "ru";
    $mzen_options['yznumber'] = "20";
    $mzen_options['yztype'] = "post";
    $mzen_options['yzfigcaption'] = "Использовать подписи";
    $mzen_options['yzimgauthorselect'] = "Автор записи";
    $mzen_options['yzimgauthor'] = "";
    $mzen_options['yzauthor'] = "";
    $mzen_options['yzthumbnail'] = "disabled";
    $mzen_options['yzselectthumb'] = "";
    $mzen_options['yzseodesc'] = "disabled";
    $mzen_options['yzseoplugin'] = "Yoast SEO";
    $mzen_options['yzexcludetags'] = "enabled";
    $mzen_options['yzexcludetagslist'] = "<div>,<span>";
    $mzen_options['yzexcludetags2'] = "enabled";
    $mzen_options['yzexcludetagslist2'] = "<iframe>,<script>,<ins>,<style>,<object>";
    $mzen_options['yzexcludecontent'] = "disabled";
    $mzen_options['yzexcludecontentlist'] = esc_textarea("<!--more-->\n<p><\/p>\n<p>&nbsp;<\/p>");  
    $mzen_options['yzqueryselect'] = "Все таксономии, кроме исключенных";
    $mzen_options['yztaxlist'] = "";
    $mzen_options['yzaddtaxlist'] = "";
    $mzen_options['yzexcerpt'] = "disabled";
    $mzen_options['yzexcludedefault'] = "disabled";
    $mzen_options['yztypearticle'] = "false";
    $mzen_options['yztypeplatform'] = "native-no";
    $mzen_options['yzindex'] = "index";

    add_option('mzen_options', $mzen_options);
    
    mzen_add_feed();
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
}
register_activation_hook( __FILE__, 'mzen_init' );
//функция установки значений по умолчанию при активации плагина end

//функция при деактивации плагина begin
function mzen_on_deactivation() {
	if ( ! current_user_can('activate_plugins') ) return;
    
    //удаляем ленту плагина при деактивации плагина и обновляем пермалинки begin
    $mzen_options = get_option('mzen_options'); 
    if (!isset($mzen_options['yzrssname'])) {$mzen_options['yzrssname']="multizen";}
    global $wp_rewrite;
    if ( in_array( $mzen_options['yzrssname'], $wp_rewrite->feeds ) ) {
       unset($wp_rewrite->feeds[array_search($mzen_options['yzrssname'], $wp_rewrite->feeds)]);
    }
    $wp_rewrite->flush_rules();
    //удаляем ленту плагина при деактивации плагина и обновляем пермалинки end
}
register_deactivation_hook( __FILE__, 'mzen_on_deactivation' );
//функция при деактивации плагина end

//функция при удалении плагина begin
function mzen_on_uninstall() {
	if ( ! current_user_can('activate_plugins') ) return;
    delete_option('mzen_options');
}
register_uninstall_hook( __FILE__, 'mzen_on_uninstall' );
//функция при удалении плагина end

//загрузка файла локализации плагина begin
function mzen_setup(){
    load_plugin_textdomain('multi-rss-for-zen');
}
add_action('init', 'mzen_setup');
//загрузка файла локализации плагина end

//добавление ссылки "Настройки" на странице со списком плагинов begin
function mzen_actions($links) {
	return array_merge(array('settings' => '<a href="options-general.php?page=multi-rss-for-zen.php">' . __('Настройки', 'multi-rss-for-zen') . '</a>'), $links);
}
add_filter('plugin_action_links_' . plugin_basename( __FILE__ ),'mzen_actions');
//добавление ссылки "Настройки" на странице со списком плагинов end

//функция загрузки скриптов и стилей плагина только в админке и только на странице настроек плагина begin
function mzen_files_admin($hook_suffix) {
	$purl = plugins_url('', __FILE__);

    if ( is_admin() && $hook_suffix == 'settings_page_multi-rss-for-zen' ) {
    
    wp_register_script('zen-lettering', $purl . '/inc/jquery.2lettering.js');  
    wp_register_script('zen-textillate', $purl . '/inc/jquery.2textillate.js');  
	wp_register_style('zen-animate', $purl . '/inc/animate.2min.css');
    wp_register_script('zen-script', $purl . '/inc/zen-2script.js', array(), '1.28');  
	
	if(!wp_script_is('jquery')) {wp_enqueue_script('jquery');}
    wp_enqueue_script('zen-lettering');
    wp_enqueue_script('zen-textillate');
    wp_enqueue_style('zen-animate');
    wp_enqueue_script('zen-script');
    
    }
}
add_action('admin_enqueue_scripts', 'mzen_files_admin');
//функция загрузки скриптов и стилей плагина только в админке и только на странице настроек плагина end

//функция вывода страницы настроек плагина begin
function mzen_options_page() {
$purl = plugins_url('', __FILE__);

if (isset($_POST['submit'])) {

//проверка безопасности при сохранении настроек плагина begin        
if ( ! wp_verify_nonce( $_POST['mzen_nonce'], plugin_basename(__FILE__) ) || ! current_user_can('edit_posts') ) {
   wp_die(__( 'Cheatin&#8217; uh?' ));
}
//проверка безопасности при сохранении настроек плагина end
    
    //проверяем и сохраняем введенные пользователем данные begin    
    $mzen_options = get_option('mzen_options');
    
    if (!preg_match('/[^A-Za-z0-9]/', $_POST['yzrssname']))  {
        $mzen_options['yzrssname'] = $_POST['yzrssname'];
        update_option('mzen_options', $mzen_options);
        mzen_add_feed();
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
    
    $mzen_options['yzcategory'] = sanitize_text_field($_POST['yzcategory']);
    $mzen_options['yzrating'] = sanitize_text_field($_POST['yzrating']);
    $mzen_options['yztitle'] = sanitize_text_field($_POST['yztitle']);
    $mzen_options['yzlink'] = esc_url_raw($_POST['yzlink']);
    $mzen_options['yzdescription'] = sanitize_text_field($_POST['yzdescription']);
    $mzen_options['yzlanguage'] = sanitize_text_field($_POST['yzlanguage']);
    
    $yznumber = sanitize_text_field($_POST['yznumber']); 
    if (is_numeric($yznumber) && (int)$yznumber>=20) {
        $mzen_options['yznumber'] = sanitize_text_field($_POST['yznumber']);
    }
    
    $mzen_options['yztype'] = sanitize_text_field($_POST['yztype']);
    $mzen_options['yzfigcaption'] = sanitize_text_field($_POST['yzfigcaption']);
    $mzen_options['yzimgauthorselect'] = sanitize_text_field($_POST['yzimgauthorselect']);
    $mzen_options['yzimgauthor'] = sanitize_text_field($_POST['yzimgauthor']);
    $mzen_options['yzauthor'] = sanitize_text_field($_POST['yzauthor']);
    
    if(isset($_POST['yzthumbnail'])){$mzen_options['yzthumbnail'] = sanitize_text_field($_POST['yzthumbnail']);}else{$mzen_options['yzthumbnail'] = 'disabled';}
    $mzen_options['yzselectthumb'] = sanitize_text_field($_POST['yzselectthumb']);
    
    if(isset($_POST['yzseodesc'])){$mzen_options['yzseodesc'] = sanitize_text_field($_POST['yzseodesc']);}else{$mzen_options['yzseodesc'] = 'disabled';}
    $mzen_options['yzseoplugin'] = sanitize_text_field($_POST['yzseoplugin']);
    
    if(isset($_POST['yzexcludetags'])){$mzen_options['yzexcludetags'] = sanitize_text_field($_POST['yzexcludetags']);}else{$mzen_options['yzexcludetags'] = 'disabled';}
    $mzen_options['yzexcludetagslist'] = esc_textarea($_POST['yzexcludetagslist']);
    
    if(isset($_POST['yzexcludetags2'])){$mzen_options['yzexcludetags2'] = sanitize_text_field($_POST['yzexcludetags2']);}else{$mzen_options['yzexcludetags2'] = 'disabled';}
    $mzen_options['yzexcludetagslist2'] = esc_textarea($_POST['yzexcludetagslist2']);
    
    if(isset($_POST['yzexcludecontent'])){$mzen_options['yzexcludecontent'] = sanitize_text_field($_POST['yzexcludecontent']);}else{$mzen_options['yzexcludecontent'] = 'disabled';}
    $mzen_options['yzexcludecontentlist'] = addcslashes(esc_textarea($_POST['yzexcludecontentlist']), '/');
    
    
    $mzen_options['yzqueryselect'] = sanitize_text_field($_POST['yzqueryselect']);
    $mzen_options['yztaxlist'] = esc_textarea($_POST['yztaxlist']);
    $mzen_options['yzaddtaxlist'] = esc_textarea($_POST['yzaddtaxlist']);
    if(isset($_POST['yzexcerpt'])){$mzen_options['yzexcerpt'] = sanitize_text_field($_POST['yzexcerpt']);}else{$mzen_options['yzexcerpt'] = 'disabled';}
    if(isset($_POST['yzexcludedefault'])){$mzen_options['yzexcludedefault'] = sanitize_text_field($_POST['yzexcludedefault']);}else{$mzen_options['yzexcludedefault'] = 'disabled';}

    $mzen_options['yztypearticle'] = sanitize_text_field($_POST['yztypearticle']);
    $mzen_options['yztypeplatform'] = sanitize_text_field($_POST['yztypeplatform']);
    $mzen_options['yzindex'] = sanitize_text_field($_POST['yzindex']);



    update_option('mzen_options', $mzen_options);
    //проверяем и сохраняем введенные пользователем данные end
}
mzen_set_new_options();
$mzen_options = get_option('mzen_options');
?>
<?php   if (!empty($_POST) ) :
if ( ! wp_verify_nonce( $_POST['mzen_nonce'], plugin_basename(__FILE__) ) || ! current_user_can('edit_posts') ) {
   wp_die(__( 'Cheatin&#8217; uh?' ));
}
?>
<div id="message" class="updated fade"><p><strong><?php _e('Настройки сохранены.', 'multi-rss-for-zen') ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
<h2><?php _e('Настройки плагина &#171;Мульти.Дзен&#187;', 'multi-rss-for-zen'); ?></h2>

<div class="metabox-holder" id="poststuff">
<div class="meta-box-sortables">

<form action="" method="post">

<div class="postbox">

    <h3 style="border-bottom: 1px solid #EEE;background: #f7f7f7;"><span class="tcode"><?php _e("Настройки", 'multi-rss-for-zen'); ?></span></h3>
    <div class="inside" style="display: block;">

        <table class="form-table">
        
        <?php if ( get_option('permalink_structure') ) {
            $kor = get_bloginfo("url") .'/feed/' . '<strong>' . $mzen_options['yzrssname'] . '</strong>/';
            $rssname = get_bloginfo("url") .'/feed/' . $mzen_options['yzrssname'] . '/';
            echo '<p>Ваша RSS-лента для Дзена доступна по адресу: <a target="new" href="'.$rssname.'">'.$rssname.'</a><br /><br />
            Новые правила добавления канала в сервис Дзен читайте на этой <a target="new" href="https://yandex.ru/support/zen/publishers/site-to-channel.html">странице</a>.<br />
            Цитата: <tt>Для сайта site.ru проверка и привязка возможна при наборе каналом 7000 дочитываний за последние семь дней. <br />Учитываются только публикации со средним временем дочитывания не менее 40 секунд</tt>.<br />
            Т.е. предлагается сначала создать и заполнить канал материалами, получить 7000 дочитываний, а уже после этого можно будет добавить ленту.
            </p>';
         } else {
            $kor = get_bloginfo("url") .'/?feed=' . '<strong>' . $mzen_options['yzrssname']. '</strong>';
            $rssname = get_bloginfo("url") .'/?feed=' . $mzen_options['yzrssname'] ;
            echo '<p>Ваша RSS-лента для Яндекс.Дзена доступна по адресу: <a target="new" href="'.$rssname.'">'.$rssname.'</a><br /><br />
            Новые правила добавления канала в сервис Яндекс.Дзен читайте на этой <a target="new" href="https://yandex.ru/support/zen/publishers/site-to-channel.html">странице</a>.<br />
            Цитата: <tt>Для сайта site.ru проверка и привязка возможна при наборе каналом 7000 дочитываний за последние семь дней. <br />Учитываются только публикации со средним временем дочитывания не менее 40 секунд</tt>.<br />
            Т.е. предлагается сначала создать и заполнить канал материалами, получить 7000 дочитываний, а уже после этого можно будет добавить ленту.
            </p>';
         } ?>
        
            <tr>
                <th><?php _e("Имя RSS-ленты:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzrssname" size="40" value="<?php echo esc_attr($mzen_options['yzrssname']); ?>" />
                    <br /><small><?php _e("Текущий URL RSS-ленты:", "multi-rss-for-zen"); ?> <tt><?php echo $kor; ?></tt><br />
                    <?php _e("Только буквы и цифры, не меняйте без необходимости.", "multi-rss-for-zen"); ?>
                    </small><div style="margin-bottom:20px;"></div>
                </td>
            </tr>
            <tr>
                <th><?php _e("Заголовок:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yztitle" size="40" value="<?php echo esc_attr(stripslashes($mzen_options['yztitle'])); ?>" />
                    <br /><small><?php _e("Название издания.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Ссылка:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzlink" size="40" value="<?php echo esc_attr(stripslashes($mzen_options['yzlink'])); ?>" />
                    <br /><small><?php _e("Адрес сайта издания.", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
            <tr>
                <th><?php _e("Описание:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzdescription" size="40" value="<?php echo esc_attr(stripslashes($mzen_options['yzdescription'])); ?>" />
                    <br /><small><?php _e("Описание издания.", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
            <tr>
                <th><?php _e("Язык:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzlanguage" size="2" value="<?php echo esc_attr(stripslashes($mzen_options['yzlanguage'])); ?>" />
                    <br /><small><?php _e("Язык статей издания в стандарте <a target='new' href='https://ru.wikipedia.org/wiki/%D0%9A%D0%BE%D0%B4%D1%8B_%D1%8F%D0%B7%D1%8B%D0%BA%D0%BE%D0%B2'>ISO 639-1</a> (Россия - <strong>ru</strong>, Украина - <strong>uk</strong> и т.д.)", "multi-rss-for-zen"); ?> </small>
                    <div  style="margin-bottom:20px;"></div>
               </td>
            </tr>
           <tr>
                <th><?php _e("Количество записей:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yznumber" size="2" value="<?php echo esc_attr(stripslashes($mzen_options['yznumber'])); ?>" />
                    <br /><small><?php _e("Количество записей в ленте (по требованиям Яндекса минимально необходимо <strong>20</strong> записей).", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
           <tr>
                <th><?php _e("Типы записей:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yztype" size="20" value="<?php echo esc_attr(stripslashes($mzen_options['yztype'])); ?>" />
                    <br /><small><?php _e("Типы записей в ленте через запятую (<strong>post</strong> - записи, <strong>page</strong> - страницы и т.д.).<br />У произвольных типов записей должно быть поле <strong>post_content</strong>!", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
            <tr>
                <th><?php _e("Автор записей:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzauthor" size="20" value="<?php echo esc_attr(stripslashes($mzen_options['yzauthor'])); ?>" />
                    <br /><small><?php _e("Автор записей (если не заполнено, то будет использовано имя автора записи).", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
            <tr>
                <th><?php _e("Описания изображений:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yzfigcaption" id="capalt" style="width: 250px;">
                        <option value="Использовать подписи" <?php if ($mzen_options['yzfigcaption'] == 'Использовать подписи') echo "selected='selected'" ?>><?php _e("Использовать подписи", "multi-rss-for-zen"); ?></option>
                        <option value="Отключить описания" <?php if ($mzen_options['yzfigcaption'] == 'Отключить описания') echo "selected='selected'" ?>><?php _e("Отключить описания", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Разметка \"описания\" для изображений.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("В html5-темах будет взята информация из тега <tt>&lt;figcaption&gt;</tt>, в html4-темах из шорткода <tt>[caption]</tt>.", "multi-rss-for-zen"); ?></small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Автор изображений:", "multi-rss-for-zen") ?></th>
                <td>
                    <select name="yzimgauthorselect" id="imgselect" style="width: 250px;">
                        <option value="Автор записи" <?php if ($mzen_options['yzimgauthorselect'] == 'Автор записи') echo "selected='selected'" ?>><?php _e("Автор записи", "multi-rss-for-zen"); ?></option>
                        <option value="Указать автора" <?php if ($mzen_options['yzimgauthorselect'] == 'Указать автора') echo "selected='selected'" ?>><?php _e("Указать автора", "multi-rss-for-zen"); ?></option>
                        <option value="Отключить указание автора" <?php if ($mzen_options['yzimgauthorselect'] == 'Отключить указание автора') echo "selected='selected'" ?>><?php _e("Отключить указание автора", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Разметка \"автора\" для изображений (<tt>&lt;span class=\"copyright\">Автор&lt;/span></tt>).", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Работает только при включенных описаниях для изображений.", "multi-rss-for-zen"); ?> <br />
                    </small>
               </td>
            </tr>
            <tr id="ownname" style="display:none;">
                <th><?php _e("Имя автора изображений:", "multi-rss-for-zen") ?></th>
                <td>
                    <input type="text" name="yzimgauthor" size="20" value="<?php echo esc_attr(stripslashes($mzen_options['yzimgauthor'])); ?>" />
                    <br /><small><?php _e("Автор изображений (если не заполнено, то будет использовано имя автора записи).", "multi-rss-for-zen"); ?> </small>
               </td>
            </tr>
            <tr>
                <th><?php _e("Тематика записей по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yzcategory" style="width: 250px;">
                        <option value="Происшествия" <?php if ($mzen_options['yzcategory'] == 'Происшествия') echo "selected='selected'" ?>><?php _e("Происшествия", "multi-rss-for-zen"); ?></option>
                        <option value="Политика" <?php if ($mzen_options['yzcategory'] == 'Политика') echo "selected='selected'" ?>><?php _e("Политика", "multi-rss-for-zen"); ?></option>
                        <option value="Война" <?php if ($mzen_options['yzcategory'] == 'Война') echo "selected='selected'" ?>><?php _e("Война", "multi-rss-for-zen"); ?></option>
                        <option value="Общество" <?php if ($mzen_options['yzcategory'] == 'Общество') echo "selected='selected'" ?>><?php _e("Общество", "multi-rss-for-zen"); ?></option>
                        <option value="Экономика" <?php if ($mzen_options['yzcategory'] == 'Экономика') echo "selected='selected'" ?>><?php _e("Экономика", "multi-rss-for-zen"); ?></option>
                        <option value="Спорт" <?php if ($mzen_options['yzcategory'] == 'Спорт') echo "selected='selected'" ?>><?php _e("Спорт", "multi-rss-for-zen"); ?></option>
                        <option value="Технологии" <?php if ($mzen_options['yzcategory'] == 'Технологии') echo "selected='selected'" ?>><?php _e("Технологии", "multi-rss-for-zen"); ?></option>
                        <option value="Наука" <?php if ($mzen_options['yzcategory'] == 'Наука') echo "selected='selected'" ?>><?php _e("Наука", "multi-rss-for-zen"); ?></option>
                        <option value="Игры" <?php if ($mzen_options['yzcategory'] == 'Игры') echo "selected='selected'" ?>><?php _e("Игры", "multi-rss-for-zen"); ?></option>
                        <option value="Музыка" <?php if ($mzen_options['yzcategory'] == 'Музыка') echo "selected='selected'" ?>><?php _e("Музыка", "multi-rss-for-zen"); ?></option>
                        <option value="Литература" <?php if ($mzen_options['yzcategory'] == 'Литература') echo "selected='selected'" ?>><?php _e("Литература", "multi-rss-for-zen"); ?></option>
                        <option value="Кино" <?php if ($mzen_options['yzcategory'] == 'Кино') echo "selected='selected'" ?>><?php _e("Кино", "multi-rss-for-zen"); ?></option>
                        <option value="Культура" <?php if ($mzen_options['yzcategory'] == 'Культура') echo "selected='selected'" ?>><?php _e("Культура", "multi-rss-for-zen"); ?></option>
                        <option value="Мода" <?php if ($mzen_options['yzcategory'] == 'Мода') echo "selected='selected'" ?>><?php _e("Мода", "multi-rss-for-zen"); ?></option>
                        <option value="Знаменитости" <?php if ($mzen_options['yzcategory'] == 'Знаменитости') echo "selected='selected'" ?>><?php _e("Знаменитости", "multi-rss-for-zen"); ?></option>
                        <option value="Психология" <?php if ($mzen_options['yzcategory'] == 'Психология') echo "selected='selected'" ?>><?php _e("Психология", "multi-rss-for-zen"); ?></option>
                        <option value="Здоровье" <?php if ($mzen_options['yzcategory'] == 'Здоровье') echo "selected='selected'" ?>><?php _e("Здоровье", "multi-rss-for-zen"); ?></option>
                        <option value="Авто" <?php if ($mzen_options['yzcategory'] == 'Авто') echo "selected='selected'" ?>><?php _e("Авто", "multi-rss-for-zen"); ?></option>
                        <option value="Дом" <?php if ($mzen_options['yzcategory'] == 'Дом') echo "selected='selected'" ?>><?php _e("Дом", "multi-rss-for-zen"); ?></option>
                        <option value="Хобби" <?php if ($mzen_options['yzcategory'] == 'Хобби') echo "selected='selected'" ?>><?php _e("Хобби", "multi-rss-for-zen"); ?></option>
                        <option value="Еда" <?php if ($mzen_options['yzcategory'] == 'Еда') echo "selected='selected'" ?>><?php _e("Еда", "multi-rss-for-zen"); ?></option>
                        <option value="Дизайн" <?php if ($mzen_options['yzcategory'] == 'Дизайн') echo "selected='selected'" ?>><?php _e("Дизайн", "multi-rss-for-zen"); ?></option>
                        <option value="Фотографии" <?php if ($mzen_options['yzcategory'] == 'Фотографии') echo "selected='selected'" ?>><?php _e("Фотографии", "multi-rss-for-zen"); ?></option>
                        <option value="Юмор" <?php if ($mzen_options['yzcategory'] == 'Юмор') echo "selected='selected'" ?>><?php _e("Юмор", "multi-rss-for-zen"); ?></option>
                        <option value="Природа" <?php if ($mzen_options['yzcategory'] == 'Природа') echo "selected='selected'" ?>><?php _e("Природа", "multi-rss-for-zen"); ?></option>
                        <option value="Путешествия" <?php if ($mzen_options['yzcategory'] == 'Путешествия') echo "selected='selected'" ?>><?php _e("Путешествия", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Тематика по умолчанию (если при публикации записи не задана конкретная тематика, то будет использована тематика по умолчанию).", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Тип статей по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yztypearticle" style="width: 250px;">
                        <option value="true" <?php if ($mzen_options['yztypearticle'] == 'true') echo "selected='selected'" ?>><?php _e("Новости", "multi-rss-for-zen"); ?></option>
                        <option value="false" <?php if ($mzen_options['yztypearticle'] == 'false') echo "selected='selected'" ?>><?php _e("Материалы", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Тип статей по умолчанию (можно изменить индивидуально для каждой статьи при ее редактировании).<br /> <strong>Новости</strong> - статьи, актуальные не больше 3 дней. <strong>Материалы</strong> - статьи, актуальные всегда. Подробнее в <a target='_blank' href='https://yandex.ru/support/zen/website/rss-modify.html#common-requirements__content'>справке</a> Яндекса.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Публикация по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yztypeplatform" style="width: 250px;">
                        <option value="native-yes" <?php if ($mzen_options['yztypeplatform'] == 'native-yes') echo "selected='selected'" ?>><?php _e("Опубликовать в Дзене", "multi-rss-for-zen"); ?></option>
                        <option value="native-draft" <?php if ($mzen_options['yztypeplatform'] == 'native-draft') echo "selected='selected'" ?>><?php _e("Сохранить как черновик в Дзене", "multi-rss-for-zen"); ?></option>
                        <option value="native-no" <?php if ($mzen_options['yztypeplatform'] == 'native-no') echo "selected='selected'" ?>><?php _e("Публикация с сайта", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Настройки публикации по умолчанию (можно изменить индивидуально для каждой статьи при ее редактировании).<br /> <strong>Опубликовать в Дзене</strong> - материал будет опубликован на платформе и попадет в ленту рекомендаций.</br />
                    <strong>Сохранить как черновик в Дзене</strong> - материал сохранится на платформе в качестве черновика. Вы можете отредактировать черновик по своему усмотрению и опубликовать.<br />
                    <strong>Публикация с сайта</strong> - материал попадет в ленту RSS как публикация с сайта.<br />
                    Подробнее в <a target='_blank' href='https://yandex.ru/support/zen/website/rss-modify.html#publication__image_jhc_dxj_vrbt'>справке</a> Яндекса.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Индексация по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yzindex" style="width: 250px;">
                        <option value="index" <?php if ($mzen_options['yzindex'] == 'index') echo "selected='selected'" ?>><?php _e("Индексировать", "multi-rss-for-zen"); ?></option>
                        <option value="noindex" <?php if ($mzen_options['yzindex'] == 'noindex') echo "selected='selected'" ?>><?php _e("Не индексировать", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Настройки индексации по умолчанию (можно изменить индивидуально для каждой статьи при ее редактировании).<br /> 
                    Подробнее в <a target='_blank' href='https://yandex.ru/support/zen/website/rss-modify.html#publication__ul_mfk_21c_zrb'>справке</a> Яндекса.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Контент для взрослых по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                     <select name="yzrating" style="width: 250px;">
                        <option value="Да (для взрослых)" <?php if ($mzen_options['yzrating'] == 'Да (для взрослых)') echo "selected='selected'" ?>><?php _e("Да (для взрослых)", "multi-rss-for-zen"); ?></option>
                        <option value="Нет (не для взрослых)" <?php if ($mzen_options['yzrating'] == 'Нет (не для взрослых)') echo "selected='selected'" ?>><?php _e("Нет (не для взрослых)", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Если при публикации записи не выбрана эта опция, то будет использовано значение по умолчанию. Учтите, что в понимании Яндекса контент не для взрослых подразумевает записи, которые можно показывать подросткам от <strong>13</strong> лет.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            
            <tr>
                <th></th>
                <td>
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Сохранить настройки &raquo;', 'multi-rss-for-zen'); ?>" />
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="postbox">
    <h3 style="border-bottom: 1px solid #EEE;background: #f7f7f7;"><span class="tcode"><?php _e('Продвинутые настройки', 'multi-rss-for-zen'); ?></span></h3>
	  <div class="inside" style="padding-bottom:15px;display: block;">
     
        <table class="form-table">
        
        <p><?php _e("В данной секции находятся продвинутые настройки. <br />Пожалуйста, будьте внимательны в этом разделе!", "multi-rss-for-zen"); ?> </p>
        
        
            <tr class="yzqueryselect">
                <th><?php _e("Включить в RSS:", "multi-rss-for-zen") ?></th>
                <td>
                    <select name="yzqueryselect" id="yzqueryselect" style="width: 280px;">
                        <option value="Все таксономии, кроме исключенных" <?php if ($mzen_options['yzqueryselect'] == 'Все таксономии, кроме исключенных') echo "selected='selected'" ?>><?php _e("Все таксономии, кроме исключенных", "multi-rss-for-zen"); ?></option>
                        <option value="Только указанные таксономии" <?php if ($mzen_options['yzqueryselect'] == 'Только указанные таксономии') echo "selected='selected'" ?>><?php _e("Только указанные таксономии", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Внимание! Будьте осторожны с этой настройкой!", "multi-rss-for-zen"); ?> <br />
                    <span id="includespan"><?php _e("Обязательно установите ниже таксономии для включения в ленту - иначе лента будет пустая.", "multi-rss-for-zen"); ?> <br /></span>
                    <span id="excludespan"><?php _e("По умолчанию в ленту попадают записи всех таксономий, кроме указанных ниже.", "multi-rss-for-zen"); ?> <br /></span>
                    </small>
               </td>
            </tr> 
            <tr class="yztaxlisttr">
                <th><?php _e("Таксономии для исключения:", 'multi-rss-for-zen') ?></th>
                <td>
                    <textarea rows="3" cols="60" name="yztaxlist" id="yztaxlist"><?php echo esc_attr(stripslashes($mzen_options['yztaxlist'])); ?></textarea>
                    <br /><small><?php _e("Используемый формат: <strong>taxonomy_name:id1,id2,id3</strong>", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Пример: <code>category:1,2,4</code> - записи рубрик с ID равным 1, 2 и 4 будут <strong style='color:red;'>исключены</strong> из RSS-ленты.", "multi-rss-for-zen"); ?><br />
                    <?php _e("Каждая новая таксономия должна начинаться с новой строки.", "multi-rss-for-zen"); ?><br />
                    <?php _e("Стандартные таксономии WordPress: рубрика: <code>category</code>, метка: <code>post_tag</code>.", "multi-rss-for-zen"); ?>
                    </small>
                </td>
            </tr>
            <tr class="yzaddtaxlisttr">
                <th><?php _e("Таксономии для добавления:", 'multi-rss-for-zen') ?></th>
                <td>
                    <textarea rows="3" cols="60" name="yzaddtaxlist" id="yzaddtaxlist"><?php echo esc_attr(stripslashes($mzen_options['yzaddtaxlist'])); ?></textarea>
                    <br /><small><?php _e("Используемый формат: <strong>taxonomy_name:id1,id2,id3</strong>", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Пример: <code>category:1,2,4</code> - записи рубрик с ID равным 1, 2 и 4 будут <strong style='color:red;'>добавлены</strong> в RSS-ленту.", "multi-rss-for-zen"); ?><br />
                    <?php _e("Каждая новая таксономия должна начинаться с новой строки.", "multi-rss-for-zen"); ?><br />
                    <?php _e("Стандартные таксономии WordPress: рубрика: <code>category</code>, метка: <code>post_tag</code>.", "multi-rss-for-zen"); ?>
                    </small>
                </td>
            </tr>    
            <tr class="yzthumbnailtr">
                <th><?php _e("Миниатюры в RSS:", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzthumbnail"><input type="checkbox" value="enabled" name="yzthumbnail" id="yzthumbnail" <?php if ($mzen_options['yzthumbnail'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Добавить миниатюру к записи", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("В начало записи в RSS будет добавлена миниатюра записи (изображение записи).", "multi-rss-for-zen"); ?> <br />
                    </small>
                </td>
            </tr>
            <tr class="yzselectthumbtr" style="display:none;">
                <th><?php _e("Размер миниатюры в RSS:", 'multi-rss-for-zen') ?></th>
                <td>
                    <select name="yzselectthumb" style="width: 250px;">
                        <?php $image_sizes = get_intermediate_image_sizes(); ?>
                        <?php foreach ($image_sizes as $size_name): ?>
                            <option value="<?php echo $size_name ?>" <?php if ($mzen_options['yzselectthumb'] == $size_name) echo "selected='selected'" ?>><?php echo $size_name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br /><small><?php _e("Выберите нужный размер миниатюры (в списке находятся все зарегистрированные на сайте размеры миниатюр). ", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr class="yzseodesctr">
                <th><?php _e("Описания записей:", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzseodesc"><input type="checkbox" value="enabled" name="yzseodesc" id="yzseodesc" <?php if ($mzen_options['yzseodesc'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Использовать данные из SEO-плагинов", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("В качестве описания записи (rss-тег <tt>&lt;description&gt;</tt>) будет использовано описание записи из выбранного SEO-плагина.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr class="yzseoplugintr" style="display:none;">
                <th><?php _e("SEO-плагин:", 'multi-rss-for-zen') ?></th>
                <td>
                    <select name="yzseoplugin" style="width: 250px;">
                        <option value="Yoast SEO" <?php if ($mzen_options['yzseoplugin'] == 'Yoast SEO') echo "selected='selected'" ?>><?php _e("Yoast SEO", "multi-rss-for-zen"); ?></option>
                        <option value="All in One SEO Pack" <?php if ($mzen_options['yzseoplugin'] == 'All in One SEO Pack') echo "selected='selected'" ?>><?php _e("All in One SEO Pack", "multi-rss-for-zen"); ?></option>
                    </select>
                    <br /><small><?php _e("Выберите используемый вами SEO-плагин. <br /> Если описание записи в SEO-плагине не установлено, то будет использовано стандартное описание записи (автогенерированное из первых 55 слов записи).", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Отрывок записей:", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzexcerpt"><input type="checkbox" value="enabled" name="yzexcerpt" id="yzexcerpt" <?php if ($mzen_options['yzexcerpt'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Добавить в начало записей \"отрывок\"", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("Используйте эту опцию только в случае необходимости.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Например, когда \"отрывок\" (цитата) записи содержит контент, которого нет в самой записи.", "multi-rss-for-zen"); ?> <br />
                    </small>
                </td>
            </tr>
            <tr class="yzexcludetagstr">
                <th><?php _e("Фильтр тегов (без контента):", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzexcludetags"><input type="checkbox" value="enabled" name="yzexcludetags" id="yzexcludetags" <?php if ($mzen_options['yzexcludetags'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Удалить указанные html-теги", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("Из контента записей будут удалены все указанные html-теги (<strong>сам контент этих тегов останется</strong>).", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr class="yzexcludetagslisttr">
                <th><?php _e("Теги для удаления:", 'multi-rss-for-zen') ?></th>
                <td>
                    <textarea rows="3" cols="60" name="yzexcludetagslist" id="yzexcludetagslist"><?php echo esc_attr(stripslashes($mzen_options['yzexcludetagslist'])); ?></textarea>
                    <br /><small><?php _e("Список удаляемых html-тегов через запятую.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Указывать классы, идентификаторы и прочее не требуется.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Самозакрывающиеся теги вроде <tt>&lt;img src=\"...\" /></tt> и <tt>&lt;br /></tt> удалить нельзя.", "multi-rss-for-zen"); ?><br />
                    </small>
                </td>
            </tr>
            <tr class="yzexcludetags2tr">
                <th><?php _e("Фильтр тегов (с контентом):", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzexcludetags2"><input type="checkbox" value="enabled" name="yzexcludetags2" id="yzexcludetags2" <?php if ($mzen_options['yzexcludetags2'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Удалить указанные html-теги", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("Из контента записей будут удалены все указанные html-теги (<strong>включая сам контент этих тегов</strong>).", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr class="yzexcludetagslist2tr">
                <th><?php _e("Теги для удаления:", 'multi-rss-for-zen') ?></th>
                <td>
                    <textarea rows="3" cols="60" name="yzexcludetagslist2" id="yzexcludetagslist2"><?php echo esc_attr(stripslashes($mzen_options['yzexcludetagslist2'])); ?></textarea>
                    <br /><small><?php _e("Список удаляемых html-тегов через запятую.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Указывать классы, идентификаторы и прочее не требуется.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("По умолчанию в список включены все теги, о которых точно известно, что они не нравятся тех. поддержке Яндекс.Дзена.", "multi-rss-for-zen"); ?> <br />
                    <?php _e("Самозакрывающиеся теги вроде <tt>&lt;img src=\"...\" /></tt> и <tt>&lt;br /></tt> удалить нельзя.", "multi-rss-for-zen"); ?><br />
                    </small>
                </td>
            </tr>
            <tr class="yzexcludecontenttr">
                <th><?php _e("Контент для удаления:", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzexcludecontent"><input type="checkbox" value="enabled" name="yzexcludecontent" id="yzexcludecontent" <?php if ($mzen_options['yzexcludecontent'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("Удалить указанный контент из RSS", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("Точные вхождения указанного контента будут удалены из записей в RSS-ленте.", "multi-rss-for-zen"); ?> </small>
                </td>
            </tr>
            <tr class="yzexcludecontentlisttr">
                <th><?php _e("Список удаляемого контента:", 'multi-rss-for-zen') ?></th>
                <td>
                    <textarea rows="5" cols="60" name="yzexcludecontentlist" id="yzexcludecontentlist"><?php echo esc_attr(stripcslashes($mzen_options['yzexcludecontentlist'])); ?></textarea>
                    <br /><small><?php _e("Каждый новый шаблон для удаления должен начинаться с новой строки.", "multi-rss-for-zen"); ?> <br />
                    </small>
                </td>
            </tr>
            <tr>
                <th><?php _e("Исключать по умолчанию:", 'multi-rss-for-zen') ?></th>
                <td>
                    <label for="yzexcludedefault"><input type="checkbox" value="enabled" name="yzexcludedefault" id="yzexcludedefault" <?php if ($mzen_options['yzexcludedefault'] == 'enabled') echo "checked='checked'"; ?> /><?php _e("По умолчанию исключать записи из ленты", "multi-rss-for-zen"); ?></label>
                    <br /><small><?php _e("Включение этой опции установит галку на \"Исключить эту запись из RSS\" по умолчанию при публикации новых записей.", "multi-rss-for-zen"); ?><br />
                    <?php _e("Используется <tt>action</tt> на <tt>save_post</tt> (сработает в случае автонаполняемого сайта).", "multi-rss-for-zen"); ?>
                    </small>
                </td>
            </tr>

            <tr>
                <th></th>
                <td>
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Сохранить настройки &raquo;', 'multi-rss-for-zen'); ?>" />
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="postbox">
    <h3 style="border-bottom: 1px solid #EEE;background: #f7f7f7;"><span class="tcode"><?php _e('О плагине', 'multi-rss-for-zen'); ?></span></h3>
	  <div class="inside" style="padding-bottom:15px;display: block;">
     
      
      <p><?php _e('Если вам нравится мой плагин, то, пожалуйста, поставьте ему <a target="_blank" href="https://wordpress.org/support/plugin/multi-rss-for-zen/reviews/#new-post"><strong>5 звезд</strong></a> в репозитории.', 'multi-rss-for-zen'); ?></p>
      <p style="margin-top:20px;margin-bottom:10px;"><?php _e('Возможно, что вам также будут интересны другие мои плагины:', 'multi-rss-for-zen'); ?></p>

      <div class="about">
        <ul>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/rss-for-yandex-turbo/">RSS for Yandex Turbo</a> - <?php _e('создание RSS-ленты для сервиса Яндекс.Турбо.', 'multi-rss-for-zen'); ?></li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/bbspoiler/">BBSpoiler</a> - <?php _e('плагин позволит вам спрятать текст под тегами [spoiler]текст[/spoiler].', 'multi-rss-for-zen'); ?></li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/easy-textillate/">Easy Textillate</a> - <?php _e('плагин очень красиво анимирует текст (шорткодами в записях и виджетах или PHP-кодом в файлах темы).', 'multi-rss-for-zen'); ?> </li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/cool-image-share/">Cool Image Share</a> - <?php _e('плагин добавляет иконки социальных сетей на каждое изображение в ваших записях.', 'multi-rss-for-zen'); ?> </li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/today-yesterday-dates/">Today-Yesterday Dates</a> - <?php _e('относительные даты для записей за сегодня и вчера.', 'multi-rss-for-zen'); ?> </li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/truncate-comments/">Truncate Comments</a> - <?php _e('плагин скрывает длинные комментарии js-скриптом (в стиле Яндекса или Амазона).', 'multi-rss-for-zen'); ?> </li>
            <li><a target="_blank" href="https://ru.wordpress.org/plugins/easy-yandex-share/">Easy Yandex Share</a> - <?php _e('продвинутый вывод блока &#8220;Яндекс.Поделиться&#8221;.', 'multi-rss-for-zen'); ?></li>
            <li><a target="_blank" href="https://wordpress.org/plugins/hide-my-dates/">Hide My Dates</a> - <?php _e('this plugin hides post and comment publishing dates from Google.', 'multi-rss-for-zen'); ?></li>
            <li style="margin: 3px 0px 3px 35px;"><a target="_blank" href="https://ru.wordpress.org/plugins/html5-cumulus/">HTML5 Cumulus</a> <span class="new">new</span> - <?php _e('современная (HTML5) версия классического плагина &#8220;WP-Cumulus&#8221;.', 'multi-rss-for-zen'); ?></li>

            </ul>
      </div>
      
      
    </div>
</div>
<?php wp_nonce_field( plugin_basename(__FILE__), 'mzen_nonce'); ?>
</form>
</div>
</div>
<?php 
}
//функция вывода страницы настроек плагина end

//функция добавления ссылки на страницу настроек плагина в раздел "Настройки" begin
function mzen_menu() {
	add_options_page('Мульти.Дзен', 'Мульти.Дзен', 'manage_options', 'multi-rss-for-zen.php', 'mzen_options_page');
}
add_action('admin_menu', 'mzen_menu');
//функция добавления ссылки на страницу настроек плагина в раздел "Настройки" end

//подключение стилей на странице настроек плагина begin
function mzen_admin_print_scripts() {
    $post_permalink = $_SERVER["REQUEST_URI"];
    if(strpos($post_permalink, 'multi-rss-for-zen.php') == true) : ?>
        <style>
        tt {padding: 1px 5px 1px;margin: 0 1px;background: #eaeaea;background: rgba(0,0,0,.07);font-size: 13px;font-family: Consolas,Monaco,monospace;unicode-bidi: embed;}

.about li {
  list-style-type: square;
  margin: 5px 0px 3px 35px;
}
.new {
  color: #fff;
  background-color: #008ec2;
  border-radius: 6px;
  display: inline-block;
  padding-left: 4px;
  padding-right: 4px;
  text-align: center;
  font-size: 10px;
  vertical-align: super;
}
        </style>
    <?php endif; ?>
<?php }    
add_action('admin_head', 'mzen_admin_print_scripts');
//подключение стилей на странице настроек плагина end

//создаем метабокс begin
function mzen_meta_box(){
    $mzen_options = get_option('mzen_options');  
    $yztype = $mzen_options['yztype']; 
    $yztype = explode(",", $yztype);
    add_meta_box('mzen_meta_box', 'Мульти Дзен', 'mzen_callback', $yztype, 'normal' , 'high');
}
add_action( 'add_meta_boxes', 'mzen_meta_box' );
//создаем метабокс end

//сохраняем метабокс begin
function mzen_save_metabox($post_id){ 
    global $post;
    
    if ( ! isset( $_POST['mzen_meta_nonce'] ) ) 
        return $post_id;
 
    if ( ! wp_verify_nonce($_POST['mzen_meta_nonce'], plugin_basename(__FILE__) ) )
		return $post_id;
    
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return $post_id;
    
    if(isset($_POST["yzcategory"])){
        $yzcategory = sanitize_text_field($_POST['yzcategory']);
        update_post_meta($post->ID, 'mzencategory_meta_value', $yzcategory);
    }
    if(isset($_POST["yzrating"])){
        $yzrating = 'Да (для взрослых)';
        update_post_meta($post->ID, 'mzenrating_meta_value', $yzrating);
    } else {
        $yzrating = 'Нет (не для взрослых)';
        update_post_meta($post->ID, 'mzenrating_meta_value', $yzrating);
    }
    if(isset($_POST["yztypearticle"])){
        $yztypearticle = sanitize_text_field($_POST['yztypearticle']);
        update_post_meta($post->ID, 'mzentypearticle_meta_value', $yztypearticle);
    }
    if(isset($_POST["yztypeplatform"])){
        $yztypeplatform = sanitize_text_field($_POST['yztypeplatform']);
        update_post_meta($post->ID, 'mzentypeplatform_meta_value', $yztypeplatform);
    }
    if(isset($_POST["yzindex"])){
        $yzindex = sanitize_text_field($_POST['yzindex']);
        update_post_meta($post->ID, 'mzenindex_meta_value', $yzindex);
    }


    if(isset($_POST["yzrssenabled"])){
        $yzrssenabled = 'yes';
        update_post_meta($post->ID, 'mzenrssenabled_meta_value', $yzrssenabled);
    } else {
        $yzrssenabled = 'no';
        update_post_meta($post->ID, 'mzenrssenabled_meta_value', $yzrssenabled);
    }     
    
}
add_action('save_post', 'mzen_save_metabox');
//сохраняем метабокс end

//выводим метабокс begin
function mzen_callback(){
    global $post;
    wp_nonce_field( plugin_basename(__FILE__), 'mzen_meta_nonce' );

    $mzen_options = get_option('mzen_options');

    $yzcategory = get_post_meta($post->ID, 'mzencategory_meta_value', true); 
    if (!$yzcategory) {$yzcategory = $mzen_options['yzcategory'];}

    $yztypearticle = get_post_meta($post->ID, 'mzentypearticle_meta_value', true); 
    if (!$yztypearticle) {$yztypearticle = $mzen_options['yztypearticle'];}

    $yztypeplatform = get_post_meta($post->ID, 'mzentypeplatform_meta_value', true);
    if (!$yztypeplatform) {$yztypeplatform = $mzen_options['yztypeplatform'];}

    $yzindex = get_post_meta($post->ID, 'mzenindex_meta_value', true);
    if (!$yzindex) {$yzindex = $mzen_options['yzindex'];}

    $yzrating = get_post_meta($post->ID, 'mzenrating_meta_value', true); 
    if (!$yzrating) {$yzrating = $mzen_options['yzrating'];}   

    $yzrssenabled = get_post_meta($post->ID, 'mzenrssenabled_meta_value', true); 
    if (!$yzrssenabled) {$yzrssenabled = "no";}
    ?>   
<style>
#yttable p {margin: 8px 0;}
</style>
<table id="yttable">
<tr>
<td style="min-width:90px;vertical-align: initial;">
     <p><strong><?php _e("Тематика:", "multi-rss-for-zen"); ?></strong>
</td>
<td style="vertical-align: initial;">
     <select name="yzcategory" style="min-width:250px;">
        <option value="Происшествия" <?php if ($yzcategory == 'Происшествия') echo "selected='selected'" ?>><?php _e("Происшествия", "multi-rss-for-zen"); ?></option>
        <option value="Политика" <?php if ($yzcategory == 'Политика') echo "selected='selected'" ?>><?php _e("Политика", "multi-rss-for-zen"); ?></option>
        <option value="Война" <?php if ($yzcategory == 'Война') echo "selected='selected'" ?>><?php _e("Война", "multi-rss-for-zen"); ?></option>
        <option value="Общество" <?php if ($yzcategory == 'Общество') echo "selected='selected'" ?>><?php _e("Общество", "multi-rss-for-zen"); ?></option>
        <option value="Экономика" <?php if ($yzcategory == 'Экономика') echo "selected='selected'" ?>><?php _e("Экономика", "multi-rss-for-zen"); ?></option>
        <option value="Спорт" <?php if ($yzcategory == 'Спорт') echo "selected='selected'" ?>><?php _e("Спорт", "multi-rss-for-zen"); ?></option>
        <option value="Технологии" <?php if ($yzcategory == 'Технологии') echo "selected='selected'" ?>><?php _e("Технологии", "multi-rss-for-zen"); ?></option>
        <option value="Наука" <?php if ($yzcategory == 'Наука') echo "selected='selected'" ?>><?php _e("Наука", "multi-rss-for-zen"); ?></option>
        <option value="Игры" <?php if ($yzcategory == 'Игры') echo "selected='selected'" ?>><?php _e("Игры", "multi-rss-for-zen"); ?></option>
        <option value="Музыка" <?php if ($yzcategory == 'Музыка') echo "selected='selected'" ?>><?php _e("Музыка", "multi-rss-for-zen"); ?></option>
        <option value="Литература" <?php if ($yzcategory == 'Литература') echo "selected='selected'" ?>><?php _e("Литература", "multi-rss-for-zen"); ?></option>
        <option value="Кино" <?php if ($yzcategory == 'Кино') echo "selected='selected'" ?>><?php _e("Кино", "multi-rss-for-zen"); ?></option>
        <option value="Культура" <?php if ($yzcategory == 'Культура') echo "selected='selected'" ?>><?php _e("Культура", "multi-rss-for-zen"); ?></option>
        <option value="Мода" <?php if ($yzcategory == 'Мода') echo "selected='selected'" ?>><?php _e("Мода", "multi-rss-for-zen"); ?></option>
        <option value="Знаменитости" <?php if ($yzcategory == 'Знаменитости') echo "selected='selected'" ?>><?php _e("Знаменитости", "multi-rss-for-zen"); ?></option>
        <option value="Психология" <?php if ($yzcategory == 'Психология') echo "selected='selected'" ?>><?php _e("Психология", "multi-rss-for-zen"); ?></option>
        <option value="Здоровье" <?php if ($yzcategory == 'Здоровье') echo "selected='selected'" ?>><?php _e("Здоровье", "multi-rss-for-zen"); ?></option>
        <option value="Авто" <?php if ($yzcategory == 'Авто') echo "selected='selected'" ?>><?php _e("Авто", "multi-rss-for-zen"); ?></option>
        <option value="Дом" <?php if ($yzcategory == 'Дом') echo "selected='selected'" ?>><?php _e("Дом", "multi-rss-for-zen"); ?></option>
        <option value="Хобби" <?php if ($yzcategory == 'Хобби') echo "selected='selected'" ?>><?php _e("Хобби", "multi-rss-for-zen"); ?></option>
        <option value="Еда" <?php if ($yzcategory == 'Еда') echo "selected='selected'" ?>><?php _e("Еда", "multi-rss-for-zen"); ?></option>
        <option value="Дизайн" <?php if ($yzcategory == 'Дизайн') echo "selected='selected'" ?>><?php _e("Дизайн", "multi-rss-for-zen"); ?></option>
        <option value="Фотографии" <?php if ($yzcategory == 'Фотографии') echo "selected='selected'" ?>><?php _e("Фотографии", "multi-rss-for-zen"); ?></option>
        <option value="Юмор" <?php if ($yzcategory == 'Юмор') echo "selected='selected'" ?>><?php _e("Юмор", "multi-rss-for-zen"); ?></option>
        <option value="Природа" <?php if ($yzcategory == 'Природа') echo "selected='selected'" ?>><?php _e("Природа", "multi-rss-for-zen"); ?></option>
        <option value="Путешествия" <?php if ($yzcategory == 'Путешествия') echo "selected='selected'" ?>><?php _e("Путешествия", "multi-rss-for-zen"); ?></option>
    </select>
    </p>
</td>
</tr>
<tr>
<td style="min-width:90px;vertical-align: initial;">
    <p><strong><?php _e("Тип статьи:", "multi-rss-for-zen"); ?></strong>
</td>
<td style="vertical-align: initial;">
    <select name="yztypearticle" style="min-width:250px;">
        <option value="true" <?php if ($yztypearticle == 'true') echo "selected='selected'" ?>><?php _e("Новость", "multi-rss-for-zen"); ?></option>
        <option value="false" <?php if ($yztypearticle == 'false') echo "selected='selected'" ?>><?php _e("Материал", "multi-rss-for-zen"); ?></option>
    </select>
    </p>
</td>
</tr>
<tr>
<td style="min-width:90px;vertical-align: initial;">
    <p><strong><?php _e("Публикация:", "multi-rss-for-zen"); ?></strong>
</td>
<td style="vertical-align: initial;">
    <select name="yztypeplatform" style="min-width:250px;">
        <option value="native-yes" <?php if ($yztypeplatform == 'native-yes') echo "selected='selected'" ?>><?php _e("Опубликовать в Дзене", "multi-rss-for-zen"); ?></option>
        <option value="native-draft" <?php if ($yztypeplatform == 'native-draft') echo "selected='selected'" ?>><?php _e("Сохранить как черновик в Дзене", "multi-rss-for-zen"); ?></option>
        <option value="native-no" <?php if ($yztypeplatform == 'native-no') echo "selected='selected'" ?>><?php _e("Публикация с сайта", "multi-rss-for-zen"); ?></option>
    </select>
    </p>
</td>
</tr>
<tr>
<td style="min-width:90px;vertical-align: initial;">
    <p><strong><?php _e("Индексация:", "multi-rss-for-zen"); ?></strong>
</td>
<td style="vertical-align: initial;">
    <select name="yzindex" style="min-width:250px;">
        <option value="index" <?php if ($yzindex == 'index') echo "selected='selected'" ?>><?php _e("Индексировать", "multi-rss-for-zen"); ?></option>
        <option value="noindex" <?php if ($yzindex == 'noindex') echo "selected='selected'" ?>><?php _e("Не индексировать", "multi-rss-for-zen"); ?></option>
    </select>
    </p>
</td>
</tr>
</table>
    <p style="margin:5px!important;">
    <label for="yzrating"><input type="checkbox" value="enabled" name="yzrating" id="yzrating" <?php if ($yzrating == 'Да (для взрослых)') echo "checked='checked'"; ?> /><?php _e("Запись с контентом для взрослых", "multi-rss-for-zen"); ?></label>
<br />
    <label for="yzrssenabled"><input type="checkbox" value="enabled" name="yzrssenabled" id="yzrssenabled" <?php if ($yzrssenabled == 'yes') echo "checked='checked'"; ?> /><?php _e("Исключить эту запись из RSS", "multi-rss-for-zen"); ?></label>
    </p>
    
<?php }
//выводим метабокс end

//добавляем новую rss-ленту begin
function mzen_add_feed(){
    $mzen_options = get_option('mzen_options'); 
    if (!isset($mzen_options['yzrssname'])) {$mzen_options['yzrssname']="multizen";update_option('mzen_options', $mzen_options);}
    add_feed($mzen_options['yzrssname'], 'mzen_feed_template');
}
add_action('init', 'mzen_add_feed');
//добавляем новую rss-ленту end

//шаблон для RSS-ленты Яндекс.Дзен begin
function mzen_feed_template(){
mzen_set_new_options();
$mzen_options = get_option('mzen_options');  

$yztitle = $mzen_options['yztitle'];
$yzlink = $mzen_options['yzlink'];
$yzdescription = $mzen_options['yzdescription'];
$yzlanguage = $mzen_options['yzlanguage']; 
$yznumber = $mzen_options['yznumber']; 
$yztype = $mzen_options['yztype']; 
$yztype = explode(",", $yztype);
$yzfigcaption = $mzen_options['yzfigcaption']; 
$yzimgauthorselect = $mzen_options['yzimgauthorselect']; 
$yzimgauthor = $mzen_options['yzimgauthor']; 
$yzauthor = $mzen_options['yzauthor'];
$yzthumbnail = $mzen_options['yzthumbnail']; 
$yzselectthumb = $mzen_options['yzselectthumb'];  
$yzseodesc = $mzen_options['yzseodesc']; 
$yzseoplugin = $mzen_options['yzseoplugin'];
$yzexcludetags = $mzen_options['yzexcludetags']; 
$yzexcludetagslist = html_entity_decode($mzen_options['yzexcludetagslist']); 
$yzexcludetags2 = $mzen_options['yzexcludetags2']; 
$yzexcludetagslist2 = html_entity_decode($mzen_options['yzexcludetagslist2']); 
$yzexcludecontent = $mzen_options['yzexcludecontent']; 
$yzexcludecontentlist = html_entity_decode($mzen_options['yzexcludecontentlist']);
$tax_query = array();

$yzqueryselect = $mzen_options['yzqueryselect'];
$yztaxlist = $mzen_options['yztaxlist']; 
$yzaddtaxlist = $mzen_options['yzaddtaxlist']; 

if ($yzqueryselect=='Все таксономии, кроме исключенных' && $yztaxlist) {
    $textAr = explode("\n", trim($yztaxlist));
    $textAr = array_filter($textAr, 'trim');
    $tax_query = array( 'relation' => 'AND' );
    foreach ($textAr as $line) {
        $tax = explode(":", $line);
        $taxterm = explode(",", $tax[1]);
        $tax_query[] = array(
            'taxonomy' => $tax[0],
            'field'    => 'id',
            'terms'    => $taxterm,
            'operator' => 'NOT IN',
        );
    } 
}    
if (!$yzaddtaxlist) {$yzaddtaxlist = 'category:10000000';}
if ($yzqueryselect=='Только указанные таксономии' && $yzaddtaxlist) {
    $textAr = explode("\n", trim($yzaddtaxlist));
    $textAr = array_filter($textAr, 'trim');
    $tax_query = array( 'relation' => 'OR' );
    foreach ($textAr as $line) {
        $tax = explode(":", $line);
        $taxterm = explode(",", $tax[1]);
        $tax_query[] = array(
            'taxonomy' => $tax[0],
            'field'    => 'id',
            'terms'    => $taxterm,
            'operator' => 'IN',
        );
    } 
} 

$args = array('ignore_sticky_posts' => 1, 'post_type' => $yztype, 'post_status' => 'publish', 'posts_per_page' => $yznumber,'tax_query' => $tax_query,
'meta_query' => array('relation' => 'OR', array('key' => 'mzenrssenabled_meta_value', 'compare' => 'NOT EXISTS',),
array('key' => 'mzenrssenabled_meta_value', 'value' => 'yes', 'compare' => '!=',),));

$args_alt = apply_filters( 'mzen_query_args', $args, 8 );
if (isset($args_alt) && is_array($args_alt)) $args = $args_alt;
$query = new WP_Query( $args );

header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'.PHP_EOL;
?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:media="http://search.yahoo.com/mrss/"
    xmlns:atom="http://www.w3.org/2005/Atom"
    xmlns:georss="http://www.georss.org/georss">
<channel>
    <title><?php echo $yztitle; ?></title>
    <link><?php echo $yzlink; ?></link>
    <description><?php echo $yzdescription; ?></description>
    <language><?php echo $yzlanguage; ?></language>
    <generator>RSS for Multi Zen v1.0 (https://giport.ru/)</generator>
    <?php while($query->have_posts()) : $query->the_post(); ?>
    <item>
        <title><?php the_title_rss(); ?></title>
        <link><?php the_permalink_rss(); ?></link>
        <guid><?php echo md5( get_the_guid() ); ?></guid>
        <?php $gmt_offset = get_option('gmt_offset');
              $gmt_offset_abs = floor(abs($gmt_offset));
              $gmt_offset_str = ($gmt_offset_abs > 9) ? $gmt_offset_abs.'00' : ('0'.$gmt_offset_abs.'00');
              $gmt_offset_str = $gmt_offset >= 0 ? '+' . $gmt_offset_str : '-' . $gmt_offset_str; ?>
        <pubDate><?php echo mysql2date('D, d M Y H:i:s '.$gmt_offset_str, get_date_from_gmt(get_post_time('Y-m-d H:i:s', true)), false); ?></pubDate>
        <?php $yzrating = get_post_meta(get_the_ID(), 'mzenrating_meta_value', true); ?>
        <?php if ( ! $yzrating ) $yzrating = $mzen_options['yzrating'];  ?>
        <?php if ($yzrating == 'Да (для взрослых)') { 
            echo '<media:rating scheme="urn:simple">adult</media:rating>'.PHP_EOL;
        } else {
            echo '<media:rating scheme="urn:simple">nonadult</media:rating>'.PHP_EOL;
        } ?>
        <?php if ($yzauthor) { 
            echo '<author>'.$yzauthor.'</author>'.PHP_EOL;
        } else {
            echo '<author>'.get_the_author().'</author>'.PHP_EOL;
        } ?>
        <?php if($yzimgauthorselect == 'Указать автора' && !$yzimgauthor){$yzimgauthor = get_the_author();} ?>
        <?php if($yzimgauthorselect == 'Автор записи'){$yzimgauthor = get_the_author();} ?>
        <?php $yzcategory = get_post_meta(get_the_ID(), 'mzencategory_meta_value', true); ?>
        <?php if ($yzcategory) { echo '<category>'.$yzcategory.'</category>'.PHP_EOL; }
        else {echo '<category>'.$mzen_options['yzcategory'].'</category>'.PHP_EOL;} ?>
        <?php 
        $yztypearticle = get_post_meta(get_the_ID(), 'mzentypearticle_meta_value', true); 
        if ( ! $yztypearticle ) $yztypearticle = $mzen_options['yztypearticle'];
        $yztypearticle = apply_filters('mzen_type_article', $yztypearticle);
        if ( $yztypearticle == 'false' ) echo '<category>evergreen</category>'.PHP_EOL;
        ?>
        <?php 
        $yztypeplatform = get_post_meta(get_the_ID(), 'mzentypeplatform_meta_value', true); 
        if ( ! $yztypeplatform ) $yztypeplatform = $mzen_options['yztypeplatform'];
        $yztypeplatform = apply_filters('mzen_type_platform', $yztypeplatform);
        if ( $yztypeplatform ) echo '<category>'.$yztypeplatform.'</category>'.PHP_EOL;
        ?>
        <?php 
        $yzindex = get_post_meta(get_the_ID(), 'mzenindex_meta_value', true); 
        if ( ! $yzindex ) $yzindex = $mzen_options['yzindex'];
        $yzindex = apply_filters('mzen_index', $yzindex);
        if ( $yzindex ) echo '<category>'.$yzindex.'</category>'.PHP_EOL;
        ?>
        <?php echo '<category>comment-subscribers</category>'.PHP_EOL; ?>
        <?php
        if ($yzthumbnail=="enabled" && has_post_thumbnail( get_the_ID() )) {
            echo '<enclosure url="' . strtok(get_the_post_thumbnail_url(get_the_ID(),$yzselectthumb), '?') . '" type="'.mzen_mime_type(strtok(get_the_post_thumbnail_url(get_the_ID(),$yzselectthumb), '?')).'"/>'.PHP_EOL; 
        }    
        $html = mzen_the_content_feed();
        
        if ($yzexcludetags != 'disabled' && $yzexcludetagslist) {
            $html = mzen_strip_tags_without_content($html, $yzexcludetagslist);
        }
        if ($yzexcludetags2 != 'disabled' && $yzexcludetagslist2) {
            $html = mzen_strip_tags_with_content($html, $yzexcludetagslist2, true);
        }
        $html = wpautop($html);

        $dom = new domDocument ('1.0','UTF-8'); 
        @$dom->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $html);
        $dom->preserveWhiteSpace = false;
        $urltoimages = $dom->getElementsByTagName('a');
        $final  = array();
        foreach ($urltoimages as $urltoimage) {    
            if (mzen_mime_type(strtok($urltoimage->getAttribute('href'), '?'))!="Unknown file type" && ! in_array($urltoimage->getAttribute('href'), $final)) {
                echo '<enclosure url="' . strtok($urltoimage->getAttribute('href'), '?') . '" type="'.mzen_mime_type(strtok($urltoimage->getAttribute('href'), '?')).'"/>'.PHP_EOL; 
                $final[] = $urltoimage->getAttribute('href');
            }    
        }
        $images = $dom->getElementsByTagName('img');    
        $final = array();
        foreach ($images as $image) {         
            if (! in_array($image->getAttribute('src'), $final)) {
                echo '<enclosure url="' . strtok($image->getAttribute('src'), '?') . '" type="'.mzen_mime_type(strtok($image->getAttribute('src'), '?')).'"/>'.PHP_EOL; 
                $final[] = $image->getAttribute('src');
            }    
        }
        ?>
        <?php 
        if ($yzseodesc != 'disabled') { 
            if ($yzseoplugin == 'Yoast SEO') {
                $temp = get_post_meta(get_the_ID(), "_yoast_wpseo_metadesc", true);
                $temp = apply_filters( 'mzen_the_excerpt', $temp );
                $temp = apply_filters( 'convert_chars', $temp );
                $temp = apply_filters( 'ent2ncr', $temp, 8 );
                if (!$temp) {$temp = mzen_the_excerpt_rss();}
                echo "<description><![CDATA[{$temp}]]></description>".PHP_EOL;
            }    
            if ($yzseoplugin == 'All in One SEO Pack') {
                $temp = get_post_meta(get_the_ID(), "_aioseop_description", true);
                $temp = apply_filters( 'mzen_the_excerpt', $temp );
                $temp = apply_filters( 'convert_chars', $temp );
                $temp = apply_filters( 'ent2ncr', $temp, 8 );
                if (!$temp) {$temp = mzen_the_excerpt_rss();}
                echo "<description><![CDATA[{$temp}]]></description>".PHP_EOL;
            }  
        } else { ?>
        <description><![CDATA[<?php echo mzen_the_excerpt_rss(); ?>]]></description>
        <?php } ?>
        <content:encoded><![CDATA[
       	<?php 
        global $post;
        $tt = $post;
		$content = mzen_the_content_feed();
        $post = $tt;
        setup_postdata( $post );
        
        if ($yzexcludetags != 'disabled' && $yzexcludetagslist) {
            $content = mzen_strip_tags_without_content($content, $yzexcludetagslist);
        }
        if ($yzexcludetags2 != 'disabled' && $yzexcludetagslist2) {
            $content = mzen_strip_tags_with_content($content, $yzexcludetagslist2, true);
        }
        
        if ($yzthumbnail=="enabled" && has_post_thumbnail( get_the_ID() )) {
            $image_data = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID(),$yzselectthumb),$yzselectthumb);
            $caption = ''; $imgurl = '';
            $caption = get_the_post_thumbnail_caption(get_the_ID());
            $imgurl = strtok(get_the_post_thumbnail_url(get_the_ID(),$yzselectthumb), '?');
            if ( $caption ) {
                $temp = '<figcaption>'.$caption.'</figcaption>';}
            else {
                $temp='';
            }
            $content = '<figure><img src="'. $imgurl .'" alt="" width="'.$image_data[1].'" height="'.$image_data[2].'" />'.$temp.'</figure>'. PHP_EOL . $content;
        }
        if ($yzthumbnail=="enabled" && ! has_post_thumbnail( get_the_ID() )) {
            $caption = ''; $imgurl = '';
            $caption = apply_filters('mzen_thumb_caption', $caption);
            $imgurl = apply_filters('mzen_thumb_imgurl', $imgurl);
            if ( $caption ) {
                $temp = '<figcaption>'.$caption.'</figcaption>';}
            else {
                $temp='';
            }
            if ( $imgurl ) {
                $content = '<figure><img src="'. $imgurl .'" alt="" />'.$temp.'</figure>'. PHP_EOL . $content;
            }
        }
        
        //удаляем все unicode-символы (как невалидные в rss)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
        
        //удаляем все атрибуты тега img кроме src, width, height
        $content = mzen_strip_attributes($content,array('src','width','height'));
        
        $content = wpautop($content);

        //удаляем разметку движка при использовании шорткода с подписью [caption] (в html4 темах - classic editor)
        $pattern = "/<div(.*?)>(.*?)<img(.*?)\/>(.*?)<\/p>\n<p(.*?)>(.*?)<\/p>\n<\/div>/i";
        $replacement = '<tempfigure>$2<tempimg$3/>$4<tempfigcaption>$6</tempfigcaption></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);
        //разметка описания на случай, если тег div удаляется в настройках плагина
        $pattern = "/<p>(.*?)<img(.*?)\/>(.*?)<\/p>\n<p(.*?)class=\"wp-caption-text\">(.*?)<\/p>/i";
        $replacement = '<tempfigure>$1<tempimg$2/>$3<tempfigcaption>$5</tempfigcaption></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //удаляем разметку движка при использовании шорткода с подписью [caption] (в html5 темах - classic editor)
        $pattern = "/<figure(.*?)>(.*?)<img(.*?)\/>(.*?)<figcaption(.*?)>(.*?)<\/figcaption><\/figure>/i";
        $replacement = '<tempfigure>$2<tempimg$3/>$4<tempfigcaption>$6</tempfigcaption></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //удаляем <figure>, если они изначально присутствуют в контенте записи (с указанным caption - gutenberg)
        $pattern = "/<figure(.*?)>(.*?)<img(.*?)\/>(.*?)<figcaption(.*?)>(.*?)<\/figcaption><\/figure>/i";
        $replacement = '<tempfigure>$2<tempimg$3/>$4<tempfigcaption>$6</tempfigcaption></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //удаляем <figure>, если они изначально присутствуют в контенте записи (без caption - gutenberg)
        $pattern = "/<figure(.*?)>(.*?)<img(.*?)>(.*?)<\/figure>/i";
        $replacement = '<tempfigure$1>$2<tempimg$3>$4</tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //удаляем <figure> вокруг всех элементов (яндекс такое не понимает)
        $pattern = "/<figure(.*?)>/i";
        $replacement = '';
        $content = preg_replace($pattern, $replacement, $content);
        $pattern = "/<\/figure>/i";
        $replacement = '';
        $content = preg_replace($pattern, $replacement, $content);
        $pattern = "/<figcaption(.*?)>(.*?)<\/figcaption>/i";
        $replacement = '';
        $content = preg_replace($pattern, $replacement, $content);

        //обрабатываем картинки в ссылках
        $pattern = "/<a(.*?)>(.*?)<img(.*?)>(.*?)<\/a>/i";
        $replacement = '<tempfigure><a$1><tempimg$3></a></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //обрабатываем картинки без ссылок
        $pattern = "/<img(.*?)>/i";
        $replacement = '<tempfigure><tempimg$1></tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        //удаляем лишние теги параграфов
        $pattern = "/<p><tempfigure>(.*?)<\/tempfigure><\/p>/i";
        $replacement = '<tempfigure>$1</tempfigure>';
        $content = preg_replace($pattern, $replacement, $content);

        $copyrighttext = ' <span class="copyright">'. $yzimgauthor .'</span>';
        if ($yzimgauthorselect == 'Отключить указание автора') {$copyrighttext = '';}
        if ($yzfigcaption != 'Отключить описания' && $yzimgauthorselect != 'Отключить указание автора') {
             $content = str_replace('</tempfigcaption>', $copyrighttext.'</tempfigcaption>', $content);
        }

        if ($yzfigcaption == 'Отключить описания') {
             $pattern = "/<tempfigcaption>(.*?)<\/tempfigcaption>/i";
             $replacement = '';
             $content = preg_replace($pattern, $replacement, $content);
        }

        $content = str_replace('<tempfigure', '<figure', $content);
        $content = str_replace('</tempfigure>', '</figure>', $content);
        $content = str_replace('<tempfigcaption>', '<figcaption>', $content);
        $content = str_replace('</tempfigcaption>', '</figcaption>', $content);
        $content = str_replace('<tempimg', '<img', $content);

        //формируем video для mp4 файлов согласно документации яндекса (гутенберг)
        $purl = plugins_url('', __FILE__);
        $pattern = "/<video(.*?)src=\"(.*?).mp4(.*?)<\/video>/i";
        $replacement = '<figure><video><source src="$2.mp4" type="video/mp4" /></video><img src="'.$purl.'/img/video.png'.'" /></figure>';
        $content = preg_replace($pattern, $replacement, $content);

        //формируем video для mp4 файлов согласно документации яндекса (классический редактор)
        $content = str_replace('<!--[if lt IE 9]><script>document.createElement(\'video\');</script><![endif]-->', '', $content);
        $pattern = "/<video class=\"wp-video-shortcode\"(.*?)><source(.*?)src=\"(.*?).mp4(.*?)\"(.*?)\/>(.*?)<\/video>/i";
        $replacement = '<figure><video><source src="$3.mp4" type="video/mp4" /></video><img src="'.$purl.'/img/video.png'.'" /></figure>';
        $content = preg_replace($pattern, $replacement, $content);

        if ($yzexcludecontent!='disabled' && $yzexcludecontentlist) {
            $textAr = explode("\n", trim($yzexcludecontentlist));
            $textAr = array_filter($textAr, 'trim');
            foreach ($textAr as $line) {
                $line = trim($line);
                $content = preg_replace('/'.$line.'/i','', $content);
            }    
        }    
        
        $content = preg_replace('/<p>https:\/\/youtu.*?<\/p>/i','', $content);
        $content = preg_replace('/<p>https:\/\/www.youtu.*?<\/p>/i','', $content);
        
        $content = apply_filters('mzen_the_content_end', $content);
    
		echo $content;

		?>]]></content:encoded>
    </item>
<?php endwhile; ?>
<?php wp_reset_postdata(); ?>
<?php wp_reset_query(); ?>
</channel>
</rss>
<?php }
//шаблон для RSS-ленты Яндекс.Дзен end

//функция установки корректного mime type для изображений begin
function mzen_mime_type($file) {
	$mime_type = array(
		"bmp"			=>	"image/bmp",
		"gif"			=>	"image/gif",
		"ico"			=>	"image/x-icon",
		"jpeg"			=>	"image/jpeg",
		"jpg"			=>	"image/jpeg",
		"png"			=>	"image/png",
		"psd"			=>	"image/vnd.adobe.photoshop",
		"svg"			=>	"image/svg+xml",
		"tiff"			=>	"image/tiff",
		"webp"			=>	"image/webp",
	);
	$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
	if (isset($mime_type[$extension])) {
		return $mime_type[$extension];
	} else {
		return "Unknown file type";
	}
}
//функция установки корректного mime type для изображений end

//установка правильного content type для ленты плагина begin
function mzen_feed_content_type( $content_type, $type ) {
    $mzen_options = get_option('mzen_options'); 
    if (!isset($mzen_options['yzrssname'])) {$mzen_options['yzrssname']="multizen";update_option('mzen_options', $mzen_options);}
    if( $mzen_options['yzrssname'] == $type ) {
        $content_type = 'application/rss+xml';
    }
    return $content_type;
}
add_filter( 'feed_content_type', 'mzen_feed_content_type', 10, 2 );
//установка правильного content type для ленты плагина end

//функция формирования description в rss begin
function mzen_the_excerpt_rss() {
    $content = get_the_excerpt();
    $content = apply_filters('mzen_the_excerpt', $content);
    $content = apply_filters('convert_chars', $content);
    $content = apply_filters('ent2ncr', $content, 8);
    return $content;
}
//функция формирования description в rss end

//функция формирования content в rss begin
function mzen_the_content_feed() {
    $mzen_options = get_option('mzen_options');  
    if ($mzen_options['yzexcerpt'] == 'enabled') {
        $content = '';
        if ( has_excerpt( get_the_ID() ) ) {
            $content = '<p>' . get_the_excerpt( get_the_ID() ) . '</p>';
        }
        $content .= apply_filters('the_content', get_post_field('post_content', get_the_ID()));
    } else {
        $content = apply_filters('the_content', get_post_field('post_content', get_the_ID()));
    }    
    $content = apply_filters('mzen_the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
    $content = apply_filters('wp_staticize_emoji', $content);
    $content = apply_filters('_oembed_filter_feed_content', $content);
    return $content;
}
//функция формирования content в rss end

//функция удаления тегов вместе с их контентом begin 
function mzen_strip_tags_with_content($text, $tags = '', $invert = FALSE) {
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
		$text = mzen_strip_tags_with_content( $text, $tags, $invert );
	}

	return $text;
} 
//функция удаления тегов вместе с их контентом end

//функция удаления тегов без их контента begin 
function mzen_strip_tags_without_content($text, $tags = '') {

    preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
    $tags = array_unique($tags[1]);
   
    if(is_array($tags) AND count($tags) > 0) {
        foreach($tags as $tag)  {
            $text = preg_replace("/<\\/?" . $tag . "(.|\\s)*?>/", '', $text);
        }
    }
    return $text;
} 
//функция удаления тегов без их контента end 

//функция принудительной установки header-тега X-Robots-Tag (решение проблемы с SEO-плагинами) begin
function mzen_index_follow_rss() {
    $mzen_options = get_option('mzen_options'); 
    if (!isset($mzen_options['yzrssname'])) {$mzen_options['yzrssname']="multizen";update_option('mzen_options', $mzen_options);}
    if ( is_feed( $mzen_options['yzrssname'] ) ) {
        header( 'X-Robots-Tag: index, follow', true );
    }
}
add_action( 'template_redirect', 'mzen_index_follow_rss', 999999 );
//функция принудительной установки header-тега X-Robots-Tag (решение проблемы с SEO-плагинами) end

//функция удаления всех атрибутов тега img кроме указанных begin
function mzen_strip_attributes($s, $allowedattr = array()) {
  if (preg_match_all("/<img[^>]*\\s([^>]*)\\/*>/msiU", $s, $res, PREG_SET_ORDER)) {
   foreach ($res as $r) {
     $tag = $r[0];
     $attrs = array();
     preg_match_all("/\\s.*=(['\"]).*\\1/msiU", " " . $r[1], $split, PREG_SET_ORDER);
     foreach ($split as $spl) {
      $attrs[] = $spl[0];
     }
     $newattrs = array();
     foreach ($attrs as $a) {
      $tmp = explode("=", $a);
      if (trim($a) != "" && (!isset($tmp[1]) || (trim($tmp[0]) != "" && !in_array(strtolower(trim($tmp[0])), $allowedattr)))) {

      } else {
          $newattrs[] = $a;
      }
     }
    
     //сортировка чтобы alt был раньше src   
     sort($newattrs);
     reset($newattrs);
     
     $attrs = implode(" ", $newattrs);
     $rpl = str_replace($r[1], $attrs, $tag);
     //заменяем одинарные кавычки на двойные
     $rpl = str_replace("'", "\"", $rpl);   
     
     //добавляем закрывающий символ / если он отсутствует
     $rpl = str_replace("\">", "\" />", $rpl);
     //добавляем пробел перед закрывающим символом /
     $rpl = str_replace("\"/>", "\" />", $rpl);
     
     //удаляем двойные пробелы
     $rpl = str_replace("  ", " ", $rpl);
    
     //выносим атрибут height в конец тега   
     $pattern = '/<img(.*?) height="(.*?)" (.*?) \/>/i';
     $replacement = '<img$1 $3 height="$2" />';
     $rpl = preg_replace($pattern, $replacement, $rpl);
     
     $s = str_replace($tag, $rpl, $s);
   }
  } 

  return $s;
}
//функция удаления всех атрибутов тега img кроме указанных end

//функция установки новых опций при обновлении плагина у пользователей begin
function mzen_set_new_options() { 
$mzen_options = get_option('mzen_options');
if (!isset($mzen_options['yzthumbnail'])) {$mzen_options['yzthumbnail']="disabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzselectthumb'])) {$mzen_options['yzselectthumb']="";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzseodesc'])) {$mzen_options['yzseodesc']="disabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzseoplugin'])) {$mzen_options['yzseoplugin']="Yoast SEO";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludetags'])) {$mzen_options['yzexcludetags']="disabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludetagslist'])) {$mzen_options['yzexcludetagslist']="<div>";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludetags2'])) {$mzen_options['yzexcludetags2']="enabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludetagslist2'])) {$mzen_options['yzexcludetagslist2']="<iframe>,<script>,<ins>,<style>,<object>";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludecontent'])) {$mzen_options['yzexcludecontent']="enabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludecontentlist'])) {$mzen_options['yzexcludecontentlist']=esc_textarea("<!--more-->\n<p><\/p>\n<p>&nbsp;<\/p>");update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzmediascope'])) {$mzen_options['yzmediascope']="";update_option('mzen_options', $mzen_options);}    
if (!isset($mzen_options['yzqueryselect'])) {$mzen_options['yzqueryselect']="Все таксономии, кроме исключенных";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yztaxlist'])) {$mzen_options['yztaxlist']="";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzaddtaxlist'])) {$mzen_options['yzaddtaxlist']="";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcerpt'])) {$mzen_options['yzexcerpt']="disabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzexcludedefault'])) {$mzen_options['yzexcludedefault']="disabled";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yztypearticle'])) {$mzen_options['yztypearticle']="false";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yztypeplatform'])) {$mzen_options['yztypeplatform']="native-no";update_option('mzen_options', $mzen_options);}
if (!isset($mzen_options['yzindex'])) {$mzen_options['yzindex']="index";update_option('mzen_options', $mzen_options);}


if ( $mzen_options['yzfigcaption'] != "Отключить описания" ) {$mzen_options['yzfigcaption'] = 'Использовать подписи';update_option('mzen_options', $mzen_options);}
}
//функция установки новых опций при обновлении плагина у пользователей end

//функция исключения записей из ленты по умолчанию begin
function mzen_new_post( $post_id, $post, $update ) {
    $mzen_options = get_option('mzen_options');
    if ( $mzen_options['yzexcludedefault'] == 'disabled' ) 
        return;
    
    if ( !get_post_meta( $post_id, 'mzenrssenabled_meta_value', true ) ) {
        update_post_meta( $post_id, 'mzenrssenabled_meta_value', 'yes' );
    }
}
add_action( 'save_post', 'mzen_new_post', 10, 3 );
//функция исключения записей из ленты по умолчанию end