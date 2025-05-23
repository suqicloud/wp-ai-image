jQuery(document).ready(function ($) {
    // 从 localStorage 加载设置
    function ai_image_load_settings() {
        const savedSettings = JSON.parse(localStorage.getItem('wpAiImageSettings')) || {};
        const settings = $.extend({}, wpAiImage.default_settings, savedSettings);

        $('#width').val(settings.width || wpAiImage.default_settings.width);
        $('#height').val(settings.height || wpAiImage.default_settings.height);
        $('#optimize_prompt').prop('checked', settings.optimize_prompt || false);
        $('#optimize_to_english').prop('checked', settings.optimize_to_english || false);
        $('#deepseek_model').val(settings.deepseek_model || wpAiImage.deepseek_models[0]);
        $('#api_type').val(settings.api_type || wpAiImage.default_settings.api_type);
        $('#generation_model').val(settings.generation_model || wpAiImage.default_settings.generation_model);
        // 加载图片风格设置
        $('#image_style').val(settings.image_style || wpAiImage.default_settings.image_style);

        const savedPreset = settings.preset_size || '';
        $('#preset_size').val(savedPreset);
        if (savedPreset) {
            const [width, height] = savedPreset.split('x').map(Number);
            $('#width').val(width);
            $('#height').val(height);
        }

        ai_image_update_model_options();
        ai_image_update_image_upload_ui();
        // 初始化图片风格下拉框
        ai_image_initialize_image_style_select();
    }

    // 保存设置到 localStorage
    function ai_image_save_settings() {
        const settings = {
            width: $('#width').val(),
            height: $('#height').val(),
            optimize_prompt: $('#optimize_prompt').is(':checked'),
            optimize_to_english: $('#optimize_to_english').is(':checked'),
            deepseek_model: $('#deepseek_model').val(),
            api_type: $('#api_type').val(),
            generation_model: $('#generation_model').val(),
            preset_size: $('#preset_size').val(),
            // 保存图片风格选择
            image_style: $('#image_style').val(),
        };
        localStorage.setItem('wpAiImageSettings', JSON.stringify(settings));
    }

    // 确保两个复选框互斥
    $('#optimize_prompt').on('change', function () {
        if ($(this).is(':checked')) {
            $('#optimize_to_english').prop('checked', false);
        }
        ai_image_save_settings();
    });

    $('#optimize_to_english').on('change', function () {
        if ($(this).is(':checked')) {
            $('#optimize_prompt').prop('checked', false);
        }
        ai_image_save_settings();
    });

    // 初始化模型选择
    function ai_image_update_model_options() {
        const apiType = $('#api_type').val();
        const $modelSelect = $('#generation_model');
        $modelSelect.empty();

        const models = apiType === 'pollinations' ? wpAiImage.pollinations_models : wpAiImage.kolors_models;
        if (models && Array.isArray(models)) {
            models.forEach((model) => {
                $modelSelect.append(
                    $('<option>', {
                        value: model,
                        text: model,
                    })
                );
            });
        }
        ai_image_update_image_upload_ui();
    }

    // 初始化图片风格下拉框（使用 Select2 插件）
    function ai_image_initialize_image_style_select() {
        $('#image_style').select2({
            templateResult: function (data) {
                if (!data.element) {
                    return data.text;
                }
                const $element = $(data.element);
                const imageUrl = $element.data('image-url');
                if (!imageUrl) {
                    return data.text;
                }
                return $(
                    '<span><img src="' + imageUrl + '" class="image-style-preview" style="width: 30px; height: 30px; margin-right: 10px; vertical-align: middle;" />' + data.text + '</span>'
                );
            },
            templateSelection: function (data) {
                if (!data.element) {
                    return data.text;
                }
                const $element = $(data.element);
                const imageUrl = $element.data('image-url');
                if (!imageUrl) {
                    return data.text;
                }
                return $(
                    '<span><img src="' + imageUrl + '" class="image-style-preview" style="width: 30px; height: 30px; margin-right: 5px; vertical-align: middle;" />' + data.text + '</span>'
                );
            },
            width: '135px', // 与其他下拉框宽度一致
        });

        // 监听风格选择变化并保存设置
        $('#image_style').on('change', function () {
            ai_image_save_settings();
        });
    }

    // 更新生成按钮状态
    function ai_image_update_generate_button_state() {
        const $btn = $('#generate-btn');
        const currentTime = Math.floor(Date.now() / 1000);

        if (!wpAiImage.is_logged_in && !wpAiImage.allow_guest) {
            $btn.hide();
            return;
        }

        if (wpAiImage.remaining_images <= 0) {
            $btn
                .prop('disabled', true)
                .css({ 'background-color': '#cccccc', 'cursor': 'not-allowed', 'opacity': '0.6' })
                .text('请明天再来');
        } else if (wpAiImage.enable_10min_limit && wpAiImage.remaining_10min <= 0 && currentTime < wpAiImage.reset_time_10min) {
            $btn
                .prop('disabled', true)
                .css({ 'background-color': '#cccccc', 'cursor': 'not-allowed', 'opacity': '0.6' })
                .text('请等一会');
            $('#limit-10min-notice').show();
            setTimeout(() => {
                $('#limit-10min-notice').hide();
                wpAiImage.remaining_10min = wpAiImage.max_images_10min;
                wpAiImage.reset_time_10min = 0;
                ai_image_update_generate_button_state();
            }, (wpAiImage.reset_time_10min - currentTime) * 1000);
        } else {
            $btn
                .prop('disabled', false)
                .css({ 'background-color': '', 'cursor': '', 'opacity': '' })
                .text('开始绘画');
            $('#limit-10min-notice').hide();
        }
    }

    // 接口切换时更新模型并保存设置
    $('#api_type').on('change', function () {
        ai_image_update_model_options();
        ai_image_update_image_upload_ui();
        ai_image_save_settings();
    });

    // 预设尺寸选择
    $('#preset_size').on('change', function () {
        const selectedValue = $(this).val();
        if (selectedValue) {
            const [width, height] = selectedValue.split('x').map(Number);
            $('#width').val(width);
            $('#height').val(height);
        } else {
            const savedSettings = JSON.parse(localStorage.getItem('wpAiImageSettings')) || {};
            $('#width').val(savedSettings.width || wpAiImage.default_settings.width);
            $('#height').val(savedSettings.height || wpAiImage.default_settings.height);
        }
        ai_image_save_settings();
    });

    // 宽度和高度手动输入时清空预设选择
    $('#width, #height').on('input', function () {
        $('#preset_size').val('');
        ai_image_save_settings();
    });

    // 其他选项变化时保存设置
    $('#optimize_prompt, #deepseek_model, #generation_model').on('change', ai_image_save_settings);

    // 检查违规词
    function ai_image_check_forbidden_words(prompt) {
        if (!wpAiImage.enable_forbidden_words || !wpAiImage.forbidden_words.length) {
            return false;
        }
        const promptLower = prompt.toLowerCase();
        return wpAiImage.forbidden_words.some((word) => word && promptLower.includes(word.toLowerCase()));
    }

    // 更新上传图片 UI
    function ai_image_update_image_upload_ui() {
        const apiType = $('#api_type').val();
        const $uploadContainer = $('#image-upload-container');

        if (!$uploadContainer.length || !wpAiImage.enable_image_upload || apiType !== 'kolors' || !wpAiImage.is_logged_in) {
            $uploadContainer.hide();
            return;
        }

        $uploadContainer.show();

        if (!$uploadContainer.hasClass('initialized')) {
            $('#upload-image-btn').on('click', function (e) {
                e.preventDefault();
                $('#image-upload-input').click();
            });

            $('#image-upload-input').on('change', function () {
                const file = this.files[0];
                if (!file) return;

                // 前端检查文件扩展名和MIME类型
                const validExtensions = ['jpg', 'jpeg', 'png'];
                const fileExt = file.name.split('.').pop().toLowerCase();
                const validMimeTypes = ['image/jpeg', 'image/png'];

                if (!validExtensions.includes(fileExt) || !validMimeTypes.includes(file.type)) {
                    ai_image_show_notice('格式不支持，仅支持 jpg 和 png 格式');
                    this.value = ''; // 清空输入
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    ai_image_show_notice('图片大小不能超过 2MB');
                    this.value = '';
                    return;
                }

                // 使用 FileReader 读取文件并验证真实图片格式
                const reader = new FileReader();
                reader.onload = function (e) {
                    const arrayBuffer = e.target.result;
                    const bytes = new Uint8Array(arrayBuffer);

                    // 检查文件头
                    const isJPEG = bytes[0] === 0xFF && bytes[1] === 0xD8;
                    const isPNG = bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4E && bytes[3] === 0x47;

                    if (!isJPEG && !isPNG) {
                        ai_image_show_notice('文件不对，请更换真实的 jpg 或 png 图片');
                        $('#image-upload-input').val('');
                        return;
                    }

                    ai_image_upload_image(file);
                };
                reader.readAsArrayBuffer(file);
            });

            $uploadContainer.addClass('initialized');
        }
    }

    // 上传图片到媒体库并显示预览
    function ai_image_upload_image(file) {
        const formData = new FormData();
        formData.append('image', file);

        $.ajax({
            url: wpAiImage.rest_url + 'upload-image',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpAiImage.nonce);
            },
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                let imageUrl = response.data ? response.data.image_url : response.image_url;
                let attachmentId = response.data ? response.data.attachment_id : response.attachment_id;

                if (imageUrl && attachmentId) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const base64Data = e.target.result;
                        const $preview = $('#image-preview');
                        $preview.empty().append(
                            $('<div>', { class: 'image-preview-wrapper' }).append(
                                $('<img>', {
                                    src: imageUrl,
                                    width: 100,
                                    height: 100,
                                    alt: '参考图片预览',
                                }),
                                $('<span>', {
                                    class: 'image-delete-btn',
                                    text: '×',
                                    click: function () {
                                        $preview.empty().removeData('attachment_id').removeData('base64');
                                    }
                                })
                            )
                        );
                        $preview.data('attachment_id', attachmentId);
                        $preview.data('base64', base64Data);
                    };
                    reader.readAsDataURL(file);
                } else {
                    ai_image_show_notice('图片上传失败');
                }
            },
            error: function (xhr) {
                const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '图片上传失败';
                ai_image_show_notice(errorMessage);
            },
        });
    }

    // 生成图片
    $('#generate-btn').on('click', function () {
        if ($(this).prop('disabled')) return;

        let prompt = $('#prompt').val();
        const optimize = $('#optimize_prompt').is(':checked');
        const optimizeToEnglish = $('#optimize_to_english').is(':checked');
        const deepseek_model = $('#deepseek_model').val();
        const api_type = $('#api_type').val();
        const generation_model = $('#generation_model').val();
        const width = $('#width').val() || 1024;
        const height = $('#height').val() || 1024;
        const seed = Math.floor(Math.random() * 4294967296);
        const $preview = $('#image-preview');
        const image_attachment_id = $preview.data('attachment_id') || 0;
        const currentTime = Math.floor(Date.now() / 1000);
        // 获取用户选择的图片风格
        const selectedStyle = $('#image_style').val();

        if (!prompt) {
            ai_image_show_notice('提示词不能为空');
            return;
        }

        if (ai_image_check_forbidden_words(prompt)) {
            ai_image_show_notice('提示词包含违规内容，请更换提示词');
            return;
        }

        if (wpAiImage.remaining_images <= 0 && (wpAiImage.is_logged_in || wpAiImage.allow_guest)) {
            ai_image_show_notice('今日绘画额度已用完，请明天再试');
            return;
        }

        if (wpAiImage.enable_10min_limit && wpAiImage.remaining_10min <= 0 && currentTime < wpAiImage.reset_time_10min) {
            ai_image_show_notice('10分钟内绘画数量已达上限，请等待30分钟后重试');
            return;
        }

        // 如果用户选择了风格，则在提示词后拼接风格描述
        if (selectedStyle) {
            prompt = prompt.trim();
            prompt += `，这张图片需要是${selectedStyle}的图片。`;
        }

        if (optimize) {
            ai_image_show_notice('正在优化提示词中...');
            ai_image_optimize_prompt(prompt, deepseek_model, api_type, generation_model, width, height, seed, image_attachment_id, false);
        } else if (optimizeToEnglish) {
            ai_image_show_notice('正在翻译并优化提示词中...');
            ai_image_optimize_prompt(prompt, deepseek_model, api_type, generation_model, width, height, seed, image_attachment_id, true);
        } else {
            ai_image_show_notice('图片生成中...', false, true);
            ai_image_generate_image(prompt, api_type, generation_model, width, height, seed, image_attachment_id);
        }
    });

    // “请先登录”按钮点击事件
    $('.wp-ai-login-btn').on('click', function (e) {
        e.preventDefault();
        const $themeLoginBtn = $('.nav-login .signin-loader');
        if ($themeLoginBtn.length) {
            $themeLoginBtn.trigger('click');
        } else {
            window.location.href = $(this).attr('href');
        }
    });

    // 优化提示词函数
    function ai_image_optimize_prompt(prompt, deepseek_model, api_type, generation_model, width, height, seed, image_attachment_id, toEnglish = false) {
        $.ajax({
            url: wpAiImage.rest_url + 'optimize-prompt',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpAiImage.nonce);
            },
            data: JSON.stringify({
                prompt: prompt,
                deepseek_model: deepseek_model,
                optimize_to_english: toEnglish,
            }),
            contentType: 'application/json',
            success: function (response) {
                if (response.optimized_prompt) {
                    $('#prompt').val(response.optimized_prompt);
                    ai_image_show_notice('图片生成中...', false, true);
                    ai_image_generate_image(response.optimized_prompt, api_type, generation_model, width, height, seed, image_attachment_id);
                } else {
                    ai_image_show_notice('提示词优化失败');
                }
            },
            error: function (xhr) {
                const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '提示词优化失败';
                ai_image_show_notice(errorMessage);
            },
        });
    }

    // 更新图片总数显示
    function ai_image_update_total_count() {
        if (!wpAiImage.enable_image_count_display) return;

        const $countSpan = $('#total-image-count');
        if ($countSpan.length) {
            $.ajax({
                url: wpAiImage.rest_url + 'get-total-images',
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpAiImage.nonce);
                },
                success: function (response) {
                    if (response.total_images !== undefined) {
                        $countSpan.text(response.total_images);
                    }
                },
                error: function () {
                    console.log('Failed to update image count');
                }
            });
        }
    }

    // 生成图片
    function ai_image_generate_image(prompt, api_type, generation_model, width, height, seed, image_attachment_id) {
        const data = {
            prompt: prompt,
            api_type: api_type,
            generation_model: generation_model,
            width: width,
            height: height,
            seed: seed,
        };

        const $preview = $('#image-preview');
        const base64Data = $preview.data('base64');
        if (api_type === 'kolors' && base64Data) {
            data.image = base64Data;
        }

        $.ajax({
            url: wpAiImage.rest_url + 'generate-image',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpAiImage.nonce);
            },
            data: JSON.stringify(data),
            contentType: 'application/json',
            timeout: 70000,
            success: function (response) {
                if (response.image_url) {
                    const $img = $('<img>', { 
                        src: response.image_url,
                        width: 300,
                        height: 300
                    });
                    $img.on('load', function () {
                        $('#generated-image').html($img);
                        ai_image_hide_notice();
                        ai_image_update_total_count();
                        wpAiImage.remaining_images--;
                        if (wpAiImage.enable_10min_limit) {
                            wpAiImage.remaining_10min--;
                            if (wpAiImage.remaining_10min <= 0) {
                                wpAiImage.reset_time_10min = Math.floor(Date.now() / 1000) + 1800;
                            }
                        }
                        if (wpAiImage.is_logged_in || wpAiImage.allow_guest) {
                            $('.wp-ai-announcement').html(
                                $('.wp-ai-announcement')
                                    .html()
                                    .replace(/今日剩余: \d+ 张/, '今日剩余: ' + wpAiImage.remaining_images + ' 张')
                            );
                        }
                        ai_image_update_generate_button_state();
                    }).on('error', function () {
                        ai_image_show_notice('图片加载失败');
                    });
                    $('#generated-image').html($img);
                } else {
                    ai_image_show_notice('图片生成失败');
                }
            },
            error: function (xhr, status, error) {
                let errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '图片生成失败';
                if (status === 'timeout' || xhr.status === 0 || xhr.status === 500) {
                    errorMessage = '网络请求超时，请稍后重试';
                } else if (xhr.status === 429 && xhr.responseJSON && xhr.responseJSON.code === '10min_limit_exceeded') {
                    errorMessage = '10分钟内绘画数量已达上限，请等待30分钟后重试';
                    if (wpAiImage.enable_10min_limit) {
                        wpAiImage.remaining_10min = 0;
                        wpAiImage.reset_time_10min = Math.floor(Date.now() / 1000) + 1800;
                        ai_image_update_generate_button_state();
                    }
                }
                ai_image_show_notice(errorMessage);
                ai_image_update_generate_button_state();
            },
        });
    }

    // 下载按钮
    $(document).on('click', '.wp-ai-download-btn', function (e) {
        e.preventDefault();
        const url = $(this).data('url');
        const fileName = url.substring(url.lastIndexOf('/') + 1) || 'generated-image.jpg';

        fetch(url)
            .then((response) => response.blob())
            .then((blob) => {
                const blobUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch((error) => {
                ai_image_show_notice('图片下载失败，请稍后重试');
            });
    });

    // 生成同款按钮
    $(document).on('click', '.wp-ai-generate-same-btn', function (e) {
        e.preventDefault();
        const prompt = $(this).data('prompt');
        const seed = $(this).data('seed') || Math.floor(Math.random() * 4294967296);
        const isGeneratePage = $('.wp-ai-image-generate').length > 0;

        if (!wpAiImage.is_logged_in && !wpAiImage.allow_guest) {
            const $themeLoginBtn = $('.nav-login .signin-loader');
            if ($themeLoginBtn.length) {
                $themeLoginBtn.trigger('click');
            } else {
                window.location.href = wpAiImage.login_url;
            }
            return;
        }

        if (wpAiImage.remaining_images <= 0 && (wpAiImage.is_logged_in || wpAiImage.allow_guest)) {
            ai_image_show_notice('今日绘画额度已用完，请明天再试');
            return;
        }

        if (isGeneratePage) {
            $('#prompt').val(prompt);
            $('#generate-btn').click();
        } else {
            const url = wpAiImage.generate_page + '?prompt=' + encodeURIComponent(prompt) + '&seed=' + seed;
            window.location.href = url;
        }
    });

    // 显示自定义删除确认框
    function ai_image_show_delete_confirm(imageId, $imageItem) {
        $('.wp-ai-delete-confirm').remove();

        const $confirmBox = $(`
            <div class="wp-ai-delete-confirm">
                <div class="wp-ai-delete-confirm-content">
                    <p>确定要删除这张图片记录吗？<br>此操作只影响您的个人展示。</p>
                    <div class="wp-ai-delete-confirm-buttons">
                        <button class="wp-ai-delete-confirm-yes" data-image-id="${imageId}">确定</button>
                        <button class="wp-ai-delete-confirm-no">取消</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append($confirmBox);
        $confirmBox.fadeIn(200);

        $confirmBox.find('.wp-ai-delete-confirm-yes').on('click', function() {
            ai_image_delete_image(imageId, $imageItem);
            $confirmBox.fadeOut(200, function() {
                $(this).remove();
            });
        });

        $confirmBox.find('.wp-ai-delete-confirm-no').on('click', function() {
            $confirmBox.fadeOut(200, function() {
                $(this).remove();
            });
        });
    }

    // 执行删除操作
    function ai_image_delete_image(imageId, $imageItem) {
        $.ajax({
            url: wpAiImage.rest_url + 'delete-image',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpAiImage.nonce);
            },
            data: JSON.stringify({
                image_id: imageId
            }),
            contentType: 'application/json',
            success: function (response) {
                if (response.success) {
                    $imageItem.fadeOut(300, function() {
                        $(this).remove();
                    });
                    ai_image_show_notice('图片记录已删除');
                    ai_image_update_total_count();
                } else {
                    ai_image_show_notice('删除失败');
                }
            },
            error: function (xhr) {
                const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : '删除失败';
                ai_image_show_notice(errorMessage);
            }
        });
    }

    // 删除按钮点击事件
    $(document).on('click', '.wp-ai-delete-btn', function (e) {
        e.preventDefault();
        
        if (!wpAiImage.allow_user_delete_images) {
            ai_image_show_notice('删除功能未启用');
            return;
        }
        
        const $btn = $(this);
        const imageId = $btn.data('image-id');
        const $imageItem = $btn.closest('.wp-ai-image-item');
        
        ai_image_show_delete_confirm(imageId, $imageItem);
    });

    // 自定义提示框
    function ai_image_show_notice(message, isLoginPrompt = false, persist = false) {
        let $notice = $('#custom-notice');
        if ($notice.length === 0) {
            $notice = $('<div id="custom-notice" class="wp-ai-notice"></div>').appendTo('body');
        }

        $notice.removeClass('login-notice').addClass(isLoginPrompt ? 'login-notice' : '');

        if (isLoginPrompt) {
            $notice.html(message + ' <a href="' + wpAiImage.login_url + '" class="wp-ai-login-link">登录</a>');
        } else {
            $notice.text(message);
        }

        $notice.stop(true, true).fadeIn();

        if (!persist) {
            $notice.delay(3000).fadeOut(400, function () {
                $(this).removeClass('login-notice');
            });
        }
    }

    function ai_image_hide_notice() {
        $('#custom-notice').fadeOut();
    }

    // 初始化加载设置并更新按钮状态
    ai_image_load_settings();
    ai_image_update_image_upload_ui();
    ai_image_update_generate_button_state();
    ai_image_update_total_count();
});