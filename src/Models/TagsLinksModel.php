<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\AvegaCmsModel;

class TagsLinksModel extends AvegaCmsModel
{
    protected $table            = 'tags_links';
    protected $primaryKey       = 'tag_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'tag_id',
        'meta_id',
        'created_by_id',
        'updated_by_id',
        'created_at',
        'updated_at',
    ];
    protected array $casts = [
        'tag_id'        => 'int',
        'meta_id'       => 'int',
        'created_by_id' => 'int',
        'updated_by_id' => 'int',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // Dates
    protected $useTimestamps = true;

    // Validation
    protected $validationRules = [
        'tag_id'        => 'required|is_natural_no_zero',
        'meta_id'       => 'required|is_natural_no_zero',
        'created_by_id' => 'required|is_natural_no_zero',
        'updated_by_id' => 'required|is_natural',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function getTagsOfPosts(array $posts, bool $hide): array
    {
        return ($hide)
            ? $this->select(['tags.name as label', 'tags.id as value', 'tags_links.meta_id'])
                ->whereIn(
                    'meta_id',
                    empty($posts) ? ['id' => 0] : array_column($posts, 'id')
                )
                ->join('tags', 'tags_links.tag_id = tags.id AND tags.active = 1')
                ->findAll()

            : $this->select(['tags.name as label', 'tags.id as value', 'tags_links.meta_id'])
                ->whereIn(
                    'meta_id',
                    empty($posts) ? ['id' => 0] : array_column($posts, 'id')
                )
                ->join('tags', 'tags_links.tag_id = tags.id')->findAll();
    }

    public function getAllowedPosts(array $tags): array
    {
        $query = $this->select('meta_id')
            ->whereIn('tag_id', $tags)
            ->groupBy('meta_id')
            ->findAll();

        return array_column($query, 'meta_id');
    }
}
