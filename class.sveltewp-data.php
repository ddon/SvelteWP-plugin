<?php

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;


class SvelteWP_Data
{
    public static function content_to_yaml($text)
    {
        $content = $text;

        if (preg_match('|<code>(.*)</code>|s', $content, $matches)) {
            $yaml = $matches[1];

            try {
                $content = Yaml::parse($yaml);

                if (!empty($content)) {
                    if (is_array($content)) {
                        array_walk_recursive(
                            $content,
                            function (&$value) {
                                $value = str_replace("\n\n", "<p></p>", $value);
                                $value = str_replace("\n", "<br/>", $value);
                            }
                        );
                    } elseif (is_string($content)) {
                        $content = str_replace("\n\n", "<p></p>", $content);
                        $content = str_replace("\n", "<br/>", $content);
                    }
                }
            } catch (ParseException $exception) {
                error_log($exception->getMessage());
                $content = '';
            }
        }

        return $content;
    }

    public static function get_languages()
    {
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

    public static function get_submenus($items, $all_items, $loop = 0)
    {
        if ($loop > 50) {
            error_log('Antiloop works. get_submenus stopped.');
            return [];
        }

        for ($i = 0; $i < count($items); $i++) {
            foreach ($all_items as $item) {
                if ($items[$i]['id'] === (int)$item->menu_item_parent) {
                    $url = $item->url;

                    if (strpos($url, 'http') === 0) {
                        if (!empty(parse_url($url)['path'])) {
                            $url = parse_url($url)['path'];
                        }
                    }

                    $items[$i]['items'][] = [
                        'id' => $item->ID,
                        'page_id' => (int)$item->object_id,
                        'url' => $url,
                        'title' => $item->title,
                        'items' => []
                    ];
                }
            }
    
            if (!empty($items[$i]['items'])) {
                $items[$i]['items'] = self::get_submenus($items[$i]['items'], $all_items, $loop++);
            }
        }
    
        return $items;
    }

    public static function get_menus_and_map()
    {
        $menus_needed = get_menus_needed();

        $menus = [];
        $url_page_map = [];

        if (!empty($menus_needed)) {
            foreach ($menus_needed as $menu_needed) {
                $menus_in_svelte_settings = [];

                if (isset($GLOBALS['polylang'])) {
                    $all_languages = pll_languages_list();

                    if (!empty($all_languages)) {
                        foreach ($all_languages as $lang) {
                            $svelte_menu_id = get_option('sveltewp_menu_' . $menu_needed['slug'] . '_' . $lang);

                            if (!empty($svelte_menu_id)) {
                                $menus_in_svelte_settings[$menu_needed['slug']][] = [
                                    'menu_id' => $svelte_menu_id,
                                    'lang' => $lang
                                ];
                            }
                        }
                    }
                } else {
                    $svelte_menu_id = get_option('sveltewp_menu_' . $menu_needed['slug']);

                    if (!empty($svelte_menu_id)) {
                        $menus_in_svelte_settings[$menu_needed['slug']][] = [
                            'menu_id' => $svelte_menu_id
                        ];
                    }
                }

                if (!empty($menus_in_svelte_settings)) {
                    foreach ($menus_in_svelte_settings as $menu_needed_slug => $menu_needed_data) {
                        foreach ($menu_needed_data as $menu_needed_d) {
                            $menu_info = wp_get_nav_menu_object($menu_needed_d['menu_id']);
                            $all_menu_items = wp_get_nav_menu_items($menu_needed_d['menu_id']);

                            $items = [];

                            foreach ($all_menu_items as $mi) {

                                $menu_item_parent = $mi->menu_item_parent;

                                $url = $mi->url;

                                if (strpos($url, 'http') === 0) {
                                    if (!empty(parse_url($url)['path'])) {
                                        $url = parse_url($url)['path'];
                                    }
                                }

                                if ((int)$menu_item_parent === 0) {
                                    $items[] = [
                                        'id' => $mi->ID,
                                        'page_id' => (int)$mi->object_id,
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

                            if (!empty($menu_needed_d['lang'])) {
                                $menus[$menu_needed_slug][$menu_needed_d['lang']] = [
                                    'menu_id' => $menu_info->term_id,
                                    'items' => $items
                                ];
                            } else {
                                $menus[$menu_needed_slug] = [
                                    'menu_id' => $menu_info->term_id,
                                    'items' => $items
                                ];
                            }
                        }
                    }
                }
            }
        }

        return [
            'menus' => $menus,
            'url_page_map' => $url_page_map
        ];
    }

    public static function get_header()
    {
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
            $p = get_post($sveltewp_header_page_id);

            $header = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'content' => $p->post_content,
            ];
        }

        return $header;
    }

    public static function get_footer()
    {
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
            $p = get_post($sveltewp_footer_page_id);

            $footer = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'content' => $p->post_content,
            ];
        }
        return $footer;
    }

    public static function get_first_pages($url_page_map)
    {
        if (empty($url_page_map['/'])) {
            return [];
        }

        $root_page_id = $url_page_map['/']['page_id'];

        $root_page = get_post($root_page_id);

        if (empty($root_page)) {
            return;
        }

        $translations = [];

        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($root_page->ID);
        }

        $languages = [];

        foreach ($translations as $lang => $page_id) {
            if ($page_id === $root_page->ID) {
                $languages[$lang] = '/';
            } else {
                foreach ($url_page_map as $url => $url_data) {
                    if ($page_id === $url_data['page_id']) {
                        $languages[$lang] = $url;
                        break;
                    }
                }
            }
        }

        return $languages;
    }

    public static function get_page_data($page_id)
    {
        $page = get_post($page_id);

        if (function_exists('pll_get_post_language')) {
            $language = pll_get_post_language($page_id);
        } else {
            $language = '';
        }

        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($page_id);
        } else {
            $translations = [];
        }

        $is_gutenberg_page = false;

        if (has_blocks($page->post_content)) {
            $blocks = parse_blocks($page->post_content);

            if (!empty($blocks)) {
                foreach ($blocks as $block) {
                    if (!empty($block['blockName']) && ($block['blockName'] !== 'core/code' && $block['blockName'] !== 'core/paragraph')) {
                        // YAML code in code block, so if found something else, then maybe its gutenberg media/images or something
                        $is_gutenberg_page = true;
                        break;
                    }
                }
            }
        }

        $content = self::content_to_yaml($page->post_content);

        return [
            'page_id' => (int)$page_id,
            'title' => $page->post_title,
            'type' => $page->post_type,
            'content' => $content,
            'date' => $page->post_date,
            'modified' => $page->post_modified,
            'slug' => $page->slug,
            'language' => $language,
            'translations' => $translations,
            'is_gutenberg' => $is_gutenberg_page
        ];
    }
}

