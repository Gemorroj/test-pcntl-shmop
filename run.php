<?php

function forkProcess(array $processes, callable $callback, int $memorySizePerProcess = 1024): void
{
    $l = \count($processes);

    $sharedMemoryMonitor = \shmop_open(\ftok(__FILE__, \chr(0)), 'c', 0644, $l);
    $sharedMemoryIds = [];
    for ($i = 1; $i <= $l; $i++) {
        $sharedMemoryIds[$i] = \shmop_open(\ftok(__FILE__, \chr($i)), 'c', 0644, $memorySizePerProcess);

        $pid = \pcntl_fork();
        if (!$pid) {
            if ($i === 1) {
                \usleep(100000);
            }
            \shmop_write($sharedMemoryIds[$i], ($processes[$i - 1])(), 0);
            \shmop_write($sharedMemoryMonitor, '1', $i - 1);
            exit($i);
        }
    }

    $plug = \str_repeat('1', $l);
    while (\pcntl_waitpid(0, $status) !== -1) {
        if (\shmop_read($sharedMemoryMonitor, 0, $l) === $plug) {
            $result = [];
            foreach ($sharedMemoryIds as $key => $value) {
                $result[$key - 1] = \shmop_read($sharedMemoryIds[$key], 0, $memorySizePerProcess);
                \shmop_delete($sharedMemoryIds[$key]);
            }
            \shmop_delete($sharedMemoryMonitor);
            $callback($result);
        }
    }
}


// Define 2 functions to run as its own process.
$processes = [
    static function () {
        // Whatever you need goes here...
        // If you need the results, return its value.
        // Eg: Long running proccess 1
        \sleep(6);
        return 'Hello ';
    },
    static function () {
        // Whatever you need goes here...
        // If you need the results, return its value.
        // Eg:
        // Eg: Long running proccess 2
        \sleep(5);
        return 'World!';
    }
];

$callback = static function (array $result) {
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

echo "Code after fork\n";
