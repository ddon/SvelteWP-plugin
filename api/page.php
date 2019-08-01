<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_PageAPI {
    public static function get_page($data) {
        $page_id = $data['id'];

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
            'ok' => true,
            'data' => [
                'page_id' => $page_id,
                'title' => $page->post_title,
                'type' => $page->post_type,
                'content' => $content,
                'date' => $page->post_date,
                'modified' => $page->post_modified,
                'slug' => $page->slug,
                'language' => $language,
                'translations' => $translations
            ]
        ];
    }
}

