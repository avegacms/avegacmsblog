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
        'updated_by_id' => 'required|is_natural_no_zero',
    ];

    public function __construct()
    {
        parent::__construct();
    }
}
