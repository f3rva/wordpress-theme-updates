(function($) {
    if (typeof acf === 'undefined') return;

    const { select, dispatch, subscribe } = wp.data;

    // Wait for the editor to be ready
    const checkACFValidation = () => {
        // ACF has a client-side API to check validation
        var valid = acf.validate.valid;
        
        // If ACF says it's invalid, we lock the post saving mechanism
        if (!valid) {
            dispatch('core/editor').lockPostSaving('acf-required-lock');
        } else {
            dispatch('core/editor').unlockPostSaving('acf-required-lock');
        }
    };

    // Run check on initial load
    $(document).ready(function() {
        // Run immediately
        checkACFValidation();
        
        // Re-run whenever ACF fields change
        acf.addAction('validation_complete', function( json, $form ){
            checkACFValidation();
        });
        
        // Also listen to general changes if needed (optional/advanced)
        // subscribe(() => { checkACFValidation(); });
    });

})(jQuery);
