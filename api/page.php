<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_PageAPI {
    public static function get_page($data) {
        $page_id = $data['id'];

        return [
            'ok' => true,
            'data' => self::get_page_data($page_id)
        ];
    }

    public static function get_page_data($page_id) {
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

        $content = SvelteWP_Data::content_to_yaml($page->post_content);

        return [
            'page_id' => $page_id,
            'title' => $page->post_title,
            'type' => $page->post_type,
            'content' => $content,
            'date' => $page->post_date,
            'modified' => $page->post_modified,
            'slug' => $page->slug,
            'language' => $language,
            'translations' => $translations
        ];
    }

    public static function get_precached_pages($data) {
        $lang_param = $data['lang'];

        $cached_pages = [];

        $cached_pages_ids = get_option('sveltewp_cached_pages_ids');

        if (empty($cached_pages_ids)) {
            return [
                'ok' => true,
                'data' => $cached_pages
            ];
        }

        if (isset($GLOBALS["polylang"]) && !empty($lang_param)) {
            foreach ($cached_pages_ids as $cached_page_id) {
                $post_lang = pll_get_post_language($cached_page_id, 'slug');

                if ($post_lang === $lang_param)  {
                    $cached_pages[$cached_page_id] = SvelteWP_PageAPI::get_page_data($cached_page_id);
                    continue;
                }

                $translations = $GLOBALS["polylang"]->model->post->get_translations($cached_page_id);

                if (!empty($translations)) {
                    foreach ($translations as $translation_post_lang => $translation_page_id) {
                        if ($translation_post_lang === $lang_param)  {
                            $cached_pages[$translation_page_id] = SvelteWP_PageAPI::get_page_data($translation_page_id);
                        }
                    }
                }
            }
        } else {
            foreach ($cached_pages_ids as $cached_page_id) {
                $cached_pages[$cached_page_id] = SvelteWP_PageAPI::get_page_data($cached_page_id);
            }
        }

        return [
            'ok' => true,
            'data' => $cached_pages
        ];
    }
}

