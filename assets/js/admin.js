jQuery(document).ready(function ($) {
    $('.ktn-import-btn').on('click', function (e) {
        e.preventDefault();

        var btn = $(this);
        var action = btn.data('action'); // 'import' or 'refresh'
        var postId = btn.data('post-id');
        var imdbId = $('#ktn_imdb_id').val().trim();
        var tmdbId = $('#ktn_tmdb_id').val().trim();
        var statusDiv = $('#ktn_import_status');

        if (!imdbId && !tmdbId) {
            statusDiv.html('<span style="color:red;">Please enter either an IMDb ID or a TMDB ID.</span>');
            return;
        }

        // Basic validation for IMDb if present
        if (imdbId && !/^tt\d{7,}$/.test(imdbId)) {
            statusDiv.html('<span style="color:red;">Invalid IMDb ID format. Must start with "tt".</span>');
            return;
        }

        // Basic validation for TMDB if present
        if (tmdbId && isNaN(tmdbId)) {
            statusDiv.html('<span style="color:red;">TMDB ID must be a numeric value.</span>');
            return;
        }

        btn.prop('disabled', true);
        statusDiv.html('<span style="color:blue; display: block; margin-top: 5px;">Importing from TMDB... Please wait.</span>');

        $.ajax({
            url: ktnAdminObj.ajax_url,
            type: 'POST',
            data: {
                action: 'ktn_import_movie',
                nonce: ktnAdminObj.nonce,
                post_id: postId,
                imdb_id: imdbId,
                tmdb_id: tmdbId
            },
            success: function (response) {
                btn.prop('disabled', false);
                if (response.success) {
                    statusDiv.html('<span style="color:green;">' + response.data.message + '</span>');
                    
                    // Update DOM fields directly for immediate feedback
                    if (response.data.title && $('#title').length) {
                        $('#title').val(response.data.title);
                        $('#title').trigger('input');
                    }
                    
                    if (response.data.content && typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                        tinyMCE.get('content').setContent(response.data.content);
                    } else if (response.data.content && $('#content').length) {
                        $('#content').val(response.data.content);
                    }

                    if (response.data.excerpt && $('#excerpt').length) {
                        $('#excerpt').val(response.data.excerpt);
                    }

                    setTimeout(function () {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    statusDiv.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                btn.prop('disabled', false);
                statusDiv.html('<span style="color:red;">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</span>');
            }
        });
    });
});
