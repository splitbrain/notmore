<?php

namespace splitbrain\notmore\Notmuch;

use Psr\Log\LoggerInterface;
use splitbrain\notmore\App;

abstract class Client
{
    protected App $app;
    protected string $bin;
    protected string $config;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->bin = $app->conf('notmuch_bin');
        $this->config = $app->conf('notmuch_config');
    }

    /**
     * Run a notmuch command and return raw stdout as a string.
     *
     * @param array $args
     * @return string
     * @throws \Exception when the notmuch command fails
     */
    protected function run(array $args): string
    {
        $cmd = array_merge([$this->bin, '--config', $this->config], $args);
        $commandString = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($commandString, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new \Exception('Unable to execute notmuch command (' . $commandString . ')');
        }

        fclose($pipes[0]); // nothing to write to stdin
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $errorOutput = trim($stderr);

        if ($exitCode !== 0) {
            $message = 'notmuch command failed with exit code ' . $exitCode . ' (' . implode(' ', $cmd) . ')';
            if ($errorOutput !== '') {
                $message .= ': ' . $errorOutput;
            }
            throw new \Exception($message);
        }

        return $stdout;
    }

    /**
     * Run a notmuch command and stream stdout directly to the PHP output buffer.
     *
     * @param array $args
     * @throws \Exception when the notmuch command fails
     */
    protected function stream(array $args): void
    {
        $cmd = array_merge([$this->bin, '--config', $this->config], $args);
        $commandString = implode(' ', array_map('escapeshellarg', $cmd));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($commandString, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new \Exception('Unable to execute notmuch command (' . $commandString . ')');
        }

        fclose($pipes[0]); // nothing to write to stdin

        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new \Exception('Unable to open output stream');
        }

        stream_copy_to_stream($pipes[1], $output);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $errorOutput = trim($stderr);

        if ($exitCode !== 0) {
            $message = 'notmuch command failed with exit code ' . $exitCode . ' (' . implode(' ', $cmd) . ')';
            if ($errorOutput !== '') {
                $message .= ': ' . $errorOutput;
            }
            throw new \Exception($message);
        }
    }

    /**
     * Run a JSON-producing notmuch command and decode the result.
     *
     * @param array $args
     * @return array
     * @throws \Exception when the notmuch command fails or JSON cannot be decoded
     */
    protected function runJson(array $args): array
    {
        $json = $this->run($args);

        if (trim($json) === '') {
            return [];
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
