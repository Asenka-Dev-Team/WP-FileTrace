(function ($) {
    'use strict';

    let mediaFrame = null;

    const $attachmentId = $('#adt-attachment-id');
    const $manualUrl = $('#adt-manual-url');
    const $title = $('#adt-title');
    const $buttonText = $('#adt-button-text');
    const $preview = $('#adt-media-preview');
    const $mediaName = $('#adt-media-name');
    const $mediaUrl = $('#adt-media-url');
    const $clearMedia = $('#adt-clear-media');
    const $generate = $('#adt-generate');
    const $status = $('#adt-status');
    const $results = $('#adt-results');

    function copyText(text, $button) {
        if (!text) return;

        const done = function () {
            const original = $button.text();
            $button.text(ADTAdmin.strings.copied);
            window.setTimeout(function () {
                $button.text(original);
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

    $('#adt-select-media').on('click', function (event) {
        event.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: ADTAdmin.strings.selectFile,
            button: { text: ADTAdmin.strings.useFile },
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
        $generate.prop('disabled', true).text(ADTAdmin.strings.working);

        $.post(ADTAdmin.ajaxUrl, {
            action: 'adt_create_tracker',
            nonce: ADTAdmin.nonce,
            attachment_id: attachmentId,
            url: url,
            title: $title.val().trim(),
            button_text: $buttonText.val().trim()
        }).done(function (response) {
            if (!response || !response.success) {
                $status.addClass('is-error').text((response && response.data && response.data.message) || ADTAdmin.strings.genericError);
                return;
            }

            $('#adt-shortcode-output').text(response.data.shortcode);
            $('#adt-external-output').text(response.data.externalUrl);
            $results.prop('hidden', false);
            $status.addClass('is-success').text('Tracker ready.');
        }).fail(function (xhr) {
            let message = ADTAdmin.strings.genericError;
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            $status.addClass('is-error').text(message);
        }).always(function () {
            $generate.prop('disabled', false).text(ADTAdmin.strings.generate);
        });
    });

    $(document).on('click', '.adt-copy', function () {
        const target = document.getElementById($(this).data('target'));
        copyText(target ? target.textContent : '', $(this));
    });

    $(document).on('click', '.adt-copy-value', function () {
        copyText($(this).data('copy') || '', $(this));
    });

    $(document).on('submit', '.adt-delete-form', function (event) {
        if (!window.confirm(ADTAdmin.strings.confirmDelete)) {
            event.preventDefault();
        }
    });

    $(document).on('click', '.adt-edit-toggle', function () {
        const target = document.getElementById($(this).data('target'));
        if (!target) return;
        target.hidden = !target.hidden;
        $(this).attr('aria-expanded', target.hidden ? 'false' : 'true');
    });
})(jQuery);
