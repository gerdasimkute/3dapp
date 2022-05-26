<?php

require_once 'pdo.php';
require_once 'streams.php';

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Controller
{

    private $app;
    private $model;

    private $uploads;

    public function __construct($model)
    {
        $this->model = $model;

        $this->app = new \Slim\App([
            'settings' => [
                'displayErrorDetails' => true,
            ],
        ]);
        $container = $this->app->getContainer();
        $container['upload_directory'] = __DIR__ . '/database/uploads';
        //Override the default Not Found Handler before creating App
        $container['notFoundHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                return $response->withStatus(404)
                    ->withHeader('Content-Type', 'text/html')
                    ->write('404 page not found: ' . $_SERVER['REQUEST_URI'] . "<br/>Your SLIM app var_dump:" . var_dump($request));
            };
        };
        $this->uploads = "database/uploads/";
    }

    public function setup_pages()
    {
        $page = $this->model->spa_page();

        $this->app->get('/', function ($request, $response, $args) use ($page) {
            return $response->withBody($page);
        });

        $self = $this;

        $this->app->get('/file', function ($request, $response, $args) use ($self) {
            return $response->withStatus(200)
                ->withHeader('Content-Type', 'text/json')
                ->write($self->model->list_files());
        });

        $this->app->get('/post', function ($request, $response, $args) use ($self) {
            return $response->withStatus(200)
                ->withHeader('Content-Type', 'text/json')
                ->write($self->model->list_posts());
        });

        $this->app->get('/file/{key}', function ($request, $response, $args) use ($self) {
            $json_response = $response->withHeader('Content-Type', 'text/json');
            $key = $args['key'];
            try {
                $result = $self->model->select_file($key);
                return $json_response
                    ->withStatus(200)
                    ->write($result);
            } catch (Exception $e) {
                return $json_response
                    ->withStatus(503)
                    ->write(json_encode(['error' => var_export($e)]));
            }
        });

        $this->app->get('/post/{key}', function ($request, $response, $args) use ($self) {
            $json_response = $response->withHeader('Content-Type', 'text/json');
            $key = $args['key'];
            try {
                $result = $self->model->select_post($key);
                return $json_response
                    ->withStatus(200)
                    ->write($result);
            } catch (Exception $e) {
                return $json_response
                    ->withStatus(503)
                    ->write(json_encode(['error' => var_export($e)]));
            }
        });

        $this->app->post('/file', function ($request, $response, $args) use ($self) {
            $json_response = $response->withHeader('Content-Type', 'text/json');
            $result = $self->model->insert_file($request, $response);
            return $json_response
                ->write($result);
        });

        $this->app->post('/post', function ($request, $response, $args) use ($self) {
            $json_response = $response->withHeader('Content-Type', 'text/json');
            $result = $self->model->insert_post($request, $response);
            return $json_response
                ->write($result);
        });

        $this->app->get('/test', function ($request, $response, $args) {
            $first = Psr7\Utils::streamFor('aaa {{%%%}} bbb123');
            // check replacement happens
            // check it handles long length
            $second = Psr7\Utils::streamFor('5---------------------- {{%%%}} ----------------------4---------------------- 456 ----------------------3---------------------- 456 ----------------------2---------------------- {{%%%}} ----------------------1---------------------- 456 ----------------------');
            return $response->withBody(do_replacement(do_replacement(do_replacement($first, $second),Utils::streamFor("abc")), Utils::streamFor("789")));
        });
    }

    public function serve_request()
    {
        // Run app
        $this->app->run();
    }
}
