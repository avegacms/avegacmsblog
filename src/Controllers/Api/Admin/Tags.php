<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\AvegaCmsAPI;
use AvegaCms\Traits\AvegaCmsApiResponseTrait;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use RuntimeException;

class Tags extends AvegaCmsAPI
{
    use AvegaCmsApiResponseTrait;

    protected TagsModel $TM;

    public function __construct()
    {
        parent::__construct();
        $this->TM = new TagsModel();
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond($this->TM->select(['id', 'name', 'slug', 'active', 'created_by_id'])->findAll());
    }

    public function new(): ResponseInterface
    {
        return $this->cmsRespond([
            'name',
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
                'name' => [
                    'rules' => 'required|string|min_length[3]|max_length[128]',
                    'label' => 'Название',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }

            $data = $this->validator->getValidated();

            if (($id = $this->TM->insert([
                'name'          => trim($data['name']),
                'slug'          => mb_url_title(mb_strtolower($data['name'])),
                'active'        => true,
                'created_by_id' => $this->userData->userId,
            ])) === false) {
                return $this->cmsRespondFail($this->TM->errors());
            }

            return $this->cmsRespondCreated($id);
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            if ($this->TM->find($id) === null) {
                return $this->failNotFound();
            }

            $data = $this->getApiData();

            if (empty($data)) {
                throw new RuntimeException('Запрос пустой');
            }

            $rules = [
                'name' => [
                    'rules' => 'required|min_length[3]|max_length[128]',
                    'label' => 'Название',
                ],
                'active' => [
                    'rules' => 'required|in_list[0,1]',
                    'label' => 'Активность',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }
            $data = $this->validator->getValidated();

            // Необходимо явно передать ID
            // Так как CI4 не подтягивает его сам
            // В итоге, видит, что в ID = 6 такая же запись
            // А сам он себя иденцифицирует, как ID = NULl
            // И валит, что такая запись уже существует
            $data['id']     = $id;
            $data['active'] = (bool) $data['active'];
            $data['slug']   = mb_url_title(mb_strtolower($data['name']));

            if ($this->TM->update($id, $data) === false) {
                return $this->cmsRespondFail($this->TM->errors());
            }

            return $this->respondNoContent();
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function delete($id): ResponseInterface
    {
        if ($this->TM->find($id) === null) {
            return $this->cmsRespondFail('Тег для удаления не найден');
        }

        $this->TM->delete($id);

        return $this->respondNoContent();
    }
}
