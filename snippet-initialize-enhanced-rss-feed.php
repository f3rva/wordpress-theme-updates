add_action('init', 'enhancedRSS');
function enhancedRSS() {
    add_feed('enhanced', 'enhancedRSSFunc');
}

function enhancedRSSFunc() {
	get_template_part('rss', 'enhanced');
}
