<?php

namespace splitbrain\notmore\Controller;

use splitbrain\notmore\App;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

abstract class AbstractController
{
    private ?Environment $twig = null;

    public function __construct(protected App $app)
    {
    }

    /**
     * Escape HTML output.
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Render a Twig template.
     */
    protected function render(string $template, array $context = []): string
    {
        return $this->twig()->render($template, $context);
    }

    /**
     * Build or reuse the Twig environment.
     */
    protected function twig(): Environment
    {
        if ($this->twig instanceof Environment) {
            return $this->twig;
        }

        $loader = new FilesystemLoader([
            __DIR__ . '/../../templates',
            __DIR__ . '/../../public',
        ]);
        $this->twig = new Environment($loader, [
            'debug' => (bool)($_ENV['DEBUG'] ?? false),
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $this->twig->addExtension(new DebugExtension());

        $this->twig->addGlobal(
            'isSwap',
            isset($_SERVER['HTTP_X_SWAP_CALL'])
        );

        return $this->twig;
    }
}
