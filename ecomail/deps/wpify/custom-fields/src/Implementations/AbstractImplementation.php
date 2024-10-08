<?php

namespace EcomailDeps\Wpify\CustomFields\Implementations;

use WP_Screen;
use EcomailDeps\Wpify\CustomFields\Api;
use EcomailDeps\Wpify\CustomFields\Parser;
use EcomailDeps\Wpify\CustomFields\Sanitizer;
use EcomailDeps\Wpify\CustomFields\CustomFields;
/**
 * Class AbstractImplementation
 * @package CustomFields\Implementations
 */
abstract class AbstractImplementation
{
    /** @var Parser */
    protected $parser;
    /** @var Sanitizer */
    protected $sanitizer;
    /** @var Api */
    protected $api;
    /** @var CustomFields */
    protected $wcf;
    /** @var bool */
    protected $wcf_shown = \false;
    /** @var string */
    protected $script_handle = '';
    public function __construct(array $args, CustomFields $wcf)
    {
        $this->wcf = $wcf;
        $this->parser = $wcf->get_parser();
        $this->sanitizer = $wcf->get_sanitizer();
        $this->api = $wcf->get_api();
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('current_screen', array($this, 'set_wcf_shown'));
    }
    /**
     * @return void
     */
    public function admin_enqueue_scripts()
    {
        if ($this->wcf_shown) {
            // Enqueue all dependencies needed for TinyMCE editor
            wp_enqueue_script('wp-block-library');
            wp_tinymce_inline_scripts();
            // Enqueue all dependencees needed for code editor
            wp_enqueue_editor();
            wp_enqueue_code_editor(array());
            // Enqueue dependencies needed for media library
            wp_enqueue_media();
            // Enqueue dependencies for WPify Custom Fields
            $this->wcf->get_assets()->enqueue_style('wpify-custom-fields.css', array('wp-components'));
            $this->script_handle = $this->wcf->get_assets()->enqueue_script('wpify-custom-fields.js', array('wp-tinymce'), \true, array('wcf_code_editor_settings' => $this->wcf->get_assets()->get_code_editor_settings(), 'wcf_build_url' => $this->get_build_url()));
        }
    }
    public function get_build_url()
    {
        return $this->wcf->get_assets()->path_to_url($this->wcf->get_assets()->get_assets_path());
    }
    /**
     * @param string $name
     * @param string $value
     *
     * @return mixed
     */
    public abstract function set_field($name, $value);
    /**
     * @param string $object_type
     * @param string $tag
     * @param array $attributes
     */
    public function render_fields(string $object_type = '', string $tag = 'div', array $attributes = array())
    {
        $data = $this->get_data();
        if (!empty($object_type)) {
            $data['object_type'] = $object_type;
        }
        $data = $this->fill_values($data);
        $data['items'] = $this->fill_selects($data['items']);
        $data['api'] = array('url' => $this->api->get_rest_url(), 'nonce' => $this->api->get_rest_nonce());
        $class = empty($attributes['class']) ? 'js-wcf' : 'js-wcf ' . $attributes['class'];
        $json = wp_json_encode($data, \JSON_UNESCAPED_UNICODE);
        $hash = 'd' . \md5($json);
        do_action('wcf_before_fields', $data);
        $script = 'try{window.wcf_data=(window.wcf_data||{});window.wcf_data.' . $hash . '=' . $json . ';}catch(e){console.error(e);}';
        echo '<script type="text/javascript">' . $script . '</script>';
        echo '<' . $tag . ' class="' . esc_attr($class) . '" data-hash="' . esc_attr($hash) . '"></' . $tag . '>';
        do_action('wcf_after_fields', $data);
    }
    public function fill_selects($items)
    {
        foreach ($items as $key => $item) {
            if (\in_array($item['type'], array('select', 'multi_select')) && !empty($item['options_callback']) && \is_callable($item['options_callback'])) {
                $callback = $item['options_callback'];
                $items[$key]['options'] = $callback($item);
            } elseif (\in_array($item['type'], array('post', 'multi_post'))) {
                $items[$key]['options'] = \array_map(function ($post) {
                    return array('label' => $post->post_title, 'value' => $post->ID, 'excerpt' => get_the_excerpt($post));
                }, get_posts(array('numberposts' => -1, 'post_type' => $item['post_type'] ?? 'post', 'include' => $item['value'], 'orderby' => 'post__in', 'post_status' => 'any')));
            } elseif (!empty($item['items'])) {
                $items[$key]['items'] = $this->fill_selects($item['items']);
            }
        }
        return $items;
    }
    /**
     * @return array
     */
    public abstract function get_data();
    /**
     * @param array $definition
     *
     * @return array
     */
    protected function fill_values(array $definition)
    {
        foreach ($definition['items'] as $key => $item) {
            $value = $this->parse_value($this->get_field($item['id']), $item);
            if (!empty($definition['items'][$key]['items'])) {
                $definition['items'][$key]['items'] = \array_map(array($this, 'normalize_item'), $definition['items'][$key]['items']);
            }
            if (empty($value)) {
                $definition['items'][$key]['value'] = '';
            } else {
                $definition['items'][$key]['value'] = $value;
            }
        }
        return $definition;
    }
    /**
     * @param string $value
     * @param array $item
     *
     * @return mixed|void
     */
    protected function parse_value($value, $item = array())
    {
        $parser = $this->parser->get_parser($item);
        return $parser($value);
    }
    /**
     * @param string $name
     *
     * @return mixed
     */
    public abstract function get_field($name);
    public abstract function set_wcf_shown(WP_Screen $current_screen);
    /**
     * @param array $item
     *
     * @return string
     */
    public function get_item_type(array $item)
    {
        switch ($item['type']) {
            case 'number':
                return 'number';
            case 'attachment':
            case 'post':
                return 'integer';
            case 'multi_attachment':
            case 'multi_group':
            case 'multi_post':
            case 'multi_select':
                return 'array';
            case 'group':
            case 'link':
                return 'object';
            case 'checkbox':
            case 'toggle':
                return 'boolean';
            default:
                return 'string';
        }
    }
    /**
     * @param array $items
     *
     * @return array
     */
    protected function prepare_items(array $items = array())
    {
        foreach ($items as $key => $item) {
            $items[$key] = $this->normalize_item($item);
        }
        return \array_values(\array_filter($items));
    }
    /**
     * @param array $args
     *
     * @return array
     */
    private function normalize_item(array $args = array())
    {
        $args = wp_parse_args($args, array('type' => '', 'id' => '', 'title' => '', 'class' => '', 'css' => '', 'default' => '', 'desc' => '', 'desc_tip' => '', 'placeholder' => '', 'suffix' => '', 'value' => '', 'custom_attributes' => array(), 'description' => '', 'tooltip_html' => ''));
        /* Compatibility with WPify Woo */
        $type_aliases = array('multiswitch' => 'multi_toggle', 'switch' => 'toggle', 'multiselect' => 'multi_select', 'colorpicker' => 'color', 'react_component' => 'react');
        foreach ($type_aliases as $alias => $correct) {
            if ($args['type'] === $alias) {
                $args['type'] = $correct;
            }
        }
        if (\in_array($args['type'], array('number', 'post', 'attachment')) && empty($args['default'])) {
            $args['default'] = 0;
        }
        if (\in_array($args['type'], array('group', 'multi_group', 'multi_post', 'multi_attachment', 'multi_toggle', 'multi_select', 'link')) && empty($args['default'])) {
            $args['default'] = array();
        }
        if (\in_array($args['type'], array('toggle', 'checkbox')) && empty($args['default'])) {
            $args['default'] = \false;
        }
        $args_aliases = array('label' => 'title', 'desc' => 'description', 'async_list_type' => 'list_type');
        foreach ($args_aliases as $alias => $correct) {
            if (empty($args[$correct]) && !empty($args[$alias])) {
                $args[$correct] = $args[$alias];
            }
        }
        if ($args['type'] === 'group' && isset($args['multi']) && $args['multi'] === \true) {
            $args['type'] = 'multi_group';
            unset($args['multi']);
        }
        if (!empty($args['items']) && \is_array($args['items'])) {
            foreach ($args['items'] as $key => $item) {
                $args['items'][$key] = $this->normalize_item($item);
            }
        }
        if ($args['type'] === 'group' && empty($args['default'])) {
            foreach ($args['items'] as $item) {
                $args['default'][$item['id']] = $item['default'];
            }
        }
        return $args;
    }
}
