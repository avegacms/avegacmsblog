<?php

namespace AvegaCmsBlog\Controllers;

use AvegaCms\Controllers\AvegaCmsFrontendController;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\Frontend\BlogPostsModel;
use CodeIgniter\HTTP\ResponseInterface;
use JetBrains\PhpStorm\NoReturn;
use ReflectionException;

class Blog extends AvegaCmsFrontendController
{
    protected ?string $moduleKey = 'blog';
    protected int $category_mid;
    protected int $post_mid;

    #[NoReturn]
    public function __construct()
    {
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
        $this->post_mid     = (int) CmsModule::meta('blog.post')['id'];

        parent::__construct();
    }

    /**
     * @throws ReflectionException
     */
    public function index(): ResponseInterface
    {
        return $this->render([
            'search'=> $this->request->getGet(),
            'posts' => model(BlogPostsModel::class)->getPosts($this->post_mid, $_GET, true)
        ], 'blog');
    }
}
