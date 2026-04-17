jQuery(function ($) {
    $('#plseo-ping-btn').on('click', function () {
        var $button = $(this);
        var $message = $('#plseo-ping-msg');

        $button.prop('disabled', true);
        $message.text(plseoAdmin.i18n.pinging);

        $.post(plseoAdmin.ajaxUrl, {
            action: 'plseo_ping_sitemap',
            nonce: plseoAdmin.nonce
        }).done(function (response) {
            $message.text(response && response.success ? plseoAdmin.i18n.pingDone : plseoAdmin.i18n.pingFail);
        }).fail(function () {
            $message.text(plseoAdmin.i18n.pingFail);
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
});
