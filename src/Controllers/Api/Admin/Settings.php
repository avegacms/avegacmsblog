<?php
declare(strict_types=1);
namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\Admin\AvegaCmsAdminAPI;
use AvegaCms\Utilities\Cms;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class Settings extends AvegaCmsAdminAPI
{
    public function index(): ResponseInterface
    {
        try {
            return $this->cmsRespond(
                json_decode(
                    Cms::settings('core.env.blog'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ),
            );
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function update(): ResponseInterface
    {
        try {
            $data = $this->getApiData();

            $rules = [
                'variants' => [
                    'rules' => 'required',
                    'label' => 'Варианты',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }

            $data = $this->validator->getValidated();

            if (is_array($data['variants']) === false) {
                return $this->cmsRespondFail('Варианты не являются массивом');
            }

            $rules = [
                'height' => [
                    'rules' => 'required|is_natural_no_zero',
                ],
                'width' => [
                    'rules' => 'required|is_natural_no_zero',
                ],
                'quality' => [
                    'rules' => 'required|is_natural_no_zero',
                ],
                'masterDim' => [
                    'rules' => 'required|in_list[height,width]',
                ],
            ];

            foreach ($data['variants'] as $key => $value) {
                if (is_array($value) === false) {
                    return $this->cmsRespondFail($key . ' не является массивом');
                }
                if ($this->validateData($value, $rules) === false) {
                    return $this->cmsRespondFail($this->validator->getErrors());
                }
            }

            Cms::settings('core.env.blog', json_encode($data['variants'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $this->respondNoContent();
        } catch (Exception $e) {
            return $this->cmsRespondFail($e->getMessage());
        }
    }
}
