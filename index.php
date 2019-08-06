<?php
/**
 * Plugin Name: SvelteWP
 * Version:     1.0.9
 * Author:      Fotki Agency / Dmitri Don
 * Text Domain: sveltewp
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/ddon/SvelteWP-plugin
 */

require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');

require_once(plugin_dir_path(__FILE__) . 'api/site.php');
require_once(plugin_dir_path(__FILE__) . 'api/page.php');


use PubNub\PNConfiguration;
use PubNub\PubNub;


add_action('rest_api_init', function () {
    // TODO: replace * with real domain
    header('Access-Control-Allow-Origin: *');

    // /wp-json/svelte-wp/v1/routing
    register_rest_route('svelte-wp/v1', '/site/init', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => ['SvelteWP_SiteAPI', 'get_init']
    ]);

    register_rest_route('svelte-wp/v1', '/page/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => ['SvelteWP_PageAPI', 'get_page']
    ]);
});


add_action('init', function () {
    add_action('admin_init', function() {
        //add_option('sveltewp_publish_key', '');
        //add_option('sveltewp_subscribe_key', '');
        //add_option('sveltewp_header_page_id');

        register_setting('sveltewp_options_group', 'sveltewp_publish_key');
        register_setting('sveltewp_options_group', 'sveltewp_subscribe_key');
        register_setting('sveltewp_options_group', 'sveltewp_header_page_id');
        register_setting('sveltewp_options_group', 'sveltewp_footer_page_id');

        $menus_needed = get_menus_needed();

        if (!empty($menus_needed)) {
            foreach ($menus_needed as $menu_needed) {
                if (isset($GLOBALS["polylang"])) {
                    $all_languages = pll_languages_list();

                    if (!empty($all_languages)) {
                        foreach ($all_languages as $lang) {
                            register_setting('sveltewp_options_group', 'sveltewp_menu_' . $menu_needed['slug'] . '_' . $lang);
                        }
                    }
                } else {
                    register_setting('sveltewp_options_group', 'sveltewp_menu_' . $menu_needed['slug']);
                }
            }
        }
    });

    add_action('admin_menu', function() {
        add_options_page('SvelteWP Settings', 'SvelteWP', 'manage_options', 'sveltewp', function() {
            ?>
                <div>
                    <h1>SvelteWP Settings</h1>

                    <h3>Multilanguage:</h3>
                    <p>
                    <?php
                        if (isset($GLOBALS["polylang"])) {
                            ?>
                                Polylang: <span>enabled</span>
                            <?php
                        } else {
                            ?>
                                We support Polylang multilingual plugin. If you need multilanguage website please install it.
                            <?php
                        }
                    ?>
                    </p>

                    <form method="post" action="options.php">
                    <?php settings_fields( 'sveltewp_options_group' ); ?>

                    <?php
                    $get_pages_args = [
                        'post_type' => 'page',
                        'post_status' => 'publish',
                        'posts_per_page' => -1
                    ];

                    if (isset($GLOBALS["polylang"])) {
                        $get_pages_args['lang'] = '';
                    }

                    $pages = new WP_Query($get_pages_args);

                    $pages_grouped = [];
                    $all_languages = [];

                    if ($pages->have_posts()) {

                        if (isset($GLOBALS["polylang"])) {
                            $all_languages = pll_languages_list();
                            $current_language = pll_current_language();

                            $pages_formatted = [];

                            foreach ($pages->posts as $page_post) {
                                $pages_formatted[$page_post->ID] = [
                                    'title' => $page_post->post_title
                                ];
                            }

                            $pages_ids_proccessed = [];

                            foreach ($pages_formatted as $page_id => $page_data) {
                                if (!empty($pages_ids_proccessed[$page_id])) {
                                    continue;
                                }

                                $translations = $GLOBALS["polylang"]->model->post->get_translations($page_id);
    
                                if (!empty($translations)) {
                                    $pages_grouped[$page_id] = [
                                        'title' => $page_data['title'],
                                        'languages' => $translations
                                    ];

                                    // add all translation ids as proccessed, that skip duplicates
                                    foreach ($translations as $lang => $translation_page_id) {
                                        $pages_ids_proccessed[$translation_page_id] = 1;
                                    }
                                }
                            }
                        } else {
                            foreach ($pages->posts as $page_post) {
                                $pages_grouped[$page_post->ID] = [
                                    'title' => $page_post->post_title
                                ];
                            }
                        }

                    }

                    ?>

                    <h3>Header & Footer:</h3>

                    <table class="wp-list-table widefat fixed striped languages" style="max-width: 700px">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column" style="width: 3.5em; text-align: center">Header</th>
                                <th scope="col" class="manage-column" style="width: 3.5em; text-align: center">Footer</th>
                                <th scope="col" class="manage-column">Page</th>
                                <?php
                                    if (!empty($all_languages)) {
                                        foreach ($all_languages as $lang) {
                                            ?>
                                            <th scope="col" class="manage-column" style="width: 2em"><?= $lang ?></th>
                                            <?php
                                        }
                                    }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="the-list">

                    <?php
                        $sveltewp_header_page_id = get_option('sveltewp_header_page_id');
                        $sveltewp_footer_page_id = get_option('sveltewp_footer_page_id');

                        foreach ($pages_grouped as $group_page_id => $group_page_data) {
                            $header_checked = $group_page_id == $sveltewp_header_page_id ? ' checked' : '';
                            $footer_checked = $group_page_id == $sveltewp_footer_page_id ? ' checked' : '';

                            ?>
                            <tr>
                                <td style="text-align:center"><input type="radio" name="sveltewp_header_page_id" value="<?= $group_page_id ?>"<?= $header_checked ?> autocomplete="off"></td>
                                <td style="text-align:center"><input type="radio" name="sveltewp_footer_page_id" value="<?= $group_page_id ?>"<?= $footer_checked ?> autocomplete="off"></td>
                                <td><?= $group_page_data['title'] ?></td>
                                <?php
                                    if (!empty($all_languages)) {
                                        foreach ($all_languages as $lang) {
                                            ?>
                                            <th scope="col" class="manage-column"><?= !empty($group_page_data['languages'][$lang]) ? '<span class="pll_icon_tick" title="' . $group_page_data['languages'][$lang] . '"></span>' : '—' ?></th>
                                            <?php
                                        }
                                    }
                                ?>
                            </tr>
                            <?php
                        }
                    ?>

                        </tbody>
                    </table>

                    <h3>Menus:</h3>

                    <?php
                        $nav_menus = wp_get_nav_menus();

                        if (!empty($nav_menus)) {
                            $menus_needed = get_menus_needed();
                    ?>

                    <table class="wp-list-table widefat fixed striped languages" style="max-width: 700px">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column">Menu</th>
                                <?php
                                    if (!empty($all_languages)) {
                                        foreach ($all_languages as $lang) {
                                            ?>
                                            <th scope="col" class="manage-column"><?= $lang ?></th>
                                            <?php
                                        }
                                    } else {
                                        ?>
                                            <th scope="col" class="manage-column"></th>
                                        <?php
                                    }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="the-list">

                    <?php
                        foreach ($menus_needed as $menu_needed) {
                            ?>
                            <tr>
                                <td><?= $menu_needed['name'] ?></td>
                                <?php
                                    if (!empty($all_languages)) {
                                        foreach ($all_languages as $lang) {
                                            $sveltewp_key = 'sveltewp_menu_' . $menu_needed['slug'] . '_' . $lang;
                                            $sveltewp_menu_id = get_option($sveltewp_key);
                                            ?>
                                            <th scope="col" class="manage-column">
                                                <select name="<?= $sveltewp_key ?>" autocomplete="off">
                                                    <option value="">None</option>
                                                    <?php
                                                        foreach ($nav_menus as $nav_menu) {
                                                            ?>
                                                                <option value="<?= $nav_menu->term_id ?>"<?= ($sveltewp_menu_id == $nav_menu->term_id) ? ' selected="selected"' : '' ?>><?= $nav_menu->name ?></option>
                                                            <?php
                                                        }
                                                    ?>
                                                </select>
                                            </th>
                                            <?php
                                        }
                                    } else {
                                        $sveltewp_key = 'sveltewp_menu_' . $menu_needed['slug'];
                                        $sveltewp_menu_id = get_option($sveltewp_key);
                                        ?>
                                        <th scope="col" class="manage-column">
                                            <select name="<?= $sveltewp_key ?>" autocomplete="off">
                                                <option value="">None</option>
                                                <?php
                                                    foreach ($nav_menus as $nav_menu) {
                                                        ?>
                                                            <option value="<?= $nav_menu->term_id ?>"<?= ($sveltewp_menu_id == $nav_menu->term_id) ? ' selected="selected"' : '' ?>><?= $nav_menu->name ?></option>
                                                        <?php
                                                    }
                                                ?>
                                            </select>
                                        </th>
                                        <?php
                                    }
                                ?>
                            </tr>
                            <?php
                        }
                    ?>

                        </tbody>
                    </table>

                    <?php
                        } else {
                            echo 'No menus found.';
                        }
                    ?>

                    <h3>PubNub:</h3>
                    <p>For real-time content update, you need to subscribe to <a href='https://www.pubnub.com/'>PubNub</a> account and paste your keys here.</p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="sveltewp_publish_key">Publish Key</label></th>
                            <td><input type="text" id="sveltewp_publish_key" class="regular-text" name="sveltewp_publish_key" value="<?php echo get_option('sveltewp_publish_key'); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="sveltewp_subscribe_key">Subscribe Key</label></th>
                            <td><input type="text" id="sveltewp_subscribe_key" class="regular-text" name="sveltewp_subscribe_key" value="<?php echo get_option('sveltewp_subscribe_key'); ?>" /></td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                    </form>
                </div>
            <?php
        });
    });

    add_action('save_post', function ($post_id) {
        $pnconf = new PNConfiguration();
        $pubnub = new PubNub($pnconf);

        $sveltewp_publish_key = get_option('sveltewp_publish_key');
        $sveltewp_subscribe_key = get_option('sveltewp_subscribe_key');

        $pnconf->setPublishKey($sveltewp_publish_key);
        $pnconf->setSubscribeKey($sveltewp_subscribe_key);

        // Use the publish command separately from the Subscribe code shown above.
        // Subscribe is not async and will block the execution until complete.
        $result = $pubnub->publish()
            ->channel('wordpress')
            ->message([
                'action' => 'post_updated',
                'post_id' => $post_id
            ])
            ->sync();

        return;
    }, 1);
});

function get_menus_needed()
{
    $menus_needed = [
        [
            'slug' => 'header',
            'name'=> 'Header'
        ],
        [
            'slug' => 'footer',
            'name'=> 'Footer'
        ]
    ];

    return $menus_needed;
}