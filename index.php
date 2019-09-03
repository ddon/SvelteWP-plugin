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

require_once(plugin_dir_path(__FILE__) . 'translations.php');
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

    register_rest_route('svelte-wp/v1', '/precached_pages(?:/(?P<lang>[a-z]{2}))?', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => ['SvelteWP_PageAPI', 'get_precached_pages']
    ]);
});


add_action('init', function () {
    add_action('admin_init', function() {
        register_setting('sveltewp_options_group', 'sveltewp_publish_key');
        register_setting('sveltewp_options_group', 'sveltewp_subscribe_key');
        register_setting('sveltewp_options_group', 'sveltewp_header_page_id');
        register_setting('sveltewp_options_group', 'sveltewp_footer_page_id');
        register_setting('sveltewp_options_group', 'sveltewp_mouse_over_menu');
        register_setting('sveltewp_options_group', 'sveltewp_cached_pages_ids');

        $menus_needed = get_menus_needed();
        
        if (!empty($menus_needed)) {
            if (isset($GLOBALS["polylang"])) {
                $active_languages = pll_languages_list();

                if (!empty($active_languages)) {
                    foreach ($menus_needed as $menu_needed) {
                        // options for each menu type (header, footer, etc)
                        register_setting('sveltewp_options_group', 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_as_dropdown');
                        register_setting('sveltewp_options_group', 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_names');
                        register_setting('sveltewp_options_group', 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_flags');
                        register_setting('sveltewp_options_group', 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_current_lang');
                        register_setting('sveltewp_options_group', 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_no_translation');

                        foreach ($active_languages as $lang) {
                            // page_id option for each menu type
                            register_setting('sveltewp_options_group', 'sveltewp_menu_' . $menu_needed['slug'] . '_' . $lang);
                        }
                    }
                }
            } else {
                foreach ($menus_needed as $menu_needed) {
                    register_setting('sveltewp_options_group', 'sveltewp_menu_' . $menu_needed['slug']);
                }
            }
        }
    });

    // make cropper flexible for custom logo in theme
    add_theme_support(
        'custom-logo',
        [
            'width'       => 400,
            'height'      => 100,
            'flex-width'  => true,
            'flex-height' => true,
        ]
    );

    add_action('admin_menu', function() {
        add_options_page('SvelteWP Settings', 'SvelteWP', 'manage_options', 'sveltewp', function() {
            $active_languages = [];
            $languages_info = [];

            if (isset($GLOBALS["polylang"])) {
                $active_languages = pll_languages_list();

                if (!empty($active_languages)) {
                    foreach ($active_languages as $active_lang) {
                        $languages_info[$active_lang] = PLL()->model->get_language($active_lang);
                    }
                }
            }

            ?>
            <div>
                <form method="post" action="options.php">
                <?php settings_fields( 'sveltewp_options_group' ); ?>

                <h1>SvelteWP Settings</h1>

                <h3>Multilanguage:</h3>
                <p>
                <?php
                    if (isset($GLOBALS["polylang"])) {
                        ?>
                            <div>Polylang: <span>enabled</span></div>
                            <div>Active languages: 
                        <?php

                        if (!empty($active_languages)) {
                            foreach ($active_languages as $active_lang) {
                                if (!empty($languages_info[$active_lang]->flag)) {
                                    echo $languages_info[$active_lang]->flag . ' ';
                                } else {
                                    echo $languages_info[$active_lang]->name . ' ';
                                }
                            }
                        }

                        echo '</div>';
                    } else {
                        ?>
                            We support Polylang multilingual plugin. If you need multilanguage website please install it.
                        <?php
                    }
                ?>
                </p>

                <h3>Common settings:</h3>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="sveltewp_mouse_over_menu">Mouse over menu</label></th>
                            <td>
                                <fieldset>
                                    <input name="sveltewp_mouse_over_menu" type="checkbox" id="sveltewp_mouse_over_menu" <?= !empty(get_option('sveltewp_mouse_over_menu')) ? ' checked' : ''; ?>>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                $get_pages_args = [
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'posts_per_page' => -1
                ];

                if (isset($GLOBALS["polylang"])) {
                    // show all pages on all on all languages
                    $get_pages_args['lang'] = '';
                }

                $pages = new WP_Query($get_pages_args);

                $pages_grouped = [];

                if ($pages->have_posts()) {

                    if (isset($GLOBALS["polylang"])) {
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

                <h3>Header & Footer content:</h3>

                <table class="wp-list-table widefat fixed striped languages" style="max-width: 700px">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column" style="width: 3.5em; text-align: center">Header</th>
                            <th scope="col" class="manage-column" style="width: 3.5em; text-align: center">Footer</th>
                            <th scope="col" class="manage-column">Page</th>
                            <?php
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
                                        ?>
                                        <th scope="col" class="manage-column" style="width: 2em"><?= !empty($languages_info[$lang]->flag) ? $languages_info[$lang]->flag : $languages_info[$lang]->name ?></th>
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
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
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

                <table class="wp-list-table widefat fixed striped languages" style="max-width: <?= (isset($GLOBALS["polylang"])) ? '1000' : '700' ?>px">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column" style="width: 4em">Menu</th>
                            <?php
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
                                        ?>
                                        <th scope="col" class="manage-column" style="width: 12em"><?= !empty($languages_info[$lang]->flag) ? $languages_info[$lang]->flag : $languages_info[$lang]->name ?></th>
                                        <?php
                                    }
                                } else {
                                    ?>
                                        <th scope="col" class="manage-column" style="width: 12em">Page</th>
                                    <?php
                                }
                            ?>
                            <?= isset($GLOBALS["polylang"]) ? '<th scope="col" class="manage-column" style="width: 16em">Settings</th>' : ''; ?>
                        </tr>
                    </thead>
                    <tbody id="the-list">

                <?php
                    foreach ($menus_needed as $menu_needed) {
                        ?>
                        <tr>
                            <td><?= $menu_needed['name'] ?></td>
                            <?php
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
                                        $sveltewp_key = 'sveltewp_menu_' . $menu_needed['slug'] . '_' . $lang;
                                        $sveltewp_menu_id = get_option($sveltewp_key);
                                        ?>
                                        <td scope="col" class="manage-column">
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
                                        </td>
                                        <?php
                                    }
                                } else {
                                    $sveltewp_key = 'sveltewp_menu_' . $menu_needed['slug'];
                                    $sveltewp_menu_id = get_option($sveltewp_key);
                                    ?>
                                    <td scope="col" class="manage-column">
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
                                    </td>
                                    <?php
                                }
                            ?>
                            <?php
                            if (isset($GLOBALS["polylang"])) {
                                $sw_settings_as_dropdown_key = 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_as_dropdown';
                                $sw_settings_as_dropdown = get_option($sw_settings_as_dropdown_key);

                                $sw_settings_show_names_key = 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_names';
                                $sw_settings_show_names = get_option($sw_settings_show_names_key);

                                $sw_settings_show_flags_key = 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_show_flags';
                                $sw_settings_show_flags = get_option($sw_settings_show_flags_key);

                                $sw_settings_hide_if_current_lang_key = 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_current_lang';
                                $sw_settings_hide_if_current_lang = get_option($sw_settings_hide_if_current_lang_key);

                                $sw_settings_hide_if_no_translation_key = 'sveltewp_lng_swtchr_' . $menu_needed['slug'] . '_hide_if_no_translation';
                                $sw_settings_hide_if_no_translation = get_option($sw_settings_hide_if_no_translation_key);
                            ?>
                            <td>
                                <p><label><input type="checkbox" autocomplete="off" name="<?= $sw_settings_as_dropdown_key ?>" <?= !empty($sw_settings_as_dropdown) ? ' checked' : ''; ?>>Displays as a dropdown</label></p>
                                <p><label><input type="checkbox" autocomplete="off" name="<?= $sw_settings_show_names_key ?>" <?= !empty($sw_settings_show_names) ? ' checked' : ''; ?>>Displays language names</label></p>
                                <p><label><input type="checkbox" autocomplete="off" name="<?= $sw_settings_show_flags_key ?>" <?= !empty($sw_settings_show_flags) ? ' checked' : ''; ?>>Displays flags</label></p>
                                <p><label><input type="checkbox" autocomplete="off" name="<?= $sw_settings_hide_if_current_lang_key ?>" <?= !empty($sw_settings_hide_if_current_lang) ? ' checked' : ''; ?>>Hides the current language</label></p>
                                <p><label><input type="checkbox" autocomplete="off" name="<?= $sw_settings_hide_if_no_translation_key ?>" <?= !empty($sw_settings_hide_if_no_translation) ? ' checked' : ''; ?>>Hides languages with no translation</label></p>
                            </td>
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


                <h3>Cached pages:</h3>

                <table class="wp-list-table widefat fixed striped languages" style="max-width: 700px">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column" style="width: 3.5em; text-align: center"></th>
                            <th scope="col" class="manage-column">Page</th>
                            <?php
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
                                        ?>
                                        <th scope="col" class="manage-column" style="width: 2em"><?= !empty($languages_info[$lang]->flag) ? $languages_info[$lang]->flag : $languages_info[$lang]->name ?></th>
                                        <?php
                                    }
                                }
                            ?>
                        </tr>
                    </thead>
                    <tbody id="the-list">

                <?php
                    $cached_pages_ids = get_option('sveltewp_cached_pages_ids');

                    if (empty($cached_pages_ids)) {
                        $cached_pages_ids = [];
                    }

                    foreach ($pages_grouped as $group_page_id => $group_page_data) {
                        ?>
                        <tr>
                            <td style="text-align:center"><input type="checkbox" name="sveltewp_cached_pages_ids[]" value="<?= $group_page_id ?>"<?= in_array($group_page_id, $cached_pages_ids) ? ' checked' : ''; ?> autocomplete="off"></td>
                            <td><?= $group_page_data['title'] ?></td>
                            <?php
                                if (!empty($active_languages)) {
                                    foreach ($active_languages as $lang) {
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