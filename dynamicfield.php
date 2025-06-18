<?php
/**
 * Plugin Name: Dynamic Participant Fields for WooCommerce Checkout
 * Description: Adds dynamic participant fields to WooCommerce checkout, admin, and emails based on product quantity.
 * Author: Performance Driving Australia
 * Version: 1.0
 */

add_action('wp_enqueue_scripts', function () {
    if (!is_checkout()) return;

    $global_addons = [];
    $selected_addons = [];

    // ✅ Step 1: Loop through the cart and collect product category IDs
    $cart_product_categories = [];
    foreach (WC()->cart->get_cart() as $item) {
        $product_id = $item['product_id'];
        $terms = get_the_terms($product_id, 'product_cat');
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $cart_product_categories[] = $term->term_id;
            }
        }

        // ✅ Step 2: Track previously selected add-ons by their sanitized key
        if (!empty($item['addons'])) {
            foreach ($item['addons'] as $addon) {
                $label = isset($addon['value']) ? $addon['value'] : $addon['name'];
                $field_name = sanitize_title($label);
                $selected_addons[$field_name] = true;
            }
        }
    }
    $cart_product_categories = array_unique($cart_product_categories);

    // ✅ Step 3: Fetch global add-on groups
    $addon_groups = get_posts([
        'post_type' => 'global_product_addon',
        'numberposts' => -1,
    ]);

    foreach ($addon_groups as $addon_group) {
        // ✅ Step 4: Get categories this add-on group is assigned to (via taxonomy)
        $assigned_categories = wp_get_post_terms($addon_group->ID, 'product_cat', ['fields' => 'ids']);

        // ✅ Step 5: Only continue if categories match
        if (!empty($assigned_categories)) {
            $intersect = array_intersect($assigned_categories, $cart_product_categories);
            if (empty($intersect)) {
                continue; // Skip add-on group not assigned to any product in the cart
            }
        }

        // ✅ Step 6: Pull all checkbox fields from the add-on group
        $fields = get_post_meta($addon_group->ID, '_product_addons', true);
        if (!$fields || !is_array($fields)) continue;

        foreach ($fields as $field) {
            if (
                isset($field['type'], $field['options']) &&
                $field['type'] === 'checkbox' &&
                is_array($field['options'])
            ) {
                foreach ($field['options'] as $option) {
                    if (!isset($option['label'])) continue;

                    $option_label = $option['label'];
                    $field_name = sanitize_title($option_label);
                    $price = isset($option['price']) ? floatval($option['price']) : 0;

                    $global_addons[] = [
                        'label' => $option_label,
                        'field_name' => $field_name,
                        'price' => $price,
                        'selected' => isset($selected_addons[$field_name]),
                    ];
                }
            }
        }
    }

    // ✅ Step 7: Inject localized JS with filtered add-ons
    wp_register_script('participant-addons', false);
    wp_enqueue_script('participant-addons');

    wp_localize_script('participant-addons', 'participant_addons_data', [
        'addons' => $global_addons,
        'selected_addons' => array_values(array_filter($global_addons, fn($addon) => !empty($addon['selected']))),
    ]);
});




// Dynamic Fields
// 1. Output participant field container
add_action('woocommerce_before_order_notes', 'custom_dynamic_participant_fields');
function custom_dynamic_participant_fields($checkout) {
    echo '<div id="participant-fields-wrapper"><h3>Participant Details</h3></div>';
}

// 2. Enqueue and localize add-on data
add_action('wp_enqueue_scripts', function () {
    // your wp_enqueue_scripts logic here...
});

// ✅ 3. Render participant fields on frontend in the footer (AFTER scripts are ready)
add_action('wp_footer', function () {
    if (!is_checkout()) return;

    $qty = array_sum(array_map(fn($item) => $item['quantity'], WC()->cart->get_cart()));
    $qty = max($qty, 1);
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function generateFields(qty) {
                const wrapper = document.getElementById('participant-fields-wrapper');
                if (!wrapper) return;

                wrapper.innerHTML = '<h3>Participant Details</h3>';
                let selectedAddons = participant_addons_data?.selected_addons || [];

                for (let i = 1; i <= qty; i++) {
                    const block = document.createElement('div');
                    block.className = 'participant-block';
                    block.style.marginBottom = '30px';
                    block.style.borderBottom = '1px solid #ddd';
                    block.style.paddingBottom = '20px';

                    let addonHTML = '';
                    if (participant_addons_data.addons.length > 0) {
                        addonHTML += '<div class="participant-addons"><b>OPTIONAL ADD-ONS</b>';

                        participant_addons_data.addons.forEach(function (addon) {
                            const fieldName = `participant_${i}_${addon.field_name}`;
                            const price_text = addon.price > 0 ? ` (+ $${addon.price.toFixed(2)})` : '';
                            const isChecked = selectedAddons.some(sel => sel.field_name === addon.field_name);

                            const html = `
                                <p>
                                    <label>
                                        <input type="checkbox"
                                            name="${fieldName}"
                                            value="on"
                                            ${isChecked ? 'checked="checked"' : ''}
                                            data-addon-key="${addon.field_name}" />
                                        ${addon.label}${price_text}
                                    </label>
                                </p>`;
                            addonHTML += html;
                        });

                        addonHTML += '</div>';
                    }

                    block.innerHTML = `
                        <h4>Participant ${i}</h4>
                        <p class="form-row form-row-wide">
                            <label>Full Name</label>
                            <input type="text" class="input-text" name="participant_${i}_full_name" required />
                        </p>
                        <p class="form-row form-row-wide">
                            <label>Phone Number</label>
                            <input type="text" class="input-text" name="participant_${i}_phone" required />
                        </p>
                        <p class="form-row form-row-wide">
                            <label>Email</label>
                            <input type="email" class="input-text" name="participant_${i}_email" required />
                        </p>
                        ${addonHTML}
                        <div class="participant-addons-summary" id="participant_${i}_summary" style="margin-top: 10px; font-style: italic;"></div>
                    `;

                    wrapper.appendChild(block);
                }
            }

            generateFields(<?php echo intval($qty); ?>);
        });
    </script>
    <?php
});
