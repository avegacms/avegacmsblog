<?php

declare(strict_types=1);

use AvegaCmsBlog\Controllers\Api\Admin\Category;
use AvegaCmsBlog\Controllers\Api\Admin\Posts as AdminPosts;
use AvegaCmsBlog\Controllers\Api\Admin\Settings;
use AvegaCmsBlog\Controllers\Api\Admin\Tags;
use AvegaCmsBlog\Controllers\Api\Public\Posts as PublicPosts;
use AvegaCmsBlog\Controllers\Blog;
use AvegaCmsBlog\Controllers\Category as FrontCategory;
use AvegaCmsBlog\Controllers\Post;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('api', static function (RouteCollection $routes) {
    $routes->group('admin', static function (RouteCollection $routes) {
        $routes->group('blog', static function (RouteCollection $routes) {
            $routes->group('category', static function (RouteCollection $routes) {
                $routes->get('/', [Category::class, 'index']);
                $routes->post('/', [Category::class, 'create']);
                $routes->put('(:num)', [[Category::class, 'update'], '$1']);
                $routes->delete('(:num)', [[Category::class, 'delete'], '$1']);
            });

            $routes->group('tags', static function (RouteCollection $routes) {
                $routes->get('/', [Tags::class, 'index']);
                $routes->put('(:num)', [[Tags::class, 'update'], '$1']);
                $routes->post('/', [Tags::class, 'create']);
                $routes->delete('(:num)', [[Tags::class, 'delete'], '$1']);
            });

            $routes->group('post', static function (RouteCollection $routes) {
                $routes->get('/', [AdminPosts::class, 'index']);
                $routes->get('(:num)', [[AdminPosts::class, 'edit'], '$1']);
                $routes->put('(:num)', [[AdminPosts::class, 'update'], '$1']);
                $routes->put('upload/(:num)', [[AdminPosts::class, 'upload'], '$1']);
                $routes->post('/', [AdminPosts::class, 'create']);
                $routes->delete('(:num)', [[AdminPosts::class, 'delete'], '$1']);
            });

            $routes->group('settings', static function (RouteCollection $routes) {
                $routes->get('/', [Settings::class, 'index']);
                $routes->put('/', [Settings::class, 'update']);
            });
        });
    });

    $routes->group('public', static function (RouteCollection $routes) {
        $routes->group('blog', static function (RouteCollection $routes) {
            $routes->group('tag', static function (RouteCollection $routes) {
                $routes->get('(:segment)', [PublicPosts::class, 'byTag']);
            });

            $routes->get('/', [PublicPosts::class, 'index']);
            $routes->group('post', static function (RouteCollection $routes) {
                $routes->get('(:segment)', [[PublicPosts::class, 'getPost'], '$1']);
            });
        });
    });
});

$routes->group('blog', static function (RouteCollection $routes) {
    $routes->get('/', [Blog::class, 'index']);
    $routes->get('post/(:segment)', [[Post::class, 'index'], '$1']);
    $routes->get('(:segment)', [[FrontCategory::class, 'index'], '$1']);
});
