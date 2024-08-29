<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Admin;

use App\Controllers\BaseController;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Models\Admin\ModulesModel;
use AvegaCms\Traits\AvegaCmsApiResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use ReflectionException;
use RuntimeException;

class Category extends BaseController
{
    use AvegaCmsApiResponseTrait;

    protected MetaDataModel $MDM;
    protected int $module_id;
    protected int $meta_blog_id;

    public function __construct()
    {
        $this->MDM          = new MetaDataModel();
        $this->meta_blog_id = $this->MDM->join('modules', 'modules.id = metadata.module_id')->where(['modules.slug' => 'blog'])->first()->id;
        $this->module_id    = (int) ((new ModulesModel())->where(['key' => 'blog.category'])->first())->id;
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond($this->MDM->select(['id', 'url', 'slug', 'meta'])->where(['module_id' => $this->module_id])->findAll());
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
            $data = (array) $this->request->getJSON();

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
                'module_id'       => $this->module_id,
                'slug'            => mb_url_title(mb_strtolower($data['title'])),
                'creator_id'      => 1,
                'item_id'         => 0,
                'title'           => $data['title'],
                'url'             => 'blog/category/{slug}',
                'use_url_pattern' => true,
                'meta'            => [],
                'status'          => MetaStatuses::Publish->name,
                'meta_type'       => MetaDataTypes::Module->name,
                'in_sitemap'      => true,
                'created_by_id'   => 1,
            ])) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            return $this->cmsRespondCreated($id);
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function update(int $id): ResponseInterface
    {
        if (($category = $this->MDM->where(['module_id' => $this->module_id])->find($id)) === null) {
            return $this->failNotFound();
        }

        try {
            $data = request()->getJSON(true);

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
            // Это parent module_id item_id use_url_pattern slug
            // + ID, чтобы не было ошибки уникальности
            $data['id']              = $id;
            $data['slug']            = mb_url_title(mb_strtolower($data['title']));
            $data['parent']          = $category->parent;
            $data['module_id']       = $category->module_id;
            $data['item_id']         = $category->item_id;
            $data['use_url_pattern'] = $category->use_url_pattern;
            $data['meta']            = $category->meta;
            $data['meta']['title']   = $data['title'];

            if ($this->MDM->update($id, $data) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            return $this->respondNoContent();
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    /**
     * @throws ReflectionException
     */
    public function delete(int $id): ResponseInterface
    {
        if ($this->MDM->where(['id' => $id, 'module_id' => $this->module_id])->first() === null) {
            return $this->failNotFound();
        }

        $this->MDM->where(['parent' => $id])->set(['parent' => 0])->update();
        $this->MDM->delete($id);

        return $this->respondNoContent();
    }
}
