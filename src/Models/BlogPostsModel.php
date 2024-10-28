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
    protected ?bool $hide = null;

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
                'urlSubstitution',
                'anonsSubstitution',
            ],
            ...$this->afterFind,
        ];

        $this->searchFields = [
            'metadata.title',
        ];

        $this->casts = [
            ...$this->casts,
            'value' => 'int'
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
                'COUNT(MD.id) as num',
            ]
        )
            ->where(['metadata.module_id' => $moduleId])
            ->join('metadata as MD', 'metadata.id = MD.parent', 'left')
            ->groupBy('metadata.id')
            ->filter(request()->getGet())
            ->apiPagination();
    }

    public function getCategoriesForDropdown(int $moduleId): array
    {
        return $this->select(
            [
                'id AS value',
                'title AS label'
            ]
        )
            ->where(['metadata.module_id' => $moduleId])
            ->findAll();
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

        if ($hide === false) {
            $posts['list'] = array_map(function($item) {
                return [
                    'publish_at'    => $item->publish_at,
                    'id'            => $item->id,
                    'slug'          => $item->slug,
                    'lang_id'       => $item->locale_id,
                    'tags'          => $item->tags,
                    'title'         => $item->title,
                    'status'        => $item->status,
                    'url'           => $item->url,
                ];
            }, $posts['list']);
        }

        if (empty($posts)) {
            return [];
        }

        $posts['list'] = $this->replaceTags($posts['list'], $hide);

        return $posts;
    }

    public function homePosts(int $moduleId, int $limit): array
    {
        $this->hide = true;

        $posts = $this
            ->getMetadataModule($moduleId)
            ->where(['parent !=' => 0])
            ->orderBy('publish_at', 'DESC')
            ->limit($limit)->findAll();

        if (empty($posts)) {
            return [];
        }

        $posts = $this->replaceTags($posts);

        return $posts;
    }

    public function getPost(int $id, int $module, $hide = false): ?object
    {
        $this->hide = $hide;

        $post = $this->getMetadata($id, $module);

        return $post ?? null;
    }

    protected function replaceTags(array $posts, bool $hide = true)
    {
        $tags = $this->TLM->getTagsOfPosts($posts, $hide);
        foreach ($posts as &$post) {
            $temp = (array) $post;
            if (empty($temp['tags']))
            {
                continue;
            }

            foreach ($temp['tags'] as &$tag) {
                foreach ($tags as $other_tag)
                {
                    if ((int) $other_tag->value === (int) $tag)
                    {
                        $tag = [
                            'label' => $other_tag->label,
                            'value' => base_url('blog?tags='. $other_tag->value),
                        ];

                        continue 2;
                    }
                }
            }

            $post['tags'] = $temp['tags'];
        }

        return $posts;
    }

    protected function tagsSubstitution(array $data): array
    {
        if (isset($this->hide) === false || empty($data['data'])) {
            return $data;
        }

        if ($data['singleton'] === false) {
            $tags = $this->TLM->getTagsOfPosts($data['data'], $this->hide);

            if ($tags === $data['data'])
            {
                return $data;
            }

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
        if (isset($this->hide) === false || $this->hide === false) {
            return $data;
        }

        if (empty($data['data']))
        {
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
                $post->tags[] = (int) $tag->value;
            }
        }

        return $post;
    }

    protected function urlSubstitute(object $post): object
    {
        if (isset($post->url) === false) {
            return $post;
        }

        $url = preg_replace(['/{slug}/', '/{id}/'], [$post->slug, $post->id], $post->url);

        $post->url = base_url($url);

        if (isset($post->meta) === false) {
            return $post;
        }

        $post->meta['og:url'] = base_url($url);

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
