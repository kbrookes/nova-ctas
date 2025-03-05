<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Shortcode {
    private $cta_manager;

    public function __construct($cta_manager) {
        $this->cta_manager = $cta_manager;
        add_shortcode('nova_cta', array($this, 'render'));
    }

    public function render($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'style' => 'default',
            'position' => 'center'
        ), $atts, 'nova_cta');

        if (empty($atts['id'])) {
            return '';
        }

        $cta = $this->cta_manager->get_cta($atts['id']);
        if (!$cta) {
            return '';
        }

        // Add custom styles based on attributes
        $custom_styles = array();
        if ($atts['style'] !== 'default') {
            $custom_styles[] = $this->get_style_rules($atts['style']);
        }
        if ($atts['position'] !== 'center') {
            $custom_styles[] = $this->get_position_rules($atts['position']);
        }

        $output = '<div class="nova-cta" style="' . implode('; ', $custom_styles) . '">';
        $output .= $cta;
        $output .= '</div>';

        return $output;
    }

    private function get_style_rules($style) {
        $styles = array(
            'minimal' => 'border: none; background: none; padding: 0;',
            'boxed' => 'border: 1px solid #ddd; padding: 20px; background: #f9f9f9;',
            'highlighted' => 'border: 2px solid #ff6b6b; padding: 20px; background: #fff;'
        );

        return isset($styles[$style]) ? $styles[$style] : '';
    }

    private function get_position_rules($position) {
        $positions = array(
            'left' => 'float: left; margin-right: 20px;',
            'right' => 'float: right; margin-left: 20px;',
            'center' => 'margin: 20px auto;'
        );

        return isset($positions[$position]) ? $positions[$position] : '';
    }
} 