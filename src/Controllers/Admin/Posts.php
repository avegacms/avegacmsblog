<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers\Admin;

use App\Controllers\BaseController;
use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Traits\AvegaCmsApiResponseTrait;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\BlogPostsModel;
use AvegaCmsBlog\Models\TagsLinksModel;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;
use JsonException;
use RuntimeException;
use stdClass;

class Posts extends BaseController
{
    use AvegaCmsApiResponseTrait;

    protected MetaDataModel $MDM;
    protected ContentModel $CM;
    protected TagsModel $TM;
    protected TagsLinksModel $TLM;
    protected int $category_module;
    protected int $post_module;
    protected BlogPostsModel $BPM;

    public function __construct()
    {
        $this->MDM             = new MetaDataModel();
        $this->BPM             = new BlogPostsModel();
        $this->CM              = new ContentModel();
        $this->TM              = new TagsModel();
        $this->TLM             = new TagsLinksModel();
        $this->category_module = (int) CmsModule::meta('blog.category')['id'];
        $this->post_module     = (int) CmsModule::meta('blog.post')['id'];
    }

    public function index(): ResponseInterface
    {
        $posts = $this->BPM->getBlogPosts($this->post_module, $this->request->getGet() ?? []);

        if (empty($posts['list'])) {
            return $this->failNotFound();
        }

        return $this->cmsRespond($posts, [
            'categories' => $this->BPM->getCategories($this->category_module),
            'tags'       => $this->TM->getTags(),
        ]);
    }

    public function new(): ResponseInterface
    {
        return $this->cmsRespond([
            'title',
            'anons',
            'category',
            'tags',
            'content',
            'extra',
        ]);
    }

    public function create(): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                throw new RuntimeException('Запрос пустой');
            }

            $rules = [
                'title' => [
                    'rules' => 'required|min_length[1]|max_length[255]|string',
                    'label' => 'Заголовок',
                ],
                'anons' => [
                    'rules' => 'required|string|max_length[255]',
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
                    'rules' => 'required|string',
                    'label' => 'Контент',
                ],
                'extra' => [
                    'rules' => 'permit_empty|string',
                    'label' => 'Дополнительно',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }

            if ($this->MDM->where(['id' => $data['category'], 'module_id' => $this->category_module])->first() === null) {
                throw new RuntimeException('Неизвестная категория');
            }

            if (isset($data['tags']) && (is_array($data['tags'])) === false) {
                throw new RuntimeException('Поле "Теги" должно быть массивом с списком номеров');
            }

            $data = $this->validator->getValidated();

            if (($id = $this->MDM->insert([
                'parent'          => $data['category'],
                'locale_id'       => 1,
                'module_id'       => $this->post_module,
                'slug'            => mb_url_title(mb_strtolower($data['title'])),
                'creator_id'      => 1,
                'item_id'         => 0,
                'title'           => $data['title'],
                'url'             => 'blog/post/{slug}_{id}',
                'use_url_pattern' => true,
                'meta'            => [],
                'status'          => MetaStatuses::Publish->name,
                'meta_type'       => MetaDataTypes::Module->name,
                'in_sitemap'      => true,
                'created_by_id'   => 1,
            ])) === false) {
                return $this->cmsRespondFail($this->MDM->errors());
            }

            if ($this->CM->insert([
                'id'      => $id,
                'anons'   => $data['anons'],
                'content' => $data['content'],
                'extra'   => $data['extra'] ?? null,
            ]) === false) {
                return $this->cmsRespondFail($this->CM->errors());
            }

            if (isset($data['tags'])) {
                $data['tags'] = array_unique($data['tags']);
                $tags         = array_column((new TagsModel())->findAll(), 'name', 'id');

                foreach ($data['tags'] as $tag) {
                    if (isset($tags[$tag]) === false) {
                        continue;
                    }
                    $this->TLM->insert([
                        'tag_id'        => $tag,
                        'meta_id'       => $id,
                        'created_by_id' => 1,
                    ]);
                }
            }

            return $this->cmsRespondCreated($id);
        } catch (Exception|HTTPException $e) {
            if (env('CI_ENVIRONMENT') === 'development') {
                log_message(
                    'error',
                    sprintf('[Blog : Post Creation] : %s', $e->getMessage())
                );
            }

            return $this->cmsRespondFail($e->getMessage());
        }
    }

    public function getPost(string $slug): ResponseInterface
    {
        if ((count($id = explode('_', $slug)) !== 2) || ((int) $id[1] === 0)) {
            return $this->failNotFound();
        }

        if (($post = $this->BPM->getBlogPost((int) $id[1], $this->post_module)) === null) {
            return $this->failNotFound();
        }

        return $this->cmsRespond((array) $post, [
            'categories' => $this->BPM->getCategories($this->category_module),
            'tags'       => $this->TM->getTags(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        try {
            if (($post = $this->BPM->getBlogPost($id, $this->post_module)) === null) {
                return $this->failNotFound();
            }

            $data = request()->getJSON(true);

            if (empty($data)) {
                throw new RuntimeException('Запрос пустой');
            }

            $rules = [
                'title' => [
                    'rules' => 'required|min_length[1]|max_length[255]|string',
                    'label' => 'Заголовок',
                ],
                'anons' => [
                    'rules' => 'required|string|max_length[255]',
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
                    'rules' => 'required|string',
                    'label' => 'Контент',
                ],
                'extra' => [
                    'rules' => 'permit_empty',
                    'label' => 'Дополнительно',
                ],
                'status' => [
                    'rules' => 'required|in_list[' . implode(',', MetaStatuses::get('name')) . ']',
                    'label' => 'Статус',
                ],
            ];

            if ($this->validateData($data, $rules) === false) {
                return $this->cmsRespondFail($this->validator->getErrors());
            }
            $data = $this->validator->getValidated();

            if (isset($data['extra']) && is_array($data['extra']) === false) {
                return $this->cmsRespondFail('Поле "Дополнительно" должно быть объектом');
            }
            // Обнаруживаем по таблицам изменилось ли что-то, когда нам отправляют все данные
            // Чтобы не дергаться сразу все 3 таблицы, а лишь необходимые

            // Array_diff работает только относительно первого массива, т.е.
            // Оно смотрит всё ли входит в 1 массив из 2 массива
            // Следовательно в 2 массиве может быть больше чем в 1
            // Но так как всё, что в 1 присутствует, оно не засчитывает
            // Следовательно требуется 2 раза прогнать с разными позициями
            // Чтобы понять, что удалилось, а что добавилось
            $tags = $post->tags ?? [];

            $less = array_diff(
                $tags,
                $data['tags'] ?? []
            );
            if (empty($less) === false) {
                foreach ($less as $tag) {
                    $this->TLM->where(['meta_id' => $id, 'tag_id' => $tag])->delete();
                }
            }

            $more = array_diff(
                $data['tags'] ?? [],
                $tags
            );
            if (empty($more) === false) {
                $tags  = array_column($this->TM->getTags(), 'name', 'id');
                $batch = [];

                foreach ($more as $tag) {
                    if (isset($tags[$tag])) {
                        $batch[] = ['meta_id' => $id, 'tag_id' => $tag, 'created_by_id' => 1, 'updated_by_id' => 1];
                    } else {
                        throw new RuntimeException('Неудалось найти тег ' . $tag);
                    }
                }
            }

            if ($data['category'] !== $post->parent
                || $data['title'] !== $post->title
                || $data['status'] !== $post->status) {
                $metadata = $this->MDM->find($id);

                $slug = strtolower((mb_url_title($data['title'])));

                if (isset($data['category'])) {
                    $category = $this->MDM->where(['module_id' => $this->category_module])->find($data['category']);

                    if ($category === null) {
                        throw new RuntimeException('Категория не найдена');
                    }
                }

                $meta             = $metadata->meta;
                $meta['title']    = $data['title'];
                $meta['og:title'] = $data['title'];
                $insert           = (array) $metadata;
                unset($insert['page_type']);
                $insert['id']     = $id;
                $insert['title']  = $data['title'] ?? $metadata->title;
                $insert['slug']   = $slug;
                $insert['meta']   = $meta;
                $insert['parent'] = $data['category'] ?? $metadata->parent;
                $insert['url']    = $metadata->url;
                $insert['status'] = $data['status'] ?? $metadata->status;
                $insert['preview_id'] ??= 0;
                unset($insert['updated_at']);
                if ($this->MDM->update($id, $insert) === false) {
                    return $this->cmsRespondFail($this->MDM->errors());
                }
            }

            if ($data['content'] !== $post->content
                || $data['anons'] !== $post->anons
                || ($data['extra'] ?? []) !== $post->extra) {
                $content = $this->CM->find($id);
                $this->CM->update($id, [
                    'title'   => $data['title'],
                    'anons'   => $data['anons'],
                    'content' => $data['content'],
                    'extra'   => $data['extra'] ?? $content->extra,
                ]);
            }
            if (empty($more) === false) {
                $this->TLM->insertBatch($batch);
            }
        } catch (Exception $e) {
            if (env('CI_ENVIRONMENT') === 'development') {
                log_message(
                    'error',
                    sprintf('[Blog : Updating Post] : %s', $e->getMessage())
                );
            }

            return $this->cmsRespondFail($e->getMessage());
        }

        return $this->respondNoContent();
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->MDM->where([
            'module_id' => $this->post_module,
        ])->find($id) === null) {
            return $this->failNotFound();
        }
        $this->MDM->delete($id);
        $this->CM->delete($id);

        return $this->respondNoContent();
    }

    private function getTagName($tagId): ?stdClass
    {
        foreach ($this->TM->getTags() as $tag) {
            if ($tag->id === $tagId) {
                return $tag;
            }
        }

        return null;
    }

    // Надо узнать, как передавать теги, идом, чтобы с мета собрал фронт, или названием, если вторым, то эта функция пригодится
    private function parseTags(array|string $tags): array
    {
        try {
            if (is_array($tags) === false) {
                $tags = json_decode($tags, true, 512, JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);
            }
            $return = [];

            foreach ($tags as $tag) {
                if ($tag !== null) {
                    $return[] = $this->getTagName($tag);
                }
            }

            return $return;
        } catch (Exception|JsonException $e) {
            log_message(
                'error',
                sprintf('[Blog : Parse Tags] : %s', $e->getMessage())
            );

            return [];
        }
    }
}
