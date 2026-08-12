<?php
/**
 * Elementor widget for dynamic shopping centre tiles.
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Directorist_Shopping_Centres_Elementor_Widget extends Widget_Base {
    public function get_name() {
        return 'directorist-shopping-centres';
    }

    public function get_title() {
        return esc_html__( 'Shopping Centres', 'directorist-shopping-centres' );
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return [ 'directorist-shopping-centres', 'general' ];
    }

    public function get_keywords() {
        return [ 'directorist', 'shopping', 'centre', 'center', 'deals', 'store' ];
    }

    public function get_style_depends() {
        return [ 'directorist-shopping-centres' ];
    }

    protected function register_controls() {
        $this->register_content_controls();
        $this->register_heading_style_controls();
        $this->register_grid_style_controls();
        $this->register_card_style_controls();
        $this->register_image_style_controls();
        $this->register_name_style_controls();
        $this->register_count_style_controls();
    }

    private function register_content_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__( 'Shopping Centres', 'directorist-shopping-centres' ),
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => esc_html__( 'Title', 'directorist-shopping-centres' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__( 'Shop deals by shopping centre', 'directorist-shopping-centres' ),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label'        => esc_html__( 'Show Title', 'directorist-shopping-centres' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Show', 'directorist-shopping-centres' ),
                'label_off'    => esc_html__( 'Hide', 'directorist-shopping-centres' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label'          => esc_html__( 'Columns', 'directorist-shopping-centres' ),
                'type'           => Controls_Manager::SELECT,
                'default'        => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options'        => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'selectors'      => [
                    '{{WRAPPER}} .dsc-centres' => '--dsc-columns: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'hide_empty',
            [
                'label'        => esc_html__( 'Hide Empty Centres', 'directorist-shopping-centres' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'directorist-shopping-centres' ),
                'label_off'    => esc_html__( 'No', 'directorist-shopping-centres' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'number',
            [
                'label'       => esc_html__( 'Limit', 'directorist-shopping-centres' ),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 100,
                'step'        => 1,
                'default'     => 0,
                'description' => esc_html__( 'Use 0 to show all shopping centres.', 'directorist-shopping-centres' ),
            ]
        );

        $this->end_controls_section();
    }

    private function register_heading_style_controls() {
        $this->start_controls_section(
            'section_heading_style',
            [
                'label'     => esc_html__( 'Heading', 'directorist-shopping-centres' ),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'heading_alignment',
            [
                'label'     => esc_html__( 'Alignment', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'left'   => [
                        'title' => esc_html__( 'Left', 'directorist-shopping-centres' ),
                        'icon'  => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__( 'Center', 'directorist-shopping-centres' ),
                        'icon'  => 'eicon-text-align-center',
                    ],
                    'right'  => [
                        'title' => esc_html__( 'Right', 'directorist-shopping-centres' ),
                        'icon'  => 'eicon-text-align-right',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .dsc-section-header' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'heading_color',
            [
                'label'     => esc_html__( 'Color', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-section-header h2' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'heading_typography',
                'selector' => '{{WRAPPER}} .dsc-section-header h2',
            ]
        );

        $this->add_responsive_control(
            'heading_spacing',
            [
                'label'      => esc_html__( 'Bottom Spacing', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 120,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-section-header' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_grid_style_controls() {
        $this->start_controls_section(
            'section_grid_style',
            [
                'label' => esc_html__( 'Grid', 'directorist-shopping-centres' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'grid_gap',
            [
                'label'      => esc_html__( 'Gap', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 80,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centres__track' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_card_style_controls() {
        $this->start_controls_section(
            'section_card_style',
            [
                'label' => esc_html__( 'Card', 'directorist-shopping-centres' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label'     => esc_html__( 'Background', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_hover_background',
            [
                'label'     => esc_html__( 'Hover Background', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'card_border',
                'selector' => '{{WRAPPER}} .dsc-centre-card',
            ]
        );

        $this->add_responsive_control(
            'card_radius',
            [
                'label'      => esc_html__( 'Border Radius', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem' ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'card_shadow',
                'selector' => '{{WRAPPER}} .dsc-centre-card',
            ]
        );

        $this->add_responsive_control(
            'card_min_height',
            [
                'label'      => esc_html__( 'Min Height', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range'      => [
                    'px' => [
                        'min' => 120,
                        'max' => 560,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card' => 'min-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_body_padding',
            [
                'label'      => esc_html__( 'Content Padding', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', 'rem' ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_image_style_controls() {
        $this->start_controls_section(
            'section_image_style',
            [
                'label' => esc_html__( 'Image', 'directorist-shopping-centres' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'image_height',
            [
                'label'      => esc_html__( 'Height', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'vh', 'em', 'rem' ],
                'range'      => [
                    'px' => [
                        'min' => 60,
                        'max' => 420,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card__image' => 'min-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'image_fallback_background',
            [
                'label'     => esc_html__( 'Fallback Background', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card__image' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_radius',
            [
                'label'      => esc_html__( 'Border Radius', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem' ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    private function register_name_style_controls() {
        $this->start_controls_section(
            'section_name_style',
            [
                'label' => esc_html__( 'Centre Name', 'directorist-shopping-centres' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'name_color',
            [
                'label'     => esc_html__( 'Color', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card__name' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'name_hover_color',
            [
                'label'     => esc_html__( 'Hover Color', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card:hover .dsc-centre-card__name' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'name_typography',
                'selector' => '{{WRAPPER}} .dsc-centre-card__name',
            ]
        );

        $this->end_controls_section();
    }

    private function register_count_style_controls() {
        $this->start_controls_section(
            'section_count_style',
            [
                'label' => esc_html__( 'Deal Count', 'directorist-shopping-centres' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'count_color',
            [
                'label'     => esc_html__( 'Color', 'directorist-shopping-centres' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .dsc-centre-card__count' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'count_typography',
                'selector' => '{{WRAPPER}} .dsc-centre-card__count',
            ]
        );

        $this->add_responsive_control(
            'count_spacing',
            [
                'label'      => esc_html__( 'Top Spacing', 'directorist-shopping-centres' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', 'rem' ],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 60,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .dsc-centre-card__count' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $title    = ( 'yes' === ( $settings['show_title'] ?? 'yes' ) ) ? ( $settings['title'] ?? '' ) : '';

        echo Directorist_Shopping_Centres::instance()->shopping_centres_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            [
                'title'      => $title,
                'columns'    => $settings['columns'] ?? 3,
                'hide_empty' => ( 'yes' === ( $settings['hide_empty'] ?? 'yes' ) ) ? 1 : 0,
                'number'     => $settings['number'] ?? 0,
            ]
        );
    }
}
