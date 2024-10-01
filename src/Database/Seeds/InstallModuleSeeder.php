<?php

namespace AvegaCmsBlog\Database\Seeds;

use AvegaCms\Enums\FieldsReturnTypes;
use AvegaCms\Utilities\Cms;
use AvegaCms\Utilities\CmsFileManager;
use AvegaCms\Utilities\CmsModule;
use CodeIgniter\Database\Seeder;
use Exception;
use ReflectionException;

class InstallModuleSeeder extends Seeder
{
    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function run(): void
    {
        CmsModule::install(
            [
                'slug'        => 'blog',
                'subModules'  => ['category', 'post'],
                'roles'       => ['root', 'admin'],
                'className'   => 'AvegaCmsBlog',
                'urlPatterns' => [
                    'blog'     => 'blog',
                    'category' => 'blog/{slug}',
                    'post'     => 'blog/{slug}_{id}',
                ],
                'inSitemap' => true,
            ]
        );
        CmsModule::createModulePage('blog', 'Блог', 'blog', slug: 'blog');

        CmsFileManager::createDirectory(
            'blog',
            [
                'module_id' => CmsModule::meta('blog')['id'],
            ]
        );

        Cms::settings('core.env.blog', json_encode([
            'big' => [
                'width'     => 1024,
                'height'    => 512,
                'masterDim' => 'height',
                'quality'   => 90,
            ],
            'mini' => [
                'width'     => 64,
                'height'    => 32,
                'masterDim' => 'height',
                'quality'   => 90,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), [
            'return_type' => FieldsReturnTypes::Json->value,
            'label'       => 'Blog.label.caption',
            'context'     => 'Blog.context.description',
            'is_core'     => 1,
        ]);
    }
}
