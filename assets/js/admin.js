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

    function refreshListingLocation($box) {
        var inCentre = $box.find('input[name="dsc_in_shopping_centre"]:checked').val() === 'yes';
        var $centreFields = $box.find('[data-dsc-centre-fields]');
        var $standaloneFields = $box.find('[data-dsc-standalone-fields]');
        var $select = $box.find('[data-dsc-centre-select]');
        var addresses = $select.data('addresses') || {};
        var address = addresses[$select.val()] || 'Add the address to the Shopping Centre record.';

        $centreFields.toggle(inCentre);
        $standaloneFields.toggle(!inCentre);
        $select.prop('required', inCentre);
        $box.find('[data-dsc-required]').prop('required', inCentre);
        $('input[name="address"], input[name="_address"], textarea[name="address"], textarea[name="_address"]').prop('required', !inCentre);
        $box.find('[data-dsc-canonical-address]').text(address);
    }

    $(document).on('change', '[data-dsc-listing-location] input[name="dsc_in_shopping_centre"], [data-dsc-centre-select]', function () {
        refreshListingLocation($(this).closest('[data-dsc-listing-location]'));
    });

    $(function () {
        $('[data-dsc-listing-location]').each(function () {
            refreshListingLocation($(this));
        });
    });
})(jQuery);
