<?php
namespace RepGroup;

class Template_Loader {
    
    public function __construct() {
        add_filter('template_include', [$this, 'load_template']);
    }

    /**
     * Load plugin templates for rep-group post type
     */
    public function load_template($template) {
        // Check if this is a rep-group post type
        if (is_singular('rep-group')) {
            $plugin_template = $this->get_template_path('single-rep-group.php');
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        if (is_post_type_archive('rep-group')) {
            $plugin_template = $this->get_template_path('archive-rep-group.php');
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }

    /**
     * Get template path from plugin
     */
    private function get_template_path($template_name) {
        return REP_GROUP_PATH . 'templates/frontend/' . $template_name;
    }


} 