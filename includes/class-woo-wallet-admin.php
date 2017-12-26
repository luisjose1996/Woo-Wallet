<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Admin')) {

    class Woo_Wallet_Admin {

        public $transaction_details_table = NULL;
        public $balance_details_table = NULL;

        /**
         * Class constructor
         */
        public function __construct() {
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'), 15);
            add_action('admin_menu', array($this, 'admin_menu'), 50);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on') && 'product' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                add_action('woocommerce_product_write_panel_tabs', array($this, 'woocommerce_product_write_panel_tabs'));
                add_action('woocommerce_product_data_panels', array($this, 'woocommerce_product_data_panels'));
                add_action('save_post_product', array($this, 'save_post_product'));
            }
            add_action('woocommerce_admin_order_totals_after_tax', array($this, 'add_wallet_partial_payment_amount'), 10, 1);
        }

        /**
         * init admin menu
         */
        public function admin_menu() {
            $wc_wallet_paymenthook = add_menu_page(__('WooWallet', 'woo-wallet'), __('WooWallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet', array($this, 'wallet_page'), 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/PjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDMzNC44NzcgMzM0Ljg3NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgMzM0Ljg3NyAzMzQuODc3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGc+PHBhdGggZD0iTTMzMy4xOTYsMTU1Ljk5OWgtMTYuMDY3VjgyLjA5YzAtMTcuNzE5LTE0LjQxNS0zMi4xMzQtMzIuMTM0LTMyLjEzNGgtMjEuNzYxTDI0MC45NjUsOS45MTdDMjM3LjU3MSwzLjc5OCwyMzEuMTEyLDAsMjI0LjEwNywwYy0zLjI2NSwwLTYuNTA0LDAuODQyLTkuMzY0LDIuNDI5bC04NS40NjQsNDcuNTI2SDMzLjgxNWMtMTcuNzE5LDAtMzIuMTM0LDE0LjQxNS0zMi4xMzQsMzIuMTM0djIyMC42NTNjMCwxNy43MTksMTQuNDE1LDMyLjEzNCwzMi4xMzQsMzIuMTM0aDI1MS4xOGMxNy43MTksMCwzMi4xMzQtMTQuNDE1LDMyLjEzNC0zMi4xMzR2LTY0LjgwMmgxNi4wNjdWMTU1Ljk5OXogTTI4NC45OTUsNjIuODA5YzkuODk3LDAsMTcuOTgyLDcuNTE5LDE5LjA2OCwxNy4xNGgtMjQuMTUybC05LjUyNS0xNy4xNEgyODQuOTk1eiBNMjIwLjk5NiwxMy42NjNjMy4wMTQtMS42OSw3LjA3LTAuNTA4LDguNzM0LDIuNDk0bDM1LjQ3Niw2My43ODZIMTAxLjc5OEwyMjAuOTk2LDEzLjY2M3ogTTMwNC4yNzUsMzAyLjc0MmMwLDEwLjYzLTguNjUxLDE5LjI4MS0xOS4yODEsMTkuMjgxSDMzLjgxNWMtMTAuNjMsMC0xOS4yODEtOC42NTEtMTkuMjgxLTE5LjI4MVY4Mi4wOWMwLTEwLjYzLDguNjUxLTE5LjI4MSwxOS4yODEtMTkuMjgxaDcyLjM1M0w3NS4zNDUsNzkuOTVIMzcuODMyYy0zLjU1NCwwLTYuNDI3LDIuODc5LTYuNDI3LDYuNDI3czIuODczLDYuNDI3LDYuNDI3LDYuNDI3aDE0LjM5NmgyMzQuODNoMTcuMjE3djYzLjIwMWgtNDYuOTk5Yy0yMS44MjYsMC0zOS41ODksMTcuNzY0LTM5LjU4OSwzOS41ODl2Mi43NjRjMCwyMS44MjYsMTcuNzY0LDM5LjU4OSwzOS41ODksMzkuNTg5aDQ2Ljk5OVYzMDIuNzQyeiBNMzIwLjM0MiwyMjUuMDg3aC0zLjIxM2gtNTkuODUzYy0xNC43NDMsMC0yNi43MzYtMTEuOTkyLTI2LjczNi0yNi43MzZ2LTIuNzY0YzAtMTQuNzQzLDExLjk5Mi0yNi43MzYsMjYuNzM2LTI2LjczNmg1OS44NTNoMy4yMTNWMjI1LjA4N3ogTTI3Ni45NjEsMTk3LjQ5N2MwLDcuODQxLTYuMzUsMTQuMTktMTQuMTksMTQuMTljLTcuODQxLDAtMTQuMTktNi4zNS0xNC4xOS0xNC4xOXM2LjM1LTE0LjE5LDE0LjE5LTE0LjE5QzI3MC42MTIsMTgzLjMwNiwyNzYuOTYxLDE4OS42NjIsMjc2Ljk2MSwxOTcuNDk3eiIvPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48L3N2Zz4=', 59);
            add_action("load-$wc_wallet_paymenthook", array($this, 'add_woo_wallet_details'));
            $wc_wallet_payment_hook_add = add_submenu_page('', __('WC Wallet', 'woo-wallet'), __('WC Wallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-add', array($this, 'add_balance_to_user_wallet'));
            add_action("load-$wc_wallet_payment_hook_add", array($this, 'add_woo_wallet_add_balance_option'));
            $wc_wallet_payment_hook_view = add_submenu_page('', __('WC Wallet', 'woo-wallet'), __('WC Wallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-transactions', array($this, 'transaction_details_page'));
            add_action("load-$wc_wallet_payment_hook_view", array($this, 'add_woo_wallet_transaction_details_option'));
        }

        /**
         * Register and enqueue admin styles and scripts
         * @global type $post
         */
        public function admin_scripts() {
            global $post;
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            // register styles
            wp_register_style('woo_wallet_admin_styles', woo_wallet()->plugin_url() . '/assets/admin/css/balance-details.css', array(), WOO_WALLET_PLUGIN_VERSION);

            // Register scripts
            wp_register_script('woo_wallet_admin_product', woo_wallet()->plugin_url() . '/assets/admin/js/admin-product.js', array('jquery'), WOO_WALLET_PLUGIN_VERSION);
            wp_register_script('woo_wallet_admin_order', woo_wallet()->plugin_url() . '/assets/admin/js/admin-order.js', array('jquery', 'wc-admin-order-meta-boxes'), WOO_WALLET_PLUGIN_VERSION);

            if (in_array($screen_id, array('product', 'edit-product'))) {
                wp_enqueue_script('woo_wallet_admin_product');
                wp_localize_script('woo_wallet_admin_product', 'woo_wallet_admin_product_param', array('product_id' => get_wallet_rechargeable_product()->get_id()));
            }
            if (in_array($screen_id, array('shop_order'))) {
                $order = wc_get_order($post->ID);
                wp_enqueue_script('woo_wallet_admin_order');
                $order_localizer = array(
                    'order_id' => $post->ID,
                    'payment_method' => $order->get_payment_method(''),
                    'default_price' => wc_price(0),
                    'is_rechargeable_order' => is_wallet_rechargeable_order($order)
                );
                wp_localize_script('woo_wallet_admin_order', 'woo_wallet_admin_order_param', $order_localizer);
            }
            if (in_array($screen_id, array('toplevel_page_woo-wallet'))) {
                wp_enqueue_style('woo_wallet_admin_styles');
            }
        }

        /**
         * Display user wallet details page
         */
        public function wallet_page() {
            ?>
            <div class="wrap">
                <h2><?php _e('User wallet details', 'woo-wallet'); ?></h2>
                <form id="posts-filter" method="get">
                    <?php $this->balance_details_table->display(); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Admin add wallet balance form
         */
        public function add_balance_to_user_wallet() {
            $user_id = filter_input(INPUT_GET, 'user_id');
            $current_wallet_balance = 0;
            if ($user_id != NULL && !empty($user_id)) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance($user_id, '');
            }
            ?>
            <div class="wrap">
                <?php settings_errors(); ?>
                <h2><?php _e('Add Balance', 'woo-wallet'); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p>
                    <?php
                    _e('Current wallet balance: ', 'woo-wallet');
                    echo wc_price($current_wallet_balance)
                    ?>
                </p>
                <form id="posts-filter" method="post">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="balance_amount"><?php _e('Amount', 'woo-wallet'); ?></label></th>
                                <td>
                                    <input type="number" step="0.01" name="balance_amount" class="regular-text" />
                                    <p class="description"><?php _e('Enter Amount to add', 'woo-wallet'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="payment_description"><?php _e('Description', 'woo-wallet'); ?></label></th>
                                <td>
                                    <textarea name="payment_description" class="regular-text"></textarea>
                                    <p class="description"><?php _e('Enter Description', 'woo-wallet'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                    <?php wp_nonce_field('wc-wallet-admin-add-balance', 'wc-wallet-admin-add-balance'); ?>
                    <?php submit_button(__('Add Balance', 'woo-wallet')); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Display transaction details page
         */
        public function transaction_details_page() {
            $user_id = filter_input(INPUT_GET, 'user_id');
            $current_wallet_balance = 0;
            if ($user_id != NULL) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance($user_id, '');
            }
            ?>
            <div class="wrap">
                <h2><?php _e('Transaction details', 'woo-wallet'); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p><?php
                    _e('Current wallet balance: ', 'woo-wallet');
                    echo wc_price($current_wallet_balance)
                    ?></p>
                <form id="posts-filter" method="get">
                    <?php $this->transaction_details_table->display(); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Wallet details page initialization
         */
        public function add_woo_wallet_details() {
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-balance-details.php' );
            $this->balance_details_table = new Woo_Wallet_Balance_Details();
            $this->balance_details_table->prepare_items();
        }

        /**
         * Handel admin add wallet balance
         */
        public function add_woo_wallet_add_balance_option() {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wc-wallet-admin-add-balance']) && wp_verify_nonce($_POST['wc-wallet-admin-add-balance'], 'wc-wallet-admin-add-balance')) {
                $transaction_id = NULL;
                $message = '';
                $user_id = filter_input(INPUT_POST, 'user_id');
                $amount = filter_input(INPUT_POST, 'balance_amount');
                $description = filter_input(INPUT_POST, 'payment_description');
                if ($user_id != NULL && !empty($user_id) && $amount != NULL && !empty($amount)) {
                    $amount = number_format($amount, 2, '.', '');
                    $transaction_id = woo_wallet()->wallet->credit($user_id, $amount, $description);
                } else {
                    $message = __('Please enter amount', 'woo-wallet');
                }
                if (is_null($transaction_id)) {
                    add_settings_error('', '102', $message);
                } else {
                    wp_safe_redirect(add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')));
                    exit();
                }
            }
        }

        /**
         * Transaction details page initialization
         */
        public function add_woo_wallet_transaction_details_option() {
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-transaction-details.php' );
            $this->transaction_details_table = new Woo_Wallet_Transaction_Details();
            $this->transaction_details_table->prepare_items();
        }

        /**
         * add wallet tab to product page
         */
        public function woocommerce_product_write_panel_tabs() {
            ?>
            <li class="wallet_tab">
                <a href="#wallet_data_tabs"> &nbsp;<?php _e('Cashback', 'woo-wallet'); ?></a>
            </li>
            <?php
        }

        /**
         * WooCommerce product tab content
         * @global object $post
         */
        public function woocommerce_product_data_panels() {
            global $post;
            ?>
            <div id="wallet_data_tabs" class="panel woocommerce_options_panel">
                <?php
                woocommerce_wp_select(array(
                    'id' => 'wcwp_cashback_type',
                    'label' => __('Cashback type', 'woo-wallet'),
                    'description' => __('Select cashback type percentage or fixed', 'woo-wallet'),
                    'options' => array('percent' => __('Percentage', 'woo-wallet'), 'fixed' => __('Fixed', 'woo-wallet')),
                    'value' => get_post_meta($post->ID, '_cashback_type', true)
                ));
                woocommerce_wp_text_input(array(
                    'id' => 'wcwp_cashback_amount',
                    'type' => 'number',
                    'label' => __('Cashback Amount', 'woo-wallet'),
                    'description' => __('Enter cashback amount', 'woo-wallet'),
                    'value' => get_post_meta($post->ID, '_cashback_amount', true)
                ));
                ?>
            </div>
            <?php
        }

        /**
         * Save post meta
         * @param int $post_ID
         */
        public function save_post_product($post_ID) {
            if (isset($_POST['wcwp_cashback_type'])) {
                update_post_meta($post_ID, '_cashback_type', $_POST['wcwp_cashback_type']);
            }
            if (isset($_POST['wcwp_cashback_amount'])) {
                update_post_meta($post_ID, '_cashback_amount', $_POST['wcwp_cashback_amount']);
            }
        }

        public function add_wallet_partial_payment_amount($order_id) {
            $order = wc_get_order($order_id);
            if(!get_post_meta($order_id, '_via_wallet_payment', true)){
                return;
            }
            ?>
            <tr>
                <td class="label"><?php _e('Via wallet', 'woo-wallet'); ?>:</td>
                <td width="1%"></td>
                <td class="via-wallet">
                    <?php echo '-'.wc_price(get_post_meta($order_id, '_via_wallet_payment', true), array( 'currency' => $order->get_currency() )); ?>
                </td>
            </tr>
            <?php
        }

    }

}
new Woo_Wallet_Admin();
