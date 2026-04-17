jQuery(function ($) {
    $('textarea[maxlength], input[maxlength]').on('input', function () {
        var max = parseInt($(this).attr('maxlength'), 10);
        var value = $(this).val() || '';
        $(this).attr('data-count', value.length + '/' + max);
    });
});
