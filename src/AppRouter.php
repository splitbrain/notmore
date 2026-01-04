<?php

namespace splitbrain\notmore;

use splitbrain\notmore\Controller\AbstractController;
use splitbrain\notmore\Controller\HomeController;
use splitbrain\notmore\Controller\MailController;

class AppRouter extends Router
{
    private ?App $app = null;

    public function __construct()
    {
        parent::__construct();
        $this->alto->addMatchTypes(['t' => '[A-Za-z0-9._+\\-]+']);
    }

    protected function registerRoutes(): void
    {
        $this->alto->map('GET', '/', [HomeController::class, 'index']);
        $this->alto->map('GET', '/search', [MailController::class, 'search']);
        $this->alto->map('GET', '/thread/[t:thread]', [MailController::class, 'thread']);
        $this->alto->map('GET', '/attachment', [MailController::class, 'attachment']);
    }

    protected function onPreflight(): void
    {
        header('Content-Type: text/html; charset=utf-8');
    }

    protected function onMatch(array $match): void
    {
        $this->app ??= new App(new ErrorLogLogger($this->isDev() ? 'debug' : 'warning'));

        [$controllerClass, $method] = $match['target'];
        $controller = new $controllerClass($this->app);
        if (!$controller instanceof AbstractController) {
            throw new HttpException('Invalid controller', 500);
        }

        $data = array_merge($_GET, $_POST, $match['params'] ?? []);

        $result = $controller->$method($data);

        if (is_string($result)) {
            echo $result;
        } elseif ($result !== null) {
            echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';
        }
    }

    protected function onError(\Exception $e): void
    {
        $code = 500;
        if ($e instanceof HttpException) {
            $code = $e->getCode();
        }

        if ($this->app) {
            $this->app->log()->critical($e->getMessage(), ['exception' => $e]);
        }

        header('Content-Type: text/html; charset=utf-8', true, $code);
        echo '<html lang="en"><head><title>Error ' . $code . '</title></head><body>';
        echo '<h1>Error ' . $code . '</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        if ($this->isDev()) {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
        }
        echo '</body></html>';
    }
}
