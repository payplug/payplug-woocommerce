<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function upme( $upgrader_object, $options ) {

    error_log("\n upme ini \$upgrader_object = ". print_r($upgrader_object,true),3,"./file,log");
    error_log("\n upme \$options = ". print_r($options,true),3,"./file,log");

    $our_plugin = plugin_basename( __FILE__ );

    if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
        foreach( $options['plugins'] as $plugin ) {
            if( $plugin == $our_plugin ) {
                error_log("\n  \$plugin = ". print_r($plugin,true),3,"./file,log");
            }
        }
    }

    error_log("\n upme end = ". print_r(1,true),3,"./file,log");

}

add_action( 'upgrader_process_complete', 'upme', 10, 2 );
