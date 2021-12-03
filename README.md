## Test pcntl + sysvshm/shmop/apcu

Warning!
- Usable only in cli mode (https://stackoverflow.com/a/35029409/531647)
- We must store only STRINGs or serializable data to shared memory. We can't share pure objects and other useful data...

TODO:
- https://habr.com/ru/post/148688/ (проверить вызов деструкторов)
- ```php
          if (!$pid) { // forked process
            if (0 === $i) { // ???
                \usleep(10000); // 0.01 sec
            }
  ```
  why we should sleep? if we skip slipping we will have errors. but why??
- make real project
- add at least 3 drivers - shmop, sysvshm, apcu
- add ext-pecl to composer.json as suggestion and add public static method "support" (it will check pcntl). it helps to add this package to require section

SEE:
- https://github.com/spatie/fork
- https://github.com/huyanping/simple-fork-php
