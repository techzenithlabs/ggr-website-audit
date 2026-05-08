<?php

if (! defined('ABSPATH')) exit;

class GGRWA_Conversion_Audit {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Conversion Audit',
            'Conversion Audit',
            'manage_options',
            'ggrwa-conversion-audit',
            [$this, 'render']
        );
    }

    public function render() {
        echo '<div class="wrap"><h1>Conversion Audit</h1></div>';
    }
}