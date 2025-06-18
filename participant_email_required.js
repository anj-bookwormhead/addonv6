

jQuery(function($) {
    // Add asterisk to participant email labels
    document.querySelectorAll('[name^="participant_"][name$="_email"]').forEach(input => {
        const label = input.closest('p').querySelector('label');
        if (label && !label.innerHTML.includes('*')) {
            label.innerHTML += ' <span style="color:red">*</span>';
        }
    });

    // WooCommerce checkout validation
    $('form.checkout').on('checkout_place_order', function() {
        let error = false;
        let emailInputs = document.querySelectorAll('[name^="participant_"][name$="_email"]');

        emailInputs.forEach(input => {
            if (!input.value.trim()) {
                error = true;
                input.classList.add('woocommerce-invalid');
                input.classList.remove('woocommerce-validated');
            } else {
                input.classList.remove('woocommerce-invalid');
                input.classList.add('woocommerce-validated');
            }
        });

        if (error) {
            if ($('.woocommerce-error').length === 0) {
                $('.woocommerce-notices-wrapper').html('<ul class="woocommerce-error"><li>Please fill the required Participant Email address(es).</li></ul>');
            }
            return false;
        }

        return true;
    });
});




