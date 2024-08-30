<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Admin;

use AvegaCms\Controllers\Api\AvegaCmsAPI;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Utilities\CmsModule;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use RuntimeException;

class Category extends AvegaCmsAPI
{
    protected MetaDataModel $MDM;
    protected int $category_mid;
    protected int $meta_blog_id;

    public function __construct()
    {
        parent::__construct();

        $this->MDM          = new MetaDataModel();
        $this->meta_blog_id = (int) $this->MDM->getMetadataModule((int) CmsModule::meta('blog')['id'])->first()->id;
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond($this->MDM->select(
            ['id', 'url', 'slug', 'meta']
        )->where(['category_mid' => $this->category_mid])->findAll());
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

            if (empty($data)) {
                throw new RuntimeException('Запрос пустой');
            }

            $rules = [
                'title' => [
                    'rules' => 'required|min_length[1]|max_length[255]|string',
                    'label' => 'Заголовок',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }

            $data = $this->validator->getValidated();

            if (($id = $this->MDM->insert([
                'parent'          => $this->meta_blog_id,
                'locale_id'       => 1,
                'category_mid'    => $this->category_mid,
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
        if (($category = $this->MDM->where(['category_mid' => $this->category_mid])->find($id)) === null) {
            return $this->failNotFound();
        }

        try {
            $data = $this->getApiData();

            if (empty($data)) {
                throw new RuntimeException('Запрос пустой');
            }

            $rules = [
                'title' => [
                    'rules' => 'required|min_length[1]|max_length[255]|string',
                    'label' => 'Заголовок',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }

            $data = $this->validator->getValidated();

            // Правильно уникальности составного ключа при обновлении
            // Не подбирает себе данные с обновляемого поста
            // И думает что поля не установлены, и валит ошибку
            // Так что необходимо явно установить все составляющие ключа
            // Это parent category_mid item_id use_url_pattern slug
            // + ID, чтобы не было ошибки уникальности
            $data['id']               = $id;
            $data['slug']             = mb_url_title(mb_strtolower($data['title']));
            $data['parent']           = $category->parent;
            $data['updated_by_id']    = $this->userData->userId;
            $data['category_mid']     = $category->category_mid;
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
        if ($this->MDM->where(['id' => $id, 'category_mid' => $this->category_mid])->first() === null) {
            return $this->failNotFound();
        }

        $this->MDM->where(['parent' => $id])->set(['parent' => 0])->update();
        $this->MDM->delete($id);

        cache()->delete('blog_categories');

        return $this->respondNoContent();
    }
}
