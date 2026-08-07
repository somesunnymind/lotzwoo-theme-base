<?php

/**
 * LotzApp B2B Base — theme bootstrap.
 *
 * Deliberately thin. A block theme declares almost everything in
 * `theme.json`, and every line here is a line that has to be maintained
 * against a WordPress release. What remains is what `theme.json` cannot say.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_theme_support('custom-logo', [
        'height' => 40,
        'width' => 200,
        'flex-height' => true,
        'flex-width' => true,
    ]);

    // WooCommerce renders its cart and checkout as blocks on this site, but the
    // account pages and the order-received screen still go through templates it
    // ships. Declaring support is what stops it from loading its own fallback
    // stylesheet over ours.
    add_theme_support('woocommerce');

    load_theme_textdomain('lotzapp-base', get_template_directory() . '/languages');
});

/**
 * The stylesheet, and the child's on top of it when there is one.
 *
 * Version taken from the file's own mtime rather than a constant: a theme is
 * edited far more often than it is released, and a stale stylesheet behind a
 * CDN is the kind of bug that costs an afternoon.
 */
add_action('wp_enqueue_scripts', static function (): void {
    $parent = get_template_directory() . '/style.css';

    wp_enqueue_style(
        'lotzapp-base',
        get_template_directory_uri() . '/style.css',
        [],
        is_readable($parent) ? (string) filemtime($parent) : null
    );

    if (get_template_directory() === get_stylesheet_directory()) {
        return;
    }

    $child = get_stylesheet_directory() . '/style.css';

    if (!is_readable($child)) {
        return;
    }

    wp_enqueue_style(
        'lotzapp-child',
        get_stylesheet_uri(),
        ['lotzapp-base'],
        (string) filemtime($child)
    );
});

/**
 * Mark the full-width template on the body, so the stylesheet can widen it.
 *
 * `wp_is_block_theme()` templates carry no body class of their own for a custom
 * template, and the alternative — widening every page and narrowing the rest —
 * gets the default wrong for the pages that are ordinary prose.
 */
add_filter('body_class', static function (array $classes): array {
    if (is_page() && get_page_template_slug() === 'page-full-width') {
        $classes[] = 'lotzwoo-page-full-width';
    }

    return $classes;
});
