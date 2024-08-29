<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\Admin\MetaDataModel;
use stdClass;

class BlogPostsModel extends MetaDataModel
{
    public function getBlogPosts(int $moduleId, array $filter): array
    {
        $TLM = new TagsLinksModel();
        if (! empty($filter['tags'])) {
            $tags = explode(',', $filter['tags']);

            $query = $TLM->select('meta_id')
                ->whereIn('tag_id', $tags)
                ->groupBy('meta_id')
                ->having('COUNT(DISTINCT tag_id) = ' . count($tags))
                ->findAll();

            $filter['id'] = array_column($query, 'meta_id');

            unset($filter['tags']);
        }

        $posts = $this->getMetadataModule($moduleId)->filter($filter)->apiPagination();

        $tags = $TLM->select(['tag_id', 'meta_id'])->whereIn('meta_id', empty($posts['list']) ? ['id' => 0] : array_column($posts['list'], 'id'))->findAll();

        foreach ($posts['list'] as $post) {
            $post->tags = $this->tagsArray(array_filter($tags, static fn ($tag) => $tag->meta_id === $post->id));
        }

        return $posts;
    }

    public function getBlogPost(int $id, int $module): ?stdClass
    {
        $post = $this->getMetadata($id, $module);

        if ($post === null) {
            return null;
        }

        $TLM  = new TagsLinksModel();
        $tags = $TLM->select(['tag_id', 'meta_id'])->where(['meta_id' => $id])->findAll();

        $post->tags = $this->tagsArray($tags);

        return $post;
    }

    public function getCategories(int $module): ?array
    {
        if (($categories = $this->getMetadataModule($module)->findAll()) === null) {
            return null;
        }

        return array_map(static fn ($category) => ['id' => $category->id, 'title' => $category->title], $categories);
    }

    protected function tagsArray(array $array): array
    {
        return array_map(static fn ($tag) => $tag->tag_id, $array);
    }
}
