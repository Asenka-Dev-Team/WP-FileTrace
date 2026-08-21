(function ($) {
    'use strict';

    let mediaFrame = null;

    function getWrap() {
        return $('.wft-wrap').first();
    }

    function getCurrentState() {
        const url = new URL(window.location.href);
        return {
            tab: url.searchParams.get('tab') || 'tracked',
            orderby: url.searchParams.get('orderby') || 'created_at',
            order: url.searchParams.get('order') || 'desc',
            paged: parseInt(url.searchParams.get('paged') || '1', 10) || 1
        };
    }

    function ajaxErrorMessage(xhr) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }
        return WFTAdmin.strings.genericError;
    }

    function showAjaxNotice(message, type) {
        const $wrap = getWrap();
        if (!$wrap.length) return;

        $wrap.find('.wft-ajax-notice').remove();

        const noticeClass = type === 'success' ? 'notice-success' : (type === 'warning' ? 'notice-warning' : 'notice-error');
        const $notice = $('<div class="notice is-dismissible wft-ajax-notice"></div>')
            .addClass(noticeClass)
            .append($('<p></p>').text(message));

        const $tabs = $wrap.find('.wft-tabs').first();
        if ($tabs.length) {
            $tabs.after($notice);
        } else {
            $wrap.prepend($notice);
        }
    }

    function setWrapBusy(isBusy) {
        const $wrap = getWrap();
        $wrap.toggleClass('is-wft-ajax-busy', !!isBusy);
        $wrap.attr('aria-busy', isBusy ? 'true' : 'false');
    }

    function setButtonBusy($button, text) {
        if (!$button || !$button.length) return function () {};

        const originalHtml = $button.html();
        const originalDisabled = $button.prop('disabled');
        $button.data('wft-original-html', originalHtml);
        $button.prop('disabled', true);
        if (text) {
            $button.text(text);
        }

        return function () {
            if (!$.contains(document, $button.get(0))) return;
            $button.html(originalHtml);
            $button.prop('disabled', originalDisabled);
        };
    }

    function responseHtml(response) {
        return response && response.success && response.data && response.data.html
            ? response.data.html
            : '';
    }

    function replaceAdminView(html, url, historyMode) {
        if (!html) return false;

        const $holder = $('<div></div>').html(html);
        let $newWrap = $holder.children('.wft-wrap').first();
        if (!$newWrap.length) {
            $newWrap = $holder.find('.wft-wrap').first();
        }

        if (!$newWrap.length) {
            return false;
        }

        const $oldWrap = getWrap();
        if (!$oldWrap.length) {
            return false;
        }

        $oldWrap.replaceWith($newWrap);

        if (url) {
            if (historyMode === 'push') {
                window.history.pushState({ wft: true }, '', url);
            } else if (historyMode === 'replace') {
                window.history.replaceState({ wft: true }, '', url);
            }
        }

        updateBulkSelectionState();

        const $newRow = $('.wft-new-row').first();
        if ($newRow.length) {
            window.setTimeout(function () {
                $newRow.removeClass('wft-new-row');
            }, 2600);
        }

        return true;
    }

    function postAdmin(data, options) {
        options = options || {};
        data = $.extend({}, data, { nonce: WFTAdmin.nonce });

        setWrapBusy(true);
        const restoreButton = setButtonBusy(options.button || $(), options.busyText || '');

        const request = $.ajax({
            url: WFTAdmin.ajaxUrl,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (response) {
            if (!response || !response.success) {
                const message = response && response.data && response.data.message
                    ? response.data.message
                    : WFTAdmin.strings.genericError;
                showAjaxNotice(message, 'error');
                return;
            }

            if (response.data && response.data.html) {
                if (!replaceAdminView(response.data.html, response.data.url || '', options.historyMode || 'replace')) {
                    showAjaxNotice(WFTAdmin.strings.genericError, 'error');
                }
            }

            if (typeof options.onSuccess === 'function') {
                options.onSuccess(response);
            }
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                showAjaxNotice(ajaxErrorMessage(xhr), 'error');
            }
        }).always(function () {
            restoreButton();
            setWrapBusy(false);
        });

        return request;
    }

    function loadViewFromUrl(targetUrl, historyMode) {
        const url = new URL(targetUrl, window.location.href);
        const data = {
            action: 'wft_render_admin_view',
            tab: url.searchParams.get('tab') || 'tracked',
            orderby: url.searchParams.get('orderby') || 'created_at',
            order: url.searchParams.get('order') || 'desc',
            paged: url.searchParams.get('paged') || '1'
        };

        postAdmin(data, {
            historyMode: historyMode || 'push'
        });
    }

    function getSubmitter(event, form) {
        const nativeSubmitter = event.originalEvent && event.originalEvent.submitter
            ? event.originalEvent.submitter
            : null;
        const storedSubmitter = $(form).data('wft-submitter') || null;
        $(form).removeData('wft-submitter');
        return nativeSubmitter || storedSubmitter;
    }

    function serializeForm(form, submitter) {
        const data = $(form).serializeArray();

        if (submitter && submitter.name) {
            data.push({ name: submitter.name, value: submitter.value || '' });
        }

        data.push({ name: 'nonce', value: WFTAdmin.nonce });
        return data;
    }

    function submitFormAjax(form, submitter, options) {
        options = options || {};
        const $form = $(form);
        let data = serializeForm(form, submitter);

        if ($form.hasClass('wft-bulk-delete-form')) {
            data = data.filter(function (item) {
                return item.name !== 'tracker_ids[]';
            });
            $('.wft-row-checkbox:checked').each(function () {
                data.push({ name: 'tracker_ids[]', value: $(this).val() });
            });
        }

        setWrapBusy(true);

        const $buttons = $form.find('button[type="submit"], input[type="submit"]');
        const originalButtons = [];
        $buttons.each(function () {
            originalButtons.push({
                el: this,
                html: $(this).html(),
                disabled: $(this).prop('disabled')
            });
            $(this).prop('disabled', true);
        });

        if (submitter && options.busyText) {
            $(submitter).text(options.busyText);
        }

        $.ajax({
            url: WFTAdmin.ajaxUrl,
            method: 'POST',
            data: $.param(data),
            dataType: 'json'
        }).done(function (response) {
            if (!response || !response.success) {
                const message = response && response.data && response.data.message
                    ? response.data.message
                    : WFTAdmin.strings.genericError;
                showAjaxNotice(message, 'error');
                return;
            }

            if (!replaceAdminView(response.data.html || '', response.data.url || '', options.historyMode || 'replace')) {
                showAjaxNotice(WFTAdmin.strings.genericError, 'error');
            }
        }).fail(function (xhr) {
            showAjaxNotice(ajaxErrorMessage(xhr), 'error');
        }).always(function () {
            originalButtons.forEach(function (button) {
                if ($.contains(document, button.el)) {
                    $(button.el).html(button.html).prop('disabled', button.disabled);
                }
            });
            setWrapBusy(false);
        });
    }

    function copyText(text, $button) {
        if (!text) return;

        const done = function () {
            const originalHtml = $button.html();
            $button.text(WFTAdmin.strings.copied);
            window.setTimeout(function () {
                if ($.contains(document, $button.get(0))) {
                    $button.html(originalHtml);
                }
            }, 1200);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
            return;
        }

        fallbackCopy(text, done);
    }

    function fallbackCopy(text, done) {
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
        const $deleteSelected = $('#wft-delete-selected');
        const $selectAll = $('#wft-select-all');

        $deleteSelected.prop('disabled', selectedCount === 0);

        if (!$selectAll.length) return;

        $selectAll.prop('checked', rowCount > 0 && selectedCount === rowCount);
        $selectAll.prop('indeterminate', selectedCount > 0 && selectedCount < rowCount);
    }

    $(document).on('click', '.wft-tab, .wft-sort-button, .wft-pagination a', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.which === 2) {
            return;
        }

        const href = $(this).attr('href');
        if (!href) return;

        event.preventDefault();
        loadViewFromUrl(href, 'push');
    });

    $(document).on('click', '#wft-select-media', function (event) {
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
            $('#wft-attachment-id').val(attachment.id);
            $('#wft-manual-url').val('');
            $('#wft-media-name').text(attachment.title || attachment.filename || ('Media #' + attachment.id));
            $('#wft-media-url').text(attachment.url || '');
            $('#wft-media-preview').prop('hidden', false);
            $('#wft-clear-media').prop('hidden', false);

            if (!$('#wft-title').val()) {
                $('#wft-title').val(attachment.title || attachment.filename || '');
            }
        });

        mediaFrame.open();
    });

    $(document).on('click', '#wft-clear-media', function () {
        $('#wft-attachment-id').val('');
        $('#wft-media-name').text('');
        $('#wft-media-url').text('');
        $('#wft-media-preview').prop('hidden', true);
        $('#wft-clear-media').prop('hidden', true);
    });

    $(document).on('input', '#wft-manual-url', function () {
        if ($(this).val().trim()) {
            $('#wft-attachment-id').val('');
            $('#wft-media-preview').prop('hidden', true);
            $('#wft-clear-media').prop('hidden', true);
        }
    });

    $(document).on('click', '#wft-generate', function () {
        const $button = $(this);
        const attachmentId = $('#wft-attachment-id').val();
        const url = $('#wft-manual-url').val().trim();
        const $status = $('#wft-status');

        if (!attachmentId && !url) {
            $status.removeClass('is-success').addClass('is-error').text('Select a media file or enter a URL first.');
            return;
        }

        $status.removeClass('is-error is-success').text('');

        postAdmin({
            action: 'wft_create_tracker',
            attachment_id: attachmentId,
            url: url,
            title: $('#wft-title').val().trim(),
            button_text: $('#wft-button-text').val().trim()
        }, {
            button: $button,
            busyText: WFTAdmin.strings.working,
            historyMode: 'replace'
        });
    });

    $(document).on('click', '.wft-copy-value', function () {
        copyText($(this).data('copy') || '', $(this));
    });

    $(document).on('change', '#wft-select-all', function () {
        $('.wft-row-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkSelectionState();
    });

    $(document).on('change', '.wft-row-checkbox', updateBulkSelectionState);

    $(document).on('click', '.wft-edit-toggle', function () {
        const target = document.getElementById($(this).data('target'));
        if (!target) return;

        target.hidden = !target.hidden;
        $(this).attr('aria-expanded', target.hidden ? 'false' : 'true');

        if (!target.hidden) {
            $(target).find('input:visible').first().trigger('focus');
        }
    });

    $(document).on('click', '.wft-wrap form button[type="submit"], .wft-wrap form input[type="submit"]', function () {
        if (this.form) {
            $(this.form).data('wft-submitter', this);
        }
    });

    $(document).on('submit', '.wft-edit-form', function (event) {
        event.preventDefault();
        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.saving
        });
    });

    $(document).on('submit', '.wft-delete-form', function (event) {
        event.preventDefault();
        if (!window.confirm(WFTAdmin.strings.confirmDelete)) return;

        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.deleting
        });
    });

    $(document).on('submit', '.wft-bulk-delete-form', function (event) {
        event.preventDefault();

        if ($('.wft-row-checkbox:checked').length === 0) {
            showAjaxNotice('Select at least one tracked file first.', 'warning');
            return;
        }

        if (!window.confirm(WFTAdmin.strings.confirmDeleteSelected)) return;

        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.deleting
        });
    });

    $(document).on('submit', '.wft-delete-all-form', function (event) {
        event.preventDefault();
        if (!window.confirm(WFTAdmin.strings.confirmDeleteAll)) return;

        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.deleting
        });
    });

    $(document).on('submit', '.wft-test-form', function (event) {
        event.preventDefault();
        if (!window.confirm(WFTAdmin.strings.confirmTest)) return;

        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.testing
        });
    });

    $(document).on('submit', '.wft-analytics-form', function (event) {
        event.preventDefault();

        const submitter = getSubmitter(event, this);
        submitFormAjax(this, submitter, {
            busyText: WFTAdmin.strings.saving
        });
    });

    $(document).on('submit', '.wft-update-check-form', function (event) {
        event.preventDefault();

        submitFormAjax(this, getSubmitter(event, this), {
            busyText: WFTAdmin.strings.checking
        });
    });

    window.addEventListener('popstate', function () {
        loadViewFromUrl(window.location.href, 'none');
    });

    updateBulkSelectionState();
})(jQuery);
