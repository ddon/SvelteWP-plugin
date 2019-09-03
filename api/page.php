<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_PageAPI
{
    public static function get_page($params)
    {
        $page_id = $params['id'];

        return [
            'ok' => true,
            'data' => SvelteWP_Data::get_page_data($page_id)
        ];
    }

    public static function get_precached_pages($params)
    {
        $lang_param = $params['lang'];

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
                    $cached_pages[$cached_page_id] = SvelteWP_Data::get_page_data($cached_page_id);
                    continue;
                }

                $translations = $GLOBALS["polylang"]->model->post->get_translations($cached_page_id);

                if (!empty($translations)) {
                    foreach ($translations as $translation_post_lang => $translation_page_id) {
                        if ($translation_post_lang === $lang_param)  {
                            $cached_pages[$translation_page_id] = SvelteWP_Data::get_page_data($translation_page_id);
                        }
                    }
                }
            }
        } else {
            foreach ($cached_pages_ids as $cached_page_id) {
                $cached_pages[$cached_page_id] = SvelteWP_Data::get_page_data($cached_page_id);
            }
        }

        return [
            'ok' => true,
            'data' => $cached_pages
        ];
    }
}

