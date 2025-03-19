<?php
/**
 * 
 * WP Letter Generator
 *
 * @package       WPLG
 * @author        SmartSoftFirm
 *
 * @wordpress-plugin
 * Plugin Name:   WP Letter Generator
 * Plugin URI:    https://nilson.smartsoftfirm.com/letter-geneate/
 * Description:   WP Letter Generator WordPress plugin provide an AI Letter Generating System. 
 *                User can Generate with Input Fill Letter Form to Generate Letter and Download it
 *                generted pdf file. This plugin very smooth and use-friendly and easy to use. Include
 *                Admin Plugin Setting option to customize color, font's and etc.
 * Version:       1.0
 * Author:        Kamrul Hasan - SmartSoftFirm
 * Author URI:    https://www.smartsoftrfirm.com
 * Text Domain:   wp-letter-generator
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:   /lang
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php';

// Enqueue scripts
function wplg_enqueue_scripts() {
    wp_enqueue_style('wplg-style', plugin_dir_url(__FILE__) . 'assets/style.css');

    // Ensure the external libraries are loaded in the correct order
    wp_enqueue_script('html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', array(), null, true);
    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), null, true);
    
    // Load the main script AFTER dependencies
    wp_enqueue_script('wplg-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);
	    wp_localize_script('wplg-script', 'wplg_ajax', array('ajax_url' => admin_url('admin-ajax.php')));


    // Pass AJAX URL to script
    wp_localize_script('wplg-script', 'wplg_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'wplg_enqueue_scripts');


// Shortcode for the letter generation form
function wplg_letter_form() {
    ob_start(); ?>
    <form id="wplg-letter-form">
        <label>Recipient’s Name:</label>
        <input type="text" name="recipient_name" required>
        
        <label>Title:</label>
        <input type="text" name="title" required>
        
        <label>Address:</label>
        <textarea name="address" required></textarea>
        
        <label>Message:</label>
        <textarea name="message" required></textarea>

        <button type="button" id="generate-letter">Generate Letter</button>
        <button type="button" id="generate_pdf">Download as PDF</button>
		 <button type="button" id="auto-fill">Auto-Fill</button> <!-- New Auto-Fill Button -->
    </form>

    <div id="letter-output"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('wplg_letter_form', 'wplg_letter_form');

// AJAX handler for letter preview
function wplg_generate_letter() {
    if (!isset($_POST['data'])) {
        wp_send_json_error('Missing data');
    }

    $data = $_POST['data'];
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/letter-template.php';
    $letter_html = ob_get_clean();

    wp_send_json_success($letter_html);
}
add_action('wp_ajax_wplg_generate_letter', 'wplg_generate_letter');
add_action('wp_ajax_nopriv_wplg_generate_letter', 'wplg_generate_letter');

///
function wplg_generate_pdf() {
    if (!isset($_POST['letter_content']) || empty($_POST['letter_content'])) {
        wp_die('Error: No letter content received.');
    }

    $letter_content = stripslashes($_POST['letter_content']);
    $letter_content = wp_kses_post($letter_content);

    if (!class_exists('TCPDF')) {
        require_once plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php';
    }

    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Letter Generator');
    $pdf->SetTitle('Generated Letter');
    $pdf->SetMargins(20, 20, 20);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->writeHTML($letter_content, true, false, true, false, '');

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Generated_Letter.pdf"');
    $pdf->Output('Generated_Letter.pdf', 'I');
    exit();
}
add_action('wp_ajax_wplg_generate_pdf', 'wplg_generate_pdf');
add_action('wp_ajax_nopriv_wplg_generate_pdf', 'wplg_generate_pdf');

// Add Main Menu and Submenu
function wplg_add_admin_menu() {
    // Main Menu (just a container, no page)
    add_menu_page(
        'Letter Generator', // Page title
        'Letter Generator', // Menu title
        'manage_options', // Capability
        'wplg-main-menu', // Unique slug
        '__return_false', // No page assigned
        'dashicons-admin-customizer', // Icon
        20 // Position
    );

    // Submenu - Letter PDF Generate (Settings Page)
    add_submenu_page(
        'wplg-main-menu', // Parent menu slug
        'Letter PDF Generate', // Page title
        'Letter PDF Generate', // Submenu title
        'manage_options', // Capability
        'wplg-settings', // Unique slug for settings page
        'wplg_settings_page' // Callback function
    );

    // Remove auto-generated submenu
    remove_submenu_page('wplg-main-menu', 'wplg-main-menu');
}
add_action('admin_menu', 'wplg_add_admin_menu');


// Display the Settings Page
function wplg_settings_page() {
    ?>
    <div class="wrap">

        <form method="post" action="options.php">
            <?php
            settings_fields('wplg_settings_group');
            do_settings_sections('wplg-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


// Register Plugin Settings
function wplg_register_settings() {
    // Register fields
    register_setting('wplg_settings_group', 'wplg_font_color');
    register_setting('wplg_settings_group', 'wplg_heading_font_color');
    register_setting('wplg_settings_group', 'wplg_font_size');
    register_setting('wplg_settings_group', 'wplg_font_family');
    register_setting('wplg_settings_group', 'wplg_custom_css');


    // Add settings section
    add_settings_section('wplg_styling_section', 'Styling Options', null, 'wplg-settings');

    // Add individual settings fields
    add_settings_field('wplg_font_color', 'Font Color', 'wplg_font_color_callback', 'wplg-settings', 'wplg_styling_section');
    add_settings_field('wplg_heading_font_color', 'Form Heading Font Color', 'wplg_heading_font_color_callback', 'wplg-settings', 'wplg_styling_section');
    add_settings_field('wplg_font_size', 'Font Size', 'wplg_font_size_callback', 'wplg-settings', 'wplg_styling_section');
    add_settings_field('wplg_font_family', 'Font Family', 'wplg_font_family_callback', 'wplg-settings', 'wplg_styling_section');
    add_settings_field('wplg_custom_css', 'Custom CSS', 'wplg_custom_css_callback', 'wplg-settings', 'wplg_styling_section');


    // Add Shortcodes Section
    add_settings_section('wplg_shortcode_section', 'Available Shortcodes', null, 'wplg-settings');
    add_settings_field('wplg_shortcodes', 'Shortcode List', 'wplg_shortcodes_callback', 'wplg-settings', 'wplg_shortcode_section');
}
add_action('admin_init', 'wplg_register_settings');

// Font Color Field
function wplg_font_color_callback() {
    $color = get_option('wplg_font_color', '#000000');
    echo '<input type="color" name="wplg_font_color" value="' . esc_attr($color) . '">';
}

// Font Size Field
function wplg_font_size_callback() {
    $size = get_option('wplg_font_size', '16px');
    echo '<input type="text" name="wplg_font_size" value="' . esc_attr($size) . '" placeholder="e.g., 16px">';
}

// Font Family Field
function wplg_font_family_callback() {
    $font = get_option('wplg_font_family', 'Arial');
    echo '<select name="wplg_font_family">
        <option value="Arial" ' . selected($font, 'Arial', false) . '>Arial</option>
        <option value="Times New Roman" ' . selected($font, 'Times New Roman', false) . '>Times New Roman</option>
        <option value="Verdana" ' . selected($font, 'Verdana', false) . '>Verdana</option>
        <option value="Courier New" ' . selected($font, 'Courier New', false) . '>Courier New</option>
    </select>';
}

// Form Heading Font Color Field
function wplg_heading_font_color_callback() {
    $heading_color = get_option('wplg_heading_font_color', '#000000');
    echo '<input type="color" name="wplg_heading_font_color" value="' . esc_attr($heading_color) . '">';
}

// Custom CSS Field
function wplg_custom_css_callback() {
    $css = get_option('wplg_custom_css', '');
    echo '<textarea name="wplg_custom_css" rows="6" cols="50" style="width:100%;">' . esc_textarea($css) . '</textarea>';
}

// Shortcode List Display
function wplg_shortcodes_callback() {
    echo '<ul>
        <li><code>[wplg_letter_form]</code> - Displays the full letter form</li>
        <li><code>[wplg_letter_output]</code> - Displays only the letter output</li>
        <li><code>[wplg_download_button]</code> - Displays only the download button</li>
    </ul>';
}

// Apply Custom Styling in Frontend
function wplg_apply_custom_styling() {
    $custom_css = get_option('wplg_custom_css', '');
    $font_color = get_option('wplg_font_color', '#000000');
    $font_size = get_option('wplg_font_size', '16px');
    $font_family = get_option('wplg_font_family', 'Arial');
    $heading_color = get_option('wplg_heading_font_color', '#000000');

    echo '<style>
        .wplg-letter-generator, #letter-output {
            color: ' . esc_attr($font_color) . ';
            font-size: ' . esc_attr($font_size) . ';
            font-family: ' . esc_attr($font_family) . ';
        }
        .wplg-letter-generator h2 {
            color: ' . esc_attr($heading_color) . ';
        }
        ' . esc_html($custom_css) . '
    </style>';
}

add_action('wp_head', 'wplg_apply_custom_styling');




