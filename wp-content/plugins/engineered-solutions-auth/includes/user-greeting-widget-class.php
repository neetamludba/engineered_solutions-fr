<?php
/**
 * ESA User Greeting Widget Class for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ESA_User_Greeting_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'esa_user_greeting';
    }

    public function get_title() {
        return __('ESA User Greeting', 'engineered-solutions-auth');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['user', 'greeting', 'login', 'logout', 'authentication'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'engineered-solutions-auth'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_user_name',
            [
                'label' => __('Show User Name', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'engineered-solutions-auth'),
                'label_off' => __('Hide', 'engineered-solutions-auth'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_status',
            [
                'label' => __('Show Status', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'engineered-solutions-auth'),
                'label_off' => __('Hide', 'engineered-solutions-auth'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_buttons',
            [
                'label' => __('Show Login/Logout Buttons', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'engineered-solutions-auth'),
                'label_off' => __('Hide', 'engineered-solutions-auth'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'greeting_text',
            [
                'label' => __('Greeting Text', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Welcome', 'engineered-solutions-auth'),
                'placeholder' => __('Enter greeting text', 'engineered-solutions-auth'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'engineered-solutions-auth'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#007cba',
                'selectors' => [
                    '{{WRAPPER}} .esa-user-greeting-widget' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => __('Text Color', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .esa-user-greeting-widget' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .esa-user-greeting-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'padding',
            [
                'label' => __('Padding', 'engineered-solutions-auth'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => 10,
                    'right' => 20,
                    'bottom' => 10,
                    'left' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .esa-user-greeting-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $show_user_name = $settings['show_user_name'] === 'yes';
        $show_status = $settings['show_status'] === 'yes';
        $show_buttons = $settings['show_buttons'] === 'yes';
        $greeting_text = $settings['greeting_text'] ?: 'Welcome';
        
        ?>
        <div class="esa-user-greeting-widget" data-greeting="<?php echo esc_attr($greeting_text); ?>">
            <div class="esa-user-info">
                <div class="esa-user-details">
                    <?php if ($show_user_name): ?>
                        <p class="esa-user-name" id="esa-user-name"><?php echo esc_html($greeting_text); ?>, User!</p>
                    <?php endif; ?>
                    <?php if ($show_status): ?>
                        <p class="esa-user-status">
                            Status: <span class="esa-status-badge esa-status-guest">Guest User</span>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($show_buttons): ?>
                    <div class="esa-auth-buttons">
                        <button class="esa-btn esa-btn-primary esa-login-icon" onclick="window.esaAuth.showModal()">
                            Sign In
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    protected function _content_template() {
        ?>
        <div class="esa-user-greeting-widget">
            <div class="esa-user-info">
                <div class="esa-user-details">
                    <# if (settings.show_user_name) { #>
                        <p class="esa-user-name" id="esa-user-name">{{{ settings.greeting_text }}}, User!</p>
                    <# } #>
                    <# if (settings.show_status) { #>
                        <p class="esa-user-status">
                            Status: <span class="esa-status-badge esa-status-guest">Guest User</span>
                        </p>
                    <# } #>
                </div>
                <# if (settings.show_buttons) { #>
                    <div class="esa-auth-buttons">
                        <button class="esa-btn esa-btn-primary esa-login-icon">
                            Sign In
                        </button>
                    </div>
                <# } #>
            </div>
        </div>
        <?php
    }
}
