<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Models;

use AvegaCms\Models\AvegaCmsModel;

class TagsModel extends AvegaCmsModel
{
    protected $table         = 'tags';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'id',
        'name',
        'slug',
        'active',
        'created_by_id',
        'updated_by_id',
        'created_at',
        'updated_at',
    ];
    protected bool $allowEmptyInserts = true;
    protected array $casts            = [
        'id'            => 'int',
        'active'        => 'int-bool',
        'created_by_id' => 'int',
        'updated_by_id' => 'int',
        'created_at'    => 'cmsdatetime',
        'updated_at'    => 'cmsdatetime',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $deletedField;

    // Validation
    protected $validationRules = [
        'id'            => 'permit_empty',
        'name'          => 'required|min_length[3]|max_length[128]',
        'slug'          => 'required|min_length[3]|max_length[64]|is_unique[tags.slug,id,{id}]',
        'active'        => 'required|in_list[0,1]',
        'created_by_id' => 'permit_empty|is_natural_no_zero',
        'updated_by_id' => 'permit_empty|is_natural_no_zero',
        'value'         => 'int'
    ];

    public function getTags(): array
    {
        return $this->select(['id', 'name'])->findAll();
    }

    public function getTagsForDropdown(): array
    {
        return $this->select(['id AS value', 'name AS label'])->findAll();
    }

    public function __construct()
    {
        parent::__construct();
    }
}
