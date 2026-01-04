<?php

namespace splitbrain\notmore;

class ApiRouter extends Router
{
    protected ?App $app = null;

    /** @inheritdoc */
    public function __construct()
    {
        parent::__construct();
        $this->alto->setBasePath('/api');
    }

    /** @inheritdoc */
    protected function registerRoutes(): void
    {
        // TODO: Define API routes here

    }

    /** @inheritdoc */
    protected function onPreflight(): void
    {
        // Set JSON content type for all responses
        header('Content-Type: application/json');
    }

    /** @inheritdoc */
    protected function onMatch(array $match): void
    {

        $app = new App(new ErrorLogLogger($this->isDev() ? 'debug' : 'warning'));

        // Parse JSON body for non-GET requests
        $data = [];
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = json_decode($input, true, JSON_THROW_ON_ERROR) ?? [];
            }
        } else {
            $data = $_GET;
        }

        // Add any URL parameters to the data array
        if (isset($match['params']) && is_array($match['params'])) {
            $data = array_merge($data, $match['params']);
        }

        // Get the controller and method
        [$controllerClass, $method] = array_pad($match['target'], 2, null);

        // Create the controller instance
        $controller = new $controllerClass($app);

        // Call the method
        $result = $controller->$method($data);

        // Return the result wrapped in a response object
        http_response_code(200);
        echo json_encode(['response' => $result], JSON_PRETTY_PRINT);
    }

    /** @inheritdoc */
    protected function onError(\Exception $e): void
    {
        // Handle errors
        $code = 500;
        if ($e instanceof HttpException) {
            $code = $e->getCode();
        }

        if ($this->app) {
            $this->app->log()->critical($e->getMessage(), ['exception' => $e]);
        }

        http_response_code($code);
        echo json_encode([
            'error' => [
                'message' => $e->getMessage(),
                'code' => $e->getCode() ?: 1,
                'trace' => $this->isDev() ? $e->getTrace() : null
            ]
        ], JSON_PRETTY_PRINT);
    }
}
