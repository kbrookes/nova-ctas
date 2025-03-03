(function($) {
    'use strict';

    // Media Upload Handling
    function initMediaUpload() {
        $('.ibcta-upload-image').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const wrapper = button.closest('.ibcta-media-wrapper');
            const input = wrapper.find('input[type="hidden"]');
            const preview = wrapper.find('.ibcta-preview-image');
            
            const frame = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                input.val(attachment.id);
                preview.html(`<img src="${attachment.url}">`);
            });

            frame.open();
        });

        $('.ibcta-remove-image').on('click', function(e) {
            e.preventDefault();
            const wrapper = $(this).closest('.ibcta-media-wrapper');
            wrapper.find('input[type="hidden"]').val('');
            wrapper.find('.ibcta-preview-image').empty();
        });
    }

    // Color Picker and Swatches
    function initColorPickers() {
        $('.ibcta-color-picker').wpColorPicker({
            change: function(event, ui) {
                $(this).val(ui.color.toString()).trigger('change');
            }
        });

        $('.ibcta-color-swatch').on('click', function() {
            const color = $(this).data('color');
            const field = $(this).closest('.ibcta-color-field').find('.ibcta-color-picker');
            field.val(color).trigger('change');
            field.wpColorPicker('color', color);
        });
    }

    // Opacity Range Slider
    function initOpacitySlider() {
        $('input[type="range"]').on('input', function() {
            $(this).next('.opacity-value').text($(this).val() + '%');
        });
    }

    // Collapsible Sections
    function initCollapsibleSections() {
        $('.ibcta-settings-section h3').on('click', function() {
            $(this).closest('.ibcta-settings-section').toggleClass('collapsed');
        });
    }

    // Initialize everything when document is ready
    jQuery(document).ready(function($) {
        // Tab switching
        $('.ibcta-tab-button').on('click', function() {
            const tab = $(this).data('tab');
            
            $('.ibcta-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.ibcta-tab-content').removeClass('active');
            $(`.ibcta-tab-content[data-tab="${tab}"]`).addClass('active');
        });

        initMediaUpload();
        initColorPickers();
        initOpacitySlider();
        initCollapsibleSections();
    });

    // Add to existing JavaScript
    $('.ibcta-alignment-button').on('click', function() {
        const $button = $(this);
        const $group = $button.closest('.ibcta-button-group');
        const $input = $group.find('input[type="hidden"]');
        
        $group.find('.ibcta-alignment-button').removeClass('active');
        $button.addClass('active');
        $input.val($button.data('align')).trigger('change');
    });

})(jQuery);