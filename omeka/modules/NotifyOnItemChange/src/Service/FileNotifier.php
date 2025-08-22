<?php declare(strict_types=1);

namespace NotifyOnItemChange\Service;

class FileNotifier
{
    protected string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
    }

    public function notify($item, string $eventName): void
    {
        $id = $item->id();
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "{$this->directory}/item_{$id}_{$eventName}_{$timestamp}.txt";

        $content = "Item ID: {$id}\nEvent: {$eventName}\nDate: {$timestamp}\n";

        file_put_contents($filename, $content);
    }
}
