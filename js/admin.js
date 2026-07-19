jQuery(document).ready(function($) {
    $('#delete-thumbnails').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(disableThumbnailsL10n.confirm_message)) {
            return;
        }

        var $button = $(this);
        var $wrapper = $('#delete-progress-wrapper');
        var $progressBar = $('#delete-progress-bar');
        var $status = $('#delete-status');
        
        $button.prop('disabled', true);
        $wrapper.show();
        $progressBar.css({
            'width': '0%',
            'background-color': '#2271b1'
        });
        $status.html(disableThumbnailsL10n.deleting_message)
               .css('color', '');

        var totalDeleted = 0;

        function doBatchDelete(page) {
            $.ajax({
                url: disableThumbnailsL10n.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_thumbnails',
                    nonce: disableThumbnailsL10n.nonce,
                    page: page
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        totalDeleted += data.deleted_batch;
                        
                        if (data.total_images > 0) {
                            var processed = Math.min(data.processed_count, data.total_images);
                            var progress = (processed / data.total_images) * 100;
                            $progressBar.css('width', progress + '%');
                            
                            var statusMsg = disableThumbnailsL10n.progress_message
                                .replace('%1$d', processed)
                                .replace('%2$d', data.total_images)
                                .replace('%3$d', totalDeleted);
                            
                            $status.html(statusMsg);
                        }

                        if (data.next_page) {
                            doBatchDelete(data.next_page);
                        } else {
                            $progressBar.css('background-color', 'green');
                            var finalMsg = disableThumbnailsL10n.success_message
                                .replace('%d', totalDeleted);
                            $status.html(finalMsg).css('color', 'green');
                            $button.prop('disabled', false);
                        }
                    } else {
                        $progressBar.css('background-color', 'red');
                        $status.html(response.data || disableThumbnailsL10n.error_message).css('color', 'red');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    $progressBar.css('background-color', 'red');
                    $status.html(disableThumbnailsL10n.error_message).css('color', 'red');
                    $button.prop('disabled', false);
                }
            });
        }

        doBatchDelete(1);
    });
});