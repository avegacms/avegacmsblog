<?php

namespace AvegaCmsBlog\Controllers\Public;

use AvegaCms\Controllers\Api\AvegaCmsAPI;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\BlogPostsModel;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\HTTP\ResponseInterface;
use JetBrains\PhpStorm\NoReturn;

class Posts extends AvegaCmsAPI
{
    protected BlogPostsModel $BPM;
    protected TagsModel $TM;
    protected int $post_mid;
    protected int $category_mid;

    #[NoReturn]
    public function __construct()
    {
        $this->BPM          = new BlogPostsModel();
        $this->TM           = new TagsModel();
        $this->post_mid     = (int) CmsModule::meta('blog.post')['id'];
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];

        parent::__construct();
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond($this->BPM->getBlogPosts($this->post_mid, request()->getGet(), true));
    }

    public function getPost($postSlug): ResponseInterface
    {
        $postSlug = explode('_', $postSlug);
        $id       = (int) array_splice($postSlug, count($postSlug) - 1, 1)[0];

        $post = $this->BPM->getBlogPost($id, $this->post_mid);

        if ($post === null) {
            return $this->failNotFound();
        }

        //        Куда должно перенаправлять? Точно ли тут должно быть?
        //        if (implode('_', $postSlug) !== $post->slug)
        //        {
        //            return redirect()->to(base_url('/api/public/blog/post/' . $post->slug . '_' . $post->id), 301);
        //        }

        return $this->cmsRespond((array) $post);
    }

    public function byTag($tagSlug): ResponseInterface
    {
        $tag = $this->TM->where(['name' => $tagSlug, 'active' => 1])->first();

        if ($tag === null) {
            return $this->failNotFound();
        }

        return $this->cmsRespond($this->BPM->getBlogPosts($this->post_mid, ['tags' => $tag->id], true));
    }
}
