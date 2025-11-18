<?php
/**
 * Template Name: Enhanced RSS Template - enhanced
 */

$postCount = 10; // The number of posts to show in the feed
$posts = query_posts('showposts=' . $postCount);

header('Content-Type: '.feed_content_type('rss-http').'; charset='.get_option('blog_charset'), true);
echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';

$new_domain = 'https://f3rva.org';
?>

<rss version="2.0"
        xmlns:content="http://purl.org/rss/1.0/modules/content/"
        xmlns:wfw="http://wellformedweb.org/CommentAPI/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:atom="http://www.w3.org/2005/Atom"
        xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
        xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
        <?php do_action('rss2_ns'); ?>>
    <channel>
        <title><?php bloginfo_rss('name'); ?></title>
        <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
        <link><?php echo $new_domain ?></link>
        <description><?php bloginfo_rss('description') ?></description>
        <lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
        <language><?php echo get_option('rss_language'); ?></language>
        <sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
        <sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	
        <?php while(have_posts()) : the_post(); ?>
        <item>
            <title><?php 
    $content = get_the_title_rss();
    $posttag = "";
	$posttags = get_the_tags();
	
	if ($posttags) {
		foreach($posttags as $tag) {
			$posttag .= '['.$tag->name . '] ';
		}
		$content = $posttag.$content;
	}
	
	echo $content;
                        ?></title>
            <link><?php
    $workout_date = get_field('workout_date_new', get_the_ID());
	$workout_url = '';
    
    if ($workout_date) {
        // Create a DateTime object from the ACF field value.
        // Assumes the date is stored in a format PHP can understand (like Y-m-d).
        $date_obj = date_create($workout_date);
        if ($date_obj) {
            $date_path = date_format($date_obj, 'Y/m/d');
            $slug = get_post_field('post_name', get_the_ID());
			$workout_url = $new_domain . '/' . $date_path . '/' . $slug . '/';
            echo esc_url($workout_url);
        } 
    } else {
        echo $new_domain;
    }
?></link>
            <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
            <dc:creator><?php the_author(); ?></dc:creator>
            <guid isPermaLink="false"><?php the_guid(); ?></guid>
            <description><![CDATA[<?php
    $site_url = get_site_url();

	// This regex finds URLs in href attributes that start with the site URL
	$pattern = '/(href=["\'])' . preg_quote($site_url, '/') . '[^"\']*(["\'])/';

	// This will replace the entire old URL with the new one
	$replacement = '$1' . $workout_url . '$2';

	$excerpt_replaced = preg_replace($pattern, $replacement, get_the_excerpt());
	echo apply_filters( 'the_excerpt_rss', $excerpt_replaced );
?>]]></description>
            <content:encoded><![CDATA[<?php
    echo apply_filters( 'the_excerpt_rss', $excerpt_replaced );
?>]]></content:encoded>
        </item>
        <?php endwhile; ?>
</channel>
</rss>
