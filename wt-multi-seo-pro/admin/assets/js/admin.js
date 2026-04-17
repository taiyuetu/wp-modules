jQuery(function ($) {
    var $button = $('#plseo-ping-btn');
    var $message = $('#plseo-ping-msg');

    function setPingMessage(message, type) {
        $message
            .text(message || '')
            .removeClass('is-success is-error')
            .addClass(type ? 'is-' + type : '');
    }

    if ($button.length) {
        $button.on('click', function () {
            $button.prop('disabled', true);
            setPingMessage(plseoAdmin.i18n.pinging, null);

            $.post(plseoAdmin.ajaxUrl, {
                action: 'plseo_ping_sitemap',
                nonce: plseoAdmin.nonce
            }).done(function (response) {
                if (response && response.success) {
                    setPingMessage(plseoAdmin.i18n.pingDone, 'success');
                    return;
                }

                setPingMessage(plseoAdmin.i18n.pingFail, 'error');
            }).fail(function () {
                setPingMessage(plseoAdmin.i18n.pingFail, 'error');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    }

    $('.plseo-wrap form').on('submit', function () {
        var $submit = $(this).find('.submit .button-primary');

        if ($submit.length) {
            $submit.prop('disabled', true);
            window.setTimeout(function () {
                $submit.prop('disabled', false);
            }, 1500);
        }
    });

    function extractFieldName(name) {
        var match = (name || '').match(/\[([a-z_]+)\]$/i);
        return match ? match[1] : '';
    }

    $(document).on('click', '.plseo-copy-default-lang', function () {
        var $button = $(this);
        var $block = $button.closest('.plseo-cpt-archive-block');
        var targetLang = $button.data('target-lang');
        var defaultLang = $block.data('default-lang');

        if (!targetLang || !defaultLang) {
            return;
        }

        var $sourceTable = $block.find('table.form-table[data-lang="' + defaultLang + '"]');
        var $targetTable = $block.find('table.form-table[data-lang="' + targetLang + '"]');

        if (!$sourceTable.length || !$targetTable.length) {
            return;
        }

        var sourceValues = {};
        $sourceTable.find('input[name], textarea[name]').each(function () {
            var $field = $(this);
            var fieldName = extractFieldName($field.attr('name'));
            if (!fieldName) {
                return;
            }

            if ($field.is(':checkbox')) {
                sourceValues[fieldName] = $field.is(':checked');
                return;
            }

            sourceValues[fieldName] = $field.val();
        });

        $targetTable.find('input[name], textarea[name]').each(function () {
            var $field = $(this);
            var fieldName = extractFieldName($field.attr('name'));
            if (!fieldName || sourceValues[fieldName] === undefined) {
                return;
            }

            if ($field.is(':checkbox')) {
                $field.prop('checked', !!sourceValues[fieldName]).trigger('change');
                return;
            }

            $field.val(sourceValues[fieldName]).trigger('input').trigger('change');
        });
    });
});
