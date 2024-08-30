<?php

namespace AvegaCmsBlog\Database\Seeds;

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
                'className'   => 'Posts',
                'urlPatterns' => [
                    'blog'     => 'blog',
                    'category' => 'blog/{slug}',
                    'post'     => 'blog/{slug}/{post}',
                ],
                'inSitemap' => true,
            ]
        );

        CmsModule::createModulePage('blog', 'Блог', 'blog');
    }
}
