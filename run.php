<?php
/**
 * based on https://www.php.net/manual/ru/function.pcntl-fork.php#115855
 */

/**
 * @param callable[] $processes
 * @param callable $callback
 * @param int $memorySizeInBytesPerProcess Memory for stored data.
 * @throws \RuntimeException
 */
function forkProcess(array $processes, callable $callback, int $memorySizeInBytesPerProcess = 1024): void
{
    /**
     * @param \Shmop[] $sharedMemoryIds
     */
    $cleanMemory = static function (\Shmop $sharedMemoryMonitor, array $sharedMemoryIds): void {
        \shmop_delete($sharedMemoryMonitor);
        foreach ($sharedMemoryIds as $value) {
            \shmop_delete($value);
        }
    };
    $l = \count($processes);

    // unique file to avoid race condition
    $tmpFile = \tempnam(\sys_get_temp_dir(), 'fork_');
    if (!$tmpFile) {
        throw new \RuntimeException('Can\'t create the temp file.');
    }

    $monitorId = \ftok($tmpFile, \chr(0));
    if (-1 === $monitorId) {
        throw new \RuntimeException('Can\'t get the System V IPC key.');
    }
    $sharedMemoryMonitor = \shmop_open($monitorId, 'n', 0644, $l);
    if (!$sharedMemoryMonitor) {
        throw new \RuntimeException('Can\'t open the shared memory.');
    }
    /** @var \Shmop[] $sharedMemoryIds */
    $sharedMemoryIds = [];
    for ($i = 1; $i <= $l; $i++) {
        $processId = \ftok($tmpFile, \chr($i));
        if (-1 === $processId) {
            $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
            throw new \RuntimeException('Can\'t get the System V IPC key.');
        }
        $sharedMemoryIds[$i] = \shmop_open($processId, 'n', 0644, $memorySizeInBytesPerProcess);
        if (!$sharedMemoryIds[$i]) {
            unset($sharedMemoryIds[$i]);
            $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
            throw new \RuntimeException('Can\'t open the shared memory.');
        }

        $pid = \pcntl_fork();
        if (-1 === $pid) {
            $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
            throw new \RuntimeException('Can\'t fork the process.');
        }

        if (!$pid) { // forked process
            if (1 === $i) { // ???
                \usleep(10000); // 0.01 sec
            }

            $data = ($processes[$i - 1])();
            $dataLength = \strlen($data);
            $wroteLength = \shmop_write($sharedMemoryIds[$i], $data, 0);
            if ($wroteLength < $dataLength) {
                $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
                throw new \RuntimeException(\sprintf('Can\'t write the data to shared memory. Data length is %d, wrote length is %d.', $wroteLength, $dataLength));
            }
            \shmop_write($sharedMemoryMonitor, '1', $i - 1);
            exit(0);
        }
    }

    $plug = \str_repeat('1', $l);
    while (-1 !== \pcntl_waitpid(0, $status)) {
        if (\shmop_read($sharedMemoryMonitor, 0, $l) === $plug) {
            $result = [];
            foreach ($sharedMemoryIds as $key => $value) {
                $result[$key - 1] = \shmop_read($value, 0, $memorySizeInBytesPerProcess);
                \shmop_delete($value);
            }
            \shmop_delete($sharedMemoryMonitor);
            $callback($result);
        }
    }

    @\unlink($tmpFile);
}

$context = \stream_context_create([
    'http' => [
        'user_agent' => __FILE__,
    ],
]);

/**
 * functions to run as its own process.
 * functions must return string
 *
 * @var callable[] $processes
 */
$processes = [
    static function () use($context): string {
        return \file_get_contents('https://httpbin.org/get?0', false, $context);
    },
    static function () use($context): string {
        return \file_get_contents('https://httpbin.org/get?1', false, $context);
    },
    static function () use($context): string {
        return \file_get_contents('https://httpbin.org/get?2', false, $context);
    }
];

/**
 * @param string[] $results
 */
$callback = static function (array $results): void {
    \print_r($results);
};


forkProcess($processes, $callback, 1024);

echo "Done!\n";
echo \round(\memory_get_peak_usage() / 1024 / 1024, 2) . "MB is used\n";
