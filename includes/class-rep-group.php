<?php
namespace RepGroup;

class Rep_Group {
    private $post_type;
    private $asset_manager;
    private $shortcode;
    private $import_export;

    public function __construct() {
        $this->init_components();
    }

    private function init_components() {
        $this->post_type = new Post_Type();
        $this->asset_manager = new Asset_Manager();
        $this->shortcode = new Shortcode();
        $this->import_export = new Import_Export();
    }
} 