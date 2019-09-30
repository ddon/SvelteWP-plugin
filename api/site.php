<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_SiteAPI
{
    public static function get_init()
    {
        $languages = SvelteWP_Data::get_languages();
        $menus_and_map = SvelteWP_Data::get_menus_and_map();

        $menus = $menus_and_map['menus'];
        $url_page_map = $menus_and_map['url_page_map'];

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

        $lang_switcher_settings = [];

        $menus_needed = get_menus_needed();

        if (!empty($menus_needed)) {
            foreach ($menus_needed as $menu_needed) {
                if (isset($GLOBALS["polylang"])) {
                    $all_languages = pll_languages_list();

                    if (!empty($all_languages)) {
                        foreach ($all_languages as $lang) {
                            $sw_settings_as_dropdown = get_option('sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_as_dropdown');
                            $sw_settings_show_names = get_option('sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_names');
                            $sw_settings_show_flags = get_option('sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_flags');
                            $sw_settings_hide_if_current_lang = get_option('sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_current_lang');
                            $sw_settings_hide_if_no_translation = get_option('sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_no_translation');

                            $lang_switcher_settings[$menu_needed['slug']] = [
                                'as_dropdown' => !empty($sw_settings_as_dropdown) ? true : false,
                                'show_names' => !empty($sw_settings_show_names) ? true : false,
                                'show_flags' => !empty($sw_settings_show_flags) ? true : false,
                                'hide_if_current_lang' => !empty($sw_settings_hide_if_current_lang) ? true : false,
                                'hide_if_no_translation' => !empty($sw_settings_hide_if_no_translation) ? true : false,
                            ];
                        }
                    }
                }
            }
        }

        $sveltewp_css_list = get_option('sveltewp_css_list');
        $sveltewp_js_list = get_option('sveltewp_js_list');

        return [
            'ok' => true,
            'data' => [
                'site_title' => $site_title,
                'site_description' => $site_description,
                'site_logo' => $custom_logo_url,
                'menus' => $menus,
                'languages' => [
                    'items' => $languages,
                    'language_switcher_settings' => $lang_switcher_settings
                ],
                'url_page_map' => $url_page_map,
                'header' => $header,
                'footer' => $footer,
                'dictionary' => !empty(SvelteWP_Translations::$dictionary) ? SvelteWP_Translations::$dictionary : [],
                'pubnub' => [
                    'publish_key' => get_option('sveltewp_publish_key') ?? '',
                    'subscribe_key' => get_option('sveltewp_subscribe_key') ?? ''
                ],
                'assets' => [
                    'css' => !empty($sveltewp_css_list) ? $sveltewp_css_list : [],
                    'js' => !empty($sveltewp_js_list) ? $sveltewp_js_list : []
                ]
            ]
        ];
    }
}
