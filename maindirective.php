<?php
/**
 * Plugin Name: Main Directive Loader
 * Description: Enqueues all necessary scripts and styles for custom checkout behavior.
 */


add_action('wp_enqueue_scripts', function () {

    // 🔍 Debug cart data
    $has_addons = false;
    foreach (WC()->cart->get_cart() as $item) {
    // error_log('🛒 Cart Item: ' . print_r($item, true));

        if (!empty($item['addons'])) {
            $has_addons = true;
        //    error_log('✅ Add-ons found in cart.');
            break;
        }
    }

    if ($has_addons) {
        // ✅ Use plugins_url for correct path resolution
        wp_enqueue_script(
            'participant-email-js',
            plugins_url('js/participant_email_required.js', __FILE__),
            ['jquery'],
            null,
            true
        );

      //   error_log('📦 JS enqueued successfully.');

        wp_localize_script('participant_email_required.js', 'addon_check', [
            'require_email' => true,
        ]);
    } else {
        // error_log('ℹ️ No add-ons found, JS not enqueued.');
    }
});



// for checkbox
wp_enqueue_script(
    'checkbox-dynamic-js',
    content_url('/mu-plugins/js/checkbox2_dynamic.js'),
    ['jquery'],
    null,
    true
);

wp_localize_script('checkbox-dynamic-js', 'wc_ajax_data', [
   'ajax_url' => home_url('/wp/wp-admin/admin-ajax.php'),
]);



//refresh when session end
add_action('template_redirect', function () {
    if (is_checkout()) {
        WC()->session->__unset('custom_selected_addons');
    }
});

// add_action('init', function () {
    add_action('wp_ajax_update_custom_addons', 'handle_update_custom_addons_safe');
    add_action('wp_ajax_nopriv_update_custom_addons', 'handle_update_custom_addons_safe');
// });

function handle_update_custom_addons_safe() {
   // error_log('🧪 AJAX handler fired');

    // Always set session to posted value or empty array
    $addons = (isset($_POST['addons']) && is_array($_POST['addons'])) ? $_POST['addons'] : [];

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('custom_selected_addons', $addons);
      //  error_log('✅ Session updated: ' . print_r($addons, true));
        wp_send_json_success(['stored' => $addons]);
    }

    wp_send_json_error(['message' => 'WC session not available']);
}
// end of AJAX...


// grab the the value if add ons selected in the product page
add_action('woocommerce_before_checkout_form', function () {
    if (is_admin() || defined('DOING_AJAX')) return;

    $session_addons = WC()->session->get('custom_selected_addons');

    if (!empty($session_addons) && is_array($session_addons)) {
        // Only skip if valid and non-empty
        return;
    }

    $initial_addons = [];

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!empty($cart_item['addons'])) {
            foreach ($cart_item['addons'] as $addon) {
                $label = $addon['value'] ?? $addon['name'];
                $field = sanitize_title($label);
                $price = floatval($addon['price'] ?? 0);

                $initial_addons[$field] = [
                    'label' => $label,
                    'field_name' => $field,
                    'price' => $price,
                    'selected' => true
                ];
            }
        }
    }

    WC()->session->set('custom_selected_addons', array_values($initial_addons));
 //   error_log('🧠 Session preloaded with global add-ons: ' . print_r($initial_addons, true));
});