                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty_school_calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty_school_calculator'); ?></td><td><?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/olympiamarketing/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://olympiamarketing.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty_school_calculator
 * 
 * @package BeautySchoolTuitionCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolTuitionCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolTuitionCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolTuitionCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty_school_calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty_school_calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty_school_calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty_school_calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty_school_calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty_school_calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty_school_calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue jQuery (WordPress default)
        wp_enqueue_script('jquery');
        
        // Localize script with minimal data
        wp_add_inline_script('jquery', $this->get_inline_javascript(), 'after');
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('bsc_calculator_nonce');
        $fafsa_enabled = $this->is_fafsa_enabled() ? '1' : '0';
        
        $strings = array(
            'selectCourse' => __('Please select a course first.', 'beauty_school_calculator'),
            'fillFields' => __('Please fill in all required fields.', 'beauty_school_calculator'),
            'calcError' => __('Error calculating costs. Please try again.', 'beauty_school_calculator')
        );
        
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var bscData = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                fafaEnabled: '<?php echo esc_js($fafsa_enabled); ?>',
                strings: <?php echo wp_json_encode($strings); ?>
            };
            
            $('#bsc-next-1').on('click', function() {
                if ($('#bsc-course-select').val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                $('#bsc-step-1').hide();
                $('#bsc-step-2').show();
            });
            
            $('#bsc-calculate-no-fafsa').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                var selectedOption = courseSelect.find('option:selected');
                
                if (!selectedOption.length || courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_costs',
                    nonce: bscData.nonce,
                    course: courseSelect.val()
                };
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-1').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-back-1').on('click', function() {
                $('#bsc-step-2').hide();
                $('#bsc-step-1').show();
            });
            
            $('#bsc-calculate').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                
                if (courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_fafsa',
                    nonce: bscData.nonce,
                    course: courseSelect.val(),
                    age: $('#bsc-age').val(),
                    dependency: $('#bsc-dependency').val(),
                    income: $('#bsc-income').val(),
                    household_size: $('#bsc-household-size').val(),
                    college_students: $('#bsc-college-students').val()
                };
                
                // Validate required fields
                var required = ['age', 'income', 'household_size', 'college_students'];
                var valid = true;
                
                for (var i = 0; i < required.length; i++) {
                    if (!data[required[i]] || data[required[i]] === '') {
                        valid = false;
                        break;
                    }
                }
                
                if (!valid) {
                    alert(bscData.strings.fillFields);
                    return;
                }
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-2').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-restart').on('click', function() {
                $('#bsc-step-3').hide();
                $('#bsc-step-1').show();
                // Reset form
                $('#bsc-course-select').val('');
                $('#bsc-age, #bsc-income, #bsc-household-size, #bsc-college-students').val('');
                $('#bsc-dependency').val('dependent');
            });
            
            function displayResults(data) {
                var html = '<table class="bsc-results-table">';
                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty-school-tuition-calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td>$' + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_program_cost.toLocaleString() + '</strong></td></tr>';
                
                if (data.fafsa_enabled) {
                    html += '<tr><td class="label"><?php esc_html_e('Expected Family Contribution (EFC):', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.efc.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Estimated Pell Grant:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.pell_grant.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Federal Loan Eligibility:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.loan_eligibility.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Total Estimated Aid:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_aid.toLocaleString() + '</strong></td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Remaining Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.remaining_cost.toLocaleString() + '</strong></td></tr>';
                }
                
                html += '</table>';
                
                html += '<p><small><strong><?php esc_html_e('Disclaimer:', 'beauty-school-tuition-calculator'); ?></strong> <?php esc_html_e('This is an estimate only. Actual costs and financial aid may vary. Please consult with a financial aid advisor for accurate information.', 'beauty-school-tuition-calculator'); ?></small></p>';
                
                html += '<div class="bsc-plugin-credit">';
                html += '<?php esc_html_e('Powered by', 'beauty-school-tuition-calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty-school-tuition-calculator'); ?></a>';
                html += '</div>';
                
                $('#bsc-results').html(html);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get inline CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_css() {
        return '
        .bsc-calculator-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .bsc-step h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .bsc-form-group {
            margin-bottom: 15px;
        }
        
        .bsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .bsc-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .bsc-button {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .bsc-button:hover {
            background: #005a87;
        }
        
        .bsc-button.bsc-secondary {
            background: #666;
        }
        
        .bsc-button.bsc-secondary:hover {
            background: #444;
        }
        
        .bsc-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bsc-results-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .bsc-results-table .label {
            font-weight: bold;
            background: #f5f5f5;
        }
        
        .bsc-plugin-credit {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .bsc-plugin-credit a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .bsc-plugin-credit a:hover {
            text-decoration: underline;
        }';
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
        .form-table th {
            width: 200px;
        }
        .bsc-course-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }');
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty_school_calculator'),
            __('Beauty Calculator', 'beauty_school_calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission with proper nonce verification
        if (isset($_POST['submit'])) {
            // Check if nonce is set and verify it
            if (!isset($_POST['bsc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsc_settings_nonce'])), 'bsc_settings')) {
                add_settings_error(
                    'bsc_settings',
                    'nonce_failed',
                    __('Security check failed. Please try again.', 'beauty_school_calculator'),
                    'error'
                );
            } else {
                $this->save_settings();
            }
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty_school_calculator'); ?></h1>
            
            <?php settings_errors('bsc_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty_school_calculator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                                <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty_school_calculator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty_school_calculator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Course Configuration', 'beauty_school_calculator'); ?></h2>
                
                <?php foreach ($courses as $key => $course): ?>
                <div class="bsc-course-section">
                    <h3><?php echo esc_html($course['name']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Course Name', 'beauty_school_calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty_school_calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Required Hours', 'beauty_school_calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty_school_calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty_school_calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Label', 'beauty_school_calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty_school_calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php esc_html_e('Usage', 'beauty_school_calculator'); ?></h2>
            <p><?php esc_html_e('Use the shortcode', 'beauty_school_calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty_school_calculator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            $post_courses = map_deep(wp_unslash($_POST['courses']), 'sanitize_text_field');
            foreach ($post_courses as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && sanitize_text_field(wp_unslash($_POST['fafsa_enabled'])) === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty_school_calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty_school_calculator')
        ), $atts, 'beauty_calculator');
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        $wrapper_class = 'bsc-calculator-container';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="bsc-step" id="bsc-step-1">
                <h4><?php esc_html_e('Select Your Course', 'beauty_school_calculator'); ?></h4>
                <select id="bsc-course-select" class="bsc-input">
                    <option value=""><?php esc_html_e('Choose a course...', 'beauty_school_calculator'); ?></option>
                    <?php foreach ($courses as $key => $course): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fafsa_enabled): ?>
                    <button type="button" id="bsc-next-1" class="bsc-button"><?php esc_html_e('Next: FAFSA Calculator', 'beauty_school_calculator'); ?></button>
                <?php else: ?>
                    <button type="button" id="bsc-calculate-no-fafsa" class="bsc-button"><?php esc_html_e('Calculate Total Cost', 'beauty_school_calculator'); ?></button>
                <?php endif; ?>
            </div>
            
            <?php if ($fafsa_enabled): ?>
            <div class="bsc-step" id="bsc-step-2" style="display:none;">
                <h4><?php esc_html_e('FAFSA Eligibility Assessment', 'beauty_school_calculator'); ?></h4>
                <p><?php esc_html_e('Please provide the following information to estimate your federal financial aid eligibility:', 'beauty_school_calculator'); ?></p>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Your Age:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-age" class="bsc-input" min="16" max="100" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Dependency Status:', 'beauty_school_calculator'); ?></label>
                    <select id="bsc-dependency" class="bsc-input">
                        <option value="dependent"><?php esc_html_e('Dependent (under 24, unmarried, no children)', 'beauty_school_calculator'); ?></option>
                        <option value="independent"><?php esc_html_e('Independent (24+, married, or have children)', 'beauty_school_calculator'); ?></option>
                    </select>
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Annual Household Income ($):', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-income" class="bsc-input" min="0" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of People in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-household-size" class="bsc-input" min="1" max="20" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of College Students in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-college-students" class="bsc-input" min="1" max="10" />
                </div>
                
                <button type="button" id="bsc-calculate" class="bsc-button"><?php esc_html_e('Calculate', 'beauty_school_calculator'); ?></button>
                <button type="button" id="bsc-back-1" class="bsc-button bsc-secondary"><?php esc_html_e('Back', 'beauty_school_calculator'); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="bsc-step" id="bsc-step-3" style="display:none;">
                <h4><?php esc_html_e('Your Cost Estimate', 'beauty_school_calculator'); ?></h4>
                <div id="bsc-results"></div>
                <button type="button" id="bsc-restart" class="bsc-button"><?php esc_html_e('Start Over', 'beauty_school_calculator'); ?></button>
            </div>
            
            <!-- Permanent plugin credit -->
            <div class="bsc-plugin-credit">
                <?php esc_html_e('Powered by', 'beauty_school_calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty_school_calculator'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty_school_calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty_school_calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty_school_calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty_school_calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty_school_calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Initialize the plugin
BeautySchoolTuitionCalculator::get_instance(); + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty_school_calculator'); ?></td><td><?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/yourusername/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty_school_calculator
 * 
 * @package BeautySchoolTuitionCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolTuitionCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolTuitionCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolTuitionCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty_school_calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty_school_calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty_school_calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty_school_calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty_school_calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty_school_calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty_school_calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue jQuery (WordPress default)
        wp_enqueue_script('jquery');
        
        // Localize script with minimal data
        wp_add_inline_script('jquery', $this->get_inline_javascript(), 'after');
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('bsc_calculator_nonce');
        $fafsa_enabled = $this->is_fafsa_enabled() ? '1' : '0';
        
        $strings = array(
            'selectCourse' => __('Please select a course first.', 'beauty_school_calculator'),
            'fillFields' => __('Please fill in all required fields.', 'beauty_school_calculator'),
            'calcError' => __('Error calculating costs. Please try again.', 'beauty_school_calculator')
        );
        
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var bscData = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                fafaEnabled: '<?php echo esc_js($fafsa_enabled); ?>',
                strings: <?php echo wp_json_encode($strings); ?>
            };
            
            $('#bsc-next-1').on('click', function() {
                if ($('#bsc-course-select').val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                $('#bsc-step-1').hide();
                $('#bsc-step-2').show();
            });
            
            $('#bsc-calculate-no-fafsa').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                var selectedOption = courseSelect.find('option:selected');
                
                if (!selectedOption.length || courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_costs',
                    nonce: bscData.nonce,
                    course: courseSelect.val()
                };
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-1').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-back-1').on('click', function() {
                $('#bsc-step-2').hide();
                $('#bsc-step-1').show();
            });
            
            $('#bsc-calculate').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                
                if (courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_fafsa',
                    nonce: bscData.nonce,
                    course: courseSelect.val(),
                    age: $('#bsc-age').val(),
                    dependency: $('#bsc-dependency').val(),
                    income: $('#bsc-income').val(),
                    household_size: $('#bsc-household-size').val(),
                    college_students: $('#bsc-college-students').val()
                };
                
                // Validate required fields
                var required = ['age', 'income', 'household_size', 'college_students'];
                var valid = true;
                
                for (var i = 0; i < required.length; i++) {
                    if (!data[required[i]] || data[required[i]] === '') {
                        valid = false;
                        break;
                    }
                }
                
                if (!valid) {
                    alert(bscData.strings.fillFields);
                    return;
                }
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-2').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-restart').on('click', function() {
                $('#bsc-step-3').hide();
                $('#bsc-step-1').show();
                // Reset form
                $('#bsc-course-select').val('');
                $('#bsc-age, #bsc-income, #bsc-household-size, #bsc-college-students').val('');
                $('#bsc-dependency').val('dependent');
            });
            
            function displayResults(data) {
                var html = '<table class="bsc-results-table">';
                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty-school-tuition-calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td>$' + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_program_cost.toLocaleString() + '</strong></td></tr>';
                
                if (data.fafsa_enabled) {
                    html += '<tr><td class="label"><?php esc_html_e('Expected Family Contribution (EFC):', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.efc.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Estimated Pell Grant:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.pell_grant.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Federal Loan Eligibility:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.loan_eligibility.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Total Estimated Aid:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_aid.toLocaleString() + '</strong></td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Remaining Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.remaining_cost.toLocaleString() + '</strong></td></tr>';
                }
                
                html += '</table>';
                
                html += '<p><small><strong><?php esc_html_e('Disclaimer:', 'beauty-school-tuition-calculator'); ?></strong> <?php esc_html_e('This is an estimate only. Actual costs and financial aid may vary. Please consult with a financial aid advisor for accurate information.', 'beauty-school-tuition-calculator'); ?></small></p>';
                
                html += '<div class="bsc-plugin-credit">';
                html += '<?php esc_html_e('Powered by', 'beauty-school-tuition-calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty-school-tuition-calculator'); ?></a>';
                html += '</div>';
                
                $('#bsc-results').html(html);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get inline CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_css() {
        return '
        .bsc-calculator-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .bsc-step h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .bsc-form-group {
            margin-bottom: 15px;
        }
        
        .bsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .bsc-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .bsc-button {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .bsc-button:hover {
            background: #005a87;
        }
        
        .bsc-button.bsc-secondary {
            background: #666;
        }
        
        .bsc-button.bsc-secondary:hover {
            background: #444;
        }
        
        .bsc-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bsc-results-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .bsc-results-table .label {
            font-weight: bold;
            background: #f5f5f5;
        }
        
        .bsc-plugin-credit {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .bsc-plugin-credit a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .bsc-plugin-credit a:hover {
            text-decoration: underline;
        }';
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
        .form-table th {
            width: 200px;
        }
        .bsc-course-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }');
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'),
            __('Beauty Calculator', 'beauty-school-tuition-calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission with proper nonce verification
        if (isset($_POST['submit'])) {
            // Check if nonce is set and verify it
            if (!isset($_POST['bsc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsc_settings_nonce'])), 'bsc_settings')) {
                add_settings_error(
                    'bsc_settings',
                    'nonce_failed',
                    __('Security check failed. Please try again.', 'beauty_school_calculator'),
                    'error'
                );
            } else {
                $this->save_settings();
            }
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'); ?></h1>
            
            <?php settings_errors('bsc_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty-school-tuition-calculator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                                <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty-school-tuition-calculator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty-school-tuition-calculator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Course Configuration', 'beauty-school-tuition-calculator'); ?></h2>
                
                <?php foreach ($courses as $key => $course): ?>
                <div class="bsc-course-section">
                    <h3><?php echo esc_html($course['name']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Course Name', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Required Hours', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Label', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty-school-tuition-calculator')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php esc_html_e('Usage', 'beauty-school-tuition-calculator'); ?></h2>
            <p><?php esc_html_e('Use the shortcode', 'beauty-school-tuition-calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty-school-tuition-calculator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            $post_courses = map_deep(wp_unslash($_POST['courses']), 'sanitize_text_field');
            foreach ($post_courses as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && sanitize_text_field(wp_unslash($_POST['fafsa_enabled'])) === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty_school_calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty_school_calculator')
        ), $atts, 'beauty_calculator');
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        $wrapper_class = 'bsc-calculator-container';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="bsc-step" id="bsc-step-1">
                <h4><?php esc_html_e('Select Your Course', 'beauty_school_calculator'); ?></h4>
                <select id="bsc-course-select" class="bsc-input">
                    <option value=""><?php esc_html_e('Choose a course...', 'beauty_school_calculator'); ?></option>
                    <?php foreach ($courses as $key => $course): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fafsa_enabled): ?>
                    <button type="button" id="bsc-next-1" class="bsc-button"><?php esc_html_e('Next: FAFSA Calculator', 'beauty_school_calculator'); ?></button>
                <?php else: ?>
                    <button type="button" id="bsc-calculate-no-fafsa" class="bsc-button"><?php esc_html_e('Calculate Total Cost', 'beauty_school_calculator'); ?></button>
                <?php endif; ?>
            </div>
            
            <?php if ($fafsa_enabled): ?>
            <div class="bsc-step" id="bsc-step-2" style="display:none;">
                <h4><?php esc_html_e('FAFSA Eligibility Assessment', 'beauty_school_calculator'); ?></h4>
                <p><?php esc_html_e('Please provide the following information to estimate your federal financial aid eligibility:', 'beauty_school_calculator'); ?></p>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Your Age:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-age" class="bsc-input" min="16" max="100" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Dependency Status:', 'beauty_school_calculator'); ?></label>
                    <select id="bsc-dependency" class="bsc-input">
                        <option value="dependent"><?php esc_html_e('Dependent (under 24, unmarried, no children)', 'beauty_school_calculator'); ?></option>
                        <option value="independent"><?php esc_html_e('Independent (24+, married, or have children)', 'beauty_school_calculator'); ?></option>
                    </select>
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Annual Household Income ($):', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-income" class="bsc-input" min="0" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of People in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-household-size" class="bsc-input" min="1" max="20" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of College Students in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-college-students" class="bsc-input" min="1" max="10" />
                </div>
                
                <button type="button" id="bsc-calculate" class="bsc-button"><?php esc_html_e('Calculate', 'beauty_school_calculator'); ?></button>
                <button type="button" id="bsc-back-1" class="bsc-button bsc-secondary"><?php esc_html_e('Back', 'beauty_school_calculator'); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="bsc-step" id="bsc-step-3" style="display:none;">
                <h4><?php esc_html_e('Your Cost Estimate', 'beauty_school_calculator'); ?></h4>
                <div id="bsc-results"></div>
                <button type="button" id="bsc-restart" class="bsc-button"><?php esc_html_e('Start Over', 'beauty_school_calculator'); ?></button>
            </div>
            
            <!-- Permanent plugin credit -->
            <div class="bsc-plugin-credit">
                <?php esc_html_e('Powered by', 'beauty_school_calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty_school_calculator'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty-school-tuition-calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty-school-tuition-calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty-school-tuition-calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty-school-tuition-calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty-school-tuition-calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Initialize the plugin
BeautySchoolTuitionCalculator::get_instance(); + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty_school_calculator'); ?></td><td><?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/yourusername/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty_school_calculator
 * 
 * @package BeautySchoolTuitionCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolTuitionCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolTuitionCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolTuitionCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty_school_calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty_school_calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty_school_calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty_school_calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty_school_calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty_school_calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty_school_calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue jQuery (WordPress default)
        wp_enqueue_script('jquery');
        
        // Localize script with minimal data
        wp_add_inline_script('jquery', $this->get_inline_javascript(), 'after');
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('bsc_calculator_nonce');
        $fafsa_enabled = $this->is_fafsa_enabled() ? '1' : '0';
        
        $strings = array(
            'selectCourse' => __('Please select a course first.', 'beauty_school_calculator'),
            'fillFields' => __('Please fill in all required fields.', 'beauty_school_calculator'),
            'calcError' => __('Error calculating costs. Please try again.', 'beauty_school_calculator')
        );
        
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var bscData = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                fafaEnabled: '<?php echo esc_js($fafsa_enabled); ?>',
                strings: <?php echo wp_json_encode($strings); ?>
            };
            
            $('#bsc-next-1').on('click', function() {
                if ($('#bsc-course-select').val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                $('#bsc-step-1').hide();
                $('#bsc-step-2').show();
            });
            
            $('#bsc-calculate-no-fafsa').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                var selectedOption = courseSelect.find('option:selected');
                
                if (!selectedOption.length || courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_costs',
                    nonce: bscData.nonce,
                    course: courseSelect.val()
                };
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-1').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-back-1').on('click', function() {
                $('#bsc-step-2').hide();
                $('#bsc-step-1').show();
            });
            
            $('#bsc-calculate').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                
                if (courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_fafsa',
                    nonce: bscData.nonce,
                    course: courseSelect.val(),
                    age: $('#bsc-age').val(),
                    dependency: $('#bsc-dependency').val(),
                    income: $('#bsc-income').val(),
                    household_size: $('#bsc-household-size').val(),
                    college_students: $('#bsc-college-students').val()
                };
                
                // Validate required fields
                var required = ['age', 'income', 'household_size', 'college_students'];
                var valid = true;
                
                for (var i = 0; i < required.length; i++) {
                    if (!data[required[i]] || data[required[i]] === '') {
                        valid = false;
                        break;
                    }
                }
                
                if (!valid) {
                    alert(bscData.strings.fillFields);
                    return;
                }
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-2').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-restart').on('click', function() {
                $('#bsc-step-3').hide();
                $('#bsc-step-1').show();
                // Reset form
                $('#bsc-course-select').val('');
                $('#bsc-age, #bsc-income, #bsc-household-size, #bsc-college-students').val('');
                $('#bsc-dependency').val('dependent');
            });
            
            function displayResults(data) {
                var html = '<table class="bsc-results-table">';
                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty-school-tuition-calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td>$' + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_program_cost.toLocaleString() + '</strong></td></tr>';
                
                if (data.fafsa_enabled) {
                    html += '<tr><td class="label"><?php esc_html_e('Expected Family Contribution (EFC):', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.efc.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Estimated Pell Grant:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.pell_grant.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Federal Loan Eligibility:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.loan_eligibility.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Total Estimated Aid:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_aid.toLocaleString() + '</strong></td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Remaining Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.remaining_cost.toLocaleString() + '</strong></td></tr>';
                }
                
                html += '</table>';
                
                html += '<p><small><strong><?php esc_html_e('Disclaimer:', 'beauty-school-tuition-calculator'); ?></strong> <?php esc_html_e('This is an estimate only. Actual costs and financial aid may vary. Please consult with a financial aid advisor for accurate information.', 'beauty-school-tuition-calculator'); ?></small></p>';
                
                html += '<div class="bsc-plugin-credit">';
                html += '<?php esc_html_e('Powered by', 'beauty-school-tuition-calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty-school-tuition-calculator'); ?></a>';
                html += '</div>';
                
                $('#bsc-results').html(html);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get inline CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_css() {
        return '
        .bsc-calculator-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .bsc-step h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .bsc-form-group {
            margin-bottom: 15px;
        }
        
        .bsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .bsc-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .bsc-button {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .bsc-button:hover {
            background: #005a87;
        }
        
        .bsc-button.bsc-secondary {
            background: #666;
        }
        
        .bsc-button.bsc-secondary:hover {
            background: #444;
        }
        
        .bsc-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bsc-results-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .bsc-results-table .label {
            font-weight: bold;
            background: #f5f5f5;
        }
        
        .bsc-plugin-credit {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .bsc-plugin-credit a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .bsc-plugin-credit a:hover {
            text-decoration: underline;
        }';
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
        .form-table th {
            width: 200px;
        }
        .bsc-course-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }');
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'),
            __('Beauty Calculator', 'beauty-school-tuition-calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission with proper nonce verification
        if (isset($_POST['submit'])) {
            // Check if nonce is set and verify it
            if (!isset($_POST['bsc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsc_settings_nonce'])), 'bsc_settings')) {
                add_settings_error(
                    'bsc_settings',
                    'nonce_failed',
                    __('Security check failed. Please try again.', 'beauty_school_calculator'),
                    'error'
                );
            } else {
                $this->save_settings();
            }
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'); ?></h1>
            
            <?php settings_errors('bsc_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty-school-tuition-calculator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                                <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty-school-tuition-calculator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty-school-tuition-calculator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Course Configuration', 'beauty-school-tuition-calculator'); ?></h2>
                
                <?php foreach ($courses as $key => $course): ?>
                <div class="bsc-course-section">
                    <h3><?php echo esc_html($course['name']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Course Name', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Required Hours', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Label', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty-school-tuition-calculator')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php esc_html_e('Usage', 'beauty-school-tuition-calculator'); ?></h2>
            <p><?php esc_html_e('Use the shortcode', 'beauty-school-tuition-calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty-school-tuition-calculator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            $post_courses = map_deep(wp_unslash($_POST['courses']), 'sanitize_text_field');
            foreach ($post_courses as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && sanitize_text_field(wp_unslash($_POST['fafsa_enabled'])) === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty_school_calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty_school_calculator')
        ), $atts, 'beauty_calculator');
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        $wrapper_class = 'bsc-calculator-container';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="bsc-step" id="bsc-step-1">
                <h4><?php esc_html_e('Select Your Course', 'beauty_school_calculator'); ?></h4>
                <select id="bsc-course-select" class="bsc-input">
                    <option value=""><?php esc_html_e('Choose a course...', 'beauty_school_calculator'); ?></option>
                    <?php foreach ($courses as $key => $course): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fafsa_enabled): ?>
                    <button type="button" id="bsc-next-1" class="bsc-button"><?php esc_html_e('Next: FAFSA Calculator', 'beauty_school_calculator'); ?></button>
                <?php else: ?>
                    <button type="button" id="bsc-calculate-no-fafsa" class="bsc-button"><?php esc_html_e('Calculate Total Cost', 'beauty_school_calculator'); ?></button>
                <?php endif; ?>
            </div>
            
            <?php if ($fafsa_enabled): ?>
            <div class="bsc-step" id="bsc-step-2" style="display:none;">
                <h4><?php esc_html_e('FAFSA Eligibility Assessment', 'beauty_school_calculator'); ?></h4>
                <p><?php esc_html_e('Please provide the following information to estimate your federal financial aid eligibility:', 'beauty_school_calculator'); ?></p>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Your Age:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-age" class="bsc-input" min="16" max="100" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Dependency Status:', 'beauty_school_calculator'); ?></label>
                    <select id="bsc-dependency" class="bsc-input">
                        <option value="dependent"><?php esc_html_e('Dependent (under 24, unmarried, no children)', 'beauty_school_calculator'); ?></option>
                        <option value="independent"><?php esc_html_e('Independent (24+, married, or have children)', 'beauty_school_calculator'); ?></option>
                    </select>
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Annual Household Income ($):', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-income" class="bsc-input" min="0" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of People in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-household-size" class="bsc-input" min="1" max="20" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of College Students in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-college-students" class="bsc-input" min="1" max="10" />
                </div>
                
                <button type="button" id="bsc-calculate" class="bsc-button"><?php esc_html_e('Calculate', 'beauty_school_calculator'); ?></button>
                <button type="button" id="bsc-back-1" class="bsc-button bsc-secondary"><?php esc_html_e('Back', 'beauty_school_calculator'); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="bsc-step" id="bsc-step-3" style="display:none;">
                <h4><?php esc_html_e('Your Cost Estimate', 'beauty_school_calculator'); ?></h4>
                <div id="bsc-results"></div>
                <button type="button" id="bsc-restart" class="bsc-button"><?php esc_html_e('Start Over', 'beauty_school_calculator'); ?></button>
            </div>
            
            <!-- Permanent plugin credit -->
            <div class="bsc-plugin-credit">
                <?php esc_html_e('Powered by', 'beauty_school_calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty_school_calculator'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty-school-tuition-calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty-school-tuition-calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty-school-tuition-calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty-school-tuition-calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty-school-tuition-calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Initialize the plugin
BeautySchoolTuitionCalculator::get_instance(); + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td><?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/yourusername/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty_school_calculator
 * 
 * @package BeautySchoolTuitionCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolTuitionCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolTuitionCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolTuitionCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty_school_calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty_school_calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty_school_calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty_school_calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty_school_calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty_school_calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty_school_calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue jQuery (WordPress default)
        wp_enqueue_script('jquery');
        
        // Localize script with minimal data
        wp_add_inline_script('jquery', $this->get_inline_javascript(), 'after');
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('bsc_calculator_nonce');
        $fafsa_enabled = $this->is_fafsa_enabled() ? '1' : '0';
        
        $strings = array(
            'selectCourse' => __('Please select a course first.', 'beauty_school_calculator'),
            'fillFields' => __('Please fill in all required fields.', 'beauty_school_calculator'),
            'calcError' => __('Error calculating costs. Please try again.', 'beauty_school_calculator')
        );
        
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var bscData = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                fafaEnabled: '<?php echo esc_js($fafsa_enabled); ?>',
                strings: <?php echo wp_json_encode($strings); ?>
            };
            
            $('#bsc-next-1').on('click', function() {
                if ($('#bsc-course-select').val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                $('#bsc-step-1').hide();
                $('#bsc-step-2').show();
            });
            
            $('#bsc-calculate-no-fafsa').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                var selectedOption = courseSelect.find('option:selected');
                
                if (!selectedOption.length || courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_costs',
                    nonce: bscData.nonce,
                    course: courseSelect.val()
                };
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-1').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-back-1').on('click', function() {
                $('#bsc-step-2').hide();
                $('#bsc-step-1').show();
            });
            
            $('#bsc-calculate').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                
                if (courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_fafsa',
                    nonce: bscData.nonce,
                    course: courseSelect.val(),
                    age: $('#bsc-age').val(),
                    dependency: $('#bsc-dependency').val(),
                    income: $('#bsc-income').val(),
                    household_size: $('#bsc-household-size').val(),
                    college_students: $('#bsc-college-students').val()
                };
                
                // Validate required fields
                var required = ['age', 'income', 'household_size', 'college_students'];
                var valid = true;
                
                for (var i = 0; i < required.length; i++) {
                    if (!data[required[i]] || data[required[i]] === '') {
                        valid = false;
                        break;
                    }
                }
                
                if (!valid) {
                    alert(bscData.strings.fillFields);
                    return;
                }
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-2').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-restart').on('click', function() {
                $('#bsc-step-3').hide();
                $('#bsc-step-1').show();
                // Reset form
                $('#bsc-course-select').val('');
                $('#bsc-age, #bsc-income, #bsc-household-size, #bsc-college-students').val('');
                $('#bsc-dependency').val('dependent');
            });
            
            function displayResults(data) {
                var html = '<table class="bsc-results-table">';
                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty-school-tuition-calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td>$' + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_program_cost.toLocaleString() + '</strong></td></tr>';
                
                if (data.fafsa_enabled) {
                    html += '<tr><td class="label"><?php esc_html_e('Expected Family Contribution (EFC):', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.efc.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Estimated Pell Grant:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.pell_grant.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Federal Loan Eligibility:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.loan_eligibility.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Total Estimated Aid:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_aid.toLocaleString() + '</strong></td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Remaining Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.remaining_cost.toLocaleString() + '</strong></td></tr>';
                }
                
                html += '</table>';
                
                html += '<p><small><strong><?php esc_html_e('Disclaimer:', 'beauty-school-tuition-calculator'); ?></strong> <?php esc_html_e('This is an estimate only. Actual costs and financial aid may vary. Please consult with a financial aid advisor for accurate information.', 'beauty-school-tuition-calculator'); ?></small></p>';
                
                html += '<div class="bsc-plugin-credit">';
                html += '<?php esc_html_e('Powered by', 'beauty-school-tuition-calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty-school-tuition-calculator'); ?></a>';
                html += '</div>';
                
                $('#bsc-results').html(html);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get inline CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_css() {
        return '
        .bsc-calculator-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .bsc-step h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .bsc-form-group {
            margin-bottom: 15px;
        }
        
        .bsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .bsc-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .bsc-button {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .bsc-button:hover {
            background: #005a87;
        }
        
        .bsc-button.bsc-secondary {
            background: #666;
        }
        
        .bsc-button.bsc-secondary:hover {
            background: #444;
        }
        
        .bsc-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bsc-results-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .bsc-results-table .label {
            font-weight: bold;
            background: #f5f5f5;
        }
        
        .bsc-plugin-credit {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .bsc-plugin-credit a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .bsc-plugin-credit a:hover {
            text-decoration: underline;
        }';
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
        .form-table th {
            width: 200px;
        }
        .bsc-course-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }');
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'),
            __('Beauty Calculator', 'beauty-school-tuition-calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission with proper nonce verification
        if (isset($_POST['submit'])) {
            // Check if nonce is set and verify it
            if (!isset($_POST['bsc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsc_settings_nonce'])), 'bsc_settings')) {
                add_settings_error(
                    'bsc_settings',
                    'nonce_failed',
                    __('Security check failed. Please try again.', 'beauty_school_calculator'),
                    'error'
                );
            } else {
                $this->save_settings();
            }
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'); ?></h1>
            
            <?php settings_errors('bsc_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty-school-tuition-calculator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                                <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty-school-tuition-calculator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty-school-tuition-calculator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Course Configuration', 'beauty-school-tuition-calculator'); ?></h2>
                
                <?php foreach ($courses as $key => $course): ?>
                <div class="bsc-course-section">
                    <h3><?php echo esc_html($course['name']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Course Name', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Required Hours', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Label', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty-school-tuition-calculator')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php esc_html_e('Usage', 'beauty-school-tuition-calculator'); ?></h2>
            <p><?php esc_html_e('Use the shortcode', 'beauty-school-tuition-calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty-school-tuition-calculator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            $post_courses = map_deep(wp_unslash($_POST['courses']), 'sanitize_text_field');
            foreach ($post_courses as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && sanitize_text_field(wp_unslash($_POST['fafsa_enabled'])) === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty_school_calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty_school_calculator')
        ), $atts, 'beauty_calculator');
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        $wrapper_class = 'bsc-calculator-container';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="bsc-step" id="bsc-step-1">
                <h4><?php esc_html_e('Select Your Course', 'beauty_school_calculator'); ?></h4>
                <select id="bsc-course-select" class="bsc-input">
                    <option value=""><?php esc_html_e('Choose a course...', 'beauty_school_calculator'); ?></option>
                    <?php foreach ($courses as $key => $course): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fafsa_enabled): ?>
                    <button type="button" id="bsc-next-1" class="bsc-button"><?php esc_html_e('Next: FAFSA Calculator', 'beauty_school_calculator'); ?></button>
                <?php else: ?>
                    <button type="button" id="bsc-calculate-no-fafsa" class="bsc-button"><?php esc_html_e('Calculate Total Cost', 'beauty_school_calculator'); ?></button>
                <?php endif; ?>
            </div>
            
            <?php if ($fafsa_enabled): ?>
            <div class="bsc-step" id="bsc-step-2" style="display:none;">
                <h4><?php esc_html_e('FAFSA Eligibility Assessment', 'beauty_school_calculator'); ?></h4>
                <p><?php esc_html_e('Please provide the following information to estimate your federal financial aid eligibility:', 'beauty_school_calculator'); ?></p>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Your Age:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-age" class="bsc-input" min="16" max="100" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Dependency Status:', 'beauty_school_calculator'); ?></label>
                    <select id="bsc-dependency" class="bsc-input">
                        <option value="dependent"><?php esc_html_e('Dependent (under 24, unmarried, no children)', 'beauty_school_calculator'); ?></option>
                        <option value="independent"><?php esc_html_e('Independent (24+, married, or have children)', 'beauty_school_calculator'); ?></option>
                    </select>
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Annual Household Income ($):', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-income" class="bsc-input" min="0" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of People in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-household-size" class="bsc-input" min="1" max="20" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of College Students in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-college-students" class="bsc-input" min="1" max="10" />
                </div>
                
                <button type="button" id="bsc-calculate" class="bsc-button"><?php esc_html_e('Calculate', 'beauty_school_calculator'); ?></button>
                <button type="button" id="bsc-back-1" class="bsc-button bsc-secondary"><?php esc_html_e('Back', 'beauty_school_calculator'); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="bsc-step" id="bsc-step-3" style="display:none;">
                <h4><?php esc_html_e('Your Cost Estimate', 'beauty_school_calculator'); ?></h4>
                <div id="bsc-results"></div>
                <button type="button" id="bsc-restart" class="bsc-button"><?php esc_html_e('Start Over', 'beauty_school_calculator'); ?></button>
            </div>
            
            <!-- Permanent plugin credit -->
            <div class="bsc-plugin-credit">
                <?php esc_html_e('Powered by', 'beauty_school_calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty_school_calculator'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty-school-tuition-calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty-school-tuition-calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty-school-tuition-calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty-school-tuition-calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty-school-tuition-calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Initialize the plugin
BeautySchoolTuitionCalculator::get_instance(); + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty_school_calculator'); ?><?php
/**
 * Plugin Name: Beauty School Tuition Calculator
 * Plugin URI: https://github.com/yourusername/beauty-school-calculator
 * Description: A comprehensive calculator for beauty schools to help students estimate tuition costs and FAFSA eligibility. Complies with WordPress.org guidelines.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beauty_school_calculator
 * 
 * @package BeautySchoolTuitionCalculator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BSC_VERSION')) {
    define('BSC_VERSION', '1.0.0');
}
if (!defined('BSC_PLUGIN_URL')) {
    define('BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('BSC_PLUGIN_PATH')) {
    define('BSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BSC_PLUGIN_BASENAME')) {
    define('BSC_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 * 
 * @since 1.0.0
 */
final class BeautySchoolTuitionCalculator {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var BeautySchoolTuitionCalculator|null
     */
    private static $instance = null;
    
    /**
     * Course configurations cache
     * 
     * @since 1.0.0
     * @var array|null
     */
    private $courses_cache = null;
    
    /**
     * FAFSA enabled cache
     * 
     * @since 1.0.0
     * @var bool|null
     */
    private $fafsa_enabled_cache = null;
    
    /**
     * Get plugin instance
     * 
     * @since 1.0.0
     * @return BeautySchoolTuitionCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Prevent unserializing
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing instances of this class is forbidden.', 'beauty_school_calculator'), esc_html(BSC_VERSION));
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_nopriv_bsc_calculate_fafsa', array($this, 'ajax_calculate_fafsa'));
        add_action('wp_ajax_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        add_action('wp_ajax_nopriv_bsc_calculate_costs', array($this, 'ajax_calculate_costs'));
        
        // Shortcode
        add_shortcode('beauty_calculator', array($this, 'calculator_shortcode'));
        
        // Text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin initialization
     * 
     * @since 1.0.0
     */
    public function init() {
        // Cache options on init for better performance
        $this->get_courses();
        $this->is_fafsa_enabled();
    }
    
    /**
     * Load text domain for translations
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'beauty_school_calculator',
            false,
            dirname(BSC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check minimum requirements
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires PHP 7.4 or higher.', 'beauty_school_calculator'));
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(BSC_PLUGIN_BASENAME);
            wp_die(esc_html__('Beauty School Calculator requires WordPress 5.0 or higher.', 'beauty_school_calculator'));
        }
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up temporary data, flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_courses = array(
            'cosmetology' => array(
                'name' => __('Cosmetology', 'beauty_school_calculator'),
                'price' => 15000,
                'hours' => 1500,
                'books_price' => 500,
                'supplies_price' => 750,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'barbering' => array(
                'name' => __('Barbering', 'beauty_school_calculator'),
                'price' => 12000,
                'hours' => 1200,
                'books_price' => 400,
                'supplies_price' => 600,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'esthetics' => array(
                'name' => __('Esthetics (Skincare)', 'beauty_school_calculator'),
                'price' => 8000,
                'hours' => 600,
                'books_price' => 300,
                'supplies_price' => 400,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            ),
            'massage' => array(
                'name' => __('Massage Therapy', 'beauty_school_calculator'),
                'price' => 10000,
                'hours' => 750,
                'books_price' => 350,
                'supplies_price' => 300,
                'other_price' => 0,
                'other_label' => __('Other Fees', 'beauty_school_calculator')
            )
        );
        
        // Use add_option to prevent overwriting existing settings
        add_option('bsc_courses', $default_courses);
        add_option('bsc_fafsa_enabled', true);
        add_option('bsc_version', BSC_VERSION);
    }
    
    /**
     * Get courses with caching
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_courses() {
        if (null === $this->courses_cache) {
            $this->courses_cache = get_option('bsc_courses', array());
        }
        return $this->courses_cache;
    }
    
    /**
     * Check if FAFSA is enabled with caching
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_fafsa_enabled() {
        if (null === $this->fafsa_enabled_cache) {
            $this->fafsa_enabled_cache = (bool) get_option('bsc_fafsa_enabled', true);
        }
        return $this->fafsa_enabled_cache;
    }
    
    /**
     * Clear caches
     * 
     * @since 1.0.0
     */
    private function clear_cache() {
        $this->courses_cache = null;
        $this->fafsa_enabled_cache = null;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'beauty_calculator')) {
            return;
        }
        
        // Enqueue jQuery (WordPress default)
        wp_enqueue_script('jquery');
        
        // Localize script with minimal data
        wp_add_inline_script('jquery', $this->get_inline_javascript(), 'after');
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('bsc_calculator_nonce');
        $fafsa_enabled = $this->is_fafsa_enabled() ? '1' : '0';
        
        $strings = array(
            'selectCourse' => __('Please select a course first.', 'beauty_school_calculator'),
            'fillFields' => __('Please fill in all required fields.', 'beauty_school_calculator'),
            'calcError' => __('Error calculating costs. Please try again.', 'beauty_school_calculator')
        );
        
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var bscData = {
                ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>',
                fafaEnabled: '<?php echo esc_js($fafsa_enabled); ?>',
                strings: <?php echo wp_json_encode($strings); ?>
            };
            
            $('#bsc-next-1').on('click', function() {
                if ($('#bsc-course-select').val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                $('#bsc-step-1').hide();
                $('#bsc-step-2').show();
            });
            
            $('#bsc-calculate-no-fafsa').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                var selectedOption = courseSelect.find('option:selected');
                
                if (!selectedOption.length || courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_costs',
                    nonce: bscData.nonce,
                    course: courseSelect.val()
                };
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-1').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-back-1').on('click', function() {
                $('#bsc-step-2').hide();
                $('#bsc-step-1').show();
            });
            
            $('#bsc-calculate').on('click', function() {
                var courseSelect = $('#bsc-course-select');
                
                if (courseSelect.val() === '') {
                    alert(bscData.strings.selectCourse);
                    return;
                }
                
                var data = {
                    action: 'bsc_calculate_fafsa',
                    nonce: bscData.nonce,
                    course: courseSelect.val(),
                    age: $('#bsc-age').val(),
                    dependency: $('#bsc-dependency').val(),
                    income: $('#bsc-income').val(),
                    household_size: $('#bsc-household-size').val(),
                    college_students: $('#bsc-college-students').val()
                };
                
                // Validate required fields
                var required = ['age', 'income', 'household_size', 'college_students'];
                var valid = true;
                
                for (var i = 0; i < required.length; i++) {
                    if (!data[required[i]] || data[required[i]] === '') {
                        valid = false;
                        break;
                    }
                }
                
                if (!valid) {
                    alert(bscData.strings.fillFields);
                    return;
                }
                
                $.post(bscData.ajaxUrl, data, function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        $('#bsc-step-2').hide();
                        $('#bsc-step-3').show();
                    } else {
                        alert(bscData.strings.calcError);
                    }
                });
            });
            
            $('#bsc-restart').on('click', function() {
                $('#bsc-step-3').hide();
                $('#bsc-step-1').show();
                // Reset form
                $('#bsc-course-select').val('');
                $('#bsc-age, #bsc-income, #bsc-household-size, #bsc-college-students').val('');
                $('#bsc-dependency').val('dependent');
            });
            
            function displayResults(data) {
                var html = '<table class="bsc-results-table">';
                html += '<tr><td class="label"><?php esc_html_e('Course:', 'beauty-school-tuition-calculator'); ?></td><td>' + data.course_name + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Tuition:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.course_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Books:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.books_price.toLocaleString() + '</td></tr>';
                html += '<tr><td class="label"><?php esc_html_e('Supplies:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.supplies_price.toLocaleString() + '</td></tr>';
                if (data.other_price > 0) {
                    html += '<tr><td class="label">' + data.other_label + ':</td><td>$' + data.other_price.toLocaleString() + '</td></tr>';
                }
                html += '<tr><td class="label"><strong><?php esc_html_e('Total Program Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_program_cost.toLocaleString() + '</strong></td></tr>';
                
                if (data.fafsa_enabled) {
                    html += '<tr><td class="label"><?php esc_html_e('Expected Family Contribution (EFC):', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.efc.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Estimated Pell Grant:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.pell_grant.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><?php esc_html_e('Federal Loan Eligibility:', 'beauty-school-tuition-calculator'); ?></td><td>$' + data.loan_eligibility.toLocaleString() + '</td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Total Estimated Aid:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.total_aid.toLocaleString() + '</strong></td></tr>';
                    html += '<tr><td class="label"><strong><?php esc_html_e('Remaining Cost:', 'beauty-school-tuition-calculator'); ?></strong></td><td><strong>$' + data.remaining_cost.toLocaleString() + '</strong></td></tr>';
                }
                
                html += '</table>';
                
                html += '<p><small><strong><?php esc_html_e('Disclaimer:', 'beauty-school-tuition-calculator'); ?></strong> <?php esc_html_e('This is an estimate only. Actual costs and financial aid may vary. Please consult with a financial aid advisor for accurate information.', 'beauty-school-tuition-calculator'); ?></small></p>';
                
                html += '<div class="bsc-plugin-credit">';
                html += '<?php esc_html_e('Powered by', 'beauty-school-tuition-calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty-school-tuition-calculator'); ?></a>';
                html += '</div>';
                
                $('#bsc-results').html(html);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get inline CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_inline_css() {
        return '
        .bsc-calculator-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .bsc-step h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .bsc-form-group {
            margin-bottom: 15px;
        }
        
        .bsc-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .bsc-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .bsc-button {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .bsc-button:hover {
            background: #005a87;
        }
        
        .bsc-button.bsc-secondary {
            background: #666;
        }
        
        .bsc-button.bsc-secondary:hover {
            background: #444;
        }
        
        .bsc-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .bsc-results-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .bsc-results-table .label {
            font-weight: bold;
            background: #f5f5f5;
        }
        
        .bsc-plugin-credit {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .bsc-plugin-credit a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .bsc-plugin-credit a:hover {
            text-decoration: underline;
        }';
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin admin pages
        if (strpos($hook, 'beauty-calculator') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
        .form-table th {
            width: 200px;
        }
        .bsc-course-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }');
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        add_options_page(
            __('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'),
            __('Beauty Calculator', 'beauty-school-tuition-calculator'),
            'manage_options',
            'beauty-calculator-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        // Handle form submission with proper nonce verification
        if (isset($_POST['submit'])) {
            // Check if nonce is set and verify it
            if (!isset($_POST['bsc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsc_settings_nonce'])), 'bsc_settings')) {
                add_settings_error(
                    'bsc_settings',
                    'nonce_failed',
                    __('Security check failed. Please try again.', 'beauty_school_calculator'),
                    'error'
                );
            } else {
                $this->save_settings();
            }
        }
        
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Beauty School Calculator Settings', 'beauty-school-tuition-calculator'); ?></h1>
            
            <?php settings_errors('bsc_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('bsc_settings', 'bsc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable FAFSA Calculator', 'beauty-school-tuition-calculator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="fafsa_enabled" value="1" <?php checked($fafsa_enabled, true); ?> />
                                <?php esc_html_e('Check this box only if your school is accredited and eligible for federal financial aid', 'beauty-school-tuition-calculator'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Only accredited beauty schools can qualify for FAFSA. Uncheck this if your school is not accredited.', 'beauty-school-tuition-calculator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Course Configuration', 'beauty-school-tuition-calculator'); ?></h2>
                
                <?php foreach ($courses as $key => $course): ?>
                <div class="bsc-course-section">
                    <h3><?php echo esc_html($course['name']); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Course Name', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($course['name']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tuition Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($course['price']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Required Hours', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][hours]" value="<?php echo esc_attr($course['hours']); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Books Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][books_price]" value="<?php echo esc_attr($course['books_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Supplies Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][supplies_price]" value="<?php echo esc_attr($course['supplies_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Label', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="text" name="courses[<?php echo esc_attr($key); ?>][other_label]" value="<?php echo esc_attr($course['other_label'] ?? __('Other Fees', 'beauty-school-tuition-calculator')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Other Price ($)', 'beauty-school-tuition-calculator'); ?></th>
                            <td><input type="number" name="courses[<?php echo esc_attr($key); ?>][other_price]" value="<?php echo esc_attr($course['other_price'] ?? 0); ?>" min="0" /></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php esc_html_e('Usage', 'beauty-school-tuition-calculator'); ?></h2>
            <p><?php esc_html_e('Use the shortcode', 'beauty-school-tuition-calculator'); ?> <code>[beauty_calculator]</code> <?php esc_html_e('to display the calculator on any page or post.', 'beauty-school-tuition-calculator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Save admin settings
     * 
     * @since 1.0.0
     */
    private function save_settings() {
        // Validate and sanitize input
        $courses = array();
        if (isset($_POST['courses']) && is_array($_POST['courses'])) {
            $post_courses = map_deep(wp_unslash($_POST['courses']), 'sanitize_text_field');
            foreach ($post_courses as $key => $course) {
                if (!is_array($course)) {
                    continue;
                }
                
                $courses[sanitize_key($key)] = array(
                    'name' => sanitize_text_field($course['name'] ?? ''),
                    'price' => absint($course['price'] ?? 0),
                    'hours' => absint($course['hours'] ?? 0),
                    'books_price' => absint($course['books_price'] ?? 0),
                    'supplies_price' => absint($course['supplies_price'] ?? 0),
                    'other_price' => absint($course['other_price'] ?? 0),
                    'other_label' => sanitize_text_field($course['other_label'] ?? __('Other Fees', 'beauty_school_calculator'))
                );
            }
        }
        
        $fafsa_enabled = isset($_POST['fafsa_enabled']) && sanitize_text_field(wp_unslash($_POST['fafsa_enabled'])) === '1';
        
        // Update options
        update_option('bsc_courses', $courses);
        update_option('bsc_fafsa_enabled', $fafsa_enabled);
        
        // Clear cache
        $this->clear_cache();
        
        add_settings_error(
            'bsc_settings',
            'settings_updated',
            __('Settings saved successfully!', 'beauty_school_calculator'),
            'updated'
        );
    }
    
    /**
     * Calculator shortcode
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function calculator_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'class' => '',
            'title' => __('Beauty School Tuition Calculator', 'beauty_school_calculator')
        ), $atts, 'beauty_calculator');
        
        // Get cached data
        $courses = $this->get_courses();
        $fafsa_enabled = $this->is_fafsa_enabled();
        
        $wrapper_class = 'bsc-calculator-container';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" id="bsc-calculator">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <div class="bsc-step" id="bsc-step-1">
                <h4><?php esc_html_e('Select Your Course', 'beauty_school_calculator'); ?></h4>
                <select id="bsc-course-select" class="bsc-input">
                    <option value=""><?php esc_html_e('Choose a course...', 'beauty_school_calculator'); ?></option>
                    <?php foreach ($courses as $key => $course): ?>
                        <option value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($course['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fafsa_enabled): ?>
                    <button type="button" id="bsc-next-1" class="bsc-button"><?php esc_html_e('Next: FAFSA Calculator', 'beauty_school_calculator'); ?></button>
                <?php else: ?>
                    <button type="button" id="bsc-calculate-no-fafsa" class="bsc-button"><?php esc_html_e('Calculate Total Cost', 'beauty_school_calculator'); ?></button>
                <?php endif; ?>
            </div>
            
            <?php if ($fafsa_enabled): ?>
            <div class="bsc-step" id="bsc-step-2" style="display:none;">
                <h4><?php esc_html_e('FAFSA Eligibility Assessment', 'beauty_school_calculator'); ?></h4>
                <p><?php esc_html_e('Please provide the following information to estimate your federal financial aid eligibility:', 'beauty_school_calculator'); ?></p>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Your Age:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-age" class="bsc-input" min="16" max="100" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Dependency Status:', 'beauty_school_calculator'); ?></label>
                    <select id="bsc-dependency" class="bsc-input">
                        <option value="dependent"><?php esc_html_e('Dependent (under 24, unmarried, no children)', 'beauty_school_calculator'); ?></option>
                        <option value="independent"><?php esc_html_e('Independent (24+, married, or have children)', 'beauty_school_calculator'); ?></option>
                    </select>
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Annual Household Income ($):', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-income" class="bsc-input" min="0" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of People in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-household-size" class="bsc-input" min="1" max="20" />
                </div>
                
                <div class="bsc-form-group">
                    <label><?php esc_html_e('Number of College Students in Household:', 'beauty_school_calculator'); ?></label>
                    <input type="number" id="bsc-college-students" class="bsc-input" min="1" max="10" />
                </div>
                
                <button type="button" id="bsc-calculate" class="bsc-button"><?php esc_html_e('Calculate', 'beauty_school_calculator'); ?></button>
                <button type="button" id="bsc-back-1" class="bsc-button bsc-secondary"><?php esc_html_e('Back', 'beauty_school_calculator'); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="bsc-step" id="bsc-step-3" style="display:none;">
                <h4><?php esc_html_e('Your Cost Estimate', 'beauty_school_calculator'); ?></h4>
                <div id="bsc-results"></div>
                <button type="button" id="bsc-restart" class="bsc-button"><?php esc_html_e('Start Over', 'beauty_school_calculator'); ?></button>
            </div>
            
            <!-- Permanent plugin credit -->
            <div class="bsc-plugin-credit">
                <?php esc_html_e('Powered by', 'beauty_school_calculator'); ?> <a href="https://olympiamarketing.com/beauty-school-digital-marketing-seo-local-pr/" target="_blank"><?php esc_html_e('Beauty School Marketing', 'beauty_school_calculator'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for FAFSA calculations
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_fafsa() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, true);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Perform calculations
        $results = $this->calculate_financial_aid($input);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for cost calculations (no FAFSA)
     * 
     * @since 1.0.0
     */
    public function ajax_calculate_costs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsc_calculator_nonce')) {
            wp_send_json_error(__('Security check failed.', 'beauty_school_calculator'));
        }
        
        // Validate and sanitize input
        $input = $this->validate_calculation_input($_POST, false);
        if (is_wp_error($input)) {
            wp_send_json_error($input->get_error_message());
        }
        
        // Calculate costs
        $results = array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'fafsa_enabled' => false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Validate calculation input
     * 
     * @since 1.0.0
     * @param array $data Input data
     * @param bool $include_fafsa Whether to validate FAFSA fields
     * @return array|WP_Error Validated data or error
     */
    private function validate_calculation_input($data, $include_fafsa = false) {
        $courses = $this->get_courses();
        
        // Validate course
        $course_key = sanitize_key($data['course'] ?? '');
        if (empty($course_key) || !isset($courses[$course_key])) {
            return new WP_Error('invalid_course', __('Invalid course selected.', 'beauty-school-tuition-calculator'));
        }
        
        $course = $courses[$course_key];
        $total_program_cost = $course['price'] + $course['books_price'] + $course['supplies_price'] + $course['other_price'];
        
        $validated = array(
            'course_name' => $course['name'],
            'course_price' => $course['price'],
            'books_price' => $course['books_price'],
            'supplies_price' => $course['supplies_price'],
            'other_price' => $course['other_price'],
            'other_label' => $course['other_label'],
            'total_program_cost' => $total_program_cost
        );
        
        // Validate FAFSA fields if needed
        if ($include_fafsa) {
            $age = absint($data['age'] ?? 0);
            $income = absint($data['income'] ?? 0);
            $household_size = absint($data['household_size'] ?? 0);
            $college_students = absint($data['college_students'] ?? 0);
            $dependency = sanitize_text_field($data['dependency'] ?? '');
            
            if ($age < 16 || $age > 100) {
                return new WP_Error('invalid_age', __('Please enter a valid age between 16 and 100.', 'beauty-school-tuition-calculator'));
            }
            
            if ($household_size < 1 || $household_size > 20) {
                return new WP_Error('invalid_household', __('Please enter a valid household size.', 'beauty-school-tuition-calculator'));
            }
            
            if ($college_students < 1 || $college_students > $household_size) {
                return new WP_Error('invalid_students', __('Number of college students cannot exceed household size.', 'beauty-school-tuition-calculator'));
            }
            
            if (!in_array($dependency, array('dependent', 'independent'), true)) {
                return new WP_Error('invalid_dependency', __('Invalid dependency status.', 'beauty-school-tuition-calculator'));
            }
            
            $validated = array_merge($validated, array(
                'age' => $age,
                'income' => $income,
                'household_size' => $household_size,
                'college_students' => $college_students,
                'dependency' => $dependency
            ));
        }
        
        return $validated;
    }
    
    /**
     * Calculate financial aid
     * 
     * @since 1.0.0
     * @param array $input Validated input data
     * @return array Results
     */
    private function calculate_financial_aid($input) {
        // Calculate EFC (Expected Family Contribution)
        $efc = $this->calculate_efc(
            $input['income'],
            $input['household_size'],
            $input['college_students'],
            $input['dependency']
        );
        
        // Calculate Pell Grant eligibility
        $pell_grant = $this->calculate_pell_grant($efc);
        
        // Calculate loan eligibility
        $loan_eligibility = $this->calculate_loan_eligibility($input['dependency'], $input['age']);
        
        $total_aid = $pell_grant + $loan_eligibility;
        $remaining_cost = max(0, $input['total_program_cost'] - $total_aid);
        
        return array(
            'course_name' => $input['course_name'],
            'course_price' => $input['course_price'],
            'books_price' => $input['books_price'],
            'supplies_price' => $input['supplies_price'],
            'other_price' => $input['other_price'],
            'other_label' => $input['other_label'],
            'total_program_cost' => $input['total_program_cost'],
            'efc' => $efc,
            'pell_grant' => $pell_grant,
            'loan_eligibility' => $loan_eligibility,
            'total_aid' => $total_aid,
            'remaining_cost' => $remaining_cost,
            'fafsa_enabled' => true
        );
    }
    
    /**
     * Calculate Expected Family Contribution (EFC)
     * 
     * @since 1.0.0
     * @param int $income Annual income
     * @param int $household_size Number of people in household
     * @param int $college_students Number of college students in household
     * @param string $dependency Dependency status
     * @return int EFC amount
     */
    private function calculate_efc($income, $household_size, $college_students, $dependency) {
        // Income protection allowances (simplified version)
        $income_protection = array(
            1 => 17040, 2 => 21330, 3 => 26520, 4 => 32710, 5 => 38490, 6 => 44780
        );
        
        $protection = $income_protection[$household_size] ?? 44780;
        $available_income = max(0, $income - $protection);
        
        // Apply assessment rate based on dependency status
        if ($dependency === 'dependent') {
            $efc = $available_income * 0.47; // Simplified dependent rate
        } else {
            $efc = $available_income * 0.50; // Simplified independent rate
        }
        
        // Adjust for multiple college students
        if ($college_students > 1) {
            $efc = $efc / $college_students;
        }
        
        return round($efc);
    }
    
    /**
     * Calculate Pell Grant eligibility
     * 
     * @since 1.0.0
     * @param int $efc Expected Family Contribution
     * @return int Pell Grant amount
     */
    private function calculate_pell_grant($efc) {
        // 2024-2025 Pell Grant maximum
        $max_pell = 7395;
        $efc_cutoff = 6656;
        
        if ($efc >= $efc_cutoff) {
            return 0;
        }
        
        // Simplified calculation
        $pell_amount = $max_pell - ($efc * 0.3);
        
        return max(0, round($pell_amount));
    }
    
    /**
     * Calculate federal loan eligibility
     * 
     * @since 1.0.0
     * @param string $dependency Dependency status
     * @param int $age Student age
     * @return int Loan amount
     */
    private function calculate_loan_eligibility($dependency, $age) {
        // Federal Direct Loan limits for vocational programs
        if ($dependency === 'independent' || $age >= 24) {
            return 12500; // Independent students
        } else {
            return 5500; // Dependent students
        }
    }
}

// Initialize the plugin
BeautySchoolTuitionCalculator::get_instance();
