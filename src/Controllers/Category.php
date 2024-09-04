<?php

namespace AvegaCmsBlog\Controllers;

use AvegaCms\Controllers\AvegaCmsFrontendController;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\BlogPostsModel;
use CodeIgniter\HTTP\ResponseInterface;
use ReflectionException;

class Category extends AvegaCmsFrontendController
{
    protected ?string $moduleKey    = 'blog.category';
    protected bool $useTemplateMeta = true;

    /**
     * @throws ReflectionException
     */
    public function index(string $slug): ResponseInterface
    {
        $BPM = new BlogPostsModel();

        if (($category = $BPM->select(['id', 'slug', 'module_id'])->where(
            [
                'slug'      => $slug,
                'module_id' => (int) CmsModule::meta('blog.category')['id'],
            ]
        )->first()) === null) {
            $this->error404();
        }
        $filter           = $_GET;
        $filter['parent'] = $category->id;

        return $this->render(['posts' => $BPM->getBlogPosts(
            (int) CmsModule::meta('blog.post')['id'],
            $filter,
            true
        )], 'blog');
    }
}
