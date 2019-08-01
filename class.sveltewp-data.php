<?php

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;


class SvelteWP_Data {
    public static function content_to_yaml($text) {
        $content = '';

        if (preg_match('|<code>(.*)</code>|s', $text, $matches)) {
            $yaml = $matches[1];

            try {
                $content = Yaml::parse($yaml);
            } catch (ParseException $exception) {
                error_log($exception->getMessage());
                $content = '';
            }
        }

        return $content;
    }

    public static function get_languages() {
        if (!function_exists('pll_the_languages')) {
            return [];
        }

        global $polylang;
        
        global $wpdb;
        
        $polylang_settings = unserialize($wpdb->get_var(
            "SELECT option_value FROM $wpdb->options WHERE option_name='polylang'"
        ));

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
                        $url = parse_url($url)['path'];
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

                $url = $mi->url;

                if (strpos($url, 'http') === 0) {
                    $url = parse_url($url)['path'];
                }

                if ($parent_id === 0) {
                    $items[] = [
                        'page_id' => $mi->object_id,
                        'url' => $url,
                        'title' => $mi->title,
                        'items' => []
                    ];
                }

                $url_page_map[$url] = [
                    'page_id' => intval($mi->object_id)
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

    public static function get_header() {
        $header = [];
        $sveltewp_header_page_id = get_option('sveltewp_header_page_id');

        if (function_exists('pll_get_post_translations') && (!empty($sveltewp_header_page_id))) {
            $header_translations = pll_get_post_translations($sveltewp_header_page_id);

            foreach($header_translations as $l => $page_id) {
                $p = get_post($page_id);
                $content = self::content_to_yaml($p->post_content);

                $header['translations'][$l] = [
                    'id' => $p->ID,
                    'title' => $p->post_title,
                    'content' => $content,
                ];
            }
        } else if (!empty($sveltewp_header_page_id)) {
            $p = get_post($page_id);

            $header = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'content' => $p->post_content,
            ];
        }

        return $header;
    }

    public static function get_footer() {
        $footer = [];
        $sveltewp_footer_page_id = get_option('sveltewp_footer_page_id');

        if (function_exists('pll_get_post_translations') && (!empty($sveltewp_footer_page_id))) {
            $footer_translations = pll_get_post_translations($sveltewp_footer_page_id);

            foreach($footer_translations as $l => $page_id) {
                $p = get_post($page_id);
                $content = self::content_to_yaml($p->post_content);

                $footer['translations'][$l] = [
                    'id' => $p->ID,
                    'title' => $p->post_title,
                    'content' => $content,
                ];
            }
        } else if (!empty($sveltewp_footer_page_id)) {
            $p = get_post($page_id);

            $footer = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'content' => $p->post_content,
            ];
        }
        return $footer;
    }
}

