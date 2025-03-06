(function($) {
    'use strict';

    // Media Upload Handling
    function initMediaUpload() {
        $('.nova-upload-image').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const wrapper = button.closest('.nova-media-wrapper');
            const input = wrapper.find('input[type="hidden"]');
            const preview = wrapper.find('.nova-preview-image');
            const removeButton = wrapper.find('.nova-remove-image');
            
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
                removeButton.show();
            });

            frame.open();
        });

        $('.nova-remove-image').on('click', function(e) {
            e.preventDefault();
            const wrapper = $(this).closest('.nova-media-wrapper');
            wrapper.find('input[type="hidden"]').val('');
            wrapper.find('.nova-preview-image').empty();
            $(this).hide();
        });
    }

    // Color Picker and Swatches
    function initColorPickers() {
        $('.nova-color-picker').wpColorPicker({
            change: function(event, ui) {
                $(this).val(ui.color.toString()).trigger('change');
            }
        });

        $('.nova-color-swatch').on('click', function() {
            const color = $(this).data('color');
            const field = $(this).closest('.nova-settings-section').find('.nova-color-picker');
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

    // Tab Switching
    function initTabs() {
        $('.nova-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Update active states
            $('.nova-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.nova-tab-content').removeClass('active');
            $('.nova-tab-content[data-tab="' + tab + '"]').addClass('active');
        });
    }

    // Alignment Buttons
    function initAlignmentButtons() {
        $('.nova-alignment-button').on('click', function() {
            const $button = $(this);
            const $group = $button.closest('.nova-button-group');
            const $input = $group.find('input[type="hidden"]');
            
            $group.find('.nova-alignment-button').removeClass('active');
            $button.addClass('active');
            $input.val($button.data('align')).trigger('change');
        });
    }

    // Initialize everything when document is ready
    $(document).ready(function() {
        initMediaUpload();
        initColorPickers();
        initOpacitySlider();
        initTabs();
        initAlignmentButtons();

        // Tab switching
        $('.nova-tab-button').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            // Update active states
            $('.nova-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.nova-tab-content').removeClass('active');
            $('.nova-tab-content[data-tab="' + tab + '"]').addClass('active');
        });

        // Save form handling
        $('.nova-cta-editor form').on('submit', function(e) {
            // Make sure TinyMCE content is updated
            if (typeof tinyMCE !== 'undefined') {
                tinyMCE.triggerSave();
            }
        });

        // Media uploader
        var mediaUploader;
        
        $('.nova-media-upload').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var previewContainer = button.siblings('.nova-media-preview');
            var hiddenInput = button.siblings('input[type="hidden"]');
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                hiddenInput.val(attachment.id);
                
                if (attachment.type === 'image') {
                    var img = $('<img>').attr('src', attachment.url);
                    previewContainer.html(img);
                }
            });
            
            mediaUploader.open();
        });

        // Log when form is about to submit
        $('form#post').on('submit', function(e) {
            console.log('Nova CTAs: Form submission detected');
            console.log('Nova CTAs: Form action:', $(this).attr('action'));
            console.log('Nova CTAs: Form method:', $(this).attr('method'));
            
            // Log all form fields
            var formData = new FormData(this);
            console.log('Nova CTAs: All form fields:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // Specifically log design settings
            console.log('Nova CTAs: Design Settings:');
            $('[name^="nova_cta_design"]').each(function() {
                console.log($(this).attr('name') + ': ' + $(this).val());
            });
            
            // Log nonce
            console.log('Nova CTAs: Nonce field:', $('#nova_cta_editor_nonce').val());
            console.log('Nova CTAs: Nonce present:', formData.has('nova_cta_editor_nonce'));
        });
    });

})(jQuery);