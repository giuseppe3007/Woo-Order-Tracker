<?php
/*
Plugin Name: Woo Order Tracker
Description: Plugin per il tracciamento degli ordini in WooCommerce.
Version: 1.0
Author: Santoro Alessandro, Miccoli Giuseppe
License: GPL2
*/

// Aggiunge una voce al menu laterale per le impostazioni del plugin
add_action('admin_menu', 'woo_order_tracker_add_menu');

function woo_order_tracker_add_menu() {
    add_menu_page(
        'Woo Order Tracker',
        'Woo Order Tracker',
        'manage_options',
        'woo-order-tracker',
        'woo_order_tracker_settings_page',
        'dashicons-analytics'
    );
}

function woo_order_tracker_load_css() {
    wp_enqueue_style('woo-order-tracker', plugins_url('woo-order-tracker.css', __FILE__));
}

add_action('admin_enqueue_scripts', 'woo_order_tracker_load_css');

// Funzione per la visualizzazione della pagina delle impostazioni del plugin
function woo_order_tracker_settings_page() {
    // Verifica se è stato inviato il modulo di salvataggio delle impostazioni
    if (isset($_POST['woo_order_tracker_save_settings'])) {
        // Verifica se il tracciamento è abilitato o disabilitato
        $tracking_enabled = isset($_POST['woo_order_tracker_tracking_enabled']) ? 1 : 0;
        
        // Salva l'URL del webhook di destinazione
        $webhook_url = sanitize_text_field($_POST['woo_order_tracker_webhook_url']);
        
        // Salva le impostazioni nel database
        update_option('woo_order_tracker_tracking_enabled', $tracking_enabled);
        update_option('woo_order_tracker_webhook_url', $webhook_url);
        
        // Messaggio di successo
        echo '<div class="notice notice-success"><p>Le impostazioni sono state salvate con successo.</p></div>';
    }
    
    // Ottiene le impostazioni salvate nel database
    $tracking_enabled = get_option('woo_order_tracker_tracking_enabled', 0);
    $webhook_url = get_option('woo_order_tracker_webhook_url', '');
    ?>
    
    <div class="wrap">
        <h1>Woo Order Tracker Settings</h1>
        
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">Abilita Tracciamento</th>
                    <td>
                        <label for="woo_order_tracker_tracking_enabled">
                            <input type="checkbox" name="woo_order_tracker_tracking_enabled" id="woo_order_tracker_tracking_enabled" value="1" <?php checked($tracking_enabled, 1); ?>>
                            Abilita il tracciamento degli ordini
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook URL di destinazione</th>
                    <td>
                        <input type="text" name="woo_order_tracker_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="woo_order_tracker_save_settings" class="button button-primary" value="Salva Impostazioni">
            </p>
        </form>
    </div>
    <?php
}

// Funzione per inviare il webhook
function woo_order_tracker_send_webhook($order_id) {
    // Ottiene i dettagli dell'ordine
    $order = wc_get_order($order_id);
    
    // Ottiene i dati dell'utente
    $user_id = $order->get_user_id();
    $user_email = $order->get_billing_email();
    $user_first_name = $order->get_billing_first_name();
    $user_last_name = $order->get_billing_last_name();
    
    // Ottiene i dettagli del prodotto
    $items = $order->get_items();
    $product_names = array();
    
    foreach ($items as $item) {
        $product = $item->get_product();
        $product_names[] = $product->get_name();
    }
    
    // Effettua il tracciamento dell'ordine
    // Puoi personalizzare questa parte per inviare i dati al webhook di destinazione o eseguire altre azioni
    $tracking_data = array(
        'order_id' => $order_id,
        'user_id' => $user_id,
        'user_email' => $user_email,
        'user_first_name' => $user_first_name,
        'user_last_name' => $user_last_name,
        'product_names' => $product_names,
        'timestamp' => current_time('mysql')
    );
    
    // Esempio: Invia i dati del tracciamento al webhook di destinazione
    $webhook_url = get_option('woo_order_tracker_webhook_url', '');
    if (!empty($webhook_url)) {
        $response = wp_remote_post($webhook_url, array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($tracking_data)
        ));
        
        // Verifica la risposta del webhook
        if (is_wp_error($response)) {
            // Gestione dell'errore
            error_log('Errore durante l\'invio del tracciamento dell\'ordine al webhook: ' . $response->get_error_message());
        } else {
            // Tracciamento inviato con successo
            // Puoi personalizzare questa parte in base alla risposta del webhook
        }
    }
}

// Aggiunge un hook agli eventi specificati per inviare il webhook
add_action('woocommerce_checkout_update_order_meta', 'woo_order_tracker_send_webhook');
add_action('woocommerce_order_status_changed', 'woo_order_tracker_send_webhook');
add_action('woocommerce_cancelled_order', 'woo_order_tracker_send_webhook');
add_action('woocommerce_trash_order', 'woo_order_tracker_send_webhook');
add_action('woocommerce_order_note_added', 'woo_order_tracker_send_webhook');
add_action('woocommerce_order_status_completed', 'woo_order_tracker_send_webhook');
