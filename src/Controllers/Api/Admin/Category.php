<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\Admin\AvegaCmsAdminAPI;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Utilities\CmsModule;
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

        $this->MDM          = new MetaDataModel();

        $this->MDM->findAll();

        $this->BPM          = new BlogPostsModel();
        $this->meta_blog_id = (int) $this->MDM->getMetadataModule((int) CmsModule::meta('blog')['id'])->first()->id;
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond(
            $this->BPM->select(
                ['metadata.id', 'metadata.url', 'metadata.slug', 'metadata.meta', 'COUNT(metadata.id) as num']
            )
                ->where(['metadata.module_id' => $this->category_mid])
                ->join('metadata as MD', 'metadata.id = MD.parent')
                ->groupBy('metadata.id')
                ->filter(request()->getGet())
                ->apiPagination()
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
            $data = $this->getApiData();

            $data = $this->getValidated($data);

            if (($id = $this->MDM->insert([
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
            ])) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            cache()->delete('blog_categories');

            return $this->cmsRespondCreated($id);
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function update(int $id): ResponseInterface
    {
        if (($category = $this->MDM->where(['module_id' => $this->category_mid])->find($id)) === null) {
            return $this->failNotFound();
        }

        try {
            $data = $this->getApiData();

            $data = $this->getValidated($data);

            // Правильно уникальности составного ключа при обновлении
            // Не подбирает себе данные с обновляемого поста
            // И думает что поля не установлены, и валит ошибку
            // Так что необходимо явно установить все составляющие ключа
            // Это parent module_id item_id use_url_pattern slug
            // + ID, чтобы не было ошибки уникальности
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
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->MDM->where(['id' => $id, 'module_id' => $this->category_mid])->first() === null) {
            return $this->failNotFound();
        }

        $this->MDM->where(['parent' => $id])->set(['parent' => 0])->update();
        $this->MDM->delete($id);

        cache()->delete('blog_categories');

        return $this->respondNoContent();
    }

    /**
     * @param array $data
     * @return array
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
            throw new RuntimeException(implode(' и ', $this->validator->getErrors()));
        }

        return $this->validator->getValidated();
    }
}
