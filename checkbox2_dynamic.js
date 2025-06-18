document.addEventListener('change', function (e) {
    const input = e.target;

    if (input.matches('input[type="checkbox"][data-addon-key]')) {
        const allParticipants = document.querySelectorAll('.participant-block');
        const selected = [];

        allParticipants.forEach((block, index) => {
            const participantIndex = index + 1;
            const participantData = {
                participant: participantIndex,
                addons: []
            };

            block.querySelectorAll('input[type="checkbox"][data-addon-key]:checked').forEach((checkbox) => {
                const key = checkbox.dataset.addonKey;
                const addon = participant_addons_data.addons.find(a => a.field_name === key);
                if (addon) {
                    participantData.addons.push({
                        label: addon.label,
                        field_name: addon.field_name,
                        price: addon.price,
                        selected: true
                    });
                }
            });

            selected.push(participantData);
        });

        // ✅ Send AJAX to update session
        jQuery.post(wc_ajax_data.ajax_url, {
            action: 'update_custom_addons',
            addons: selected
        }, function (response) {
            jQuery('body').trigger('update_checkout');
        });
    }
});

