<?php

declare(strict_types=1);

use AvegaCmsBlog\Controllers\Admin\Category;
use AvegaCmsBlog\Controllers\Admin\Posts as AdminPosts;
use AvegaCmsBlog\Controllers\Admin\Tags;
use AvegaCmsBlog\Controllers\Public\Posts as PublicPosts;
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
                $routes->post('/', [AdminPosts::class, 'create']);
                $routes->delete('(:num)', [[AdminPosts::class, 'delete'], '$1']);
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
