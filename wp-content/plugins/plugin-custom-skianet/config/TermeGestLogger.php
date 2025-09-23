<?php

declare(strict_types=1);

namespace TermeGest\Type;

use const PHP_INT_MAX;

use WC_Logger_Interface;

use function sprintf;

class TermeGestLogger
{
    /**
     * Singleton instance of this class
     */
    private static ?self $logger = null;

    /**
     * The context for the wp logger
     */
    private array $context;

    /**
     * Mantains strings to log until flush
     */
    private array $entries = [];

    /**
     * If has logged some errors
     */
    private bool $hasErrors = false;

    /**
     * The name of this instance
     */
    private string $name = 'skn-termegest';

    /**
     * Instance of wp standard logger
     */
    private readonly WC_Logger_Interface $wcLogger;

    /**
     * Logger constructor.
     */
    private function __construct()
    {
        $this->wcLogger = wc_get_logger();
        $this->context = ['source' => $this->name];
    }

    /**
     * Create the singleton instance if not present
     */
    public static function getInstance(): self
    {
        if (! self::$logger instanceof self) {
            self::$logger = new static();
        }

        return self::$logger;
    }

    /**
     * Flushes the sved log strings to wp handler
     */
    public function flushLog(): void
    {
        add_filter('woocommerce_format_log_entry', fn (string $message, array $parameters): string => $this->overrideOriginalLogging($message, $parameters), PHP_INT_MAX, 2);
        foreach ($this->entries as $entry) {
            $text = $entry['text'];
            $level = $entry['level'];
            $this->wcLogger->{$level}($text, $this->context);
        }

        $this->hasErrors = false;
    }

    /**
     * Overrides original wp log timestamps with the valid ones
     */
    public function overrideOriginalLogging(string $message, array $parameters): string
    {
        if (
            ! empty($parameters['context'])
            && ! empty($parameters['context']['source'])
            && $parameters['context']['source'] === $this->context['source']
        ) {
            foreach ($this->entries as $key => $entry) {
                if ($parameters['message'] === $entry['text'] && $parameters['level'] === $entry['level']) {
                    $time_string = date('Y/m/d H:i:s', $entry['time']);
                    $level_string = mb_strtoupper((string) $entry['level']);
                    unset($this->entries[$key]);

                    return sprintf('%s %s %s', $time_string, $level_string, $entry['text']);
                }
            }
        }

        return $message;
    }

    /**
     * Gets the real memory usage (with resources) for the script
     */
    public function getMemoryUsage(): string
    {
        $status = file_get_contents('/proc/'.getmypid().'/status');
        $matchArr = [];
        preg_match_all('~^(VmRSS|VmSwap):\s*(\d+).*$~im', $status, $matchArr);
        if (! isset($matchArr[2][0], $matchArr[2][1])) {
            return '0B';
        }

        $bytes = ((int) $matchArr[2][0] + (int) $matchArr[2][1]) * 1000;
        $units = ['B', 'K', 'M', 'G', 'T'];
        for ($i = 0; $bytes >= 1000; $i++) {
            $bytes /= 1000;
        }

        return round($bytes, 3).$units[$i];
    }

    /**
     * Returns the name of this instance
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * If this instance has logged some errors
     */
    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    /**
     * Sends log trough the standard wp channel
     */
    public function send(string $text, string $level = 'debug'): void
    {
        if (mb_stripos($level, 'error') !== false) {
            $this->hasErrors = true;
        }

        $this->entries[] = [
            'time' => time(),
            'text' => $text,
            'level' => $level,
        ];
    }
}
