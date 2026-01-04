<?php

namespace splitbrain\notmore;

use splitbrain\notmore\Controller\HomeController;

class FileRouter extends Router
{
    public function __construct()
    {
        parent::__construct();
        $this->alto->addMatchTypes(['f' => '[a-zA-Z0-9_\\.\\-]+\.(js|css|map|json)']);
        $this->alto->addMatchTypes(['md' => '[a-zA-Z0-9_\\.\\-]+']);
    }

    protected function registerRoutes(): void
    {
        $this->alto->map('GET', '/', [HomeController::class, 'index']);
    }

    protected function onPreflight(): void
    {
    }

    protected function onMatch(array $match): void
    {
        [$controller, $method] = $match['target'];
        call_user_func_array([new $controller(), $method], $match['params']);
    }

    protected function onError(\Exception $e): void
    {
        $code = 500;
        if ($e instanceof HttpException) {
            $code = $e->getCode();
        }

        header('Content-Type: text/html; charset=utf-8', true, $code);
        echo '<html lang="en"><head><title>Error ' . $code . '</title></head><body>';
        echo '<h1>Error ' . $code . '</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
    }
}
