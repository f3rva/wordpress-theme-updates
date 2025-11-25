/**
 * Removes special characters from slugs before they are saved.
 * Only allows lowercase letters, numbers, and hyphens.
 *
 * @param string $title The title (slug) to be sanitized.
 * @param string $raw_title The original title.
 * @param string $context The context of the sanitization ('save' or 'query').
 * @return string The sanitized title.
 */
function remove_special_chars_from_slug($title, $raw_title, $context) {
    // Only apply this logic when the slug is being prepared for saving
    if ('save' == $context) {
        // The regex pattern below:
        // - [^a-z0-9-] : Matches any character that is NOT a lowercase letter (a-z), a number (0-9), or a hyphen (-).
        // - /i : Makes the match case-insensitive (so it includes A-Z as well).
        $title = preg_replace('/[^a-z0-9-]/i', '', $title);

        // Optional: Convert to lowercase and remove duplicate hyphens
        $title = strtolower($title);
        $title = preg_replace('/--+/', '-', $title); // Replace multiple hyphens with a single one
        $title = trim($title, '-'); // Remove leading/trailing hyphens
    }
    return $title;
}

// Hook the function into the sanitize_title filter
add_filter('sanitize_title', 'remove_special_chars_from_slug', 10, 3);