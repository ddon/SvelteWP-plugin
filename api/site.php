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

        $custom_logo_url = '';

        if (has_custom_logo()) {
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_meta = wp_get_attachment_image_src($custom_logo_id, 'full');
            $custom_logo_url = $logo_meta[0];
        }

        $first_pages = SvelteWP_Data::get_first_pages($url_page_map);

        for ($i = 0; $i < count($languages); $i++) {
            if (empty($first_pages[$languages[$i]['slug']])) {
                $languages[$i]['default_url'] = '/';
            } else {
                $languages[$i]['default_url'] = $first_pages[$languages[$i]['slug']];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'site_title' => $site_title,
                'site_description' => $site_description,
                'site_logo' => $custom_logo_url,
                'menus' => $menus,
                'languages' => $languages,
                'url_page_map' => $url_page_map,
                'header' => $header,
                'footer' => $footer,
                'dictionary' => !empty(SvelteWP_Translations::$dictionary) ? SvelteWP_Translations::$dictionary : [],
                'common_settings' => [
                    'mouse_over_menu' => !empty(get_option('sveltewp_mouse_over_menu')) ? true : false
                ],
            ]
        ];
    }
}
