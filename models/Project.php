<?php namespace Bronx\Project\Models;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use October\Rain\Database\Model;
use October\Rain\Database\Traits\Purgeable;
use October\Rain\Database\Traits\Sortable;

class Project extends Model
{
    public $table = 'bronx_project_tab_project';

    use Sortable;

    use Purgeable;
    protected $purgeable = [
        'addressfinder',
    ];

    public $belongsTo = [
        'relCategory' => [
            Category::class,
            'key' => 'category_id',
        ],
    ];

    public $attachOne = [
        'relImage' => [
            File::class,
        ],
    ];

    public $attachMany = [
        'relImages' => [
            File::class,
        ],
        'relFiles'  => [
            File::class,
        ],
    ];

    public function takeUrl($pageName, $controller)
    {
        $params = [
            'id'   => $this->id,
            'slug' => $this->takeSlug(),
        ];

        return $controller->pageUrl($pageName, $params);
    }

    public function takeSlug()
    {
        $slug = $this->slug;
        $category = $this->relCategory()
            ->first();

        if ($category != null) {
            $parents = $category->getParentsAndSelf()->reverse();

            $parents->each(function ($parent) use (&$slug) {
                $slug = $parent->slug . '/' . $slug;
            });
        }

        return $slug;
    }

    /*
     * EVENT
     */

    public function afterCreate()
    {
        $this->save();
    }

    public function beforeSave()
    {
        $this->slug = str_slug(implode('-', [
            'p',
            $this->id,
            $this->name,
        ]), '-');
    }

    /*
     * SITEMAP
     */

    /**
     * Получение списка страниц которые выводят содержимое этой модели
     * @param $type
     * @return array
     */
    public static function getMenuTypeInfo($type)
    {
        $theme = Theme::getActiveTheme();
        $pages = Page::listInTheme($theme, true);

        $cmsPages = [];
        foreach ($pages as $page) {
            if (!$page->hasComponent('bronxProjectCatalog')) {
                continue;
            }

            $properties = $page->getComponentProperties('bronxProjectCatalog');
            if (!isset($properties['catalogSlug']) || !preg_match('/{{\s*:/', $properties['catalogSlug'])) {
                continue;
            }

            $cmsPages[] = $page;
        }

        return [
            'cmsPages' => $cmsPages,
        ];
    }

    /**
     * Генерация Sitemap для данной модели
     * @param $item
     * @param $url
     * @param $theme
     * @return array
     */
    public static function resolveMenuItem($item, $url, $theme)
    {
        $records = self::orderBy('id')
            ->get();

        $result = [];
        foreach ($records as $record) {
            $result['items'][] = [
                'title' => $record->name,
                'url'   => self::getContentUrl($item->cmsPage, $record, $theme),
                'mtime' => $record->updated_at,
            ];
        }

        return $result;
    }

    /**
     * Генерация ссылки на страницу
     * @param $pageCode
     * @param $record
     * @param $theme
     * @return string|void
     */
    protected static function getContentUrl($pageCode, $record, $theme)
    {
        $page = Page::loadCached($theme, $pageCode);

        if (!$page) {
            return;
        }

        $properties = $page->getComponentProperties('bronxProjectCatalog');

        if (!isset($properties['catalogSlug'])) {
            return;
        }

        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['catalogSlug'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);

        return Page::url($page->getBaseFileName(), [$paramName => $record->takeSlug()]);
    }
}