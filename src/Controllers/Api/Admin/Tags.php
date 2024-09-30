<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\Admin\AvegaCmsAdminAPI;
use AvegaCms\Traits\AvegaCmsApiResponseTrait;
use AvegaCmsBlog\Models\TagsLinksModel;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use RuntimeException;

class Tags extends AvegaCmsAdminAPI
{
    use AvegaCmsApiResponseTrait;

    protected TagsModel $TM;
    protected TagsLinksModel $TLM;

    public function __construct()
    {
        parent::__construct();
        $this->TM  = new TagsModel();
        $this->TLM = new TagsLinksModel();
    }

    public function index(): ResponseInterface
    {
        return $this->cmsRespond($this->TLM->getTags(request()->getGet()));
    }

    public function edit(int $id): ResponseInterface
    {
        return $this->cmsRespond( (array)
            $this->TM
            ->select([
                'id',
                'name',
                'slug',
                'active',
                'created_by_id'
            ])
            ->find($id)
        );
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

            $data['active'] = 1;

            $data = $this->getValidated($data);

            $data = [
                'name'          => trim($data['name']),
                'slug'          => mb_url_title(mb_strtolower($data['name'])),
                'active'        => true,
                'created_by_id' => $this->userData->userId,
            ];

            if (($id = $this->TM->insert($data)) === false) {
                return $this->cmsRespondFail($this->TM->errors());
            }

            return $this->cmsRespondCreated($id);
        } catch (Exception $e) {
            log_message(
                'error',
                sprintf('[Blog : Tags creating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

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
            $data = $this->getValidated($data);

            $data['id']            = $id;
            $data['active']        = !is_bool($data['active']) || $data['active'];
            $data['slug']          = mb_url_title(mb_strtolower($data['name']));
            $data['updated_by_id'] = $this->userData->userId;

            if ($this->TM->save($data) === false) {
                return $this->cmsRespondFail($this->TM->errors());
            }

            return $this->respondNoContent();
        } catch (Exception $e) {
            log_message(
                'error',
                sprintf('[Blog : Tags updating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

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

    protected function getValidated(array $data): array
    {
        $rules = [
            'name' => [
                'rules' => 'required|min_length[3]|max_length[128]',
                'label' => 'Название',
            ],
            'active' => [
                'rules' => 'required',
                'label' => 'Активность',
            ],
        ];

        if ($this->validateData($data, $rules) === false) {
            throw new RuntimeException(implode(' и ', $this->validator->getErrors()));
        }

        return $this->validator->getValidated();
    }
}
