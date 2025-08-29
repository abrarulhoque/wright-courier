<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Plugin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Include required classes
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-frontend.php';
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-calculator.php';
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-google.php';
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-rest.php';
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-cart.php';
        require_once WWC_PLUGIN_PATH . 'includes/class-wwc-order.php';
        
        // Initialize components
        $this->init_hooks();
        $this->init_classes();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'init_components']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
    }
    
    private function init_classes() {
        // Initialize main components
        new WWC_Frontend();
        new WWC_REST();
        new WWC_Cart();
        new WWC_Order();
    }
    
    public function init_components() {
        // Load text domain
        load_plugin_textdomain('wright-courier', false, dirname(plugin_basename(WWC_PLUGIN_PATH)) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Scripts are now enqueued conditionally by the Frontend class
        // when shortcode is detected on the page
        return;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Wright Courier Settings', 'wright-courier'),
            __('Wright Courier', 'wright-courier'),
            'manage_options',
            'wright-courier-settings',
            [$this, 'admin_settings_page']
        );
    }
    
    public function admin_settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('wwc_settings');
            
            update_option('wwc_test_mode', sanitize_text_field($_POST['wwc_test_mode'] ?? 'no'));
            update_option('wwc_google_api_key', sanitize_text_field($_POST['wwc_google_api_key'] ?? ''));
            update_option('wwc_target_product_id', absint($_POST['wwc_target_product_id'] ?? 177));
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'wright-courier') . '</p></div>';
        }
        
        $test_mode = get_option('wwc_test_mode', 'yes');
        $api_key = get_option('wwc_google_api_key', '');
        $product_id = get_option('wwc_target_product_id', 177);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Wright Courier Settings', 'wright-courier'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('wwc_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Test Mode', 'wright-courier'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wwc_test_mode" value="yes" <?php checked($test_mode, 'yes'); ?>>
                                <?php _e('Enable test mode (uses mock data instead of Google API)', 'wright-courier'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Google API Key', 'wright-courier'); ?></th>
                        <td>
                            <input type="text" name="wwc_google_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('Google API key with Places and Distance Matrix API enabled.', 'wright-courier'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Target Product ID', 'wright-courier'); ?></th>
                        <td>
                            <input type="number" name="wwc_target_product_id" value="<?php echo esc_attr($product_id); ?>" class="small-text">
                            <p class="description"><?php _e('Product ID to show courier calculator on.', 'wright-courier'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}