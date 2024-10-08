<?php

namespace EcomailDeps\Wpify\CustomFields\Implementations;

use WP_Screen;
use WP_User;
use EcomailDeps\Wpify\CustomFields\CustomFields;
/**
 * Class User
 * @package CustomFields\Implementations
 */
final class User extends AbstractImplementation
{
    /** @var int */
    private $user_id;
    /** @var array */
    private $items;
    /**
     * User constructor.
     *
     * @param array $args
     * @param CustomFields $wcf
     */
    public function __construct(array $args, CustomFields $wcf)
    {
        parent::__construct($args, $wcf);
        $args = wp_parse_args($args, array('items' => array(), 'user_id' => null, 'init_priority' => 10));
        $this->items = $args['items'];
        $this->user_id = $args['user_id'];
        add_action('show_user_profile', array($this, 'render_edit_form'));
        add_action('edit_user_profile', array($this, 'render_edit_form'));
        add_action('personal_options_update', array($this, 'save'));
        add_action('edit_user_profile_update', array($this, 'save'));
        add_action('init', array($this, 'register_meta'), $args['init_priority']);
    }
    public function register_meta()
    {
        $items = $this->get_items();
        foreach ($items as $item) {
            register_meta('user', $item['id'], array('type' => $this->get_item_type($item), 'description' => $item['title'], 'single' => \true, 'default' => $item['default'] ?? null));
        }
    }
    public function get_items()
    {
        $items = apply_filters('wcf_user_items', $this->items);
        return $this->prepare_items($items);
    }
    /**
     * @return void
     */
    public function set_wcf_shown(WP_Screen $current_screen)
    {
        $this->wcf_shown = $current_screen->base === 'profile' || $current_screen->base === 'user-edit';
    }
    /**
     * @return void
     */
    public function render_add_form()
    {
        $this->render_fields('add_user');
    }
    /**
     * @return array
     */
    public function get_data()
    {
        return array('object_type' => 'user', 'items' => $this->get_items());
    }
    /**
     * @param WP_User $user
     */
    public function render_edit_form($user)
    {
        $this->set_user($user->ID);
        $this->render_fields('edit_user');
    }
    /**
     * @param number $user_id
     */
    public function set_user($user_id)
    {
        $this->user_id = $user_id;
    }
    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get_field($name)
    {
        return get_user_meta($this->user_id, $name, \true);
    }
    /**
     * @param number $user_id
     */
    public function save($user_id)
    {
        $this->set_user($user_id);
        foreach ($this->get_items() as $item) {
            if (!isset($_POST[$item['id']])) {
                continue;
            }
            $sanitizer = $this->sanitizer->get_sanitizer($item);
            $value = $sanitizer(wp_unslash($_POST[$item['id']]));
            $this->set_field($item['id'], $value);
        }
    }
    /**
     * @param string $name
     * @param string $value
     *
     * @return bool|int|\WP_Error
     */
    public function set_field($name, $value)
    {
        return update_user_meta($this->user_id, $name, $value);
    }
}
