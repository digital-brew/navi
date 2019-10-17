<?php

namespace Log1x\Navi;

use Illuminate\Support\Str;
use Illuminate\Support\Fluent;

class Navi
{
    /**
     * Menu
     *
     * @var mixed
     */
    protected $menu;

    /**
     * Blacklisted Classes
     *
     * @var array
     */
    protected $classes = [
        'current-menu',
        'current_page',
        'sub-menu',
        'menu-item',
        'menu_item',
        'page-item',
        'page_item',
    ];

    /**
     * Create a new Navi instance.
     *
     * @param  mixed $menu
     * @return $this
     */
    public function __construct($menu = 'primary_navigation')
    {
        if (is_string($menu)) {
            $menu = get_nav_menu_locations()[$menu] ?? [];
        }

        if (empty($menu)) {
            return;
        }

        $menu = wp_get_nav_menu_items($menu);
        _wp_menu_item_classes_by_context($menu);

        $this->menu = collect($menu);
    }

    /**
     * Returns the raw nav menu items with the proper navigation hierarchy.
     *
     * @return array
     */
    public function raw()
    {
        return
            $this->menu->map(function ($value, $key) {
                return [$key => $value];
            });
    }

    /**
     * Parse the array of WP_Post objects returned by wp_get_nav_menu_items().
     *
     * @return object
     */
    public function toArray()
    {
        return $this->tree(
            $this->menu->map(function ($item) {
                return new Fluent([
                    'parent' => $item->menu_item_parent != 0 ? $item->menu_item_parent : false,
                    'id' => $item->ID,
                    'label' => $item->title,
                    'slug' => $item->post_name,
                    'url' => $item->url,
                    'active' => $item->current,
                    'activeAncestor' => $item->current_item_ancestor,
                    'activeParent' => $item->current_item_parent,
                    'classes' => $this->filterClasses($item->classes),
                    'title' => $item->attr_title,
                    'description' => $item->description,
                    'target' => $item->target,
                    'xfn' => $item->xfn,
                ]);
            })
        );
    }

    /**
     * Returns a filtered list of classes.
     *
     * @param  array  $classes
     * @return string
     */
    protected function filterClasses($classes)
    {
        return collect($classes)->filter(function ($class) {
            return ! Str::contains($class, $this->classes);
        })->implode(' ');
    }

    /**
     * Build a multi-dimensional array containing children nav items.
     *
     * @param  object $items
     * @param  int    $parent
     * @param  array  $branch
     * @return array
     */
    protected function tree($items, $parent = 0, $branch = [])
    {
        foreach ($items as $item) {
            if ($item->parent == $parent) {
                $children = $this->tree($items, $item->id);

                $item->children = [];

                if (! empty($children)) {
                    $item->children = $children;
                }

                $branch[$item->id] = $item;
                unset($item);
            }
        };

        return $branch;
    }
}
