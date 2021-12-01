<?php
/**
 * based on https://www.php.net/manual/ru/function.pcntl-fork.php#115855
 */

/**
 * @param callable[] $processes
 * @param callable $callback
 * @throws \RuntimeException
 */
function forkProcess(array $processes, callable $callback): void
{
    /**
     * @param \string[] $sharedMemoryIds
     */
    $cleanMemory = static function (array $sharedMemoryIds): void {
        foreach ($sharedMemoryIds as $value) {
            \apcu_delete($value);
        }
    };
    $l = \count($processes);

    // unique key
    $globalPid = \getmypid();
    if (false === $globalPid) {
        throw new \RuntimeException('Can\'t get PID.');
    }
    $uniqueId = \uniqid($globalPid.'_', true);

    /** @var string[] $sharedMemoryIds */
    $sharedMemoryIds = [];
    for ($i = 0; $i < $l; $i++) {
        $sharedMemoryIds[$i] = $uniqueId.$i;

        $pid = \pcntl_fork();
        if (-1 === $pid) {
            throw new \RuntimeException('Can\'t fork the process.');
        }

        if (!$pid) { // forked process
            if (0 === $i) { // ???
                \usleep(10000); // 0.01 sec
            }

            $data = ($processes[$i])();
            $result = \apcu_add($sharedMemoryIds[$i], $data); // $data must be serializable
            if (false === $result) {
                unset($sharedMemoryIds[$i]);
                $cleanMemory($sharedMemoryIds);
                throw new \RuntimeException('Can\'t store the data to shared memory.');
            }
            exit($i); // set number of process
        }
    }

    $results = [];
    while (-1 !== \pcntl_waitpid(0, $status)) {
        $key = \pcntl_wexitstatus($status); // get number of process
        if (!isset($sharedMemoryIds[$key])) {
            continue;
        }

        $value = $sharedMemoryIds[$key];

        $results[$key] = \apcu_fetch($value, $success);
        \apcu_delete($value);
        if (!$success) {
            // throw new \RuntimeException('Can\'t fetch the data from shared memory.');
        }
    }

    \ksort($results, \SORT_NUMERIC);

    $cleanMemory($sharedMemoryIds);
    $callback($results);
}

$context = \stream_context_create([
    'http' => [
        'user_agent' => __FILE__,
    ],
]);

/**
 * functions to run as its own process.
 * functions must return serializable data
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

// see https://github.com/symfony/symfony/blob/6.0/src/Symfony/Component/Cache/Adapter/ApcuAdapter.php
$supported = \function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN);
if (!$supported) {
    throw new \RuntimeException('APCU doesnt supported');
}
if ('cli' === \PHP_SAPI) {
    \ini_set('apc.use_request_time', 0);
}


forkProcess($processes, $callback);

echo "Done!\n";
echo \round(\memory_get_peak_usage() / 1024 / 1024, 2) . "MB is used\n";
