<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Api\Admin;

use AvegaCms\Controllers\Api\Admin\AvegaCmsAdminAPI;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Exceptions\AvegaCmsException;
use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Utilities\Cms;
use AvegaCms\Utilities\CmsFileManager;
use AvegaCms\Utilities\CmsModule;
use AvegaCms\Utilities\Exceptions\UploaderException;
use AvegaCmsBlog\Exception\ValidationException;
use AvegaCmsBlog\Models\BlogPostsModel;
use AvegaCmsBlog\Models\Frontend\BlogPostsModel as FrontBlogPostsModel;
use AvegaCmsBlog\Models\TagsLinksModel;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use JsonException;
use ReflectionException;
use RuntimeException;

class Posts extends AvegaCmsAdminAPI
{
    protected ContentModel $CM;
    protected TagsModel $TM;
    protected TagsLinksModel $TLM;
    protected BlogPostsModel $BPM;
    protected int $category_mid;
    protected int $post_mid;

    public function __construct()
    {
        parent::__construct();
        $this->BPM          = new BlogPostsModel();
        $this->CM           = new ContentModel();
        $this->TM           = new TagsModel();
        $this->TLM          = new TagsLinksModel();
        $this->category_mid = (int) CmsModule::meta('blog.category')['id'];
        $this->post_mid     = (int) CmsModule::meta('blog.post')['id'];
    }

    public function index(): ResponseInterface
    {
        $posts = $this->BPM->getPosts($this->post_mid, $this->request->getGet() ?? []);

        return $this->cmsRespond($posts);
    }

    public function new(): ResponseInterface
    {
        return $this->cmsRespond([], [
            'categories' => $this->BPM->getCategoriesForDropdown($this->category_mid),
        ]);
    }

    public function getPosts()
    {
        return $this->cmsRespond(model(FrontBlogPostsModel::class)
            ->getPosts($this->post_mid, $this->request->getGet() ?? [], true));
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->getApiData();

            $data['status'] = MetaStatuses::Publish->name;

            $data = $this->getValidated($data);

            $create_array = $this->getMetaArray($data);

            $exists = $this->BPM->where(
                    [
                        'slug'      => $create_array['slug'],
                        'module_id' => $this->post_mid,
                    ]
                )->first() !== null;

            if ($exists) {
                return $this->cmsRespondFail('Данное имя уже занято');
            }

            if (($id = $this->BPM->insert($create_array)) === false) {
                return $this->cmsRespondFail($this->BPM->errors());
            }

            $this->setContent($id, $data);

            $this->setTags($id, $data);

            return $this->cmsRespondCreated($id);
        } catch (Exception|ValidationException $e) {
            log_message(
                'error',
                sprintf('[Blog : Post updating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

            return $this->cmsException($e);
        }
    }

    public function edit(int $id): ResponseInterface
    {
        if (($post = $this->BPM->getPost($id, $this->post_mid)) === null) {
            return $this->failNotFound();
        }

        return $this->cmsRespond((array) $post,[
            'categories'    => $this->BPM->getCategoriesForDropdown($this->category_mid),
            'tags'          => $this->TM->getTagsForDropdown()
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        try {
            if (($post = $this->BPM->getPost($id, $this->post_mid)) === null) {
                return $this->failNotFound();
            }

            $data = $this->getApiData();

            if (isset($data['parent']))
            {
                $data['category'] = $data['parent'];
            }

            $data = $this->getValidated($data);

            $update_array = $this->getMetaArray($data);

            $exists = $this->BPM->where(
                    [
                        'slug'      => $update_array['slug'],
                        'module_id' => $this->post_mid,
                        'id !='     => $id,
                    ]
                )->first() !== null;

            if ($exists) {
                return $this->cmsRespondFail('Данное имя уже занято');
            }

            $this->CM->delete($post->id);
            $this->TLM->where(['meta_id' => $post->id])->delete();

            $update_array['meta']             = $post->meta;
            $update_array['meta']['title']    = $data['title'];
            $update_array['meta']['og:title'] = $data['title'];

            if (isset($data['meta']))
            {
                $update_array['meta']         = $data['meta'];
            }

            if (isset($data['meta_sitemap']))
            {
                $update_array['meta_sitemap'] = $data['meta_sitemap'];
            }

            if (isset($update_array['in_sitemap']))
            {
                $update_array['in_sitemap']   = (bool) $data['in_sitemap'];
            }
            if ($this->BPM->update($id, ['id' => $id, ...$update_array]) === false) {
                return $this->cmsRespondFail($this->BPM->errors());
            }

            $this->setContent($id, $data);

            $this->setTags($id, $data);
        } catch (Exception|ValidationException $e) {
            log_message(
                'error',
                sprintf('[Blog : Post updating] : %s & %s', $e->getMessage(), $e->getTraceAsString())
            );

            return $this->cmsException($e);
        }

        return $this->respondNoContent();
    }

    public function deleteImage(int $id): ResponseInterface
    {
        try {
            if ($this->BPM->where([
                    'module_id' => $this->post_mid,
                ])->find($id) === null) {
                return $this->failNotFound();
            }

            $this->BPM->builder()?->resetQuery();

            if ($this->BPM->update($id, ['preview_id' => null]) === false) {
                throw new AvegaCmsException($this->BPM->errors());
            }

            return $this->respondNoContent();
        } catch (Exception $e) {
            return $this->cmsException($e);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->BPM->where([
                'module_id' => $this->post_mid,
            ])->find($id) === null) {
            return $this->failNotFound();
        }
        $this->BPM->delete($id);
        $this->CM->delete($id);

        return $this->respondNoContent();
    }

    public function upload(int $id): ResponseInterface
    {
        if ($this->BPM->where([
                'module_id' => $this->post_mid,
            ])->find($id) === null) {
            return $this->failNotFound();
        }

        try {
            $preview = CmsFileManager::upload(
                [
                    'module_id' => (int) CmsModule::meta('blog')['id'],
                    'entity_id' => 1,
                    'item_id'   => 1,
                    'user_id'   => $this->userData->userId,
                ],
                [
                    'field'     => 'file',
                    'directory' => 'blog',
                    'extType'   => 'images',
                    'maxDims'   => '4096,4096',
                    'maxSize'   => 24576,
                ],
                [
                    'resize' => json_decode(
                        Cms::settings('core.env.blog'),
                        true,
                        512,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                    ),
                ]
            )[0];

            if (! $this->BPM->update($id, ['id' => $id, 'preview_id' => (int) $preview->id])) {
                return $this->failValidationErrors($this->BPM->errors());
            }

            return $this->respondNoContent();
        } catch (JsonException|ReflectionException|UploaderException $e) {
            return $this->cmsException($e);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function getValidated(array $data): array
    {
        $rules = $this->rules();

        if ($this->validateData($data, $rules) === false) {
            throw new AvegaCmsException($this->validator->getErrors());
        }

        if ($this->BPM->where(['id' => $data['category'], 'module_id' => $this->category_mid])->first() === null) {
            throw new RuntimeException('Неизвестная категория');
        }

        if (isset($data['tags']) && (is_array($data['tags'])) === false) {
            throw new RuntimeException('Поле "Теги" должно быть массивом с списком номеров');
        }

        return $this->validator->getValidated();
    }

    protected function getMetaArray(array $data): array
    {
        $array = [
            'parent'          => $data['category'],
            'locale_id'       => 1,
            'module_id'       => $this->post_mid,
            'slug'            => mb_url_title(mb_strtolower($data['title'])),
            'creator_id'      => 1,
            'item_id'         => 0,
            'title'           => $data['title'],
            'url'             => 'blog/post/{slug}_{id}',
            'use_url_pattern' => true,
            'status'          => $data['status'],
            'meta_type'       => MetaDataTypes::Module->name,
            'in_sitemap'      => true,
            'created_by_id'   => $this->userData->userId,
        ];

        if (isset($data['preview_id'])) {
            $array['preview_id'] = (int) $data['preview_id'];
        }

        if (isset($data['meta'])) {
            $array['meta'] = [];
        }

        return $array;
    }

    /**
     * @throws ReflectionException
     * @throws ValidationException
     */
    protected function setContent(int $id, array $data): void
    {
        if ($this->CM->insert([
                'id' => $id,
                'anons' => $data['anons'] ?? null,
                'content' => $data['content'] ?? null,
                'extra' => $data['extra'] ?? null,
            ]) === false) {
            throw new AvegaCmsException($this->CM->errors());
        }
    }

    /**
     * @throws ValidationException
     * @throws ReflectionException
     */
    protected function setTags(int $id, array $data)
    {
        if (isset($data['tags'])) {
            $data['tags'] = array_unique($data['tags']);
            $tags         = array_column($this->TM->getTags(), 'name', 'id');
            $batch        = [];

            foreach ($data['tags'] as $tag) {
                if (isset($tags[$tag]) === false) {
                    $this->BPM->delete($id);
                    $this->CM->delete($id);
                    throw new RuntimeException('Тег ' . $tag . ' не существует');
                }
                $batch[] = [
                    'tag_id'        => $tag,
                    'meta_id'       => $id,
                    'created_by_id' => $this->userData->userId,
                    'updated_by_id' => 0,
                ];
            }

            if (empty($batch)) {
                return;
            }

            if ($this->TLM->insertbatch($batch) !== count($batch)) {
                throw new AvegaCmsException($this->TLM->errors());
            }
        }
    }

    protected function rules(): array
    {
        return [
            'title' => [
                'rules' => 'required|min_length[1]|max_length[255]|string',
                'label' => 'Заголовок',
            ],
            'anons' => [
                'rules' => 'permit_empty|string|max_length[255]',
                'label' => 'Анонс',
            ],
            'category' => [
                'rules' => 'required|is_natural_no_zero',
                'label' => 'Категория',
            ],
            'tags' => [
                'rules' => 'permit_empty',
                'label' => 'Теги',
            ],
            'tags.*' => [
                'rules' => 'if_exist|is_natural_no_zero',
                'label' => 'Теги',
            ],
            'content' => [
                'rules' => 'permit_empty|string',
                'label' => 'Контент',
            ],
            'extra' => [
                'rules' => 'permit_empty|string',
                'label' => 'Дополнительно',
            ],
            'status' => [
                'rules' => 'required|in_list[' . implode(',', MetaStatuses::get('name')) . ']',
                'label' => 'Статус',
            ],
            'preview_id' => [
                'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[files.id]',
                'label' => 'Номер превью',
            ],
            'in_sitemap' => [
                'rules' => 'permit_empty',
                'label' => 'Присутствие в sitemap'
            ],
            'meta' => [
                'rules' => 'permit_empty',
                'label' => 'OpenGraph мета данные'
            ],
            'meta_sitemap' => [
                'rules' => 'permit_empty',
                'label' => 'SiteMap мета данные'
            ]
        ];
    }
}
