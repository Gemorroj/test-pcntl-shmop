<?php

/**
 * @param callable[] $processes
 * @param callable $callback
 * @param int $memorySizeInBytesPerProcess
 * @throws \RuntimeException
 */
function forkProcess(array $processes, callable $callback, int $memorySizeInBytesPerProcess = 1024): void
{
    /**
     * @param \Shmop $sharedMemoryMonitor
     * @param \Shmop[] $sharedMemoryIds
     */
    $cleanMemory = static function (\Shmop $sharedMemoryMonitor, array $sharedMemoryIds): void {
        \shmop_delete($sharedMemoryMonitor);
        foreach ($sharedMemoryIds as $value) {
            \shmop_delete($value);
        }
    };
    $l = \count($processes);

    $sharedMemoryMonitor = \shmop_open(\ftok(__FILE__, \chr(0)), 'c', 0644, $l);
    if (!$sharedMemoryMonitor) {
        throw new \RuntimeException('Can\'t open the shared memory.');
    }
    $sharedMemoryIds = [];
    for ($i = 1; $i <= $l; $i++) {
        $sharedMemoryIds[$i] = \shmop_open(\ftok(__FILE__, \chr($i)), 'c', 0644, $memorySizeInBytesPerProcess);
        if (!$sharedMemoryIds[$i]) {
            unset($sharedMemoryIds[$i]);
            $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
            throw new \RuntimeException('Can\'t open the shared memory.');
        }

        $pid = \pcntl_fork();
        if (!$pid) {
            if ($i === 1) {
                \usleep(100000);
            }

            $data = ($processes[$i - 1])();
            $dataLength = \strlen($data);
            $wroteLength = \shmop_write($sharedMemoryIds[$i], $data, 0);
            if ($wroteLength < $dataLength) {
                $cleanMemory($sharedMemoryMonitor, $sharedMemoryIds);
                throw new \RuntimeException(\sprintf('Can\'t write the data to shared memory. Data length is %d, wrote length is %d.', $wroteLength, $dataLength));
            }
            \shmop_write($sharedMemoryMonitor, '1', $i - 1);
            exit($i);
        }
    }

    $plug = \str_repeat('1', $l);
    while (\pcntl_waitpid(0, $status) !== -1) {
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
}


// Define 2 functions to run as its own process.
$processes = [
    static function (): string {
        // Whatever you need goes here...
        // If you need the results, return its value.
        // Eg: Long running process 1
        \sleep(6);
        return 'Hello ';
    },
    static function (): string {
        // Whatever you need goes here...
        // If you need the results, return its value.
        // Eg:
        // Eg: Long running process 2
        \sleep(5);
        return 'World!';
    }
];

$callback = static function (array $result): void {
    // $results is an array of return values...
    // $result[0] for $process[0] &
    // $result[1] for $process[1] &
    // Eg:
    foreach ($result as $item) {
        echo $item;
    }
    echo "\n";
};

forkProcess($processes, $callback);

echo "Done!\n";
echo \round(\memory_get_peak_usage() / 1024 / 1024, 2) . "MB is used\n";
