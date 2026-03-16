jQuery(document).ready(function ($) {
    $('.ktn-import-btn').on('click', function (e) {
        e.preventDefault();

        var btn = $(this);
        var action = btn.data('action'); // 'import' or 'refresh'
        var postId = btn.data('post-id');
        var imdbId = $('#ktn_imdb_id').val();
        var statusDiv = $('#ktn_import_status');

        if (!imdbId) {
            statusDiv.html('<span style="color:red;">Please enter an IMDb ID first.</span>');
            return;
        }

        btn.prop('disabled', true);
        statusDiv.html('<span style="color:blue;">Importing/refreshing from TMDB... Please wait.</span>');

        $.ajax({
            url: ktnAdminObj.ajax_url,
            type: 'POST',
            data: {
                action: 'ktn_import_movie',
                nonce: ktnAdminObj.nonce,
                post_id: postId,
                imdb_id: imdbId
            },
            success: function (response) {
                btn.prop('disabled', false);
                if (response.success) {
                    statusDiv.html('<span style="color:green;">' + response.data.message + '</span>');
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
