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
    protected FilesModel $FM;

    public function __construct()
    {
        parent::__construct();

        $this->TLM = new TagsLinksModel();
        $this->TM  = new TagsModel();
        $this->CM  = new ContentModel();
        $this->FM  = new FilesModel();


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

        if (empty($posts)) {
            return [];
        }

        $tags    = $this->TLM->getTagsOfPosts($posts['list'], $hide);
        $anonses = $this->CM->select(['id', 'anons'])
            ->whereIn('id', array_column($posts['list'], 'id'))->findAll();
        $previews = $this->FM->select(['id', 'data'])
            ->whereIn('id', array_column($posts['list'], 'preview_id'))->findAll();
        $categories = cache()->remember(
            'blog_categories',
            DAY,
            fn () => $this->select(['id', 'title'])->findAll()
        );

        foreach ($posts['list'] as $post) {
            $post->parent  = $this->unwrapParent($categories, $post->parent);
            $post->tags    = $this->filterTags($tags, $post->id);
            $post->anons   = $this->getAnons($anonses, $post->id);
            $post->preview = $this->getPreview($previews, (int) $post->preview_id);

            unset($post->preview_id);
        }

        return $posts;
    }

    public function getBlogPost(int $id, int $module, $hide = false): ?stdClass
    {
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

        $tags = $this->TLM->getTagsOfPosts([$post], $hide);
        $post->tags = $this->filterTags($tags, $post->id);

        $previews = $this->FM->select(['id', 'data'])->where(['id' => $post->preview_id])->first();

        $post->preview = ($previews !== null)
            ? $this->getPreview([$previews], (int) $post->preview_id)
            : null;

        unset($post->preview_id);

        return $post;
    }

    protected function getPreview(array $previews, $fileId): ?array
    {
        foreach ($previews as $preview) {
            if ($preview->id === $fileId) {
                return $preview->data;
            }
        }

        return null;
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
