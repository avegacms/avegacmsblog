<?php

declare(strict_types=1);

use AvegaCmsBlog\Controllers\Admin\Category;
use AvegaCmsBlog\Controllers\Admin\Posts;
use AvegaCmsBlog\Controllers\Admin\Tags;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('api', static function ($routes) {
    $routes->group('admin', static function ($routes) {
        $routes->group('blog', static function ($routes) {
            $routes->group('category', static function ($routes) {
                $routes->get('/', [Category::class, 'index']);
                $routes->post('/', [Category::class, 'create']);
                $routes->put('(:num)', [[Category::class, 'update'], '$1']);
                $routes->delete('(:num)', [[Category::class, 'delete'], '$1']);
            });

            $routes->group('tags', static function ($routes) {
                $routes->get('/', [Tags::class, 'index']);
                $routes->put('(:num)', [[Tags::class, 'update'], '$1']);
                $routes->post('/', [Tags::class, 'create']);
                $routes->delete('(:num)', [[Tags::class, 'delete'], '$1']);
            });

            $routes->get('/', [Posts::class, 'index']);
            $routes->group('post', static function ($routes) {
                $routes->get('(:segment)', [[Posts::class, 'getPost'], '$1']);
                $routes->put('(:num)', [[Posts::class, 'update'], '$1']);
                $routes->post('/', [Posts::class, 'create']);
                $routes->delete('(:num)', [[Posts::class, 'delete'], '$1']);
            });
        });
    });

    // Что есть публичный?
    // Есть ли разделение на пользователей и гостей?
    // Если да, могут ли пользователи создавать, удалять, изменять свои посты?
    $routes->group('blog', static function ($routes) {
        $routes->group('category', static function ($routes) {
            $routes->get('/', [Category::class, 'index']);
        });

        $routes->group('tags', static function ($routes) {
            $routes->get('/', [Tags::class, 'index']);
        });

        $routes->get('/', [Posts::class, 'index']);
        $routes->group('post', static function ($routes) {
            $routes->get('(:segment)', [[Posts::class, 'getPost'], '$1']);
        });
    });
});
