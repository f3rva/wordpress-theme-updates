(function($) {
    if (typeof acf === 'undefined') return;

    const { select, dispatch } = wp.data;

    // ACF validation check
    const checkACFValidation = () => {
        const validation = acf.validate || acf.validation;
        if (!validation || typeof validation.valid === 'undefined') {
            return;
        }

        if (!validation.valid) {
            dispatch('core/editor').lockPostSaving('acf-required-lock');
        } else {
            dispatch('core/editor').unlockPostSaving('acf-required-lock');
        }
    };

    // Run check when ACF and DOM are ready
    $(document).ready(function() {
        if (typeof acf.addAction === 'function') {
            acf.addAction('ready', checkACFValidation);
            acf.addAction('validation_complete', function(json, $form) {
                checkACFValidation();
            });
        } else {
            checkACFValidation();
        }
    });
})(jQuery);
