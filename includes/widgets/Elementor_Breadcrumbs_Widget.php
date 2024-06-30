<?php

namespace BCFE\Widgets;

use \Elementor\Widget_Base;
use BCFE\Breadcrumbs;

class Elementor_Breadcrumbs_Widget extends Widget_Base
{

    public function get_name()
    {
        return 'breadcrumbs-for-elementor';
    }

    public function get_title()
    {
        return esc_html__('Breadcrumbs', 'elementor-breadcrumbs-widget');
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
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'elementor-breadcrumbs-widget'),
            ]
        );

        $this->add_control(
            'show_homepage',
            [
                'label' => esc_html__('Show Homepage', 'elementor-breadcrumbs-widget'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Show', 'elementor'),
                'label_off' => esc_html__('Hide', 'elementor'),
                'return_value' => 'yes',
                'default' => 'yes',
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
                ]
            ]
        );

        $this->add_control(
            'divider_icon',
            [
                'label' => esc_html__('Divider Icon', 'elementor-breadcrumbs-widget'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-angle-right',
                    'library' => 'fa-solid',
                ],
                'skin' => "inline",
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__('Style', 'elementor-breadcrumbs-widget'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'breadcrumbs_typography',
                'label' => esc_html__('Breadcrumbs Typography', 'elementor-breadcrumbs-widget'),
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
                'label' => esc_html__('Breadcrumbs Color', 'elementor-breadcrumbs-widget'),
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
                'label' => esc_html__('Breadcrumbs Color', 'elementor-breadcrumbs-widget'),
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
                'label' => esc_html__('Breadcrumbs Color', 'elementor-breadcrumbs-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-color-active: {{VALUE}};'
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();


        $this->add_control(
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
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => esc_html__('Icon Color', 'elementor-breadcrumbs-widget'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-icon-color: {{VALUE}};',
                ]
            ]
        );

        $this->add_control(
            'space_between',
            [
                'label' => esc_html__('Space Between', 'elementor   '),
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
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}}' => '--breadcrumbs-for-elementor-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_trans',
            [
                'label' => esc_html__('Space Between', 'elementor-breadcrumbs-widget'),
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

    protected function get_breadcrumbs()
    {
        if(defined('THE_SEO_FRAMEWORK_PRESENT')){
            return \The_SEO_Framework\Meta\Breadcrumbs::get_breadcrumb_list();
        }else{
            return Breadcrumbs::get_breadcrumb_list();
        }
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $show_homepage = $settings['show_homepage'] === 'yes';
        $divider_icon = $settings['divider_icon'];
        $title_homepage = $settings["title_homepage"];

        $crumbs = $this->get_breadcrumbs();
        $count = count($crumbs);
        $items = [];

        if($show_homepage){
            $crumbs[0]['name'] = $title_homepage;
        }

        if (1 === $count) {
            $items[] = sprintf(
                '<span aria-current="page">%s</span>',
                esc_html($crumbs[0]['name'])
            );
        } else {
            foreach ($crumbs as $i => $crumb) {
                if (($count - 1) === $i) {
                    $items[] = sprintf(
                        '<span aria-current="page">%s</span>',
                        esc_html($crumb['name'])
                    );
                } else {
                    $items[] = sprintf(
                        '<a href="%s">%s</a>',
                        esc_url($crumb['url']),
                        esc_html($crumb['name'])
                    );
                }
            }
        } ?>

        <nav class="breadcrumbs" aria-label="Breadcrumbs" data-crumbs="<?= esc_attr(json_encode($items)) ?>" data-settings="<?= esc_attr(json_encode($settings)) ?>">
            <ol>
                <?php foreach ($items as $index => $item): ?>
                    <li class="breadcrumbs-item">
                        <?= $item; ?>
                        <?php if ($index < count($items) - 1) {
                            \Elementor\Icons_Manager::render_icon($divider_icon, ['aria-hidden' => 'true']);
                        } ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        var settings = settings,
        show_homepage = settings.show_homepage === 'yes',
        title_homepage = settings.title_homepage,
        crumbs = <?php echo json_encode($this->get_breadcrumbs()); ?>,
        items = [],
        iconHTML = elementor.helpers.renderIcon( view, settings.divider_icon, { 'aria-hidden': true }, 'i' , 'object' );;

        if (show_homepage) {
            crumbs[0]['name'] = _.escape(title_homepage);
        }else{
            crumbs.splice(0,1);
        }

        var count = crumbs.length;
        console.log(crumbs);
        if (1 === count) {
        items.push('<span aria-current="page">' + crumbs[0].name + '</span>');
        } else {
        _.each(crumbs, function(crumb, i) {
        if ((count - 1) === i) {
        items.push('<span aria-current="page">' + crumb.name + '</span>');
        } else {
        items.push('<a href="' + _.escape(crumb.url) + '">' + _.escape(crumb.name) + '</a>');
        }
        });
        }
        console.log(items);
        #>

        <nav class="breadcrumbs" aria-label="Breadcrumbs" data-crumbs="{{ JSON.stringify(items) }}" data-settings="{{ JSON.stringify(settings) }}">
            <ol>
                <# _.each(items, function(item, index) { #>
                <li class="breadcrumbs-item">
                    {{{ item }}}
                    <# if (index < items.length - 1) { #>
                    {{{ iconHTML.value }}}
                    <# } #>
                </li>
                <# }); #>
            </ol>
        </nav>
        <?php
    }
}