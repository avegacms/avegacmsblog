<?php

namespace AvegaCmsBlog\Controllers;

use AvegaCms\Controllers\AvegaCmsFrontendController;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\BlogPostsModel;
use CodeIgniter\HTTP\ResponseInterface;
use JetBrains\PhpStorm\NoReturn;
use ReflectionException;

class Post extends AvegaCmsFrontendController
{
    protected ?string $moduleKey    = 'blog.post';
    protected bool $useTemplateMeta = true;
    protected int $category_mid;
    protected int $post_mid;
    protected BlogPostsModel $BPM;

    #[NoReturn]
    public function __construct()
    {
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
        $this->post_mid     = (int) CmsModule::meta('blog.post')['id'];
        $this->BPM          = new BlogPostsModel();

        [$slug, $id] = explode('_', request()->getUri()->getSegments()[request()->getUri()->getTotalSegments() - 1]);

        $this->metaParams = [
            'slug' => $slug,
            'id'   => $id
        ];

        parent::__construct();
    }

    /**
     * @throws ReflectionException
     */
    public function index(string $slug): ResponseInterface
    {
        $array = explode('_', $slug);

        if (count($array) !== 2
            || ($post = $this->BPM
                ->select(['id', 'slug'])
                ->where(['module_id' => $this->post_mid])
                ->find($array[1])) === null) {
            $this->error404();
        }

        if ($array[0] !== $post->slug) {
            return redirect()->to('/blog/post/' . $post->slug . '_' . $post->id, 301);
        }

        return $this->render([]);
    }
}
