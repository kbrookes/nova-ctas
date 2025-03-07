(function($) {
    'use strict';

    // Media Upload Handling
    function initMediaUpload() {
        $('.nova-media-upload').on('click', function(e) {
            e.preventDefault();
            console.log('Nova CTAs: Media upload button clicked');
            
            var button = $(this);
            var wrapper = button.closest('.nova-media-wrapper');
            var input = wrapper.find('input[type="hidden"]');
            var preview = wrapper.find('.nova-media-preview');
            var removeButton = wrapper.find('.nova-remove-image');
            
            var frame = wp.media({
                title: 'Select or Upload Media',
                button: {
                    text: 'Use this media'
                },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                console.log('Nova CTAs: Media selected:', attachment);
                
                input.val(attachment.id).trigger('change');
                preview.html('<img src="' + attachment.url + '" alt="">');
                removeButton.show();
            });

            frame.open();
        });

        // Handle remove image button
        $('.nova-remove-image').on('click', function(e) {
            e.preventDefault();
            console.log('Nova CTAs: Remove image button clicked');
            
            var button = $(this);
            var wrapper = button.closest('.nova-media-wrapper');
            var input = wrapper.find('input[type="hidden"]');
            var preview = wrapper.find('.nova-media-preview');
            
            input.val('').trigger('change');
            preview.empty();
            button.hide();
        });
    }

    // Color Picker and Swatches
    function initColorPickers() {
        $('.nova-color-picker').wpColorPicker({
            change: function(event, ui) {
                console.log('Nova CTAs: Color picker changed:', $(this).attr('name'), ui.color.toString());
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

    // Range Slider
    function initRangeInputs() {
        $('input[type="range"]').on('input change', function() {
            console.log('Nova CTAs: Range input changed:', $(this).attr('name'), $(this).val());
            var suffix = $(this).next('.range-value').text().includes('%') ? '%' : 'px';
            $(this).next('.range-value').text($(this).val() + suffix);
            $(this).trigger('change');
        });
    }

    // Tab Switching
    function initTabs() {
        $('.nova-tab').on('click', function(e) {
            e.preventDefault();
            console.log('Nova CTAs: Tab clicked:', $(this).data('tab'));
            
            var tab = $(this).data('tab');
            $('.nova-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.nova-tab-content').removeClass('active');
            $('.nova-tab-content[data-tab="' + tab + '"]').addClass('active');
            
            // Store active tab in sessionStorage
            sessionStorage.setItem('nova_cta_active_tab', tab);
        });
        
        // Restore active tab from sessionStorage
        var activeTab = sessionStorage.getItem('nova_cta_active_tab');
        if (activeTab) {
            $('.nova-tab[data-tab="' + activeTab + '"]').trigger('click');
        }
    }

    // Form Handling
    function initFormHandling() {
        var $form = $('#post');
        var formModified = false;

        // Track form changes
        $form.on('change', 'input, select, textarea', function() {
            formModified = true;
            console.log('Nova CTAs: Form modified, field:', $(this).attr('name'));
        });

        // Before form submission
        $form.on('submit', function(e) {
            if (!formModified) {
                console.log('Nova CTAs: Form not modified, proceeding with normal submission');
                return true;
            }

            console.log('Nova CTAs: Form submission started');
            
            // Log all form data
            var formData = new FormData(this);
            console.log('Nova CTAs: Form data entries:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Ensure content is updated
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('nova_cta_content');
                if (editor) {
                    editor.save();
                    console.log('Nova CTAs: TinyMCE content saved');
                }
            }

            // Save form state to sessionStorage
            var formState = {};
            $('[name^="nova_cta_"]').each(function() {
                formState[$(this).attr('name')] = $(this).val();
            });
            sessionStorage.setItem('nova_cta_form_state', JSON.stringify(formState));
            console.log('Nova CTAs: Form state saved to sessionStorage');

            return true;
        });

        // Restore form state if available
        var savedState = sessionStorage.getItem('nova_cta_form_state');
        if (savedState) {
            try {
                var formState = JSON.parse(savedState);
                console.log('Nova CTAs: Restoring form state from sessionStorage');
                
                for (var key in formState) {
                    var $field = $('[name="' + key + '"]');
                    if ($field.length) {
                        $field.val(formState[key]).trigger('change');
                    }
                }
                
                sessionStorage.removeItem('nova_cta_form_state');
            } catch (e) {
                console.error('Nova CTAs: Error restoring form state:', e);
            }
        }
    }

    // Opacity Range Slider
    function initOpacitySlider() {
        $('input[type="range"]').on('input', function() {
            $(this).next('.opacity-value').text($(this).val() + '%');
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
        console.log('Nova CTAs: Admin JS loaded');

        initMediaUpload();
        initColorPickers();
        initOpacitySlider();
        initRangeInputs();
        initTabs();
        initAlignmentButtons();
        initFormHandling();
    });

})(jQuery);