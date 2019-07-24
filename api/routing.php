<?php


class SvelteWP_Data {
    public static function get_languages() {
        if (!function_exists('pll_the_languages')) {
            return [];
        }

        global $polylang;
        
        global $wpdb;
        
        $polylang_settings = unserialize($wpdb->get_var("SELECT option_value FROM wp_options WHERE option_name='polylang'"));
        $default_lang = $polylang_settings['default_lang'];

        $all_languages = get_terms('language', [
            'hide_empty' => false,
            'orderby' => 'term_group'
        ]);

        $languages = [];

        foreach ($all_languages as $l) {
            $desc = unserialize($l->description);

            $languages[] = [
                'name' => $l->name,
                'slug' => $l->slug,
                'locale' => $desc['locale'],
                'flag_code' => $desc['flag_code'],
                'default' => ($l->slug === $default_lang ? true : false)
            ];
        }
        
        return $languages;
    }

    public static function get_submenus($items, $all_items) {
        for ($i = 0; $i < count($items); $i++) {
            foreach ($all_items as $item) {
                if ($items[$i]['page_id'] == $item->post_parent) {
                    $url = $item->url;

                    if (strpos($url, 'http') === 0) {
                        $url = parse_url($url)["path"];
                    }

                    $items[$i]['items'][] = [
                        'page_id' => $item->object_id,
                        'url' => $url,
                        'title' => $item->title,
                        'items' => []
                    ];
                }
            }
    
            if (!empty($items[$i]['items'])) {
                $items[$i]['items'] = self::get_submenus($items[$i]['items'], $all_items);
            }
        }
    
        return $items;
    }

    public static function get_menus_and_map() {
        $nav_menus = wp_get_nav_menus();

        $menus = [];
        $url_page_map = [];

        foreach ($nav_menus as $menu) {
            $all_menu_items = wp_get_nav_menu_items($menu);

            $items = [];

            foreach ($all_menu_items as $mi) {
                $parent_id = $mi->post_parent;
            
                if ($parent_id === 0) {
                    $url = $mi->url;

                    if (strpos($url, 'http') === 0) {
                        $url = parse_url($url)["path"];
                    }

                    $items[] = [
                        'page_id' => $mi->object_id,
                        'url' => $url,
                        'title' => $mi->title,
                        'items' => []
                    ];
                }

                $url_page_map[$url] = [
                    'page_id' => $mi->object_id
                ];
            }
            
            $items = self::get_submenus($items, $all_menu_items);

            $menus[] = [
                'menu_id' => $menu->term_id,
                'slug' => $menu->slug,
                'items' => $items
            ];
        }

        return [
            'menus' => $menus,
            'url_page_map' => $url_page_map
        ];
    }
}

class SvelteWP_RoutingAPI {
    public static function get_routing() {
        $languages = SvelteWP_Data::get_languages();
        $data = SvelteWP_Data::get_menus_and_map();

        $menus = $data['menus'];
        $url_page_map = $data['url_page_map'];

        return [
            'ok' => true,
            'data' => [
                'menus' => $menus,
                'languages' => $languages,
                'url_page_map' => $url_page_map
            ]
        ];
    }
}

