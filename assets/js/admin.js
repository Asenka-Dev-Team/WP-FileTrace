(function ($) {
    'use strict';

    let mediaFrame = null;

    const $attachmentId = $('#wft-attachment-id');
    const $manualUrl = $('#wft-manual-url');
    const $title = $('#wft-title');
    const $buttonText = $('#wft-button-text');
    const $preview = $('#wft-media-preview');
    const $mediaName = $('#wft-media-name');
    const $mediaUrl = $('#wft-media-url');
    const $clearMedia = $('#wft-clear-media');
    const $generate = $('#wft-generate');
    const $status = $('#wft-status');
    const $selectAll = $('#wft-select-all');
    const $deleteSelected = $('#wft-delete-selected');

    function copyText(text, $button) {
        if (!text) return;

        const done = function () {
            const originalHtml = $button.html();
            $button.text(WFTAdmin.strings.copied);
            window.setTimeout(function () {
                $button.html(originalHtml);
            }, 1200);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }

        const $temp = $('<textarea>')
            .css({ position: 'fixed', left: '-9999px', top: '-9999px' })
            .val(text)
            .appendTo('body')
            .trigger('select');

        try {
            document.execCommand('copy');
            done();
        } finally {
            $temp.remove();
        }
    }

    function updateBulkSelectionState() {
        const $rowCheckboxes = $('.wft-row-checkbox');
        const selectedCount = $rowCheckboxes.filter(':checked').length;
        const rowCount = $rowCheckboxes.length;

        $deleteSelected.prop('disabled', selectedCount === 0);

        if (!$selectAll.length) return;

        $selectAll.prop('checked', rowCount > 0 && selectedCount === rowCount);
        $selectAll.prop('indeterminate', selectedCount > 0 && selectedCount < rowCount);
    }

    $('#wft-select-media').on('click', function (event) {
        event.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: WFTAdmin.strings.selectFile,
            button: { text: WFTAdmin.strings.useFile },
            multiple: false
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $attachmentId.val(attachment.id);
            $manualUrl.val('');
            $mediaName.text(attachment.title || attachment.filename || ('Media #' + attachment.id));
            $mediaUrl.text(attachment.url || '');
            $preview.prop('hidden', false);
            $clearMedia.prop('hidden', false);

            if (!$title.val()) {
                $title.val(attachment.title || attachment.filename || '');
            }
        });

        mediaFrame.open();
    });

    $clearMedia.on('click', function () {
        $attachmentId.val('');
        $mediaName.text('');
        $mediaUrl.text('');
        $preview.prop('hidden', true);
        $clearMedia.prop('hidden', true);
    });

    $manualUrl.on('input', function () {
        if ($(this).val().trim()) {
            $attachmentId.val('');
            $preview.prop('hidden', true);
            $clearMedia.prop('hidden', true);
        }
    });

    $generate.on('click', function () {
        const attachmentId = $attachmentId.val();
        const url = $manualUrl.val().trim();

        if (!attachmentId && !url) {
            $status.removeClass('is-success').addClass('is-error').text('Select a media file or enter a URL first.');
            return;
        }

        $status.removeClass('is-error is-success').text('');
        $generate.prop('disabled', true).text(WFTAdmin.strings.working);

        $.post(WFTAdmin.ajaxUrl, {
            action: 'wft_create_tracker',
            nonce: WFTAdmin.nonce,
            attachment_id: attachmentId,
            url: url,
            title: $title.val().trim(),
            button_text: $buttonText.val().trim()
        }).done(function (response) {
            if (!response || !response.success) {
                $status.addClass('is-error').text((response && response.data && response.data.message) || WFTAdmin.strings.genericError);
                return;
            }

            $status.addClass('is-success').text(WFTAdmin.strings.created);
            const page = parseInt(response.data.page, 10) || 1;
            const target = WFTAdmin.pageUrl
                + '&orderby=created_at&order=desc&paged=' + encodeURIComponent(page) + '&wft_created='
                + encodeURIComponent(response.data.id);
            window.location.assign(target);
        }).fail(function (xhr) {
            let message = WFTAdmin.strings.genericError;
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            $status.addClass('is-error').text(message);
        }).always(function () {
            $generate.prop('disabled', false).text(WFTAdmin.strings.generate);
        });
    });

    $(document).on('click', '.wft-copy-value', function () {
        copyText($(this).data('copy') || '', $(this));
    });

    $selectAll.on('change', function () {
        $('.wft-row-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkSelectionState();
    });

    $(document).on('change', '.wft-row-checkbox', updateBulkSelectionState);

    $(document).on('submit', '.wft-delete-form', function (event) {
        if (!window.confirm(WFTAdmin.strings.confirmDelete)) {
            event.preventDefault();
        }
    });

    $(document).on('submit', '.wft-bulk-delete-form', function (event) {
        if ($('.wft-row-checkbox:checked').length === 0 || !window.confirm(WFTAdmin.strings.confirmDeleteSelected)) {
            event.preventDefault();
        }
    });

    $(document).on('submit', '.wft-delete-all-form', function (event) {
        if (!window.confirm(WFTAdmin.strings.confirmDeleteAll)) {
            event.preventDefault();
        }
    });

    $(document).on('submit', '.wft-test-form', function (event) {
        if (!window.confirm(WFTAdmin.strings.confirmTest)) {
            event.preventDefault();
        }
    });

    $(document).on('click', '.wft-edit-toggle', function () {
        const target = document.getElementById($(this).data('target'));
        if (!target) return;
        target.hidden = !target.hidden;
        $(this).attr('aria-expanded', target.hidden ? 'false' : 'true');
    });

    updateBulkSelectionState();
})(jQuery);
