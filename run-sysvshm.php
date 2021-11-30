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
     * @param \SysvSharedMemory[] $sharedMemoryIds
     */
    $cleanMemory = static function (array $sharedMemoryIds): void {
        foreach ($sharedMemoryIds as $value) {
            \shm_remove($value);
            \shm_detach($value);
        }
    };
    $l = \count($processes);

    // unique file to avoid race condition
    $tmpFile = \tempnam(\sys_get_temp_dir(), 'fork_');
    if (!$tmpFile) {
        throw new \RuntimeException('Can\'t create the temp file.');
    }

    /** @var \SysvSharedMemory[] $sharedMemoryIds */
    $sharedMemoryIds = [];
    for ($i = 0; $i < $l; $i++) {
        $processId = \ftok($tmpFile, \chr($i));
        if (-1 === $processId) {
            $cleanMemory($sharedMemoryIds);
            throw new \RuntimeException('Can\'t get the System V IPC key.');
        }
        $sharedMemoryIds[$i] = \shm_attach($processId, $memorySizeInBytesPerProcess, 0644);
        if (!$sharedMemoryIds[$i]) {
            unset($sharedMemoryIds[$i]);
            $cleanMemory($sharedMemoryIds);
            throw new \RuntimeException('Can\'t open the shared memory.');
        }

        $pid = \pcntl_fork();
        if (-1 === $pid) {
            $cleanMemory($sharedMemoryIds);
            throw new \RuntimeException('Can\'t open the shared memory.');
        }

        if (!$pid) { // forked process
            if (0 === $i) { // ???
                \usleep(10000); // 0.01 sec
            }

            $data = ($processes[$i])();
            $wroteResult = \shm_put_var($sharedMemoryIds[$i], $i, $data); // $data must be serializable
            if (!$wroteResult) {
                $cleanMemory($sharedMemoryIds);
                throw new \RuntimeException('Can\'t write the data to shared memory.');
            }
            exit($i); // set number of process
        }
    }

    $results = [];
    while (-1 !== \pcntl_waitpid(0, $status)) {
        $key = \pcntl_wexitstatus($status); // get number of process
        $value = $sharedMemoryIds[$key];

        $results[$key] = \shm_get_var($value, $key);
        \shm_remove_var($value, $key);
    }

    \ksort($results, \SORT_NUMERIC);

    $cleanMemory($sharedMemoryIds);
    $callback($results);

    @\unlink($tmpFile);
}


/**
 * functions to run as its own process.
 * functions must return serializable data
 *
 * @var callable[] $processes
 */
$processes = [
    static function (): string {
        // Whatever you need goes here...
        // If you need the results, return its value.
        // Eg: Long running process 1
        \sleep(3);
        return 'Hello ';
    },
    static function (): string {
        \sleep(4);
        return 'World';
    },
    static function (): string {
        \sleep(3);
        return '!';
    }
];

$callback = static function (array $results): void {
    \print_r($results);
};


forkProcess($processes, $callback, 1024);

echo "Done!\n";
echo \round(\memory_get_peak_usage() / 1024 / 1024, 2) . "MB is used\n";
