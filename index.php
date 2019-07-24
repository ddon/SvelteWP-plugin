<?php
/**
 * Plugin Name: SvelteWP
 * Version:     1.0.1
 * Author:      Fotki Agency / Dmitri Don
 * Text Domain: sveltewp
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/ddon/SvelteWP-plugin
 */

require_once(plugin_dir_path(__FILE__) . 'api/routing.php');


function get_menu() {
    # Change 'menu' to your own navigation slug.
    if (function_exists('pll_the_languages')) {
        global $polylang;

        //return pll_the_languages();

        $languages = pll_the_languages(array('raw' => 1));
        return $languages;
    } else {
        return '2';
    }
    //return wp_get_nav_menu_items('menu');
    // return wp_get_nav_menu_items('menu');
}

add_action('rest_api_init', function () {
    register_rest_route('myroutes', '/menu', [
        'methods' => 'GET',
        'callback' => 'get_menu',
    ]);

    // https://wp.greenoak.ee/wp-json/svelte-wp/v1/routing
    register_rest_route('svelte-wp/v1', '/routing', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => ['SvelteWP_RoutingAPI', 'get_routing']
    ]);
});

function wpse_modify_taxonomy() {
    // get the arguments of the already-registered taxonomy
    $language_args = get_taxonomy( 'language' ); // returns an object

    // make changes to the args
    // in this example there are three changes
    // again, note that it's an object
    $language_args->show_in_rest = true;

    // re-register the taxonomy
    register_taxonomy( 'language', 'post', (array) $language_args );
}
// hook it up to 11 so that it overrides the original register_taxonomy function
add_action( 'init', 'wpse_modify_taxonomy', 11 );
