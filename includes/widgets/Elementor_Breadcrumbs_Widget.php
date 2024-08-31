<?php

namespace BCFE\Widgets;

use BCFE\Breadcrumbs;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Elementor_Breadcrumbs_Widget extends \Elementor\Widget_Base
{
    protected function is_dynamic_content(): bool
    {
        return true;
    }

    public function get_name()
    {
        return 'breadcrumbs-for-elementor';
    }

    public function get_title()
    {
        return esc_html__('Breadcrumbs', 'breadcrumbs-for-elementor');
    }

    public function get_icon()
    {
        return 'eicon-navigation-horizontal';
    }

    public function get_categories()
    {
        return ['general'];
    }

    public function get_style_depends()
    {
        return ['elementor-breadcrumbs-widget-style'];
    }

    protected function register_controls()
    {
        // Content Section
        $this->content_section();
        // Style Section
        $this->style_section();
    }


    protected function get_breadcrumbs(): array
    {
        $args = null;
        if (\Elementor\Plugin::$instance->editor->is_edit_mode() && (!empty($_POST["editor_post_id"]) || !empty($_POST["initial_document_id"]))) {
            $post_id_for_breadcrumbs = !empty($_POST["editor_post_id"]) ? $_POST["editor_post_id"] :$_POST["initial_document_id"];
            $post_id_for_breadcrumbs = sanitize_key(wp_unslash($post_id_for_breadcrumbs));
            $args = [
                "id" => $post_id_for_breadcrumbs,
            ];
        }
        return defined('THE_SEO_FRAMEWORK_PRESENT') ?
            \The_SEO_Framework\Meta\Breadcrumbs::get_breadcrumb_list($args)
            : Breadcrumbs::get_breadcrumb_list($args);
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $show_homepage = $settings['show_homepage'] === 'yes';
        $divider_icon = $settings['divider_icon'];
        $divider_text = $settings['divider_text'];
        $title_homepage = $settings["title_homepage"];
        $is_divider_icon = $settings["divider_icon_or_text"] === "icon";

        $crumbs = $this->get_breadcrumbs();

        if ($show_homepage) {
            $crumbs[0]['name'] = $title_homepage;
        } else {
            unset($crumbs[0]);
        }

        $count = count($crumbs);
        if (!empty($crumbs)):?>
            <nav class="breadcrumbs" aria-label="Breadcrumbs">
                <ol>
                    <?php foreach ($crumbs as $i => $crumb): ?>
                        <li class="breadcrumbs-item">
                            <?php if (($count - 1) === $i): ?>
                                <span aria-current="page"><?php echo esc_html($crumb['name']); ?></span>
                            <?php else: ?>
                                <a href="<?php echo esc_url($crumb['url']); ?>"><?php echo esc_html($crumb['name']); ?></a>
                                <?php if ($is_divider_icon): ?>
                                    <?php \Elementor\Icons_Manager::render_icon($divider_icon, ['aria-hidden' => 'true']); ?>
                                <?php else: ?>
                                    <span class="divider-text"><?php echo esc_html($divider_text); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif;
    }


    protected function content_template()
    {
        ?>
        <#
        var show_homepage = settings.show_homepage === 'yes',
        title_homepage = settings.title_homepage,
        crumbs = <?php echo json_encode($this->get_breadcrumbs()); ?>,
        isDividerIcon = settings.divider_icon_or_text === "icon",
        divider = isDividerIcon ?
        elementor.helpers.renderIcon(view, settings.divider_icon, { 'aria-hidden': true }, 'i', 'object') :
        { value: settings.divider_text };

        if (show_homepage) {
        crumbs[0].name = title_homepage;
        } else {
        crumbs.splice(0, 1);
        }

        var count = crumbs.length;
        if(count){ #>
            <nav class="breadcrumbs" aria-label="Breadcrumbs">
                <ol>
                    <# for (let i = 0; i < count; i++) {
                    let crumb = crumbs[i];
                    #>
                    <li class="breadcrumbs-item">
                        <# if ((count - 1) === i) { #>
                        <span aria-current="page">{{{ _.escape(crumb.name) }}}</span>
                        <# } else { #>
                        <a href="{{{ _.escape(crumb.url) }}}">{{{ _.escape(crumb.name) }}}</a>
                        <# if (!isDividerIcon) { #>
                        <span class="divider-text">
                                <# } #>
                                    {{{ divider.value }}}
                                <# if (!isDividerIcon) { #>
                                    </span>
                        <# } #>
                        <# } #>
                    </li>
                    <# } #>
                </ol>
            </nav>
        <# } #>
        <?php
    }

    private function content_section()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'breadcrumbs-for-elementor'),
            ]
        );

        $this->add_control(
            'show_homepage',
            [
                'label' => esc_html__('Show Homepage', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Show', 'elementor'),
                'label_off' => esc_html__('Hide', 'elementor'),
                'return_value' => 'yes',
                'default' => 'yes',
                "render_type" => "template"
            ]
        );

        $this->add_control(
            'title_homepage',
            [
                'label' => esc_html__('Title for Home', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Home', 'breadcrumbs-for-elementor'),
                'placeholder' => esc_html__('Choose the name to be displayed for the frontpage', 'breadcrumbs-for-elementor'),
                'condition' => [
                    "show_homepage" => "yes"
                ],
                "render_type" => "template"
            ]
        );

        $this->add_control(
            'divider_icon_or_text',
            [
                'label' => esc_html__('Divider Type', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Icon', 'elementor'),
                'label_off' => esc_html__('Text', 'elementor'),
                'return_value' => 'icon',
                'default' => 'icon',
                "render_type" => "template"
            ]
        );

        $this->add_control(
            'divider_icon',
            [
                'label' => esc_html__('Divider Icon', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-angle-right',
                    'library' => 'fa-solid',
                ],
                "condition" => [
                    "divider_icon_or_text" => "icon"
                ],
                "render_type" => "template"
            ]
        );

        $this->add_control(
            'divider_text',
            [
                'label' => esc_html__('Divider Icon', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ">",
                'placeholder' => esc_html__('Text displayed between breadcrumb items', 'breadcrumbs-for-elementor'),
                "condition" => [
                    "divider_icon_or_text!" => "icon"
                ],
                "render_type" => "template"
            ]
        );

        $this->end_controls_section();

    }

    private function style_section()
    {
        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__('Style', 'breadcrumbs-for-elementor'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'breadcrumbs_typography',
                'label' => esc_html__('Breadcrumbs Typography', 'breadcrumbs-for-elementor'),
                'selector' => '{{WRAPPER}} .breadcrumbs',
            ]
        );

        $this->start_controls_tabs(
            'breadcrumb_color_tabs'
        );

        $this->start_controls_tab(
            'breadcrumb_color_tab_normal',
            [
                'label' => esc_html__('Normal', 'elementor'),
            ]
        );

        $this->add_control(
            'breadcrumbs_color',
            [
                'label' => esc_html__('Breadcrumbs Color', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'breadcrumb_color_tab_hover',
            [
                'label' => esc_html__('Hover', 'elementor'),
            ]
        );

        $this->add_control(
            'breadcrumbs_color_hover',
            [
                'label' => esc_html__('Breadcrumbs Color', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-color-hover: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'breadcrumb_color_tab_active',
            [
                'label' => esc_html__('Active', 'elementor'),
            ]
        );

        $this->add_control(
            'breadcrumbs_color_active',
            [
                'label' => esc_html__('Breadcrumbs Color', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-color-active: {{VALUE}};'
                ],
                "render_type" => "none"
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'justify_content',
            [
                'label' => esc_html__('Justify Content', 'elementor'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'label_block' => true,
                'default' => 'flex-start',
                'options' => [
                    'flex-start' => [
                        'title' => esc_html__('Start', 'elementor'),
                        'icon' => 'eicon-flex eicon-justify-start-h',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elementor'),
                        'icon' => 'eicon-flex eicon-justify-center-h',
                    ],
                    'flex-end' => [
                        'title' => esc_html__('End', 'elementor'),
                        'icon' => 'eicon-flex eicon-justify-end-h',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-justify-content: {{VALUE}};',
                ],
                'responsive' => true,
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label' => esc_html__('Text Align', 'elementor'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'label_block' => true,
                'default' => 'left',
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'elementor'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elementor'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'elementor'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-text-align: {{VALUE}};',
                ],
                'responsive' => true,
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label' => esc_html__('Icon Size', 'elementor'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 1000,
                        'step' => 5,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-icon-size: {{SIZE}}{{UNIT}};',
                ],
                "condition" => [
                    "divider_icon_or_text" => "icon"
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'divider_typography',
                'label' => esc_html__('Divider Typography', 'breadcrumbs-for-elementor'),
                'selector' => '{{WRAPPER}} .breadcrumbs .divider-text',
                "condition" => [
                    "divider_icon_or_text!" => "icon"
                ],
            ]
        );

        $this->add_control(
            'divider_color',
            [
                'label' => esc_html__('Divider Color', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-divider-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'space_between',
            [
                'label' => esc_html__('Space Between', 'elementor'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_vertical_correction',
            [
                'label' => esc_html__('Icon Vertical Adjustment', 'breadcrumbs-for-elementor'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em', 'rem', 'custom'],
                'range' => [
                    'px' => [
                        'min' => -10,
                        'max' => 10,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-icon-translatey: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

}
