# Laravel 5.1 benchmarks
Quick-and-dirty Laravel 5.1 Benchmarks for Cache, DB, Session, etc.

To see exactly what this benchmark is doing, look at [`resources/views/benchmark.php`](resources/views/benchmark.php) -- it is a standalone file running on stock Laravel 5.1.

Sample run on Linode with xdebug disabled:
![Sample run](http://i.imgur.com/66rdMAY.png)

## Installation on Homestead (YMMV)
* `composer install`
* `cp .env-example .env`
* `mysqladmin -v create -f homestead -u homestead --password=secret`
* `sudo apt-get install php5-redis;service php5-fpm restart`

## Usage
Visit the site in a browser, the benchmarks should appear.

## Tips and tricks
* xdebug makes a large difference -- try it with and without.
* Try redis over a socket -- put this in your config/database.php redis settings
  * 'path'     => env('REDIS_PATH', '/tmp/redis.sock'),
  * 'scheme'   => env('REDIS_SCHEME', 'unix'),
  * You'll also have to tell Redis to listen on a socket -- add this to /etc/redis/redis.php
    * unixsocket /tmp/redis.sock
    * unixsocketperm 777
* Mysql already connects over a socket by default for a host of `localhost` -- change the host to `127.0.0.1` to compare (may have to enable mysql to listen first).

## Disclaimer
This is useful to me. I hope it's useful to you. It is what it is. PRs welcome.
