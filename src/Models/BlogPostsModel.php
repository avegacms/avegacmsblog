<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Utilities\CmsModule;

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
        $this->afterFind    = [
            ...[
                'tagsSubstitution',
                'parentSubstitution',
                'urlSubstitution',
                'anonsSubstitution',
            ],
            ...$this->afterFind,
        ];

        $this->searchFields = [
            'metadata.title',
        ];
    }

    public function getCategories(int $moduleId): array
    {
        return $this->select(
            [
                'metadata.id',
                'metadata.title',
                'metadata.url',
                'metadata.slug',
                'metadata.meta',
                'COUNT(metadata.id) as num',
            ]
        )
            ->where(['metadata.module_id' => $moduleId])
            ->join('metadata as MD', 'metadata.id = MD.parent')
            ->groupBy('metadata.id')
            ->filter(request()->getGet())
            ->apiPagination();
    }

    public function getPosts(int $moduleId, array $filter, bool $hide = false): array
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

        return $posts;
    }

    public function getPost(int $id, int $module, $hide = false): ?object
    {
        $this->hide = $hide;

        $post = $this->getMetadata($id, $module);

        return $post ?? null;
    }

    protected function tagsSubstitution(array $data): array
    {
        if (isset($this->hide) === false || empty($data['data'])) {
            return $data;
        }

        if ($data['singleton'] === false) {
            $tags = $this->TLM->getTagsOfPosts($data['data'], $this->hide);

            foreach ($data['data'] as &$item) {
                $item = $this->tagsSubstitute($item, $tags);
            }
        } else {
            $tags         = $this->TLM->getTagsOfPosts([$data['data']], $this->hide);
            $data['data'] = $this->tagsSubstitute($data['data'], $tags);
        }

        return $data;
    }

    protected function anonsSubstitution(array $data): array
    {
        if (isset($this->hide) === false) {
            return $data;
        }

        if ($data['singleton'] === false) {
            $anonses = $this->CM->select(['id', 'anons'])
                ->whereIn('id', array_column($data['data'], 'id'))->findAll();

            foreach ($data['data'] as &$item) {
                $item = $this->anonsSubstitute($item, $anonses);
            }
        }

        return $data;
    }

    protected function anonsSubstitute(object $post, array $anonses): object
    {
        foreach ($anonses as $anons) {
            if ($anons->id === $post->id) {
                $post->anons = $anons->anons;

                return $post;
            }
        }

        return $post;
    }

    protected function parentSubstitution(array $data): array
    {
        if (isset($this->hide) === false || empty($data['data'])) {
            return $data;
        }

        if ($data['singleton'] === false) {
            foreach ($data['data'] as &$item) {
                $item = $this->parentSubstitute($item);
            }
        } else {
            $data['data'] = $this->parentSubstitute($data['data']);
        }

        return $data;
    }

    protected function urlSubstitution(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        if ($data['singleton'] === false) {
            foreach ($data['data'] as &$item) {
                $item = $this->urlSubstitute($item);
            }
        } else {
            $data['data'] = $this->urlSubstitute($data['data']);
        }

        return $data;
    }

    protected function tagsSubstitute(object $post, array $tags): object
    {
        $post->tags = [];

        foreach ($tags as $tag) {
            if ($tag->meta_id === $post->id) {
                $post->tags[] = [
                    'label' => $tag->label,
                    'value' => (int) $tag->value,
                ];
            }
        }

        return $post;
    }

    protected function urlSubstitute(object $post): object
    {
        if (isset($post->url) === false) {
            return $post;
        }

        $post->url = preg_replace(['/{slug}/', '/{id}/'], [$post->slug, $post->id], $post->url);

        if (isset($post->meta) === false) {
            return $post;
        }

        $post->meta['og:url'] = preg_replace(['/{slug}/', '/{id}/'], [$post->slug, $post->id], $post->url);

        return $post;
    }

    protected function parentSubstitute(object $post): object
    {
        $categories = cache()->remember(
            'blog_categories',
            DAY,
            static fn () => (new MetaDataModel())->select(['id', 'title'])->where(['module_id' => CmsModule::meta('blog.category')['id']])->findAll()
        );

        $parent = null;

        foreach ($categories as $category) {
            if ($category->id === $post->parent) {
                $parent = $category;
                break;
            }
        }

        if ($parent === null) {
            $post->parent = null;
        } else {
            $post->parent = [
                'value' => $parent->id,
                'label' => $parent->title,
            ];
        }

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