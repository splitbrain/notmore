<?php

namespace splitbrain\notmore;

use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class App
{

    /**
     * @var array Configuration options
     */
    protected array $config;

    /**
     * @var LoggerInterface Logger
     */
    protected LoggerInterface $logger;


    /**
     * Constructor
     *
     * @param string $site Site name
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(LoggerInterface|null $logger = null)
    {
        if ($logger instanceof LoggerInterface) {
            $this->logger = $logger;
        } else {
            $this->logger = new NullLogger();
        }

        $this->config = $this->loadConfig();
    }

    public function loadConfig(): array
    {
        // populate $_ENV even if it's not enabled in variables_order
        $_ENV = getenv();

        // Load environment variables from .env file if it exists
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
            $dotenv->load();
        }

        // Check for notmuch config
        if (!isset($_ENV['NOTMUCH_CONFIG']) || trim((string)$_ENV['NOTMUCH_CONFIG']) === '') {
            throw new \RuntimeException('Environment variable NOTMUCH_CONFIG is not set');
        }

        $notmuch_config = (string)$_ENV['NOTMUCH_CONFIG'];
        if ($notmuch_config[0] !== '/') {
            $notmuch_config = __DIR__ . '/../' . $notmuch_config;
        }
        $notmuch_config = realpath($notmuch_config);
        if(!file_exists($notmuch_config)) {
            throw new \RuntimeException('Notmuch config file not found: ' . $notmuch_config);
        }

        // check for notmuch binary (resolve from PATH when no explicit path is given)
        $notmuch_bin = $_ENV['NOTMUCH_BIN'] ?? 'notmuch';
        if (!str_contains($notmuch_bin, DIRECTORY_SEPARATOR)) {
            $notmuch_bin = trim((string)shell_exec('command -v ' . escapeshellarg($notmuch_bin)));
        }

        if ($notmuch_bin === '' || !is_executable($notmuch_bin)) {
            throw new \RuntimeException('Notmuch binary not found or not executable: ' . $notmuch_bin);
        }

        // for now only this. We need to read the notmuch config here
        $config = [
            'notmuch_bin' => $notmuch_bin,
            'notmuch_config' => $notmuch_config,
        ];

        return $config;
    }

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key
     * @param mixed|null $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function conf(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function log(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
