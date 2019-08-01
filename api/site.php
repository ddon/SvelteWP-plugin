<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_SiteAPI {
    public static function get_init() {
        $languages = SvelteWP_Data::get_languages();
        $data = SvelteWP_Data::get_menus_and_map();

        $menus = $data['menus'];
        $url_page_map = $data['url_page_map'];

        $header = SvelteWP_Data::get_header();
        $footer = SvelteWP_Data::get_footer();

        $site_title = get_bloginfo('name');
        $site_description = get_bloginfo('description');

        return [
            'ok' => true,
            'data' => [
                'site_title' => $site_title,
                'site_description' => $site_description,
                'menus' => $menus,
                'languages' => $languages,
                'url_page_map' => $url_page_map,
                'header' => $header,
                'footer' => $footer
            ]
        ];
    }
}
