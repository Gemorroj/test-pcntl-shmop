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
    $cleanMemory = static function (array $sharedMemoryIds): void {
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

    /** @var \Shmop[] $sharedMemoryIds */
    $sharedMemoryIds = [];
    for ($i = 0; $i < $l; $i++) {
        $processId = \ftok($tmpFile, \chr($i));
        if (-1 === $processId) {
            $cleanMemory($sharedMemoryIds);
            throw new \RuntimeException('Can\'t get the System V IPC key.');
        }
        $sharedMemoryIds[$i] = \shmop_open($processId, 'n', 0644, $memorySizeInBytesPerProcess);
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
            $dataLength = \strlen($data);
            $wroteLength = \shmop_write($sharedMemoryIds[$i], $data, 0);
            if ($wroteLength < $dataLength) {
                $cleanMemory($sharedMemoryIds);
                throw new \RuntimeException(\sprintf('Can\'t write the data to shared memory. Data length is %d, wrote length is %d.', $wroteLength, $dataLength));
            }
            exit($i); // set number of process
        }
    }

    $results = [];
    while (-1 !== \pcntl_waitpid(0, $status)) {
        $key = \pcntl_wexitstatus($status); // get number of process
        $value = $sharedMemoryIds[$key];

        $results[$key] = \shmop_read($value, 0, $memorySizeInBytesPerProcess);
        \shmop_delete($value);
    }

    \ksort($results, \SORT_NUMERIC);

    $callback($results);
    @\unlink($tmpFile);
}


/**
 * functions to run as its own process.
 * functions must return string
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

/**
 * @param string[] $results
 */
$callback = static function (array $results): void {
    \print_r($results);
};


forkProcess($processes, $callback, 100);

echo "Done!\n";
echo \round(\memory_get_peak_usage() / 1024 / 1024, 2) . "MB is used\n";
