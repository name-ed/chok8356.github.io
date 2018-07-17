<?php
/*
* Exits Module
* Tracks exits out of the knowledge base - to a ticketing system, for example
*
*/


if( !class_exists('HT_KB_Exits') ){

    //define exits URL filter tage
    if(!defined('HKB_EXITS_URL_FILTER_TAG')){
        define('HKB_EXITS_URL_FILTER_TAG', 'ht_kb_generate_exit_url');
    }

	class HT_KB_Exits {		

		//constructor
		function __construct(){
            //redirect
            include_once('php/ht-kb-exits-redirect.php');
			//shortcode
            include_once('php/ht-kb-exits-shortcodes.php');
            //widget
            include_once('php/widget-kb-exits.php');
            //database controller
            include_once('php/ht-kb-exits-database.php');
            $this->exits_database = new HT_KB_Exits_Database();
            //dummy data - for debug only
            include_once('php/ht-kb-exits-dummy-data-creator.php');

            //generate url filter
            add_filter(HKB_EXITS_URL_FILTER_TAG, array( $this, 'generate_exit_url' ), 50, 2 );

            //end of article action hook 
            add_action('ht_kb_end_article', array( $this, 'end_of_article_actions' ) );

            //check hkb database version
            add_action( 'admin_notices', array( $this, 'exit_table_exists_messages' ) );

		}


        /**
        * Displays any database version check warnings / errors
        */
        function exit_table_exists_messages(){
            //only proceed if we are admin and can activate plugins
            if(!is_admin() || !current_user_can('activate_plugins')){
                return;
            }

            $database_check = $this->check_exits_table_exists();

            if( is_admin() && is_wp_error( $database_check ) ){
                $message = $database_check->get_error_message();
                echo '<div class="error"><p>' . $message . '</p></div>';
            }
        }

        /**
        * Check exits table exists
        */
        function check_exits_table_exists(){
            global $wpdb;
            $exits_table_exists = false;
            $exits_table_name = $wpdb->prefix.HT_KB_EXITS_TABLE;
            if($wpdb->get_var("SHOW TABLES LIKE '$exits_table_name'") != $exits_table_name) {
                //exits table does not exist
                //do nothing
            } else {
                //table exists
                $exits_table_exists = true;
            }
            if(! $exits_table_exists && ! get_transient('_ht_kb_just_installed') ){
                return new WP_Error( 'ht-kb-db-exits-not-found', sprintf( __( 'Heroic Knowledge Base Exits table does not exist, please <a href="%s">Deactivate</a> then re-Activate the Heroic Knowledge Base plugin', 'ht-knowledge-base'), admin_url('plugins.php#heroic-knowledge-base') ) );
            }
            return false;
        }
        


        /**
        * Generate exit url
        * Filterable - use ht_kb_generate_exit_url
        * @param (String) $url Exit URL
        * @param (String) $source The location where the filter is called from
        * @return (String) Replacement url
        */

        function generate_exit_url($url, $source='undefined'){
            global $post;
            //add redirect here

            $queried_object_data = hkb_get_queried_object_data();
            
            //security
            $nonce = wp_create_nonce( 'hkb-redirect' );
            //generate url
            $replacement_url = '?' . 'hkb-redirect' .   '&' . 'nonce=' . $nonce . 
                                                        '&' . 'redirect=' . urlencode($url) . 
                                                        '&' . 'otype=' . $queried_object_data['object_type'] . 
                                                        '&' . 'oid=' . $queried_object_data['object_id'] .
                                                        '&' . 'source=' . $source;

            return $replacement_url;
        }

 
        /**
        * Add exit details to the database
        */
        function add_tracked_exit_details_to_db($data){
            $this->exits_database->add_tracked_exit_to_db($data);
        }

        /**
        * Display at end of article actions
        */
        function end_of_article_actions(){
            if(ht_kb_exit_display_at_end_of_article()){
                //show the exit template at the article
                hkb_get_template_part('hkb-exit');
            }            
        }

	
	} //end class
} //end class exists

if(class_exists('HT_KB_Exits')){
	global $ht_kb_exits_init;

	$ht_kb_exits_init = new HT_KB_Exits();

    function ht_kb_exits_add_tracked_exit_details_to_db($data){
        global $ht_kb_exits_init;
        $ht_kb_exits_init->add_tracked_exit_details_to_db($data);
    }
}