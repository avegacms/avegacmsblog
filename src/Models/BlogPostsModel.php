<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Models\Admin\FilesModel;
use AvegaCms\Models\Admin\MetaDataModel;
use stdClass;

class BlogPostsModel extends MetaDataModel
{
    protected TagsLinksModel $TLM;
    protected TagsModel $TM;
    protected ContentModel $CM;
    protected bool $hide;
    public function __construct()
    {
        parent::__construct();

        $this->TLM = new TagsLinksModel();
        $this->TM  = new TagsModel();
        $this->CM  = new ContentModel();
    }
    protected function initialize(): void
    {
        $this->casts['num'] = 'int';
        $this->afterFind[] = 'getTags';
        $this->afterFind[] = 'getParent';
    }

    protected function getTags(array $data): array
    {
        if (isset($this->hide) === false)
        {
            return $data;
        }

        if ($data['singleton'] === false)
        {
            $tags    = $this->TLM->getTagsOfPosts($data['data'], $this->hide);
            foreach ($data['data'] as $item)
            {
                $item->tags = [];
                foreach ($tags as $tag) {
                    if ($tag->meta_id === $item->id) {
                        $item->tags[] = [
                            'label' => $tag->label,
                            'value' => (int) $tag->value,
                        ];
                    }
                }

            }
        }
        else {
            $tags    = $this->TLM->getTagsOfPosts([$data['data']], $this->hide);
            $data['data']->tags = [];
            foreach ($tags as $tag) {
                if ($tag->meta_id === $data['data']->id) {
                    $data['data']->tags[] = [
                        'label' => $tag->label,
                        'value' => (int) $tag->value,
                    ];
                }
            }
        }

        return $data;
    }

    protected function getParent(array $data): array
    {
        if (isset($this->hide) === false)
        {
            return $data;
        }

        $categories = cache()->remember(
            'blog_categories',
            DAY,
            fn () => $this->select(['id', 'title'])->findAll()
        );

        // TODO


    }

    public function getBlogPosts(int $moduleId, array $filter, bool $hide = false): array
    {
        if (! empty($filter['tags'])) {
            $tags = array_unique(explode(',', (string) $filter['tags']));

            $filter['id'] = $this->TLM->getAllowedPosts($tags);

            unset($filter['tags']);
        }

        $this->hide = $hide;

        $posts = ($hide)
            ? $this->getMetadataModule($moduleId)->where(['parent !=' => 0])->filter($filter)->apiPagination()
            : $this->getMetadataModule($moduleId)->filter($filter)->apiPagination();

        if (empty($posts)) {
            return [];
        }

        $anonses = $this->CM->select(['id', 'anons'])
            ->whereIn('id', array_column($posts['list'], 'id'))->findAll();

        foreach ($posts['list'] as $post) {
            $post->parent  = $this->unwrapParent($categories, $post->parent);
            $post->anons   = $this->getAnons($anonses, $post->id);
        }

        return $posts;
    }

    public function getBlogPost(int $id, int $module, $hide = false): ?stdClass
    {
        $this->hide = $hide;

        $post = $this->getMetadata($id, $module);

        if ($post === null) {
            return null;
        }

        $categories = cache()->remember(
            'blog_categories',
            DAY,
            fn () => $this->select(['id', 'title'])->findAll()
        );

        $post->parent = $this->unwrapParent($categories, $post->parent);

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

    protected function unwrapParent(array $categories, int $parentId): ?array
    {
        $parent = null;

        foreach ($categories as $category) {
            if ($category->id === $parentId) {
                $parent = $category;
                break;
            }
        }

        if ($parent === null) {
            return null;
        }

        return [
            'value' => $parent->id,
            'label' => $parent->title,
        ];
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
