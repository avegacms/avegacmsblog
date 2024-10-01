<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\Admin\AvegaCmsAdminAPI;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Exception\ValidationException;
use AvegaCmsBlog\Models\BlogPostsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use RuntimeException;

class Category extends AvegaCmsAdminAPI
{
    protected MetaDataModel $MDM;
    protected BlogPostsModel $BPM;
    protected int $category_mid;
    protected int $meta_blog_id;

    public function __construct()
    {
        parent::__construct();

        $this->MDM = new MetaDataModel();

        $this->BPM          = new BlogPostsModel();
        $this->meta_blog_id = (int) $this->MDM->getMetadataModule((int) CmsModule::meta('blog')['id'])->first()->id;
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond(
            $this->BPM->getCategories($this->category_mid)
        );
    }

    public function edit(int $id): ResponseInterface
    {
        return $this->cmsRespond( (array)
            $this->BPM->select([
                'id',
                'title',
                'url',
                'slug',
                'meta',
            ])->where(['module_id' => $this->category_mid])->find($id)
        );
    }

    public function new(): ResponseInterface
    {
        return $this->cmsRespond([
            'title',
        ]);
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->getValidated($this->getApiData());

            $data = [
                'parent'          => $this->meta_blog_id,
                'locale_id'       => 1,
                'module_id'       => $this->category_mid,
                'slug'            => mb_url_title(mb_strtolower($data['title'])),
                'item_id'         => 0,
                'title'           => $data['title'],
                'url'             => 'blog/category/{slug}',
                'use_url_pattern' => true,
                'meta'            => [],
                'status'          => MetaStatuses::Publish->name,
                'meta_type'       => MetaDataTypes::Module->name,
                'in_sitemap'      => true,
                'created_by_id'   => $this->userData->userId,
            ];
            if (($id = $this->MDM->insert($data)) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            cache()->delete('blog_categories');

            return $this->cmsRespondCreated($id);
        } catch (Exception $e) {
            log_message(
                'error',
                sprintf('[Blog : Category creating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

            if ($e instanceof ValidationException) {
                return $this->cmsRespondFail($e->getErrors());
            }

            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function update(int $id): ResponseInterface
    {
        if (($category = $this->MDM->where(['module_id' => $this->category_mid])->find($id)) === null) {
            return $this->failNotFound();
        }

        try {
            $data = $this->getValidated($this->getApiData());

            $data['id']               = $id;
            $data['slug']             = mb_url_title(mb_strtolower($data['title']));
            $data['parent']           = $category->parent;
            $data['updated_by_id']    = $this->userData->userId;
            $data['module_id']        = $category->module_id;
            $data['item_id']          = $category->item_id;
            $data['use_url_pattern']  = $category->use_url_pattern;
            $data['meta']             = $category->meta;
            $data['meta']['title']    = $data['title'];
            $data['meta']['og:title'] = $data['title'];

            if ($this->MDM->update($id, $data) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            cache()->delete('blog_categories');

            return $this->respondNoContent();
        } catch (Exception|ValidationException $e) {
            log_message(
                'error',
                sprintf('[Blog : Category updating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

            if ($e instanceof ValidationException) {
                return $this->cmsRespondFail($e->getErrors());
            }

            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->MDM->getMetadata($id, $this->category_mid) === null) {
            return $this->failNotFound();
        }

        $this->MDM->where(['parent' => $id])->set(['parent' => 0])->update();
        $this->MDM->delete($id);

        cache()->delete('blog_categories');

        return $this->respondNoContent();
    }

    /**
     * @throws ValidationException
     */
    protected function getValidated(array $data): array
    {
        $rules = [
            'title' => [
                'rules' => 'required|min_length[1]|max_length[255]|string',
                'label' => 'Заголовок',
            ],
        ];

        if ($this->validateData($data, $rules) === false) {
            throw new ValidationException($this->validator->getErrors());
        }

        return $this->validator->getValidated();
    }
}
