<?php

    /**
     * Yes, this is one big long file.
     *
     * I know you're tempted to refactor it into a hundred tiny functions. Resist the temptation. You'll thank me later.
     */

    use App\User;

    $_start = microtime(true);

    Cache::setDefaultDriver('redis');
    Cache::flush();
    DB::beginTransaction();

    $count = isset($_GET['count']) ? (int)$_GET['count'] : 100;
    $numUsers = isset($_GET['users']) ? (int)$_GET['users'] : 50;

    if ( ! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->string('foo');
            $table->text('foo1');
            $table->text('foo2');
        });
        $createdUsers = true;
    }

    if ($numUsers > User::count()) {

        if (User::count() === 0) {
            $users = [];
            $longVal = str_repeat(1, 5000);
            for ($i = 0; $i < $numUsers; ++$i) {
                $users[] = [
                    'email' => "foo$i@example.com",
                    'foo'  => $longVal,
                    'foo1' => $longVal,
                    'foo2' => $longVal,
                ];
            }

            User::insert($users);
        }

        $numUsers = User::count();
    }

    $id = User::first(['id'])->id;
    if (empty($id)) die("Must have a row in the user table for this benchmark");

    $conn = mysqli_connect(
        Config::get('database.connections.mysql.host'),
        Config::get('database.connections.mysql.username'),
        Config::get('database.connections.mysql.password'),
        Config::get('database.connections.mysql.database')
    );
    $result = mysqli_query($conn, "SELECT id, email FROM users WHERE id=$id");
    $row = mysqli_fetch_assoc($result);
    $cacheRows = [];
    for ($i = 0; $i < $numUsers; ++$i) {
        $cacheRows[] = $row;
    }
    Cache::put('row', $cacheRows, 5);
    $cacheKeyNoPrefix = Cache::tags('foo')->taggedItemKey('row');
    $cacheKey = Cache::getPrefix() . $cacheKeyNoPrefix;

    /**
     * NOTE: IN order to get nginx to honor disabling of output buffering, set in nginx.conf:
     *   fastcgi_keep_conn on;
     *   gzip off;
     */
    while (ob_get_level()) ob_end_clean();

?>

<html>
<head>
    <title>Laravel Benchmarks</title>
    <style>
        h1,h2 { font-size: 16px; margin: 5px 0 0 0; display: inline-block; text-align: right; vertical-align: bottom; width: 240px; }
        p { margin-top: 0; display: inline; }
        p:after { content: '\a'; white-space: pre;}
        strong { color: darkblue; }
    </style>
</head>
<body>

<h1 class="title">Laravel Benchmarks</h1>
<form method="get">
    <strong>
        (Running each query/lookup
        <input style="width:5em" name=count value="<?= $count ?>">
        times, with
        <input style="width:5em" name=users value="<?= $numUsers ?>">
        users)
    </strong>
    <input type="submit" value="Rerun">
</form>

<h2>Empty loop</h2>
<?php
    flush();$start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Local variable</h2>
<?php
    $row = $cacheRows;
    flush();$start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = $cacheRows;
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Global variable</h2>
<?php
    $GLOBALS['row'] = $cacheRows;
    flush();$start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = $GLOBALS['row'];
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Raw Session</h2>
<?php
    $_SESSION['row'] = $cacheRows;
    flush();$start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = $_SESSION['row'];
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>new StdClass()</h2>
<?php
    flush();$start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $class = new StdClass();
        $class->rows = $cacheRows;
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>array_get()</h2>
<?php
    flush();$start = microtime(true);
    $arr = [$cacheRows];
    for ($i = 0; $i < $count; ++$i) {
        $rows = array_get($arr, 0);
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Laravel Session Driver Store</h2>
<?php
    Session::put('rows', $cacheRows);
    flush();$start = microtime(true);
    $session = Session::getFacadeRoot()->driver();
    for ($i = 0; $i < $count; ++$i) {
        $rows = $session->get('rows');
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Laravel Session Facade</h2>
<?php
    Session::put('row', $cacheRows);
    flush(); $start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = Session::get('row');
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Laravel Redis with Pipeline</h2>
<?php
    /** @var Predis\Client $laravelRedis */
    LaravelRedis::set($cacheKey, serialize($cacheRows));
    flush(); $start = microtime(true);
    $rows = LaravelRedis::pipeline(function(Predis\Pipeline\Pipeline $pipe) use ($count, $cacheKey) {
        $ret = [];
        for ($i = 0; $i < $count; ++$i) {
            $row = $pipe->get($cacheKey);
            $ret[] = $row;
        }
        return $ret;
    });
    foreach ($rows as &$row) {
        $row = unserialize($row);
    }
    if ($rows[0] !== $cacheRows) {
        die ("Cache failed");
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>MySqli</h2>
<?php
    flush(); $start = microtime(true);
    $query  = "
		SELECT u.id, email FROM users u
		limit $numUsers
	";
    for ($i = 0; $i < $count; ++$i) {
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_all($result);
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<?php if (class_exists('Redis')): ?>
    <h2>Raw Redis with php extension</h2>
    <?php
    /** @var Redis/Client $redisExtension */
    $redisExtension = new Redis();
    $redisExtension->connect(Config::get('database.redis.default.scheme') === 'unix' ? Config::get('database.redis.default.path') : Config::get('database.redis.default.host'));
    $redisExtension->select(0);
    $redisExtension->set($cacheKey, serialize($cacheRows));

    flush(); $start = microtime(true);
    $arr = null;
    for ($i = 0; $i < $count; ++$i) {
        $result = $redisExtension->get($cacheKey);
        $row = unserialize($result);
    }
    if ($row != $cacheRows) {
        die ("Cache failed");
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
    ?>
<?php endif; ?>

<h2>Raw Redis with Laravel</h2>
<?php
    /** @var Predis\Client $laravelRedis */
    $laravelRedis = Cache::store()->getStore()->connection();
    flush(); $start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = $laravelRedis->get($cacheKey);
        if (isset($row)) {
            $row = unserialize($row);
        }
    }
    if ($row !== $cacheRows) {
        die ("Cache failed");
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>PDO</h2>
<?php
    flush(); $start = microtime(true);
    $pdo = DB::getPdo();
    $query  = "
			SELECT u.id, email FROM users u
			limit $numUsers
		";
    for ($i = 0; $i < $count; ++$i) {
        $result = $pdo->query($query);
        $data = $result->fetchAll();
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Laravel Redis without tags</h2>
<?php
    Cache::put('row', $cacheRows, 5);
    flush(); $start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $row = Cache::get('row');
    }
    if ($row !== $cacheRows) {
        die ("Cache failed");
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>Laravel Redis with tags</h2>
<?php
    flush(); $start = microtime(true);
    $tags = Cache::tags('foo');
    for ($i = 0; $i < $count; ++$i) {
        $row = $tags->get('row');
    }
    if ($row !== $cacheRows) {
        die ("Cache failed");
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<h2>DB</h2>
<?php
    flush(); $start = microtime(true);
    $query = DB::table('users')->select(['id', 'email'])->limit($numUsers);
    for ($i = 0; $i < $count; ++$i) {
        $model = $query->get();
    }
    $time = number_format(microtime(true) - $start, 7);

    $start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $query = DB::table('users')->select(['id', 'email'])->limit($numUsers);
    }
    $buildTime = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second) + build time: ";
    echo "$buildTime seconds (<strong>" . number_format($count / $buildTime) . "</strong> per second)</p>";
?>

<h2>Eloquent</h2>
<?php
    flush(); $start = microtime(true);
    $query = User::limit($numUsers)->select(['id', 'email']);
    for ($i = 0; $i < $count; ++$i) {
        $model = $query->get();
        if (microtime(true) - $start > 1.5) {
            echo "Exceeded 1.5 second limit";
            break;
        }
    }
    $time = number_format(microtime(true) - $start, 7);
    $start = microtime(true);
    for ($i = 0; $i < $count; ++$i) {
        $query = User::limit($numUsers)->select(['id', 'email']);
    }
    $buildTime = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second) + build time: ";
    echo "$buildTime seconds (<strong>" . number_format($count / $buildTime) . "</strong> per second)</p>";
?>

<h2>Eloquent selecting all fields</h2>
<?php
    flush(); $start = microtime(true);
    $query = User::limit($numUsers);
    for ($i = 0; $i < $count; ++$i) {
        $model = $query->get();
        if (microtime(true) - $start > 1.5) {
            echo "Exceeded 1.5 second limit";
            break;
        }
    }
    $time = number_format(microtime(true) - $start, 7);
    echo "<p>$time seconds (<strong>" . number_format($count / $time) . "</strong> per second)</p>";
?>

<?php $time = number_format(microtime(true) - $_start, 7); ?>
<?= "<br><p>TOTAL: $time seconds</p>"; ?>
</body>
</html>

<?php
    if (isset($createdUsers)) {
        Schema::drop('users');
    }
    DB::rollBack();
?>
