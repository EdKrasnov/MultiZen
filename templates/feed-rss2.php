<?php
/**
 * XML-шаблон ленты RSS 2.0 для канала.
 *
 * Ожидает переменные:
 *   @var array    $channel Параметры канала.
 *   @var WP_Query $query   Готовый запрос.
 *
 * @package MultiZen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var array $channel */
/** @var WP_Query $query */

$channel_title       = MZEN_Helpers::opt( $channel, 'title', get_bloginfo( 'name' ) );
$channel_link        = MZEN_Helpers::opt( $channel, 'link', get_bloginfo( 'url' ) );
$channel_description = MZEN_Helpers::opt( $channel, 'description', get_bloginfo( 'description' ) );
$channel_language    = MZEN_Helpers::opt( $channel, 'language', 'ru' );

$figcaption          = MZEN_Helpers::opt( $channel, 'figcaption', 'Использовать подписи' );
$imgauthorselect     = MZEN_Helpers::opt( $channel, 'imgauthorselect', 'Автор записи' );
$imgauthor           = MZEN_Helpers::opt( $channel, 'imgauthor', '' );
$author              = MZEN_Helpers::opt( $channel, 'author', '' );
$thumbnail           = MZEN_Helpers::opt( $channel, 'thumbnail', 'disabled' );
$selectthumb         = MZEN_Helpers::opt( $channel, 'selectthumb', '' );
$seodesc             = MZEN_Helpers::opt( $channel, 'seodesc', 'disabled' );
$seoplugin           = MZEN_Helpers::opt( $channel, 'seoplugin', 'Yoast SEO' );
$excludetags         = MZEN_Helpers::opt( $channel, 'excludetags', 'enabled' );
$excludetagslist     = html_entity_decode( MZEN_Helpers::opt( $channel, 'excludetagslist', '' ) );
$excludetags2        = MZEN_Helpers::opt( $channel, 'excludetags2', 'enabled' );
$excludetagslist2    = html_entity_decode( MZEN_Helpers::opt( $channel, 'excludetagslist2', '' ) );
$excludecontent      = MZEN_Helpers::opt( $channel, 'excludecontent', 'disabled' );
$excludecontentlist  = html_entity_decode( MZEN_Helpers::opt( $channel, 'excludecontentlist', '' ) );

echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:media="http://search.yahoo.com/mrss/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:georss="http://www.georss.org/georss">
<channel>
	<title><?php echo esc_html( $channel_title ); ?></title>
	<link><?php echo esc_url( $channel_link ); ?></link>
	<description><?php echo esc_html( $channel_description ); ?></description>
	<language><?php echo esc_html( $channel_language ); ?></language>
	<generator>RSS for Multi Zen v<?php echo esc_html( MZEN_VERSION ); ?></generator>
<?php
while ( $query->have_posts() ) :
	$query->the_post();
	$post_id = get_the_ID();

	// -------- Заголовок, ссылка, GUID --------
	?>
	<item>
		<title><?php the_title_rss(); ?></title>
		<link><?php the_permalink_rss(); ?></link>
		<guid><?php echo esc_html( md5( get_the_guid() ) ); ?></guid>
	<?php
		// -------- pubDate --------
		$gmt_offset      = get_option( 'gmt_offset' );
		$gmt_offset_abs  = floor( abs( $gmt_offset ) );
		$gmt_offset_str  = ( $gmt_offset_abs > 9 ) ? $gmt_offset_abs . '00' : ( '0' . $gmt_offset_abs . '00' );
		$gmt_offset_str  = ( $gmt_offset >= 0 ? '+' : '-' ) . $gmt_offset_str;
		$pub_date        = mysql2date( 'D, d M Y H:i:s ' . $gmt_offset_str, get_date_from_gmt( get_post_time( 'Y-m-d H:i:s', true ) ), false );
	?>
		<pubDate><?php echo esc_html( $pub_date ); ?></pubDate>
	<?php
		// -------- media:rating --------
		$rating = get_post_meta( $post_id, '_mzen_rating', true );
		if ( ! $rating ) {
			$rating = MZEN_Helpers::opt( $channel, 'rating', 'Нет (не для взрослых)' );
		}
		if ( 'Да (для взрослых)' === $rating ) {
			echo "\t\t<media:rating scheme=\"urn:simple\">adult</media:rating>\n";
		} else {
			echo "\t\t<media:rating scheme=\"urn:simple\">nonadult</media:rating>\n";
		}

		// -------- author --------
		if ( $author ) {
			echo "\t\t<author>" . esc_html( $author ) . "</author>\n";
		} else {
			echo "\t\t<author>" . esc_html( get_the_author() ) . "</author>\n";
		}

		// -------- вычисляем автора изображений --------
		if ( 'Указать автора' === $imgauthorselect && ! $imgauthor ) {
			$imgauthor = get_the_author();
		}
		if ( 'Автор записи' === $imgauthorselect ) {
			$imgauthor = get_the_author();
		}

		// -------- category (тематика) --------
		$category = get_post_meta( $post_id, '_mzen_category', true );
		if ( ! $category ) {
			$category = MZEN_Helpers::opt( $channel, 'category', 'Общество' );
		}
		echo "\t\t<category>" . esc_html( $category ) . "</category>\n";

		// -------- typearticle: evergreen --------
		$typearticle = get_post_meta( $post_id, '_mzen_type_article', true );
		if ( ! $typearticle ) {
			$typearticle = MZEN_Helpers::opt( $channel, 'typearticle', 'false' );
		}
		$typearticle = apply_filters( 'mzen_type_article', $typearticle );
		if ( 'false' === $typearticle ) {
			echo "\t\t<category>evergreen</category>\n";
		}

		// -------- typeplatform --------
		$typeplatform = get_post_meta( $post_id, '_mzen_type_platform', true );
		if ( ! $typeplatform ) {
			$typeplatform = MZEN_Helpers::opt( $channel, 'typeplatform', 'native-no' );
		}
		$typeplatform = apply_filters( 'mzen_type_platform', $typeplatform );
		if ( $typeplatform ) {
			echo "\t\t<category>" . esc_html( $typeplatform ) . "</category>\n";
		}

		// -------- index --------
		$index = get_post_meta( $post_id, '_mzen_index', true );
		if ( ! $index ) {
			$index = MZEN_Helpers::opt( $channel, 'index', 'index' );
		}
		$index = apply_filters( 'mzen_index', $index );
		if ( $index ) {
			echo "\t\t<category>" . esc_html( $index ) . "</category>\n";
		}

		echo "\t\t<category>comment-subscribers</category>\n";

		// -------- thumbnail enclosure --------
		if ( 'enabled' === $thumbnail && has_post_thumbnail( $post_id ) ) {
			$thumb_url = strtok( get_the_post_thumbnail_url( $post_id, $selectthumb ), '?' );
			echo "\t\t<enclosure url=\"" . esc_url( $thumb_url ) . "\" type=\"" . esc_attr( MZEN_Helpers::mime_type( $thumb_url ) ) . "\"/>\n";
		}

		// -------- разбор контента для enclosure + собственно content --------
		$html = MZEN_Helpers::the_content_feed( $channel );

		if ( 'disabled' !== $excludetags && $excludetagslist ) {
			$html = MZEN_Helpers::strip_tags_without_content( $html, $excludetagslist );
		}
		if ( 'disabled' !== $excludetags2 && $excludetagslist2 ) {
			$html = MZEN_Helpers::strip_tags_with_content( $html, $excludetagslist2, true );
		}
		$html = wpautop( $html );

		// Ищем ссылки и картинки — соберём enclosure.
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		@$dom->loadHTML( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $html );
		$dom->preserveWhiteSpace = false;

		$final = array();
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = $a->getAttribute( 'href' );
			$mime = MZEN_Helpers::mime_type( strtok( $href, '?' ) );
			if ( 'Unknown file type' !== $mime && ! in_array( $href, $final, true ) ) {
				echo "\t\t<enclosure url=\"" . esc_url( strtok( $href, '?' ) ) . "\" type=\"" . esc_attr( $mime ) . "\"/>\n";
				$final[] = $href;
			}
		}

		$final = array();
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$src = $img->getAttribute( 'src' );
			if ( ! in_array( $src, $final, true ) ) {
				echo "\t\t<enclosure url=\"" . esc_url( strtok( $src, '?' ) ) . "\" type=\"" . esc_attr( MZEN_Helpers::mime_type( strtok( $src, '?' ) ) ) . "\"/>\n";
				$final[] = $src;
			}
		}

		// -------- description --------
		if ( 'disabled' !== $seodesc ) {
			$temp = '';
			if ( 'Yoast SEO' === $seoplugin ) {
				$temp = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			} elseif ( 'All in One SEO Pack' === $seoplugin ) {
				$temp = get_post_meta( $post_id, '_aioseop_description', true );
			}
			$temp = apply_filters( 'mzen_the_excerpt', $temp );
			$temp = apply_filters( 'convert_chars', $temp );
			$temp = apply_filters( 'ent2ncr', $temp, 8 );
			if ( ! $temp ) {
				$temp = MZEN_Helpers::the_excerpt_rss( $channel );
			}
			echo "\t\t<description><![CDATA[" . $temp . "]]></description>\n";
		} else {
			echo "\t\t<description><![CDATA[" . MZEN_Helpers::the_excerpt_rss( $channel ) . "]]></description>\n";
		}

		// -------- content:encoded --------
		global $post;
		$backup_post = $post;
		$content     = MZEN_Helpers::the_content_feed( $channel );
		$post        = $backup_post;
		setup_postdata( $post );

		if ( 'disabled' !== $excludetags && $excludetagslist ) {
			$content = MZEN_Helpers::strip_tags_without_content( $content, $excludetagslist );
		}
		if ( 'disabled' !== $excludetags2 && $excludetagslist2 ) {
			$content = MZEN_Helpers::strip_tags_with_content( $content, $excludetagslist2, true );
		}

		// Миниатюра в начало контента.
		if ( 'enabled' === $thumbnail && has_post_thumbnail( $post_id ) ) {
			$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), $selectthumb );
			$caption    = get_the_post_thumbnail_caption( $post_id );
			$imgurl     = strtok( get_the_post_thumbnail_url( $post_id, $selectthumb ), '?' );
			$temp       = $caption ? '<figcaption>' . $caption . '</figcaption>' : '';
			$content    = '<figure><img src="' . esc_url( $imgurl ) . '" alt="" width="' . intval( $image_data[1] ) . '" height="' . intval( $image_data[2] ) . '" />' . $temp . '</figure>' . PHP_EOL . $content;
		}

		// Если миниатюры нет — попробуем подставить через фильтр.
		if ( 'enabled' === $thumbnail && ! has_post_thumbnail( $post_id ) ) {
			$caption = apply_filters( 'mzen_thumb_caption', '' );
			$imgurl  = apply_filters( 'mzen_thumb_imgurl', '' );
			$temp    = $caption ? '<figcaption>' . $caption . '</figcaption>' : '';
			if ( $imgurl ) {
				$content = '<figure><img src="' . esc_url( $imgurl ) . '" alt="" />' . $temp . '</figure>' . PHP_EOL . $content;
			}
		}

		// Удаляем невалидные unicode-символы.
		$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content );

		// Оставляем у <img> только src, width, height.
		$content = MZEN_Helpers::strip_attributes( $content, array( 'src', 'width', 'height' ) );

		$content = wpautop( $content );

		// -------- Нормализация <figure>/<figcaption>/<img> --------
		$purl = MZEN_URL;

		// [caption] classic editor (html4)
		$content = preg_replace(
			'/<div(.*?)>(.*?)<img(.*?)\/>(.*?)<\/p>\n<p(.*?)>(.*?)<\/p>\n<\/div>/i',
			'<tempfigure>$2<tempimg$3/>$4<tempfigcaption>$6</tempfigcaption></tempfigure>',
			$content
		);
		$content = preg_replace(
			'/<p>(.*?)<img(.*?)\/>(.*?)<\/p>\n<p(.*?)class="wp-caption-text">(.*?)<\/p>/i',
			'<tempfigure>$1<tempimg$2/>$3<tempfigcaption>$5</tempfigcaption></tempfigure>',
			$content
		);

		// Gutenberg figure с figcaption
		$content = preg_replace(
			'/<figure(.*?)>(.*?)<img(.*?)\/>(.*?)<figcaption(.*?)>(.*?)<\/figcaption><\/figure>/i',
			'<tempfigure>$2<tempimg$3/>$4<tempfigcaption>$6</tempfigcaption></tempfigure>',
			$content
		);

		// Gutenberg figure без figcaption
		$content = preg_replace(
			'/<figure(.*?)>(.*?)<img(.*?)>(.*?)<\/figure>/i',
			'<tempfigure$1>$2<tempimg$3>$4</tempfigure>',
			$content
		);

		// Убираем остатки figure
		$content = preg_replace( '/<figure(.*?)>/i', '', $content );
		$content = preg_replace( '/<\/figure>/i', '', $content );
		$content = preg_replace( '/<figcaption(.*?)>(.*?)<\/figcaption>/i', '', $content );

		// Ссылки с картинками
		$content = preg_replace(
			'/<a(.*?)>(.*?)<img(.*?)>(.*?)<\/a>/i',
			'<tempfigure><a$1><tempimg$3></a></tempfigure>',
			$content
		);

		// Просто картинки
		$content = preg_replace(
			'/<img(.*?)>/i',
			'<tempfigure><tempimg$1></tempfigure>',
			$content
		);

		// Убираем <p> вокруг <tempfigure>
		$content = preg_replace(
			'/<p><tempfigure>(.*?)<\/tempfigure><\/p>/i',
			'<tempfigure>$1</tempfigure>',
			$content
		);

		// Copyright автора изображения
		$copyrighttext = ' <span class="copyright">' . esc_html( $imgauthor ) . '</span>';
		if ( 'Отключить указание автора' === $imgauthorselect ) {
			$copyrighttext = '';
		}
		if ( 'Отключить описания' !== $figcaption && 'Отключить указание автора' !== $imgauthorselect ) {
			$content = str_replace( '</tempfigcaption>', $copyrighttext . '</tempfigcaption>', $content );
		}

		if ( 'Отключить описания' === $figcaption ) {
			$content = preg_replace( '/<tempfigcaption>(.*?)<\/tempfigcaption>/i', '', $content );
		}

		// Возвращаем теги в нормальный вид.
		$content = str_replace( '<tempfigure', '<figure', $content );
		$content = str_replace( '</tempfigure>', '</figure>', $content );
		$content = str_replace( '<tempfigcaption>', '<figcaption>', $content );
		$content = str_replace( '</tempfigcaption>', '</figcaption>', $content );
		$content = str_replace( '<tempimg', '<img', $content );

		// -------- Видео mp4 (Gutenberg + классический редактор) --------
		$content = preg_replace(
			'/<video(.*?)src="(.*?).mp4(.*?)<\/video>/i',
			'<figure><video><source src="$2.mp4" type="video/mp4" /></video><img src="' . esc_url( $purl . 'img/video.png' ) . '" /></figure>',
			$content
		);

		$content = str_replace(
			'<!--[if lt IE 9]><script>document.createElement(\'video\');</script><![endif]-->',
			'',
			$content
		);
		$content = preg_replace(
			'/<video class="wp-video-shortcode"(.*?)><source(.*?)src="(.*?).mp4(.*?)"(.*?)\/>(.*?)<\/video>/i',
			'<figure><video><source src="$3.mp4" type="video/mp4" /></video><img src="' . esc_url( $purl . 'img/video.png' ) . '" /></figure>',
			$content
		);

		// -------- excludecontent --------
		if ( 'disabled' !== $excludecontent && $excludecontentlist ) {
			$textAr = array_filter( array_map( 'trim', explode( "\n", $excludecontentlist ) ) );
			foreach ( $textAr as $line ) {
				$content = preg_replace( '/' . preg_quote( $line, '/' ) . '/i', '', $content );
			}
		}

		// -------- Убираем одинокие ссылки на YouTube --------
		$content = preg_replace( '/<p>https:\/\/youtu.*?<\/p>/i', '', $content );
		$content = preg_replace( '/<p>https:\/\/www.youtu.*?<\/p>/i', '', $content );

		$content = apply_filters( 'mzen_the_content_end', $content );
	?>
		<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
	</item>
<?php
endwhile;

wp_reset_postdata();
wp_reset_query();
?>
</channel>
</rss>