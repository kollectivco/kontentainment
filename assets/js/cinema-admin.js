(function($) {
    'use strict';

    $(function() {
        // Media Uploader Handle
        $('.ktn-upload-btn').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var target = button.data('target');
            var custom_uploader = wp.media({
                title: 'Select Cinema Media',
                button: {
                    text: 'Use this Image'
                },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $(target).val(attachment.url);
                
                // Update Preview
                var preview = button.closest('.ktn-media-field-wrapper').find('.ktn-preview');
                preview.html('<img src="' + attachment.url + '" style="max-width:100%; height:auto; display:block; margin:0 auto;">');
            }).open();
        });

        // Live Preview for URL input
        $('input[name="ktn_cinema_logo"], input[name="ktn_cinema_cover_image"]').on('change input', function() {
            var url = $(this).val();
            var preview = $(this).closest('.ktn-media-field-wrapper').find('.ktn-preview');
            if (url) {
                preview.html('<img src="' + url + '" style="max-width:100%; height:auto; display:block; margin:0 auto;">');
            } else {
                preview.html('<span style="font-size:11px; color:#999;">No Preview</span>');
            }
        });

        // City & Area logic (Logically dependent dropdowns)
        $('#ktn-cinema-city').on('change', function() {
            var cityName = $(this).val();
            var areaSelect = $('#ktn-cinema-area');
            
            if (!cityName) {
                areaSelect.html('<option value="">Select Area</option>');
                return;
            }

            // Show loading state
            areaSelect.html('<option value="">Loading areas...</option>');

            $.ajax({
                url: ktn_cinema_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktn_get_areas_by_city',
                    city_name: cityName,
                    nonce: ktn_cinema_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<option value="">Select Area</option>';
                        $.each(response.data, function(i, area) {
                            html += '<option value="' + area.name + '">' + area.name + '</option>';
                        });
                        areaSelect.html(html);
                    } else {
                        areaSelect.html('<option value="">Error loading areas</option>');
                    }
                }
            });
        });
    });

})(jQuery);
