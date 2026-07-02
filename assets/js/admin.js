(function ($) {
    'use strict';

    function setPreview($wrap, imageId, imageUrl) {
        $wrap.find('#dsc-image-id').val(imageId || '');
        $wrap.find('.dsc-term-image-preview').html(imageUrl ? '<img src="' + imageUrl + '" alt="" style="max-width:160px;height:auto;">' : '');
    }

    $(document).on('click', '.dsc-select-image', function (event) {
        event.preventDefault();

        var $wrap = $(this).closest('.term-dsc-image-wrap');
        var frame = wp.media({
            title: 'Select Shopping Centre Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
            setPreview($wrap, attachment.id, imageUrl);
        });

        frame.open();
    });

    $(document).on('click', '.dsc-remove-image', function (event) {
        event.preventDefault();
        setPreview($(this).closest('.term-dsc-image-wrap'), '', '');
    });
})(jQuery);
