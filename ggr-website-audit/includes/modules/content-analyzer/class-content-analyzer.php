<?php

if (! defined('ABSPATH')) exit;

class GGRWA_Content_Analyzer {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            'ggrwa-audit-dashboard',
            'Content Analyzer',
            'Content Analyzer',
            'manage_options',
            'ggrwa-content-analyzer',
            [$this, 'render']
        );
    }

    public function render() {
        echo '<div class="wrap"><h1>Content Analyzer</h1></div>';
    }
}