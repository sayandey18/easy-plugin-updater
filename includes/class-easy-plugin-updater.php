<?php

/**
 * Prevent direct access to this file.
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is dedicated for update WordPress plugin from 
 * external web server.
 * 
 * @since   1.0.0
 * @package EasyPluginUpdater
 */

if( !class_exists( 'EasyPluginUpdater' ) ) {
    class EasyPluginUpdater {
        private string $slug;
        private string $version;
        private string $plugin_file;

        /**
         * Initialize plugin update and set up hooks.
         * 
         * @since 1.0.0
         * @return void
         */
        public function epup_update() {
            $this->version = defined( 'EPUP_PLUGIN_VERSION' ) ? EPUP_PLUGIN_VERSION : '1.0.0';
            $this->slug = defined( 'EPUP_PLUGIN_SLUG' ) ? EPUP_PLUGIN_SLUG : plugin_basename( __DIR__ );
            $this->plugin_file = defined( 'EPUP_PLUGIN_FILE' ) ? EPUP_PLUGIN_FILE : plugin_basename( __FILE__ );

            add_filter( 'plugins_api', [ $this, 'epup_plugin_info' ], 15, 3 );
            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'epup_plugin_update' ], 15, 1 );
        }

        /**
         * Checks for plugin updates and modifies the transient data 
         * if a new version is available.
         * 
         * @since  1.0.0
         * @return object
         */
        public function epup_plugin_update( $transient ) {
            global $pagenow;

            if ( !is_object( $transient ) ) {
                $transient = new stdClass();
            }

            if ( !empty( $transient->response[$this->plugin_file] ) ) {
                return $transient;
            }

            $api_res = $this->epup_plugin_request();

            if ( false !== $api_res && is_object( $api_res ) && isset( $api_res->version ) ) {
                if ( version_compare( $this->version, $api_res->version, '<' ) ) {
                    // Set up the expected properties for the update API.
                    $api_res->slug = $this->slug;
                    $api_res->plugin = $this->plugin_file;

                    $transient->response[ $this->plugin_file ] = $api_res;
                } else {
                    // Support auto-updates by populating no_update property.
                    $transient->no_update[ $this->plugin_file ] = $api_res;
                }
            }

            $transient->last_checked = time();
		    $transient->checked[$this->plugin_file] = $this->version;

            return $transient;
        }

        /**
         * Provides plugin information to WordPress when requested.
         * 
         * @since  1.0.0
         * @return mixed|false
         */
        public function epup_plugin_info( $result, $action, $args ) {
            // Do nothing if not getting plugin information.
			if( 'plugin_information' !== $action ) {
				return $result;
			}

            // Do nothing if the plugin slug not matched.
			if( !isset( $args->slug ) || $args->slug !== $this->slug ) {
				return $result;
			}

            // Get the transient where we store the api request for 24 hours.
            $trans_api_res = $this->epup_get_version_cache();

            // If we have no transient-saved value, run the API to set a fresh transient.
            if ( empty( $trans_api_res ) ) {
                $remote_api_res = $this->epup_plugin_request();

                // Expires in 3 hours.
                $this->epup_set_version_cache( $remote_api_res );

                if ( false !== $remote_api_res ) {
                    $result = $remote_api_res;
                }
            } else {
                $result = $trans_api_res;
            }

            // Convert sections into an associative array.
            if ( isset( $result->sections ) && !is_array( $result->sections ) ) {
                $result->sections = $this->epup_object_to_array( $result->sections );
            }
    
            // Convert banners into an associative array.
            if ( isset( $result->banners ) && !is_array( $result->banners ) ) {
                $result->banners = $this->epup_object_to_array( $result->banners );
            }
    
            // Convert icons into an associative array.
            if ( isset( $result->icons ) && !is_array( $result->icons ) ) {
                $result->icons = $this->epup_object_to_array( $result->icons );
            }
    
            // Convert contributors into an associative array.
            if ( isset( $result->contributors ) && !is_array( $result->contributors ) ) {
                $result->contributors = $this->epup_object_to_array( $result->contributors );
            }
    
            if ( !isset( $result->plugin ) ) {
                $result->plugin = $this->plugin_file;
            }

            return $result;
        }

        /**
         * Fetch latest plugin info from plugin API.
         * 
         * @since  1.0.0
         * @return object|false
         */
        public function epup_plugin_request() {

            $response = $this->epup_get_version_cache();

            if ( false === $response ) {
                // Get the updater API endpoint.
                $endpoint = defined( 'EPUP_UPDATER_API_URL' ) ? EPUP_UPDATER_API_URL : 'https://wp.serverhome.biz/';

                // Send API request for plugin info.
                $request = wp_remote_get(
                    $endpoint . 'epup/manifest.json',
                    array(
                        'timeout' => 15,
                        'headers' => array(
                            'Accept' => 'application/json'
                        )
                    )
                );

                // Return false if there is WP_Error or API error. 
                if(
                    is_wp_error( $request )
                    || 200 !== wp_remote_retrieve_response_code( $request )
                    || empty( wp_remote_retrieve_body( $request ) )
                ) {
                    return false;
                }

                // Get the response from API request.
                $response = wp_remote_retrieve_body( $request );

                $this->epup_set_version_cache( json_decode( $response ) );
            }
            
            return $response;
        }

        /**
         * Get the version info from the cache, if it exists.
         * 
         * @since  1.0.0
         * @return object
         */
        public function epup_get_version_cache( $cache_key = '' ) {
            if ( empty( $cache_key ) ) {
                $cache_key = $this->epup_get_cache_key();
            }

            $cache = get_option( $cache_key );

            // Cache is expired.
            if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
                return false;
            }

            $cache['value'] = json_decode( $cache['value'] );

            // Convert icons and banners to arrays if present.
            foreach ( ['icons', 'banners'] as $key ) {
                if ( !empty( $cache['value']->$key ) ) {
                    $cache['value']->$key = (array) $cache['value']->$key;
                }
            }

            if ( !empty( $cache['value']->version ) ) {
                $cache['value']->new_version = $cache['value']->version;
            }

            if ( !empty( $cache['value']->download_link ) ) {
                $cache['value']->package = $cache['value']->download_link;
            }

            return $cache['value'];
        }

        /**
         * Adds the plugin version information to the database.
         * 
         * @since  1.0.0
         * @return void
         */
        public function epup_set_version_cache( $value = '', $cache_key = '' ) {

            if ( empty( $cache_key ) ) {
                $cache_key = $this->epup_get_cache_key();
            }

            $data = array(
                'timeout' => strtotime( '+3 hours', time() ),
                'value'   => wp_json_encode( $value ),
            );

            update_option( $cache_key, $data, 'no' );
        }

        /**
         * Convert some objects to arrays when injecting data into the update API
         * 
         * @since  1.0.0
         * @return array
         */
        private function epup_object_to_array( $data ) {
            if ( !is_array( $data ) && !is_object( $data ) ) {
                return [];
            }

            $new_data = [];
            foreach ( $data as $key => $value ) {
                $new_data[$key] = is_object( $value ) ? $this->epup_object_to_array( $value ) : $value;
            }

            return $new_data;
        }

        /**
         * Gets the unique key (option name) for a plugin.
         *
         * @since  1.0.0
         * @return string
         */
        private function epup_get_cache_key() {
            $string = $this->slug;
            return 'epup_key_' . md5( serialize( $string ) );
        }
    }
}