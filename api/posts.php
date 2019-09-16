<?php

require_once(plugin_dir_path(__FILE__) . '../class.sveltewp-data.php');


class SvelteWP_PostsAPI
{
    public static function get_posts($params)
    {
        $first_param = $params['first_param'];

        if (!empty($first_param)) {
            // here will be logic for get posts by some params
        } else {
            
        }

        $posts_formatted = [];

        $db_posts = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => -1
        ]);

        if ($db_posts->have_posts()) {
            foreach ($db_posts->posts as $db_post) {
                $featured_image = '';

                if (has_post_thumbnail($db_post->ID)) {
                    $image = wp_get_attachment_image_src(get_post_thumbnail_id($db_post->ID), 'single-post-thumbnail');
                    $featured_image = $image[0];
                }

                $posts_formatted[] = [
                    'id' => $db_post->ID,
                    'post_date' => $db_post->post_date,
                    'title' => $db_post->post_title,
                    'slug' => $db_post->post_name,
                    'featured_image' => $featured_image
                ];
            }
        }

        return [
            'ok' => true,
            'data' => $posts_formatted
        ];
    }
}