<?php
if (!defined('ABSPATH')) {
    exit;
}

class GF_UTM_Tracker_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;
    private $github_url = 'https://api.github.com/repos/{owner}/{repo}';
    private $access_token;
    private $authorize_token;

    public function __construct($file) {
        $this->file = $file;
        $this->basename = plugin_basename($file);
        $this->active = is_plugin_active($this->basename);

        // Set the GitHub repository details
        $this->github_url = str_replace(
            array('{owner}', '{repo}'),
            array('RayTurk', 'gravityforms-utm-tracker'),
            $this->github_url
        );

        // Optional: Add GitHub token for private repos
        $this->authorize_token = false; // Change to true if using private repo
        $this->access_token = ''; // Add your GitHub token here if using private repo

        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    public function modify_transient($transient) {
        if (!isset($transient->checked)) {
            return $transient;
        }

        // Get plugin info
        $plugin_data = get_plugin_data($this->file);
        $this->plugin = $plugin_data;

        // Check GitHub for updates
        $remote_version = $this->get_remote_version();
        $current_version = $plugin_data['Version'];

        if ($remote_version && version_compare($current_version, $remote_version, '<')) {
            $res = new stdClass();
            $res->slug = $this->basename;
            $res->plugin = $this->basename;
            $res->new_version = $remote_version;
            $res->tested = '6.4.3'; // Update this as needed
            $res->package = $this->get_remote_package();
            $transient->response[$this->basename] = $res;
        }

        return $transient;
    }

    private function get_remote_version() {
        $request = $this->get_repository_info();
        if (!is_wp_error($request)) {
            $this->github_response = json_decode($request['body']);
            if (isset($this->github_response->tag_name)) {
                return ltrim($this->github_response->tag_name, 'v');
            }
        }
        return false;
    }

    private function get_remote_package() {
        if (isset($this->github_response->zipball_url)) {
            return $this->github_response->zipball_url;
        }
        return false;
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $args = array();
            if ($this->authorize_token) {
                $args['headers']['Authorization'] = "token {$this->access_token}";
            }

            $request = wp_remote_get($this->github_url . '/releases/latest', $args);

            return (!is_wp_error($request)) ? $request : false;
        }
    }

    public function plugin_popup($result, $action, $args) {
        if (!isset($args->slug) || $args->slug !== dirname($this->basename)) {
            return $result;
        }

        // Get plugin & GitHub release information
        $plugin_data = get_plugin_data($this->file);
        $this->plugin = $plugin_data;
        $this->get_repository_info();

        // Set plugin info
        $plugin_info = new stdClass();
        $plugin_info->name = $plugin_data['Name'];
        $plugin_info->slug = dirname($this->basename);
        $plugin_info->version = $this->github_response->tag_name;
        $plugin_info->author = $plugin_data['Author'];
        $plugin_info->homepage = $plugin_data['PluginURI'];
        $plugin_info->requires = '5.8';
        $plugin_info->tested = '6.4.3';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $this->github_response->published_at;
        $plugin_info->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => $this->github_response->body
        );
        $plugin_info->download_link = $this->github_response->zipball_url;

        return $plugin_info;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Get the plugin directory name (without -main or other GitHub additions)
        $plugin_dir = dirname($this->basename);

        // Get the exact folder name that GitHub created
        $github_folder = $result['destination'];

        // The desired final destination
        $proper_destination = WP_PLUGIN_DIR . '/' . $plugin_dir;

        // Delete existing plugin directory if it exists
        if ($wp_filesystem->is_dir($proper_destination)) {
            $wp_filesystem->delete($proper_destination, true);
        }

        // Move from GitHub folder to proper location
        $wp_filesystem->move($github_folder, $proper_destination);
        $result['destination'] = $proper_destination;

        // Reactivate if it was active
        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}