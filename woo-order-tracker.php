<?php
/*
Plugin Name: Woo Order Tracker
Description: Plugin per il tracciamento degli ordini in WooCommerce.
Version: 1.0
Author: Santoro Alessandro, Miccoli Giuseppe
License: GPL2
*/

// Carica il file di lingua del plugin
function load_plugin_textdomain_woo_order_tracker() {
    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain('woo-order-tracker', false, $plugin_dir . '/languages/');
}
add_action('plugins_loaded', 'load_plugin_textdomain_woo_order_tracker');


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


// Funzione per la visualizzazione della pagina delle impostazioni del plugin
function woo_order_tracker_settings_page() {
    // Verifica se è stato inviato il modulo di salvataggio delle impostazioni
    if (isset($_POST['woo_order_tracker_save_settings'])) {
        // Verifica se il tracciamento è abilitato o disabilitato
        $tracking_enabled = isset($_POST['woo_order_tracker_tracking_enabled']) ? 1 : 0;

        // Salva la lista di URL dei webhook
        $webhook_urls = sanitize_text_field($_POST['woo_order_tracker_webhook_urls']);
        $webhook_urls = explode(',', $webhook_urls);
        $webhook_urls = array_map('trim', $webhook_urls);
        $webhook_urls = array_filter($webhook_urls);

        // Salva le impostazioni nel database
        update_option('woo_order_tracker_tracking_enabled', $tracking_enabled);
        update_option('woo_order_tracker_webhook_urls', $webhook_urls);

        // Messaggio di successo
        echo '<div class="notice notice-success"><p>Le impostazioni sono state salvate con successo.</p></div>';
    }

    // Verifica se è stato inviato il modulo di eliminazione degli URL dei webhook
    if (isset($_POST['woo_order_tracker_delete_webhook'])) {
        $delete_webhook_url = $_POST['woo_order_tracker_delete_webhook_url'];
        $webhook_urls = get_option('woo_order_tracker_webhook_urls', array());
        $webhook_urls = array_diff($webhook_urls, array($delete_webhook_url));
        update_option('woo_order_tracker_webhook_urls', $webhook_urls);

        // Messaggio di successo
        echo '<div class="notice notice-success"><p>L\'URL del webhook è stato eliminato con successo.</p></div>';
    }

    // Ottiene le impostazioni salvate nel database
    $tracking_enabled = get_option('woo_order_tracker_tracking_enabled', 0);
    $webhook_urls = get_option('woo_order_tracker_webhook_urls', array());
    $webhook_urls = implode(', ', $webhook_urls);
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
                    <th scope="row">Webhook URLs di destinazione (separati da virgola)</th>
                    <td>
                        <input type="text" name="woo_order_tracker_webhook_urls" value="<?php echo esc_attr($webhook_urls); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="woo_order_tracker_save_settings" class="button button-primary" value="Salva Impostazioni">
            </p>
        </form>

        <h2>Gestisci URL dei Webhook</h2>

        <?php if (!empty($webhook_urls)) { ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>URL del Webhook</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php // Verifica se la variabile $webhook_urls è un array prima di utilizzare il loop foreach
                        if (is_array($webhook_urls)) {
                            foreach ($webhook_urls as $webhook_url) {?>
                                <tr>
                                    <td><?php echo esc_html($webhook_url); ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <input type="hidden" name="woo_order_tracker_delete_webhook_url" value="<?php echo esc_attr($webhook_url); ?>">
                                            <input type="submit" name="woo_order_tracker_delete_webhook" class="button" value="Elimina">
                                        </form>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {// Gestione dell'errore o azioni alternative se $webhook_urls non è un array
                            ?>
                            <p>Nessun URL di webhook trovato.</p>
                        <?php } ?>
                    </div>
                <?php
                }
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
    // Puoi personalizzare questa parte per inviare i dati ai webhook di destinazione o eseguire altre azioni
    $tracking_data = array(
        'order_id' => $order_id,
        'user_id' => $user_id,
        'user_email' => $user_email,
        'user_first_name' => $user_first_name,
        'user_last_name' => $user_last_name,
        'product_names' => $product_names,
        'timestamp' => current_time('mysql')
    );
   
    // Ottiene la lista di URL dei webhook
    $webhook_urls = get_option('woo_order_tracker_webhook_urls', array());
   
    // Invia i dati del tracciamento a ciascun webhook URL
    foreach ($webhook_urls as $webhook_url) {
        wp_remote_post($webhook_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($tracking_data)
        ));
    }
}

// Aggiunge un'azione per inviare il webhook quando viene creato un nuovo ordine
add_action('woocommerce_new_order', 'woo_order_tracker_send_webhook');
