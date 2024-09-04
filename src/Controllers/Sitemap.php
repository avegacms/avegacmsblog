<?php

declare(strict_types=1);

namespace AvegaCmsBlog\Controllers;

use AvegaCms\Models\Admin\MetaDataModel;
use AvegaCms\Models\Admin\MetaDataSiteMapModel;
use AvegaCms\Traits\AvegaCmsSitemapTrait;
use AvegaCms\Utilities\CmsModule;
use CodeIgniter\Controller;
use ReflectionException;

class Sitemap extends Controller
{
    use AvegaCmsSitemapTrait;

    /**
     * @throws ReflectionException
     */
    public function generate(): void
    {
        $post = CmsModule::meta('blog.post')['id'];
        $category = CmsModule::meta('blog.category')['id'];

        $array = [
            ...(new MetaDataSiteMapModel())->getContentSitemap('Module', $category),
            ...(new MetaDataSiteMapModel())->getContentSitemap('Module', $post),
        ];

        $MDM = (new MetaDataModel())->select(['id','slug'])
            ->orWhere(['module_id' => $post])
            ->orWhere(['module_id' => $category])
            ->findAll();

        foreach ($array as $number => $page) {
            $id = null;
            foreach ($MDM as $key => $item) {
                if ($array[$number]->id === $MDM[$key]->id)
                {
                    $id = $key;
                    break;
                }
            }

            if (empty($id))
            {
                continue;
            }

            $page->url = str_replace(['{slug}', '{id}'], [$MDM[$key]->slug, $MDM[$key]->id], $page->url) ;
        }

        $this->moduleName = 'AvegaCmsBlog';
        $this->setGroup(
            list: $array
        );
    }
}
