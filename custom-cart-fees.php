<?php
/**
 * Plugin Name: Custom Cart Add-On Fee Breakdown
 * Description: Dynamically adds participant add-on fees based on WooCommerce Product Add-Ons selections.
 */


// remove add on in the cart
add_filter('woocommerce_get_item_data', 'remove_product_add_ons_from_display', 99, 2);
function remove_product_add_ons_from_display($item_data, $cart_item) {
    if (isset($cart_item['addons'])) {
        return []; // Don't show any addon info
    }
    return $item_data;
}

// remove the add-ons price and pull the base price only
add_action('woocommerce_before_calculate_totals', 'remove_addon_price_from_product_total', 90);
function remove_addon_price_from_product_total($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (
            !isset($cart_item['data']) ||
            !$cart_item['data'] instanceof WC_Product
        ) continue;

        $product = $cart_item['data'];
        $qty     = $cart_item['quantity'];
        $price   = $product->get_price(); // This is the inflated price (with add-ons)

        $addon_total = 0;
        if (!empty($cart_item['addons'])) {
            foreach ($cart_item['addons'] as $addon) {
                if (isset($addon['price']) && is_numeric($addon['price'])) {
                    $addon_total += floatval($addon['price']);
                }
            }
        }

        $adjusted_price = $price - ($addon_total / max($qty, 1));

        // Set the adjusted base price per unit (removing add-ons)
        $product->set_price($adjusted_price);
    }
}


// remove the add ons price
add_action('woocommerce_cart_calculate_fees', 'always_show_optional_addons_fee', 20, 1);
function always_show_optional_addons_fee($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $addons = WC()->session->get('custom_selected_addons');

    // 🧼 Normalize: ensure it's an array — otherwise reset it
    if (!is_array($addons)) {
        $addons = [];
        WC()->session->__unset('custom_selected_addons'); // Reset to avoid carrying stale data
    //    error_log('❌ Invalid addon session data. Resetting to empty array.');
    }

    $addon_total = 0;

    foreach ($addons as $addon) {
        if (!empty($addon['price']) && is_numeric($addon['price'])) {
            $addon_total += floatval($addon['price']);
        }
    }

    // ✅ Always apply fee, even if 0 — avoids stale display
    $label = sprintf(__('Additional: $%.2f', 'your-textdomain'), $addon_total);
    $cart->add_fee($label, $addon_total, false);

  //  error_log("💰 Fee Applied: $addon_total");
}



// initialize the form //
add_action('woocommerce_before_checkout_form', function () {
    if (is_admin() || defined('DOING_AJAX')) return;

    // 1️⃣ Restore coupon message
    if (!WC()->cart->applied_coupons) {
        echo '<div class="woocommerce-info">' . __('Have a gift voucher?') . ' <a href="#" class="showcoupon">' . __('Click here to enter your gift voucher code') . '</a></div>';
        woocommerce_checkout_coupon_form();
    }

    // 2️⃣ Initialize addon session if not set
    if (!empty(WC()->session->get('custom_selected_addons'))) return;

    $addons = [];

    foreach (WC()->cart->get_cart() as $item) {
        $qty = $item['quantity'];
        if (!empty($item['addons'])) {
            foreach ($item['addons'] as $addon) {
                $label = $addon['value'] ?? $addon['name'];
                $field = sanitize_title($label);
                $price = floatval($addon['price'] ?? 0);

                for ($i = 0; $i < $qty; $i++) {
                    $addons[] = [
                        'label' => $label,
                        'field_name' => $field,
                        'price' => $price,
                        'selected' => true
                    ];
                }
            }
        }
    }

    WC()->session->set('custom_selected_addons', $addons);
  //  error_log('🧠 Session initialized on checkout load: ' . print_r($addons, true));
}, 5); 

// initialize the checkout form.


