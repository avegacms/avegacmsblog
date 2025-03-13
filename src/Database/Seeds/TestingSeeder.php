<?php

namespace AvegaCmsBlog\Database\Seeds;

use AvegaCms\Enums\MetaDataTypes;
use AvegaCms\Enums\MetaStatuses;
use AvegaCms\Models\Admin\ContentModel;
use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Utilities\CmsModule;
use AvegaCmsBlog\Models\TagsLinksModel;
use AvegaCmsBlog\Models\TagsModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use Faker\Factory;
use Random\RandomException;
use ReflectionException;

class TestingSeeder extends Seeder
{
    /**
     * @throws RandomException
     * @throws ReflectionException
     */
    public function run(): void
    {
        $MDM = new MetaDataModel();
        $TM  = new TagsModel();
        $CM  = new ContentModel();
        $TLM = new TagsLinksModel();

        $faker = Factory::create();

        $post_module  = (int) CmsModule::meta('blog.post')['id'];
        $meta_blog_id = (int) $MDM->getMetadataModule((int) CmsModule::meta('blog')['id'])->first()->id;
        $module_id    = (int) CmsModule::meta('blog.category')['id'];

        $created = [0, 0, 0];

        CLI::write('Creating data for test blog...');

        $countOfCategories = CLI::prompt(
            'How many categories do you want create?',
            null,
            ['required', 'is_natural_no_zero']
        );

        $categories = [];

        for ($i = 0; $i < $countOfCategories; $i++) {
            CLI::showProgress($i + 1, $countOfCategories);
            $title = $faker->words(random_int(1, 6), true);

            if (($id = $MDM->insert([
                'parent'          => $meta_blog_id,
                'locale_id'       => 1,
                'module_id'       => $module_id,
                'slug'            => mb_url_title(mb_strtolower($title)),
                'item_id'         => 0,
                'title'           => $title,
                'url'             => 'blog/{slug}',
                'use_url_pattern' => true,
                'meta'            => [],
                'status'          => MetaStatuses::Publish->name,
                'meta_type'       => MetaDataTypes::Module->name,
                'in_sitemap'      => true,
                'created_by_id'   => 1,
            ])) === false) {
                d($MDM->errors());
                CLI::newLine();

                continue;
            }

            $categories[] = $id;
            $created[0]++;
        }

        CLI::newLine();

        gc_collect_cycles();

        $countOfTags = CLI::prompt(
            'How many tags do you want create?',
            null,
            ['required', 'is_natural_no_zero']
        );

        $tags = [];

        for ($i = 0; $i < $countOfTags; $i++) {
            CLI::showProgress($i + 1, $countOfTags);
            $name = $faker->words(random_int(1, 2), true);
            if (($id = $TM->insert([
                'name'          => trim($name),
                'slug'          => mb_url_title(mb_strtolower($name)),
                'active'        => true,
                'created_by_id' => 1,
            ])) === false) {
                d($TM->errors());
                CLI::newLine();

                continue;
            }

            $tags[] = $id;
            $created[1]++;
        }

        CLI::newLine();

        gc_collect_cycles();

        $countOfPosts = CLI::prompt(
            'How many posts do you want create?',
            null,
            ['required', 'is_natural_no_zero']
        );

        for ($i = 0; $i < $countOfPosts; $i++) {
            CLI::showProgress($i + 1, $countOfPosts);
            $title = $faker->realTextBetween(2, 48);

            $category = $categories[random_int(0, count($categories) - 1)];

            if (($id = $MDM->insert([
                'parent'          => $category,
                'locale_id'       => 1,
                'module_id'       => $post_module,
                'slug'            => mb_url_title(mb_strtolower($title)),
                'creator_id'      => 1,
                'item_id'         => 0,
                'title'           => $title,
                'url'             => 'blog/post/{slug}_{id}',
                'use_url_pattern' => true,
                'meta'            => [],
                'status'          => MetaStatuses::Publish->name,
                'meta_type'       => MetaDataTypes::Module->name,
                'in_sitemap'      => true,
                'created_by_id'   => 1,
            ])) === false) {
                d($MDM->errors());
                CLI::newLine();
                $i -= 0.5;

                continue;
            }

            $CM->insert([
                'id'      => $id,
                'anons'   => $faker->realTextBetween(10, 100),
                'content' => $faker->realTextBetween(100, 2000),
                'extra'   => null,
            ]);

            $postTags = [];

            for ($j = 0, $jMax = random_int(0, min(10, count($tags))); $j < $jMax; $j++) {
                $postTags[] = $tags[random_int(0, count($tags) - 1)];
            }

            $postTags = array_unique($postTags);

            foreach ($postTags as $tag) {
                $TLM->insert([
                    'tag_id'        => $tag,
                    'meta_id'       => $id,
                    'created_by_id' => 1,
                    'updated_by_id' => 0,
                ]);
            }

            $created[2]++;
        }

        CLI::newLine();

        gc_collect_cycles();

        CLI::write('Categories created: ' . $created[0]);
        CLI::write('Tags created: ' . $created[1]);
        CLI::write('Posts created: ' . $created[2]);
    }
}
