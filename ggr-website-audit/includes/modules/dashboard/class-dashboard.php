<?php

if (!defined('ABSPATH')) exit;

class GGRWA_Dashboard {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 20);
    }

    public function register_menu() {

        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ggrwa-audit-dashboard',
            [$this, 'render']
        );
    }

    public function render() {        
        require GGRWA_PLUGIN_PATH . 'includes/admin/views/dashboard.php';
    }
}