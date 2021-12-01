# test-pcntl-shmop

Warning!
- We must store only STRINGs or serializable data to shared memory. We can't share pure objects and other useful data...

TODO:
- make real project
- add at least 2 drivers - shmop and sysvshm
- add ext-pecl to composer.json as suggestion and add public static method "support" (it will check pcntl). it helps to add this package to require section
