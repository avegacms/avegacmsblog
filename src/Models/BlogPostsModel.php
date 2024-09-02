<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Models\Admin\MetaDataModel;
use stdClass;

class BlogPostsModel extends MetaDataModel
{
    protected TagsLinksModel $TLM;
    protected TagsModel $TM;
    protected ContentModel $CM;

    public function __construct()
    {
        parent::__construct();

        $this->TLM = new TagsLinksModel();
        $this->TM  = new TagsModel();
        $this->CM  = new ContentModel();
    }

    public function getBlogPosts(int $moduleId, array $filter, bool $hide = false): array
    {
        if (! empty($filter['tags'])) {
            $tags = array_unique(explode(',', (string) $filter['tags']));

            $filter['id'] = $this->TLM->getAllowedPosts($tags);

            unset($filter['tags']);
        }

        $posts = ($hide)
            ? $this->getMetadataModule($moduleId)->where(['parent !=' => 0])->filter($filter)->apiPagination()
            : $this->getMetadataModule($moduleId)->filter($filter)->apiPagination();

        if (empty($posts))
        {
            return [];
        }

        $tags = $this->TLM->getTagsOfPosts($posts['list'], $hide);
        $anonses = $this->CM->select(['id','anons'])->whereIn('id', array_column($posts['list'], 'id'))->findAll();

        foreach ($posts['list'] as &$post) {
            $post       = $this->unwrapParent($post);
            $post->tags = $this->filterTags($tags, $post->id);
            $post->anons = $this->getAnons($anonses, $post->id);
        }

        return $posts;
    }

    public function getBlogPost(int $id, int $module, $hide = false): ?stdClass
    {
        $post = $this->getMetadata($id, $module);

        if ($post === null) {
            return null;
        }

        $post = $this->unwrapParent($post);

        $tags = $this->TLM->getTagsOfPosts([$post], $hide);

        $post->tags = $this->filterTags($tags, $post->id);

        return $post;
    }

    protected function getAnons(array $anonses, $postId): ?string
    {
        foreach ($anonses as $anons) {
            if ($anons->id === $postId) {
                return $anons->anons;
            }
        }
        return null;
    }

    protected function unwrapParent(stdClass $post): stdClass
    {
        $categories = cache()->remember('blog_categories', DAY, fn () => $this->select(['id', 'title'])->findAll());

        $parent = null;

        foreach ($categories as $category) {
            if ($category->id === $post->parent) {
                $parent = $category;
                break;
            }
        }

        if ($parent === null) {
            return $post;
        }

        $post->parent = [
            'value' => $parent->id,
            'label' => $parent->title,
        ];

        return $post;
    }

    protected function filterTags(array $tags, int $postId): array
    {
        $return = [];

        foreach ($tags as $tag) {
            if ($tag->meta_id === $postId) {
                $return[] = [
                    'label' => $tag->label,
                    'value' => (int) $tag->value,
                ];
            }
        }

        return $return;
    }
}
