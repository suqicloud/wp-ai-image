<?php
/*
Plugin Name: 小半AI绘画
Description: 基于WordPress的ai图片绘画生成插件
Version: 1.1
Plugin URI: https://www.jingxialai.com/4827.html
Author: Summer
License: GPL License
Author URI: https://www.jingxialai.com/
*/

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 创建数据表
function wp_ai_image_generator_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL DEFAULT 0,
        prompt text NOT NULL,
        optimized_prompt text,
        image_url varchar(255) NOT NULL,
        width int NOT NULL,
        height int NOT NULL,
        model varchar(100),
        api_type varchar(50),
        seed bigint(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        is_deleted tinyint(1) DEFAULT 0,
        hide_in_frontend tinyint(1) DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 创建生成页面
function wp_ai_image_generator_create_generate_page() {
    $pages = get_posts(array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        's'           => '[ai_image_generate]',
        'numberposts' => 1,
    ));

    if (empty($pages)) {
        wp_insert_post(array(
            'post_title'    => 'AI 图片生成',
            'post_content'  => '[ai_image_generate]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'ai-image-generate',
            'comment_status' => 'closed',
            'ping_status'    => 'closed'
        ));
    }
}

// 创建推荐页面
function wp_ai_image_generator_create_gallery_page() {
    $pages = get_posts(array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        's'           => '[ai_image_gallery]',
        'numberposts' => 1,
    ));

    if (empty($pages)) {
        wp_insert_post(array(
            'post_title'    => 'AI 图片推荐',
            'post_content'  => '[ai_image_gallery]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'ai-image-gallery',
            'comment_status' => 'closed',
            'ping_status'    => 'closed'
        ));
    }
}

// 注册激活钩子
register_activation_hook(__FILE__, 'wp_ai_image_generator_create_table');
register_activation_hook(__FILE__, 'wp_ai_image_generator_create_generate_page');
register_activation_hook(__FILE__, 'wp_ai_image_generator_create_gallery_page');

// 插件列表页面添加设置入口
function wp_ai_image_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wp-ai-image-generator">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wp_ai_image_add_settings_link');

// 添加管理菜单
add_action('admin_menu', 'wp_ai_image_generator_menu');
function wp_ai_image_generator_menu() {
    add_menu_page(
        'AI 图片生成',
        'AI 图片生成',
        'manage_options',
        'wp-ai-image-generator',
        'wp_ai_image_generator_settings_page',
        'dashicons-format-image',
        80
    );
    add_submenu_page(
        'wp-ai-image-generator',
        'AI图片设置',
        '设置',
        'manage_options',
        'wp-ai-image-generator',
        'wp_ai_image_generator_settings_page'
    );
    add_submenu_page(
        'wp-ai-image-generator',
        'AI图片记录',
        '记录',
        'manage_options',
        'wp-ai-image-records',
        'wp_ai_image_generator_records_page'
    );
    add_submenu_page(
        'wp-ai-image-generator',
        'AI图片用户管理',
        '用户',
        'manage_options',
        'wp-ai-user-settings',
        'wp_ai_image_generator_user_settings_page'
    );    
}

// 设置
add_action('admin_enqueue_scripts', 'wp_ai_image_generator_admin_styles');
function wp_ai_image_generator_admin_styles($hook) {
    if (!in_array($hook, ['toplevel_page_wp-ai-image-generator', 'ai-image-generator_page_wp-ai-image-records', 'ai-image-generator_page_wp-ai-user-settings'])) {
        return;
    }
    
    $custom_css = "
        .ai_image_wrap {
            max-width: 900px;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .ai_image_wrap h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .ai_image_wrap .form-table th {
            width: 200px;
            padding: 15px 10px;
            font-weight: 600;
            color: #555;
        }
        .ai_image_wrap .form-table td {
            padding: 15px 10px;
        }
        .ai_image_wrap .form-table input[type='text'],
        .ai_image_wrap .form-table input[type='number'] {
            width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .ai_image_wrap .form-table input[type='checkbox'] {
            width: auto;
        }
        .ai_image_wrap .button-primary {
            padding: 8px 20px;
            font-size: 14px;
            border-radius: 4px;
        }
        .ai_image_wrap .description {
            color: #777;
            font-size: 12px;
        }
    ";
    wp_add_inline_style('wp-admin', $custom_css);
}

// 设置页面
function wp_ai_image_generator_settings_page() {
    if (isset($_POST['save_settings'])) {
        update_option('enabled_apis', isset($_POST['enabled_apis']) ? array_map('sanitize_text_field', $_POST['enabled_apis']) : []);
        update_option('pollinations_models', sanitize_text_field($_POST['pollinations_models']));
        update_option('kolors_api_key', sanitize_text_field($_POST['kolors_api_key']));
        update_option('ai_image_deepseek_api_key', sanitize_text_field($_POST['deepseek_api_key']));
        update_option('ai_image_deepseek_api_url', sanitize_text_field($_POST['deepseek_api_url']));
        update_option('deepseek_models', sanitize_text_field($_POST['deepseek_models']));
        update_option('allow_guest', isset($_POST['allow_guest']) ? 1 : 0);
        update_option('ai_image_announcement', wp_kses_post($_POST['ai_image_announcement']));
        update_option('enable_forbidden_words', isset($_POST['enable_forbidden_words']) ? 1 : 0);
        update_option('forbidden_words', sanitize_text_field($_POST['forbidden_words']));
        update_option('pollinations_display_name', sanitize_text_field($_POST['pollinations_display_name']));
        update_option('kolors_display_name', sanitize_text_field($_POST['kolors_display_name']));
        update_option('default_daily_limit', intval($_POST['default_daily_limit']));
        update_option('enable_image_upload', isset($_POST['enable_image_upload']) ? 1 : 0);
        update_option('preset_image_sizes', sanitize_textarea_field($_POST['preset_image_sizes']));
        update_option('ai_image_ad_content', wp_kses_post($_POST['ai_image_ad_content']));
        update_option('allow_user_delete_images', isset($_POST['allow_user_delete_images']) ? 1 : 0);
        update_option('enable_10min_limit', isset($_POST['enable_10min_limit']) ? 1 : 0);
        update_option('max_images_10min', intval($_POST['max_images_10min']));
        update_option('enable_image_count_display', isset($_POST['enable_image_count_display']) ? 1 : 0);
        // 保存图片风格设置
        update_option('image_styles', sanitize_textarea_field($_POST['image_styles']));

        ?>
        <div id="settings-saved-notice" class="updated"><p>设置已保存</p></div>
        <script type="text/javascript">
            setTimeout(function() {
                document.getElementById('settings-saved-notice').style.display = 'none';
            }, 2000);
        </script>
        <?php
    }
    $enabled_apis = get_option('enabled_apis', ['pollinations']);
    $announcement = get_option('ai_image_announcement', '');
    $enable_forbidden_words = get_option('enable_forbidden_words', 0);
    $forbidden_words = get_option('forbidden_words', '');
    $deepseek_api_url = get_option('ai_image_deepseek_api_url', 'https://api.deepseek.com/chat/completions');
    $pollinations_display_name = get_option('pollinations_display_name', 'Pollinations');
    $kolors_display_name = get_option('kolors_display_name', 'Kolors');
    $default_daily_limit = get_option('default_daily_limit', 100);
    $enable_image_upload = get_option('enable_image_upload', 0);
    $ad_content = get_option('ai_image_ad_content', ''); // 获取广告内容
    $allow_user_delete_images = get_option('allow_user_delete_images', 0);
    $enable_10min_limit = get_option('enable_10min_limit', 0);
    $max_images_10min = get_option('max_images_10min', 50);
    $enable_image_count_display = get_option('enable_image_count_display', 0);
    // 获取图片风格设置
    $image_styles = get_option('image_styles', '');

    ?>
    <div class="ai_image_wrap">
        <h1>AI 图片生成设置</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>启用的生成接口</th>
                    <td>
                        <label><input type="checkbox" name="enabled_apis[]" value="pollinations" <?php checked(in_array('pollinations', $enabled_apis)); ?> /> Pollinations</label><br>
                        <label><input type="checkbox" name="enabled_apis[]" value="kolors" <?php checked(in_array('kolors', $enabled_apis)); ?> /> Kolors</label>
                    </td>
                </tr>
                <tr>
                    <th>Pollinations 前台显示名称</th>
                    <td>
                        <input type="text" name="pollinations_display_name" value="<?php echo esc_attr($pollinations_display_name); ?>" placeholder="e.g., 无限制接口" />
                        <p class="description">Pollinations接口在前台的下拉菜单显示名称。</p>
                    </td>
                </tr>
                <tr>
                    <th>Kolors 前台显示名称</th>
                    <td>
                        <input type="text" name="kolors_display_name" value="<?php echo esc_attr($kolors_display_name); ?>" placeholder="e.g., 有限制接口" />
                        <p class="description">Kolors接口在前台的下拉菜单显示名称。</p>
                    </td>
                </tr>
                <tr>
                    <th>Pollinations 模型</th>
                    <td><input type="text" name="pollinations_models" value="<?php echo esc_attr(get_option('pollinations_models', 'flux')); ?>" placeholder="e.g., flux, turbo" /></td>
                </tr>
                <tr>
                    <th>Kolors API Key</th>
                    <td><input type="text" name="kolors_api_key" value="<?php echo esc_attr(get_option('kolors_api_key')); ?>" /></td>
                </tr>
                <tr>
                    <th>自定义模型 API Key</th>
                    <td><input type="text" name="deepseek_api_key" value="<?php echo esc_attr(get_option('ai_image_deepseek_api_key')); ?>" /></td>
                </tr>
                <tr>
                    <th>自定义模型 API URL</th>
                    <td>
                        <input type="text" name="deepseek_api_url" value="<?php echo esc_attr($deepseek_api_url); ?>" />
                        <p class="description">自定义提示词优化接口地址，需兼容OpenAI API格式。</p>
                    </td>
                </tr>
                <tr>
                    <th>自定义模型 参数</th>
                    <td><input type="text" name="deepseek_models" value="<?php echo esc_attr(get_option('deepseek_models')); ?>" /></td>
                </tr>
                <tr>
                    <th>允许游客生成</th>
                    <td>
                        <input type="checkbox" name="allow_guest" <?php checked(get_option('allow_guest'), 1); ?> />
                        <p class="description">启用后，游客每天可生成 2 张图片。</p>
                    </td>
                </tr>
                <tr>
                    <th>启用图片上传</th>
                    <td>
                        <input type="checkbox" name="enable_image_upload" <?php checked($enable_image_upload, 1); ?> />
                        <p class="description">启用后，用户在选择 Kolors 模型时可上传参考图片，仅支持 jpg/png 格式，最大 2MB。</p>
                    </td>
                </tr>
                <tr>
                    <th>允许用户删除自己的图片记录</th>
                    <td>
                        <input type="checkbox" name="allow_user_delete_images" <?php checked($allow_user_delete_images, 1); ?> />
                        <p class="description">启用后，用户可以在“我的图片”页面删除自己的图片记录（仅影响前台展示，后台记录保留）。</p>
                    </td>
                </tr>
                <tr>
                    <th>默认每日生成限额</th>
                    <td>
                        <input type="number" name="default_daily_limit" value="<?php echo esc_attr($default_daily_limit); ?>" min="0" />
                        <p class="description">设置登录用户默认每日可生成图片的数量，未单独设置的用户以此为准（游客始终限制为 2 张）。</p>
                    </td>
                </tr>
                <tr>
                    <th>启用10分钟生成限制</th>
                    <td>
                        <input type="checkbox" name="enable_10min_limit" <?php checked($enable_10min_limit, 1); ?> />
                        <p class="description">启用后，限制用户在10分钟内生成图片的数量。</p>
                    </td>
                </tr>
                <tr>
                    <th>10分钟内最大生成数量</th>
                    <td>
                        <input type="number" name="max_images_10min" value="<?php echo esc_attr($max_images_10min); ?>" min="1" />
                        <p class="description">设置10分钟内用户最多可生成图片的数量，默认50张。</p>
                    </td>
                </tr>
                <tr>
                    <th>显示图片总数</th>
                    <td>
                        <input type="checkbox" name="enable_image_count_display" <?php checked($enable_image_count_display, 1); ?> />
                        <p class="description">启用后，将在图片展示页面底部显示当前总共生成的图片数量。</p>
                    </td>
                </tr>
                <tr>
                    <th>预设图片尺寸</th>
                    <td>
                        <textarea name="preset_image_sizes" rows="4" cols="60" ><?php echo esc_textarea(get_option('preset_image_sizes', '正方形(1024x1024),视频竖版封面(1080x1920)')); ?></textarea>
                        <p class="description">输入预设尺寸，格式为: 名称(宽度x高度)，多个预设用英文逗号分隔。</p>
                    </td>
                </tr>
                <!-- 图片风格设置 -->
                <tr>
                    <th>预设图片风格</th>
                    <td>
                        <textarea name="image_styles" rows="4" cols="60"><?php echo esc_textarea($image_styles); ?></textarea>
                        <p class="description">输入图片风格，格式为：风格名称|图片链接，每行一个风格。例如：<br>中国风|https://example.com/chinese-style.jpg<br>动漫风格|https://example.com/anime-style.jpg</p>
                    </td>
                </tr>
                <tr>
                    <th>公告内容</th>
                    <td>
                        <textarea name="ai_image_announcement" rows="5" cols="50" placeholder="输入公告内容，支持 HTML 代码"><?php echo esc_textarea($announcement); ?></textarea>
                        <p class="description">支持HTML代码，用于在图片生成页面显示公告。</p>
                    </td>
                </tr>
                <tr>
                    <th>启用违规词检测</th>
                    <td><input type="checkbox" name="enable_forbidden_words" <?php checked($enable_forbidden_words, 1); ?> /></td>
                </tr>
                <tr>
                    <th>违规关键词</th>
                    <td>
                        <textarea name="forbidden_words" rows="8" cols="60"><?php echo esc_textarea($forbidden_words); ?></textarea>
                        <p class="description">输入需要检测的违规词，多个词用英文逗号分隔。</p>
                    </td>
                </tr>
                <tr>
                    <th>广告内容</th>
                    <td>
                        <textarea name="ai_image_ad_content" rows="5" cols="50" placeholder="输入广告内容，支持 HTML 代码"><?php echo esc_textarea($ad_content); ?></textarea>
                        <p class="description">支持HTML代码，将显示在图片生成页面和展示页面的第一排第一个位置。如果留空则不显示。</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="save_settings" class="button-primary" value="保存设置" /></p>
        </form>
        基于Pollinations和硅基流动的图片生成接口，因为这2个是免费的。<br>
        这是开源版，对于多数用户应该够用了，之后不会更新这个开源版了，演示: <a href="https://ai.wujiit.com/" target="_blank">无忌Ai</a><br>
        有问题到QQ群: 16966111 教程可以参考小半ai助手: https://www.wujiit.com/wpaidocs<br>
    </div>
    <?php
}

// 用户设置页面
function wp_ai_image_generator_user_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    
    if (isset($_POST['save_user_settings'])) {
        $user_id = intval($_POST['user_id']);
        $custom_limit = intval($_POST['custom_daily_limit']);
        $hide_in_frontend = isset($_POST['hide_in_frontend']) ? 1 : 0;

        update_user_meta($user_id, 'ai_image_daily_limit', $custom_limit);
        update_user_meta($user_id, 'ai_image_hide_in_frontend', $hide_in_frontend);

        // 更新现有记录的 hide_in_frontend 状态
        $wpdb->update(
            $table_name,
            ['hide_in_frontend' => $hide_in_frontend],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        );

        ?>


        <div id="settings-saved-notice" class="updated"><p>用户设置已保存</p></div>
        <script type="text/javascript">
            setTimeout(function() {
                document.getElementById('settings-saved-notice').style.display = 'none';
            }, 2000);
        </script>
        <?php
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $today = date('Y-m-d');
    $daily_count = $user_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND DATE(created_at) = %s",
        $user_id, $today
    )) : 0;
    $total_count = $user_id ? $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
        $user_id
    )) : 0;
    $custom_limit = $user_id ? get_user_meta($user_id, 'ai_image_daily_limit', true) : '';
    $hide_in_frontend = $user_id ? get_user_meta($user_id, 'ai_image_hide_in_frontend', true) : '';
    $default_limit = get_option('default_daily_limit', 100);
    $effective_limit = $custom_limit !== '' ? $custom_limit : $default_limit;
    ?>
    <div class="ai_image_wrap">
        <h1>用户设置</h1>
        <form method="get" action="">
            <input type="hidden" name="page" value="wp-ai-user-settings" />
            <label for="user_id">输入用户 ID:</label>
            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($user_id); ?>" />
            <input type="submit" class="button" value="查询" />
        </form>
        <?php if ($user_id) { ?>
            <h2>用户 <?php echo esc_html($user_id); ?> 的统计</h2>
            <p>今日生成图片数量: <strong><?php echo esc_html($daily_count); ?></strong></p>
            <p>总共生成图片数量: <strong><?php echo esc_html($total_count); ?></strong></p>
            <p>当前有效每日限额: <strong><?php echo esc_html($effective_limit); ?></strong> 张</p>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th>自定义每日生成限额</th>
                        <td>
                            <input type="number" name="custom_daily_limit" value="<?php echo esc_attr($custom_limit); ?>" min="0" />
                            <p class="description">为空则使用默认限额（<?php echo esc_html($default_limit); ?> 张）。</p>
                        </td>
                    </tr>
                    <tr>
                        <th>不在前台展示图片</th>
                        <td>
                            <input type="checkbox" name="hide_in_frontend" <?php checked($hide_in_frontend, 1); ?> />
                            <p class="description">启用后，该用户的图片不会显示在随机推荐和图片展示页面。</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
                <p><input type="submit" name="save_user_settings" class="button-primary" value="保存设置" /></p>
            </form>
        <?php } ?>
    </div>
    <?php
}

// 记录统计页面
function wp_ai_image_generator_records_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';

    // 处理删除请求
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('delete_image_' . $_GET['id'])) {
        $id = intval($_GET['id']);
        $image = $wpdb->get_row($wpdb->prepare("SELECT image_url FROM $table_name WHERE id = %d", $id));
        if ($image) {
            $attachment_id = attachment_url_to_postid($image->image_url);
            if ($attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
            $wpdb->delete($table_name, ['id' => $id], ['%d']);
            echo '<div id="record-deleted-notice" class="updated"><p>图片记录已删除</p></div>';
            echo '<script>setTimeout(() => document.getElementById("record-deleted-notice").style.display = "none", 2000);</script>';
        }
    }

    // 处理屏蔽/取消屏蔽请求
    if (isset($_GET['action']) && in_array($_GET['action'], ['hide', 'unhide']) && isset($_GET['id']) && check_admin_referer($_GET['action'] . '_image_' . $_GET['id'])) {
        $id = intval($_GET['id']);
        $hide = $_GET['action'] === 'hide' ? 1 : 0;
        $wpdb->update(
            $table_name,
            ['hide_in_frontend' => $hide],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        $message = $hide ? '图片已屏蔽' : '图片已取消屏蔽';
        echo "<div id='record-updated-notice' class='updated'><p>$message</p></div>";
        echo '<script>setTimeout(() => document.getElementById("record-updated-notice").style.display = "none", 2000);</script>';
    }

    // 处理发布请求
    if (isset($_GET['action']) && $_GET['action'] === 'publish' && isset($_GET['id']) && check_admin_referer('publish_image_' . $_GET['id'])) {
        $id = intval($_GET['id']);
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($record) {
            $title = mb_substr($record->prompt, 0, 20, 'UTF-8');
            $content = '<p>' . esc_html($record->prompt) . '</p>' .
                       '<p><img src="' . esc_url($record->image_url) . '" alt="' . esc_attr($record->prompt) . '"></p>';

            $post_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'draft',
                'post_type' => 'post',
                'post_author' => get_current_user_id(),
            ]);

            if ($post_id) {
                echo '<div id="record-published-notice" class="updated"><p>图片已发布为草稿，<a href="' . get_edit_post_link($post_id) . '">查看草稿</a></p></div>';
                echo '<script>setTimeout(() => document.getElementById("record-published-notice").style.display = "none", 3000);</script>';
            }
        }
    }

    $user_id_filter = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $where = $user_id_filter !== null ? $wpdb->prepare("WHERE user_id = %d", $user_id_filter) : '';

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_images = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_images / $per_page);

    $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
    ?>
    <div class="wrap">
        <h1>AI 图片记录</h1>
        <p>总共生成图片数量: <strong><?php echo esc_html($total_images); ?></strong></p>
        
        <form method="get" action="">
            <input type="hidden" name="page" value="wp-ai-image-records" />
            <label for="user_id">查询用户 ID: </label>
            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($user_id_filter); ?>" placeholder="输入用户 ID" />
            <input type="submit" class="button" value="查询" />
            <?php if ($user_id_filter !== null) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-ai-image-records')); ?>" class="button">显示全部记录</a>
            <?php endif; ?>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>用户名</th>
                    <th>图片预览</th>
                    <th>提示词</th>
                    <th>种子(Seed)</th>
                    <th>生成接口</th>
                    <th>生成时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records) : ?>
                    <?php foreach ($records as $record) : ?>
                        <?php
                        $user_info = $record->user_id ? get_userdata($record->user_id) : null;
                        $username = $user_info ? $user_info->user_login : '游客';
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=wp-ai-image-records&action=delete&id=' . $record->id . ($user_id_filter !== null ? '&user_id=' . $user_id_filter : '')),
                            'delete_image_' . $record->id
                        );
                        $publish_url = wp_nonce_url(
                            admin_url('admin.php?page=wp-ai-image-records&action=publish&id=' . $record->id . ($user_id_filter !== null ? '&user_id=' . $user_id_filter : '')),
                            'publish_image_' . $record->id
                        );
                        $hide_url = wp_nonce_url(
                            admin_url('admin.php?page=wp-ai-image-records&action=hide&id=' . $record->id . ($user_id_filter !== null ? '&user_id=' . $user_id_filter : '')),
                            'hide_image_' . $record->id
                        );
                        $unhide_url = wp_nonce_url(
                            admin_url('admin.php?page=wp-ai-image-records&action=unhide&id=' . $record->id . ($user_id_filter !== null ? '&user_id=' . $user_id_filter : '')),
                            'unhide_image_' . $record->id
                        );
                        // 截取提示词前10个字符
                        $short_prompt = mb_substr($record->prompt, 0, 10, 'UTF-8');
                        ?>
                        <tr>
                            <td><?php echo esc_html($record->user_id); ?></td>
                            <td><?php echo esc_html($username); ?></td>
                            <td><img src="<?php echo esc_url($record->image_url); ?>" width="60" height="60" alt="预览" /></td>
                            <td title="<?php echo esc_attr($record->prompt); ?>"><?php echo esc_html($short_prompt); ?></td>
                            <td><?php echo esc_html($record->seed ?? 'N/A'); ?></td>
                            <td><?php echo esc_html(ucfirst($record->api_type)); ?></td>
                            <td><?php echo esc_html($record->created_at); ?></td>
                            <td>
                                <a href="<?php echo esc_url($publish_url); ?>" class="button button-small button-primary" onclick="return confirm('确定要将此图片发布为文章草稿吗？');">发布</a>
                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-danger" onclick="return confirm('确定要删除此记录吗？');">删除</a>
                                <?php if ($record->hide_in_frontend) : ?>
                                    <a href="<?php echo esc_url($unhide_url); ?>" class="button button-small button-secondary" onclick="return confirm('确定要取消屏蔽此图片吗？');">取消屏蔽</a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($hide_url); ?>" class="button button-small button-secondary" onclick="return confirm('确定要屏蔽此图片吗？');">屏蔽</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="8">暂无记录</td></tr> <!-- 更新列数为8 -->
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%' . ($user_id_filter !== null ? '&user_id=' . $user_id_filter : '')),
                'format' => '',
                'prev_text' => __('«'),
                'next_text' => __('»'),
                'total' => $total_pages,
                'current' => $current_page,
            ]);
            echo '</div></div>';
        }
        ?>
    </div>
    <?php
}

// 删除媒体库图片时同步删除数据库记录
add_action('delete_attachment', 'wp_ai_image_generator_delete_record');
function wp_ai_image_generator_delete_record($attachment_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    $image_url = wp_get_attachment_url($attachment_id);
    if ($image_url) {
        $wpdb->delete($table_name, ['image_url' => $image_url], ['%s']);
    }
}

// 检测违规词的函数
function wp_ai_check_forbidden_words($prompt) {
    $enable_forbidden_words = get_option('enable_forbidden_words', 0);
    if (!$enable_forbidden_words) {
        return false;
    }

    $forbidden_words = array_map('trim', explode(',', get_option('forbidden_words', '')));
    if (empty($forbidden_words) || $forbidden_words[0] === '') {
        return false;
    }

    $prompt_lower = strtolower($prompt);
    foreach ($forbidden_words as $word) {
        if (!empty($word) && strpos($prompt_lower, strtolower($word)) !== false) {
            return true;
        }
    }
    return false;
}

// 注册短代码
add_shortcode('ai_image_generate', 'wp_ai_image_generate_shortcode');
add_shortcode('ai_image_gallery', 'wp_ai_image_gallery_shortcode');

// 获取用户每日生成限额和剩余数量
function wp_ai_get_user_image_limits($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    $today = date('Y-m-d');
    
    if ($user_id == 0) {
        $limit = 2;
    } else {
        $custom_limit = get_user_meta($user_id, 'ai_image_daily_limit', true);
        $default_limit = get_option('default_daily_limit', 100);
        $limit = $custom_limit !== '' ? $custom_limit : $default_limit;
    }
    
    $daily_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND DATE(created_at) = %s",
        $user_id, $today
    ));
    
    return [
        'limit' => $limit,
        'used' => $daily_count,
        'remaining' => max(0, $limit - $daily_count)
    ];
}

// 检查用户是否超过限额
function wp_ai_check_user_limit($user_id) {
    $limits = wp_ai_get_user_image_limits($user_id);
    return $limits['remaining'] > 0;
}

// 提取加载JS和CSS的函数
function wp_ai_image_load_assets() {
    wp_enqueue_style('wp-ai-image-style', plugins_url('assets/css/style.css', __FILE__));
    wp_enqueue_script('wp-ai-image-script', plugins_url('assets/js/script.js', __FILE__), ['jquery'], null, true);

    // 添加 Lightbox 2 资源
    wp_enqueue_style('lightbox-css', plugins_url('assets/css/lightbox.min.css', __FILE__));
    wp_enqueue_script('lightbox-js', plugins_url('assets/js/lightbox.min.js', __FILE__), ['jquery'], null, true);
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

    // 添加自定义 Lightbox 配置
    $lightbox_config = "
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': false,
            'disableScrolling': true,
            'fitImagesInViewport': true,
            'maxWidth': Math.min(1200, window.innerWidth * 0.6),
            'maxHeight': Math.min(1200, window.innerHeight * 0.6),
            'positionFromTop': 50,
            'albumLabel': '图片 %1 / %2'
        });
    ";
    wp_add_inline_script('lightbox-js', $lightbox_config);
}

// 检查内容中是否包含特定短代码
function wp_ai_image_has_shortcode($content) {
    return has_shortcode($content, 'ai_image_generate') || has_shortcode($content, 'ai_image_gallery');
}

// 在适当的时候加载资源
add_action('wp_enqueue_scripts', 'wp_ai_image_conditional_assets');
function wp_ai_image_conditional_assets() {
    global $post;
    
    // 检查是否是 singular 页面且内容包含特定短代码
    if (is_a($post, 'WP_Post') && is_singular() && wp_ai_image_has_shortcode($post->post_content)) {
        wp_ai_image_load_assets();
    }
}


// 生成页面
function wp_ai_image_generate_shortcode() {
    global $post;
    
    // 如果当前页面没有这个短代码，直接返回空
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_image_generate')) {
        return '';
    }

    ob_start();

    $user_id = get_current_user_id();
    $limits = wp_ai_get_user_image_limits($user_id);
    $limit_10min = wp_ai_check_user_10min_limit($user_id);

    $allow_guest = get_option('allow_guest', 0);
    $pollinations_display_name = get_option('pollinations_display_name', 'Pollinations');
    $kolors_display_name = get_option('kolors_display_name', 'Kolors');
    $api_display_names = [
        'pollinations' => $pollinations_display_name,
        'kolors' => $kolors_display_name,
    ];
    $deepseek_models = explode(',', get_option('deepseek_models', 'deepseek-chat'));
    $default_deepseek_model = trim($deepseek_models[0]);
    $default_settings = [
        'width' => 1024,
        'height' => 1024,
        'optimize_prompt' => false,
        'deepseek_model' => $default_deepseek_model,
        'api_type' => get_option('enabled_apis', ['pollinations'])[0],
        'generation_model' => 'flux',
        // 默认风格设置
        'image_style' => '',
    ];

    // 解析图片风格设置
    $image_styles_raw = get_option('image_styles', '');
    $image_styles = [];
    if (!empty($image_styles_raw)) {
        $styles = array_map('trim', explode("\n", $image_styles_raw));
        foreach ($styles as $style) {
            $parts = array_map('trim', explode('|', $style, 2));
            if (count($parts) === 2) {
                $image_styles[] = [
                    'name' => $parts[0],
                    'image_url' => $parts[1],
                ];
            }
        }
    }

    // 动态获取包含 [ai_image_gallery] 的页面链接
    $gallery_page_url = '';
    $pages = get_pages();
    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, 'ai_image_gallery')) {
            $gallery_page_url = get_permalink($page->ID);
            break;
        }
    }
    // 如果没找到，使用默认值作为fallback
    if (empty($gallery_page_url)) {
        $gallery_page_url = get_permalink(get_page_by_path('ai-image-gallery'));
    }
    $my_records_url = add_query_arg('my_records', '1', $gallery_page_url);

    wp_localize_script('wp-ai-image-script', 'wpAiImage', [
        'rest_url' => rest_url('wp-ai-image/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'enabled_apis' => get_option('enabled_apis', ['pollinations']),
        'pollinations_models' => explode(',', get_option('pollinations_models', 'flux')),
        'kolors_models' => ['Kwai-Kolors/Kolors'],
        'deepseek_models' => array_map('trim', $deepseek_models),
        'is_logged_in' => is_user_logged_in(),
        'login_url' => wp_login_url(get_permalink()),
        'allow_guest' => $allow_guest,
        'enable_forbidden_words' => get_option('enable_forbidden_words', 0),
        'forbidden_words' => array_map('trim', explode(',', get_option('forbidden_words', ''))),
        'api_display_names' => $api_display_names,
        'default_settings' => $default_settings,
        'daily_limit' => $limits['limit'],
        'remaining_images' => $limits['remaining'],
        'enable_image_upload' => get_option('enable_image_upload', 0),
        'enable_10min_limit' => get_option('enable_10min_limit', 0),
        'max_images_10min' => get_option('max_images_10min', 50),
        'remaining_10min' => $limit_10min['remaining_10min'] !== null ? $limit_10min['remaining_10min'] : $limits['remaining'],
        'reset_time_10min' => $limit_10min['reset_time'] !== null ? $limit_10min['reset_time'] : 0,
        // 传递图片风格数据给前端
        'image_styles' => $image_styles,
    ]);

    $initial_prompt = isset($_GET['prompt']) ? sanitize_text_field($_GET['prompt']) : '';
    $initial_seed = isset($_GET['seed']) ? intval($_GET['seed']) : null;
    $announcement = get_option('ai_image_announcement', '');
    if (is_user_logged_in() || $allow_guest) {
        if (get_option('enable_10min_limit', 0)) {
            $announcement .= '<p id="limit-10min-notice" style="display: none;">额，你在10分钟内生成的图片数量已达上限，这超出了正常使用频率，请等待30分钟后刷新网页再提交绘画。</p>';
        }
    }
    ?>
    <div class="wp-ai-image-generate">
        <h2>免费AI绘画</h2>
        <div class="wp-ai-prompt-form">
            <div class="wp-ai-form-group wp-ai-prompt-group">
                <label for="prompt">提示词</label>
                <textarea id="prompt" placeholder="输入描述图片的提示词"><?php echo esc_textarea($initial_prompt); ?></textarea>
            </div>
            <div class="wp-ai-form-group wp-ai-options-group">
                <div class="wp-ai-option">
                    <label for="width">宽度</label>
                    <input type="number" id="width" placeholder="宽度" />
                </div>
                <div class="wp-ai-option">
                    <label for="height">高度</label>
                    <input type="number" id="height" placeholder="高度" />
                </div>

                <div class="wp-ai-option wp-ai-preset-size">
                    <label for="preset_size">预设比例</label>
                    <select id="preset_size">
                        <option value="">选择比例</option>
                        <?php
                        $preset_sizes = array_map('trim', explode(',', get_option('preset_image_sizes', '正方形(1024x1024),视频竖版封面(1080x1920)')));
                        foreach ($preset_sizes as $preset) {
                            if (preg_match('/^(.*?)\((\d+)x(\d+)\)$/', $preset, $matches)) {
                                $name = trim($matches[1]);
                                $width = intval($matches[2]);
                                $height = intval($matches[3]);
                                echo "<option value='{$width}x{$height}' data-name='{$name}' data-width='{$width}' data-height='{$height}'>{$name} ({$width}x{$height})</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <!-- 图片风格选择 -->
                <div class="wp-ai-option wp-ai-image-style">
                    <label for="image_style">预设风格</label>
                    <select id="image_style" name="image_style">
                        <option value="">选择风格</option>
                        <?php foreach ($image_styles as $style) { ?>
                            <option value="<?php echo esc_attr($style['name']); ?>" data-image-url="<?php echo esc_url($style['image_url']); ?>">
                                <?php echo esc_html($style['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
    
                <div class="wp-ai-option">
                    <label><input type="checkbox" id="optimize_prompt" /> 优化提示词</label>
                </div>
                <div class="wp-ai-option">
                    <label><input type="checkbox" id="optimize_to_english" /> 优化成英文</label>
                </div>
                <div class="wp-ai-option">
                    <label for="deepseek_model">优化模型</label>
                    <select id="deepseek_model">
                        <?php foreach ($deepseek_models as $model) { ?>
                            <option value="<?php echo esc_attr(trim($model)); ?>"><?php echo esc_html(trim($model)); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="wp-ai-option">
                    <label for="api_type">绘画接口</label>
                    <select id="api_type">
                        <?php foreach (get_option('enabled_apis', ['pollinations']) as $api) { ?>
                            <option value="<?php echo esc_attr($api); ?>"><?php echo esc_html($api_display_names[$api]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="wp-ai-option">
                    <label for="generation_model">绘画模型</label>
                    <select id="generation_model"></select>
                </div>
                <?php if (is_user_logged_in() || $allow_guest) { ?>
                    <button id="generate-btn" class="wp-ai-button">开始绘画</button>
                    <?php if (is_user_logged_in() && get_option('enable_image_upload', 0)) { ?>
                        <div id="image-upload-container" class="wp-ai-option" style="display: none;">
                            <label>选择参考图片</label>
                            <button id="upload-image-btn" class="wp-ai-upload-btn">上传图片</button>
                            <input type="file" id="image-upload-input" accept=".jpg,.jpeg,.png" style="display:none;">
                            <div id="image-preview" style="margin-top:10px;"></div>
                            </div>
                        <?php } ?>
                <?php } else { ?>
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="wp-ai-button wp-ai-login-btn">请先登录</a>
                <?php } ?>
            </div>
        </div>

        <div id="generated-image" class="wp-ai-generated-image"></div>
        
        <?php if (!empty($announcement)) { ?>
            <div class="wp-ai-announcement"><?php echo wp_kses_post($announcement); ?></div>
        <?php } ?>

        <div id="custom-notice" class="wp-ai-notice"></div>
        <h3>随机推荐
            <?php if (is_user_logged_in()) { ?>
                <a href="<?php echo esc_url($my_records_url); ?>" target="_blank" class="wp-ai-my-images-link">我的图片</a>
            <?php } ?>
        </h3>
        <div class="wp-ai-random-images">
            <?php
            $ad_content = get_option('ai_image_ad_content', '');
            if (!empty($ad_content)) {
                echo '<div class="wp-ai-image-item wp-ai-ad-item">';
                echo wp_kses_post($ad_content); // 输出广告内容，支持 HTML
                echo '</div>';
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_image_generator';
            $images = $wpdb->get_results("
                SELECT * FROM $table_name 
                WHERE hide_in_frontend = 0 
                AND is_deleted = 0 
                ORDER BY RAND() 
                LIMIT 20
            ");
            foreach ($images as $image) {
                echo '<div class="wp-ai-image-item">';
                echo '<a href="' . esc_url($image->image_url) . '" data-lightbox="random-images" data-title="' . esc_attr($image->prompt) . '">';
                echo '<img src="' . esc_url($image->image_url) . '" alt="' . esc_attr($image->prompt) . '" />';
                echo '</a>';
                echo '<div class="wp-ai-image-actions">';
                if (is_user_logged_in() || $allow_guest) {
                    echo '<button class="wp-ai-download-btn" data-url="' . esc_url($image->image_url) . '">下载</button>';
                }
                echo '<button class="wp-ai-generate-same-btn" data-prompt="' . esc_attr($image->prompt) . '" data-seed="' . esc_attr($image->seed ?? '') . '">生成同款</button>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 动态获取包含 [ai_image_generate] 的页面 URL，并添加缓存
function get_ai_generate_page_url() {
    $cache_key = 'ai_generate_page_url';
    $generate_page_url = get_transient($cache_key);
    
    if ($generate_page_url === false) {
        $pages = get_pages();
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'ai_image_generate')) {
                $generate_page_url = get_permalink($page->ID);
                break;
            }
        }
        // 如果没找到，使用默认值作为fallback
        if (empty($generate_page_url)) {
            $generate_page_url = get_permalink(get_page_by_path('ai-image-generate'));
        }
        // 缓存结果，设置过期时间为1小时
        set_transient($cache_key, $generate_page_url, HOUR_IN_SECONDS);
    }
    
    return $generate_page_url;
}

// 展示页面
function wp_ai_image_gallery_shortcode() {
    global $post;
    
    // 如果当前页面没有这个短代码，直接返回空
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'ai_image_gallery')) {
        return '';
    }

    ob_start();

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    $user_id = get_current_user_id();
    
    // 分页参数
    $per_page = 30;
    $current_page = isset($_GET['gallery_page']) ? max(1, intval($_GET['gallery_page'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // 查询条件
    $where = $user_id && isset($_GET['my_records']) 
        ? $wpdb->prepare("WHERE user_id = %d AND is_deleted = 0", $user_id) 
        : "WHERE hide_in_frontend = 0 AND is_deleted = 0";
    
    $total_images = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_images / $per_page);
    
    $images = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    // 使用动态查找函数获取生成页面 URL
    $generate_page_url = get_ai_generate_page_url();
    $allow_user_delete_images = get_option('allow_user_delete_images', 0);
    $enable_image_count_display = get_option('enable_image_count_display', 0);

    wp_localize_script('wp-ai-image-script', 'wpAiImage', [
        'rest_url' => rest_url('wp-ai-image/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'generate_page' => $generate_page_url,  // 使用动态获取的 URL
        'is_logged_in' => is_user_logged_in(),
        'login_url' => wp_login_url(get_permalink()),
        'enable_image_count_display' => $enable_image_count_display,
        'total_images' => $total_images,  // 将总数传递给前端
        'enable_forbidden_words' => get_option('enable_forbidden_words', 0),
        'allow_user_delete_images' => $allow_user_delete_images,
        'forbidden_words' => array_map('trim', explode(',', get_option('forbidden_words', ''))),
        'default_settings' => [
            'width' => 1024,
            'height' => 1024,
            'optimize_prompt' => false,
            'optimize_to_english' => false,
            'deepseek_model' => 'deepseek-chat',
            'api_type' => 'pollinations',
            'generation_model' => 'flux'
        ]
    ]);
    ?>
    <div class="wp-ai-image-gallery">
        <h2>图片展示</h2>
        <?php if (is_user_logged_in()) { ?>
            <div class="wp-ai-gallery-actions">
                <a href="?my_records=1" class="wp-ai-button wp-ai-no-underline">我的图片</a>
                <a href="<?php echo remove_query_arg('my_records'); ?>" class="wp-ai-button wp-ai-no-underline">全部图片</a>
            </div>
        <?php } ?>
        <div class="wp-ai-gallery-images">
            <?php

            $ad_content = get_option('ai_image_ad_content', '');
            if (!empty($ad_content)) {
                echo '<div class="wp-ai-image-item wp-ai-ad-item">';
                echo wp_kses_post($ad_content); // 输出广告内容，支持 HTML
                echo '</div>';
            }

            if ($images) {
                foreach ($images as $image) { ?>
                    <div class="wp-ai-image-item">
                        <a href="<?php echo esc_url($image->image_url); ?>" data-lightbox="gallery-images" data-title="<?php echo esc_attr($image->prompt); ?>">
                            <img src="<?php echo esc_url($image->image_url); ?>" alt="<?php echo esc_attr($image->prompt); ?>" />
                        </a>
                        <div class="wp-ai-image-actions">
                            <?php if (is_user_logged_in() || get_option('allow_guest', 0)) { ?>
                                <button class="wp-ai-download-btn" data-url="<?php echo esc_url($image->image_url); ?>">下载</button>
                            <?php } ?>
                            <button class="wp-ai-generate-same-btn" data-prompt="<?php echo esc_attr($image->prompt); ?>" data-seed="<?php echo esc_attr($image->seed ?? ''); ?>">生成同款</button>

                            <?php if ($allow_user_delete_images && $user_id && $image->user_id == $user_id && isset($_GET['my_records'])) { ?>
                                <button class="wp-ai-delete-btn" data-image-id="<?php echo esc_attr($image->id); ?>">删除</button>
                            <?php } ?>
                        </div>
                    </div>
                <?php }
            } else { ?>
                <p>暂无图片</p>
            <?php } ?>
        </div>
        
        <?php if ($total_pages > 1) { ?>
            <div class="wp-ai-pagination">
                <?php
                $base_url = remove_query_arg('gallery_page');
                if (isset($_GET['my_records'])) {
                    $base_url = add_query_arg('my_records', 1, $base_url);
                }
                
                // 首页
                if ($current_page > 1) {
                    echo '<a href="' . esc_url($base_url) . '" class="wp-ai-page-link">首页</a>';
                }
                
                // 上一页
                if ($current_page > 1) {
                    $prev_page = $current_page - 1;
                    $prev_url = add_query_arg('gallery_page', $prev_page, $base_url);
                    echo '<a href="' . esc_url($prev_url) . '" class="wp-ai-page-link">上一页</a>';
                }
                
                // 当前页显示
                // echo '<span class="wp-ai-current-page">第 ' . esc_html($current_page) . ' 页 / 共 ' . esc_html($total_pages) . ' 页</span>';
                
                // 下一页
                if ($current_page < $total_pages) {
                    $next_page = $current_page + 1;
                    $next_url = add_query_arg('gallery_page', $next_page, $base_url);
                    echo '<a href="' . esc_url($next_url) . '" class="wp-ai-page-link">下一页</a>';
                }
                
                // 末页
                if ($current_page < $total_pages) {
                    $last_url = add_query_arg('gallery_page', $total_pages, $base_url);
                    echo '<a href="' . esc_url($last_url) . '" class="wp-ai-page-link">最后一页</a>';
                }
                ?>
            </div>
        <?php } ?>

        <?php if ($enable_image_count_display) { ?>
            <div class="wp-ai-image-count">
                已免费生成AI图片 <span id="total-image-count"><?php echo esc_html($total_images); ?></span> 张
            </div>
        <?php } ?>

    </div>
    <?php
    return ob_get_clean();
}

// REST API 端点注册
add_action('rest_api_init', 'wp_ai_image_generator_register_routes');
function wp_ai_image_generator_register_routes() {
    register_rest_route('wp-ai-image/v1', '/optimize-prompt', [
        'methods' => 'POST',
        'callback' => 'ai_image_optimize_prompt_callback',
        'permission_callback' => function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest') ? true : new WP_Error('rest_forbidden', 'Nonce 验证失败', ['status' => 403]);
        },
    ]);

    register_rest_route('wp-ai-image/v1', '/generate-image', [
        'methods' => 'POST',
        'callback' => 'ai_image_generate_image_callback',
        'permission_callback' => function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest') ? true : new WP_Error('rest_forbidden', 'Nonce 验证失败', ['status' => 403]);
        },
    ]);

    register_rest_route('wp-ai-image/v1', '/upload-image', [
        'methods' => 'POST',
        'callback' => 'ai_image_upload_image_callback',
        'permission_callback' => function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest') ? true : new WP_Error('rest_forbidden', 'Nonce 验证失败', ['status' => 403]);
        },
    ]);

    register_rest_route('wp-ai-image/v1', '/delete-image', [
        'methods' => 'POST',
        'callback' => 'ai_image_delete_image_callback',
        'permission_callback' => function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest') && is_user_logged_in();
        },
    ]);

    register_rest_route('wp-ai-image/v1', '/get-total-images', [
        'methods' => 'GET',
        'callback' => 'ai_image_get_total_images_callback',
        'permission_callback' => function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest');
        },
    ]);
}

// 违规词
function ai_image_optimize_prompt_callback($request) {
    $params = $request->get_json_params();
    $prompt = sanitize_text_field($params['prompt']);
    $model = sanitize_text_field($params['deepseek_model']);
    $optimize_to_english = filter_var($params['optimize_to_english'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (empty($prompt)) {
        return new WP_Error('invalid_prompt', '提示词不能为空', ['status' => 400]);
    }

    if (wp_ai_check_forbidden_words($prompt)) {
        return new WP_Error('forbidden_prompt', '提示词包含违规内容，请更换提示词', ['status' => 400]);
    }

    $optimized_prompt = ai_image_optimize_prompt($prompt, $model, $optimize_to_english);
    if (is_wp_error($optimized_prompt)) {
        error_log('优化提示词失败: ' . $optimized_prompt->get_error_message());
        return $optimized_prompt;
    }

    return rest_ensure_response(['optimized_prompt' => $optimized_prompt]);
}

// 上传图片
function ai_image_upload_image_callback($request) {
    if (!get_option('enable_image_upload')) {
        return new WP_Error('upload_disabled', '图片上传功能未启用', ['status' => 403]);
    }

    $user_id = get_current_user_id();
    if (!wp_ai_check_user_limit($user_id)) {
        return new WP_Error('limit_exceeded', '今日绘画额度已用完，请明天再试', ['status' => 429]);
    }

    $files = $request->get_file_params();
    if (empty($files['image'])) {
        error_log('WP AI Image Generator: No image file received in upload request');
        return new WP_Error('no_image', '未上传图片', ['status' => 400]);
    }

    $file = $files['image'];
    $allowed_mime_types = ['image/jpeg', 'image/png'];
    $max_size = 2 * 1024 * 1024;

    // 检查MIME类型
    if (!in_array($file['type'], $allowed_mime_types)) {
        error_log('WP AI Image Generator: Invalid file type - ' . $file['type']);
        return new WP_Error('invalid_type', '格式不支持，仅支持 jpg 和 png 格式', ['status' => 400]);
    }

    // 检查文件大小
    if ($file['size'] > $max_size) {
        error_log('WP AI Image Generator: File size exceeds 2MB - ' . $file['size']);
        return new WP_Error('file_too_large', '图片大小不能超过 2MB', ['status' => 400]);
    }

    // 检查真实图片格式
    $file_path = $file['tmp_name'];
    $image_info = getimagesize($file_path);
    if ($image_info === false || !in_array($image_info['mime'], $allowed_mime_types)) {
        error_log('WP AI Image Generator: File is not a valid image - ' . $file['name']);
        return new WP_Error('invalid_image', '文件不对，请更换真实的 jpg 或 png 图片', ['status' => 400]);
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (isset($upload['error'])) {
        error_log('WP AI Image Generator: Upload error - ' . $upload['error']);
        return new WP_Error('upload_error', $upload['error'], ['status' => 500]);
    }

    $attachment = [
        'post_mime_type' => $file['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if (!$attach_id) {
        error_log('WP AI Image Generator: Failed to insert attachment');
        return new WP_Error('attachment_error', '无法保存图片到媒体库', ['status' => 500]);
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return rest_ensure_response([
        'image_url' => wp_get_attachment_url($attach_id),
        'attachment_id' => $attach_id
    ]);
}

// 生成图片
function ai_image_generate_image_callback($request) {
    if (!get_option('allow_guest') && !is_user_logged_in()) {
        return new WP_Error('no_permission', '游客无权限绘画', ['status' => 403]);
    }

    $user_id = get_current_user_id();
    if (!wp_ai_check_user_limit($user_id)) {
        return new WP_Error('limit_exceeded', '今日绘画额度已用完，请明天再试', ['status' => 429]);
    }

    $limit_10min = wp_ai_check_user_10min_limit($user_id);
    if (get_option('enable_10min_limit', 0) && !$limit_10min['allowed']) {
        return new WP_Error('10min_limit_exceeded', '10分钟内绘画数量已达上限，请等待30分钟后重试', ['status' => 429]);
    }

    $params = $request->get_json_params();
    $prompt = sanitize_text_field($params['prompt']);
    $width = intval($params['width']);
    $height = intval($params['height']);
    $api_type = sanitize_text_field($params['api_type']);
    $model = sanitize_text_field($params['generation_model']);
    $seed = isset($params['seed']) ? intval($params['seed']) : mt_rand(0, 4294967295);
    $base64_image = isset($params['image']) ? $params['image'] : null;

    if (empty($prompt)) {
        return new WP_Error('invalid_prompt', '提示词不能为空', ['status' => 400]);
    }

    if (wp_ai_check_forbidden_words($prompt)) {
        return new WP_Error('forbidden_prompt', '提示词包含违规内容，请更换提示词', ['status' => 400]);
    }

    $enabled_apis = get_option('enabled_apis', ['pollinations']);
    if (!in_array($api_type, $enabled_apis)) {
        return new WP_Error('invalid_api', '所选接口未启用', ['status' => 400]);
    }

    $image_data = ($api_type === 'kolors') ?
        ai_image_generate_image_kolors($prompt, $width, $height, $model, $seed, $base64_image) :
        ai_image_generate_image_pollinations($prompt, $width, $height, $model, $seed);

    if (is_wp_error($image_data)) {
        return $image_data;
    }

    $new_image_url = ai_image_save_to_media_library($image_data, $prompt);

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'prompt' => $prompt,
        'optimized_prompt' => $params['optimized_prompt'] ?? null,
        'image_url' => $new_image_url,
        'width' => $width,
        'height' => $height,
        'model' => $model,
        'api_type' => $api_type,
        'seed' => $seed,
    ]);

    // 生成成功后更新10分钟计数
    wp_ai_update_10min_count($user_id);

    return rest_ensure_response(['image_url' => $new_image_url]);
}

// 前台删除
function ai_image_delete_image_callback($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_image_generator';
    
    if (!get_option('allow_user_delete_images')) {
        return new WP_Error('delete_disabled', '删除功能未启用', ['status' => 403]);
    }
    
    $user_id = get_current_user_id();
    $image_id = intval($request->get_param('image_id'));
    
    $image = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM $table_name WHERE id = %d",
        $image_id
    ));
    
    if (!$image) {
        return new WP_Error('image_not_found', '图片不存在', ['status' => 404]);
    }
    
    if ($image->user_id != $user_id) {
        return new WP_Error('no_permission', '无权限删除此图片', ['status' => 403]);
    }
    
    $wpdb->update(
        $table_name,
        ['is_deleted' => 1],
        ['id' => $image_id],
        ['%d'],
        ['%d']
    );
    
    return rest_ensure_response(['success' => true]);
}

// 优化
function ai_image_optimize_prompt($prompt, $model, $optimize_to_english = false) {
    $api_key = get_option('ai_image_deepseek_api_key');
    $api_url = get_option('ai_image_deepseek_api_url', 'https://api.deepseek.com/chat/completions');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', '未配置 DeepSeek API Key', ['status' => 500]);
    }
    if (empty($api_url)) {
        return new WP_Error('no_api_url', '未配置 DeepSeek API URL', ['status' => 500]);
    }

    $system_prompt_base = "你是一个专业的图像生成提示词优化专家。你的任务是接收用户提供的原始提示词，将其优化为一个极其详细、丰富的描述，以提升图像生成的质量和准确性。优化时需遵循以下原则：
    1. 增强描述性：大幅增加具体细节，包括但不限于颜色、材质、纹理、光线类型（如柔和的晨光、戏剧性的夕阳）、天气、环境细节（如背景中的树木、河流）、物体比例、空间布局等，使描述生动且具象化。
    2. 添加氛围与情感：融入场景的氛围（如宁静、神秘、欢乐）和情感基调，提升图像的表现力。
    3. 结构清晰：保持语法通顺，描述逻辑分明，按场景、主体、背景的顺序组织，便于AI理解。
    4. 避免冗余：去除无关或重复的词语，但确保细节丰富。
    5. 适配图像生成：使用适合图像生成模型的语言风格，注重视觉元素的具体性和可描绘性。
    只返回优化后的提示词，不要添加任何解释、示例或其他内容。";

    $system_prompt_english = "You are a professional image generation prompt optimization expert. Your task is to take the user-provided prompt, translate it into English if it’s not already in English, and optimize it into an extremely detailed and rich description to enhance the quality and accuracy of image generation. Follow these principles:
    1. Enhance descriptiveness: Significantly add specific details, including but not limited to colors, materials, textures, lighting types (e.g., soft morning glow, dramatic sunset), weather conditions, environmental elements (e.g., trees or rivers in the background), object proportions, spatial arrangement, etc., to make the description vivid and concrete.
    2. Incorporate atmosphere and emotion: Infuse the scene with atmosphere (e.g., serene, mysterious, joyful) and emotional tone to enhance the image’s expressiveness.
    3. Ensure clarity: Maintain clear grammar and logical structure, organizing the description in a sequence of scene, subject, and background for AI comprehension.
    4. Avoid redundancy: Eliminate irrelevant or repetitive words while ensuring rich detail.
    5. Adapt for image generation: Use a language style tailored to image generation models, emphasizing specificity and visual depictability.
    Return only the optimized English prompt, without any explanations, examples, or additional content.";

    $system_prompt = $optimize_to_english ? $system_prompt_english : $system_prompt_base;

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => "优化此提示词以更好地生成图像: $prompt"],
            ],
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $lines = explode("\n", $body);
    $optimized_prompt = '';
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0 && $line !== 'data: [DONE]') {
            $data = json_decode(substr($line, 6), true);
            if (isset($data['choices'][0]['delta']['content'])) {
                $optimized_prompt .= $data['choices'][0]['delta']['content'];
            }
        }
    }

    $optimized_prompt = trim($optimized_prompt);
    return $optimized_prompt ?: $prompt;
}

// pollinations
function ai_image_generate_image_pollinations($prompt, $width, $height, $model = null, $seed = null) {
    $default_models = explode(',', get_option('pollinations_models', 'flux'));
    $model = $model ?: $default_models[0];
    $url = "https://image.pollinations.ai/prompt/" . urlencode($prompt) . 
           "?width=$width&height=$height&nologo=true&private=true&safe=true&model=" . urlencode($model);
    if ($seed !== null) {
        $url .= "&seed=$seed";
    }

    $response = wp_remote_get($url, ['timeout' => 90]);
    if (is_wp_error($response)) {
        return $response;
    }

    return wp_remote_retrieve_body($response);
}

// 可图
function ai_image_generate_image_kolors($prompt, $width, $height, $model = 'Kwai-Kolors/Kolors', $seed = null, $base64_image = null) {
    $api_key = get_option('kolors_api_key');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', '未配置 Kolors API Key', ['status' => 500]);
    }

    $request_body = [
        'model' => $model,
        'prompt' => $prompt,
        'image_size' => "{$width}x{$height}",
        'batch_size' => 1,
        'seed' => $seed ?? mt_rand(0, 4294967295),
        'num_inference_steps' => 20,
        'guidance_scale' => 7.5,
    ];

    if ($base64_image) {
        $request_body['image'] = $base64_image;
        error_log("Kolors Base64 Image Sent: " . substr($base64_image, 0, 50) . "...");
    } else {
        error_log("No Base64 image provided for Kolors");
    }

    $response = wp_remote_post('https://api.siliconflow.cn/v1/images/generations', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode($request_body),
        'timeout' => 90,
    ]);

    if (is_wp_error($response)) {
        error_log("Kolors API Request Error: " . $response->get_error_message());
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '今天绘画的额度已使用完，请更换其他接口生成', ['status' => 429]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log("Kolors API Response: " . print_r($body, true));
    if (isset($body['images'][0]['url'])) {
        $image_response = wp_remote_get($body['images'][0]['url'], ['timeout' => 30]);
        if (is_wp_error($image_response)) {
            return $image_response;
        }
        return wp_remote_retrieve_body($image_response);
    }

    return new WP_Error('api_error', 'Kolors API 返回数据异常', ['status' => 500]);
}

// 上传文件保存
function ai_image_save_to_media_library($image_data, $prompt) {
    $upload_dir = wp_upload_dir();
    $filename = 'ai-image-' . wp_generate_password(8, false) . '.jpg';
    $file_path = $upload_dir['path'] . '/' . $filename;

    file_put_contents($file_path, $image_data);

    // 使用日期+随机值作为标题，而不是提示词
    $title = 'AI-Image-' . date('YmdHis') . '-' . wp_generate_password(8, false);
    $sanitized_title = rtrim(sanitize_file_name($title), '.'); // 移除尾部的点

    $attachment = [
        'post_mime_type' => 'image/jpeg',
        'post_title' => $sanitized_title, // 使用日期+随机值作为标题
        'post_content' => '',
        'post_status' => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return wp_get_attachment_url($attach_id);
}

// 检查用户10分钟内生成限制
function wp_ai_check_user_10min_limit($user_id) {
    if (!get_option('enable_10min_limit', 0)) {
        return ['allowed' => true, 'remaining_10min' => null, 'reset_time' => null];
    }

    $max_images_10min = get_option('max_images_10min', 50);
    $transient_key = 'ai_image_10min_' . $user_id;
    $data = get_transient($transient_key);

    $current_time = current_time('timestamp');

    if (!$data) {
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    // 如果冷却时间已过，重置计数
    if ($current_time >= $data['cooldown_until']) {
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    // 检查10分钟窗口
    $time_elapsed = $current_time - $data['start_time'];
    if ($time_elapsed >= 600) { // 10分钟窗口结束，重置
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    $remaining_10min = max(0, $max_images_10min - $data['count']);
    $reset_time = $data['cooldown_until'] > $current_time ? $data['cooldown_until'] : 0;

    if ($remaining_10min <= 0) {
        return [
            'allowed' => false,
            'remaining_10min' => 0,
            'reset_time' => $reset_time ?: ($current_time + 1800) // 达到上限时立即设置30分钟冷却
        ];
    }

    return [
        'allowed' => true,
        'remaining_10min' => $remaining_10min,
        'reset_time' => $reset_time
    ];
}

// 更新10分钟计数
function wp_ai_update_10min_count($user_id) {
    if (!get_option('enable_10min_limit', 0)) {
        return;
    }

    $max_images_10min = get_option('max_images_10min', 50);
    $transient_key = 'ai_image_10min_' . $user_id;
    $data = get_transient($transient_key);

    $current_time = current_time('timestamp');

    if (!$data) {
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    if ($current_time >= $data['cooldown_until']) {
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    if ($current_time - $data['start_time'] >= 600) { // 10分钟窗口结束
        $data = [
            'count' => 0,
            'start_time' => $current_time,
            'cooldown_until' => 0
        ];
    }

    $data['count']++;
    if ($data['count'] >= $max_images_10min) {
        $data['cooldown_until'] = $current_time + 1800; // 达到上限时立即设置30分钟冷却
    }

    set_transient($transient_key, $data, 2400); // 40分钟过期时间，确保覆盖10分钟+30分钟
}

// 首页统计数据
function ai_image_get_total_images_callback($request) {
    $cache_key = 'ai_image_total_count';
    $total_images = get_transient($cache_key);
    
    if ($total_images === false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_image_generator';
        $total_images = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_deleted = 0");
        set_transient($cache_key, $total_images, 300); // 缓存5分钟
    }
    
    return rest_ensure_response(['total_images' => $total_images]);
}