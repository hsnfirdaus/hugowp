<?php
/**
 * Plugin Name: HugoWP
 * Plugin URI: http://github.com/hsnfirdaus/hugowp
 * Description: Plugin for importing hugo content.
 * Version: 1.0
 * Author: Muhammad Hasan Firdaus
 * Author URI: https://hasanfirdaus.my.id
 */

function hugowp_register_custom_menu(){
    add_menu_page(
        __( 'Hugo Importer', 'hugowp' ),
        'Hugo Importer',
        'import',
        'hugowp',
        function(){
            include __DIR__.'/frontend/admin-page.php';
        },
        'dashicons-format-aside',
        null
    );
}
add_action( 'admin_menu', 'hugowp_register_custom_menu' );
?>