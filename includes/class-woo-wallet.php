<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class WooWallet {

    /**
     * The single instance of the class.
     *
     * @var wc_wallet_payment
     * @since 1.0.0
     */
    protected static $_instance = null;
    /* settings api object */
    public $settings_api = null;
    /* wallet object */
    public $wallet = null;
    /**
     * Main instance
     * @return class object
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * Class constructor
     */
    public function __construct() {
        if (Woo_Wallet_Dependencies::is_woocommerce_active()) {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
            do_action('woo_wallet_loaded');
        } else {
            add_action('admin_notices', array($this, 'admin_notices'), 15);
        }
    }
    /**
     * Constants define
     */
    private function define_constants() {
        $this->define('WOO_WALLET_ABSPATH', dirname(WOO_WALLET_PLUGIN_FILE) . '/');
        $this->define('WOO_WALLET_PLUGIN_FILE', plugin_basename(WOO_WALLET_PLUGIN_FILE));
        $this->define('WOO_WALLET_PLUGIN_VERSION', '1.0.1');
    }
    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
    /**
     * Check request
     * @param string $type
     * @return bool
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined('DOING_AJAX');
            case 'cron' :
                return defined('DOING_CRON');
            case 'frontend' :
                return (!is_admin() || defined('DOING_AJAX') ) && !defined('DOING_CRON');
        }
    }
    /**
     * load plugin files
     */
    public function includes() {
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-util.php' );
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-install.php' );
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings-api.php' );
        $this->settings_api = new Woo_Wallet_Settings_API();
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-wallet.php' );
        $this->wallet = new Woo_Wallet_Wallet();
        if ($this->is_request('admin')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php' );
        }
        if ($this->is_request('frontend')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php' );
        }
        if ($this->is_request('ajax')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php' );
        }
    }
    /**
     * Plugin url
     * @return string path
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', WOO_WALLET_PLUGIN_FILE));
    }
    /**
     * Plugin init
     */
    private function init_hooks() {
        register_activation_hook(WOO_WALLET_PLUGIN_FILE, array('Woo_Wallet_Install', 'install'));
        add_action('init', array($this, 'init'), 10);
        do_action('woo_wallet_init');
    }
    /**
     * Plugin init
     */
    public function init() {
        $this->load_plugin_textdomain();
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-payment-method.php' );
        add_filter('woocommerce_email_classes', array($this, 'woocommerce_email_classes'));
        add_filter('woocommerce_payment_gateways', array($this, 'load_gateway'));
        add_action('woocommerce_order_status_processing', array($this->wallet, 'wallet_credit_purchase'));
        add_action('woocommerce_order_status_completed', array($this->wallet, 'wallet_credit_purchase'));
        
        add_action('woocommerce_order_status_processing', array($this->wallet, 'wallet_partial_payment'), 10);
        add_action('woocommerce_order_status_completed', array($this->wallet, 'wallet_partial_payment'), 10);
        
        add_action('woocommerce_order_status_processing', array($this->wallet, 'wallet_cashback'), 12);
        add_action('woocommerce_order_status_completed', array($this->wallet, 'wallet_cashback'), 12);
        
        add_rewrite_endpoint('woo-wallet', EP_PAGES);
        add_rewrite_endpoint('woo-wallet-transactions', EP_PAGES);
        if (!get_option('_wallet_enpoint_added')) {
            flush_rewrite_rules();
            update_option('_wallet_enpoint_added', true);
        }
    }
    /**
     * Text Domain loader
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'woo-wallet');

        unload_textdomain('woo-wallet');
        load_textdomain('woo-wallet', WP_LANG_DIR . '/wc-wallet/wc-wallet-' . $locale . '.mo');
        load_plugin_textdomain('woo-wallet', false, plugin_basename(dirname(WOO_WALLET_PLUGIN_FILE)) . '/languages');
    }
    /**
     * WooCommerce wallet payment gateway loader
     * @param array $load_gateways
     * @return array
     */
    public function load_gateway($load_gateways) {
        $load_gateways[] = 'Woo_Gateway_Wallet_payment';
        return $load_gateways;
    }
    /**
     * WooCommerce email loader
     * @param array $emails
     * @return array
     */
    public function woocommerce_email_classes($emails) {
        $emails['Woo_Wallet_Email_New_Transaction'] = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-new-transaction.php';
        return $emails;
    }
    /**
     * Load template
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     */
    public function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if ($args && is_array($args)) {
            extract($args);
        }
        $located = $this->locate_template($template_name, $template_path, $default_path);
        include ($located);
    }
    /**
     * Locate template file
     * @param string $template_name
     * @param string $template_path
     * @param string $default_path
     * @return string
     */
    public function locate_template($template_name, $template_path = '', $default_path = '') {
        $default_path = apply_filters('woo_wallet_template_path', $default_path);
        if (!$template_path) {
            $template_path = 'woo-wallet';
        }
        if (!$default_path) {
            $default_path = WOO_WALLET_ABSPATH . 'templates/';
        }
        // Look within passed path within the theme - this is priority
        $template = locate_template(array(trailingslashit($template_path) . $template_name, $template_name));
        // Add support of third perty plugin
        $template = apply_filters('woo_wallet_locate_template', $template, $template_name, $template_path, $default_path);
        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }
        return $template;
    }
    /**
     * Display admin notice
     */
    public function admin_notices() {
        echo '<div class="error"><p>';
        _e('WooCommerce wallet payment plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woo-wallet');
        echo '</p></div>';
    }

}
