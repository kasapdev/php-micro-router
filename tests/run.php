<?php

declare(strict_types=1);

$__failures = 0;
function check(string $label, bool $condition): void
{
    global $__failures;
    echo ($condition ? "[PASS] " : "[FAIL] ") . $label . "\n";
    if (!$condition) {
        $__failures++;
    }
}

require_once __DIR__ . '/../src/Route.php';
require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/RouteNotFoundException.php';
require_once __DIR__ . '/../src/MethodNotAllowedException.php';

use Kasapdev\MicroRouter\MethodNotAllowedException;
use Kasapdev\MicroRouter\RouteNotFoundException;
use Kasapdev\MicroRouter\Router;

// --- Basic static route matching -------------------------------------------------

$router = new Router();
$router->get('/hello', fn (array $req) => 'hello world');

check('static GET route matches and returns handler result', $router->dispatch('GET', '/hello') === 'hello world');

// --- Named parameters --------------------------------------------------------------

$router = new Router();
$router->get('/users/{id}', fn (array $req) => 'user:' . $req['params']['id']);

check('named param extracted from path', $router->dispatch('GET', '/users/42') === 'user:42');

// --- Regex-constrained parameters ---------------------------------------------------

$router = new Router();
$router->get('/files/{path:.+}', fn (array $req) => 'file:' . $req['params']['path']);
$router->get('/posts/{id:\d+}', fn (array $req) => 'post:' . $req['params']['id']);

check('regex-constrained param matches nested slashes', $router->dispatch('GET', '/files/a/b/c.txt') === 'file:a/b/c.txt');
check('regex-constrained numeric param matches', $router->dispatch('GET', '/posts/123') === 'post:123');

$threw = false;
try {
    $router->dispatch('GET', '/posts/abc');
} catch (RouteNotFoundException $e) {
    $threw = true;
}
check('regex-constrained param rejects non-matching value', $threw);

// --- RouteNotFoundException ---------------------------------------------------------

$router = new Router();
$router->get('/only', fn () => 'ok');

$threw = false;
try {
    $router->dispatch('GET', '/nope');
} catch (RouteNotFoundException $e) {
    $threw = true;
}
check('RouteNotFoundException thrown for unmatched path', $threw);

// --- MethodNotAllowedException -------------------------------------------------------

$router = new Router();
$router->get('/thing', fn () => 'get-thing');
$router->post('/thing', fn () => 'post-thing');

$threw = false;
$allowed = [];
try {
    $router->dispatch('DELETE', '/thing');
} catch (MethodNotAllowedException $e) {
    $threw = true;
    $allowed = $e->getAllowedMethods();
}
check('MethodNotAllowedException thrown when path matches but method does not', $threw);
sort($allowed);
check('MethodNotAllowedException reports the allowed methods', $allowed === ['GET', 'POST']);

// --- any() matches every method ------------------------------------------------------

$router = new Router();
$router->any('/anything', fn (array $req) => 'method-was:' . $req['method']);

check('any() matches GET', $router->dispatch('GET', '/anything') === 'method-was:GET');
check('any() matches DELETE', $router->dispatch('DELETE', '/anything') === 'method-was:DELETE');

// --- Route groups (including nested groups) -------------------------------------------

$router = new Router();
$router->group('/api', function (Router $r) {
    $r->get('/users', fn () => 'api-users-list');
    $r->group('/v1', function (Router $r2) {
        $r2->get('/users/{id}', fn (array $req) => 'api-v1-user:' . $req['params']['id']);
    });
});

check('group prefix applied to route', $router->dispatch('GET', '/api/users') === 'api-users-list');
check('nested group prefixes concatenated', $router->dispatch('GET', '/api/v1/users/7') === 'api-v1-user:7');

// --- Query string parsing -------------------------------------------------------------

$router = new Router();
$router->get('/search', fn (array $req) => $req['query']['q'] ?? '(none)');

check('query string parsed into request array', $router->dispatch('GET', '/search?q=php') === 'php');
check('missing query param handled gracefully', $router->dispatch('GET', '/search') === '(none)');

// --- Trailing slash normalization ------------------------------------------------------

$router = new Router();
$router->get('/trailing', fn () => 'ok');
check('trailing slash on request path is normalized', $router->dispatch('GET', '/trailing/') === 'ok');

$router = new Router();
$router->get('/', fn () => 'root');
check('root path "/" matches', $router->dispatch('GET', '/') === 'root');

// --- Global middleware: onion pipeline order -------------------------------------------

$router = new Router();
$log = [];
$router->use(function (array $req, callable $next) use (&$log) {
    $log[] = 'global-before';
    $result = $next($req);
    $log[] = 'global-after';
    return $result;
});
$router->get('/pipeline', function (array $req) use (&$log) {
    $log[] = 'handler';
    return 'pipeline-result';
})->middleware(function (array $req, callable $next) use (&$log) {
    $log[] = 'route-mw-before';
    $result = $next($req);
    $log[] = 'route-mw-after';
    return $result;
});

$result = $router->dispatch('GET', '/pipeline');
check('middleware pipeline returns handler result', $result === 'pipeline-result');
check(
    'onion-style middleware executes in correct nested order',
    $log === ['global-before', 'route-mw-before', 'handler', 'route-mw-after', 'global-after']
);

// --- Middleware can short-circuit (never call $next) ------------------------------------

$router = new Router();
$router->get('/blocked', fn () => 'should-not-run')
    ->middleware(function (array $req, callable $next) {
        return 'blocked-by-middleware';
    });

check('middleware can short-circuit without calling handler', $router->dispatch('GET', '/blocked') === 'blocked-by-middleware');

// --- Middleware can mutate the request passed downstream --------------------------------

$router = new Router();
$router->use(function (array $req, callable $next) {
    $req['params']['injected'] = 'yes';
    return $next($req);
});
$router->get('/mutate/{id}', fn (array $req) => $req['params']['id'] . '/' . $req['params']['injected']);

check('middleware can mutate request seen by downstream handler', $router->dispatch('GET', '/mutate/9') === '9/yes');

// --- Multiple middleware via fluent ->middleware(...$mw) --------------------------------

$router = new Router();
$order = [];
$router->get('/multi', function () use (&$order) {
    $order[] = 'handler';
    return 'done';
})->middleware(
    function (array $req, callable $next) use (&$order) {
        $order[] = 'first';
        return $next($req);
    },
    function (array $req, callable $next) use (&$order) {
        $order[] = 'second';
        return $next($req);
    }
);

$router->dispatch('GET', '/multi');
check('multiple middleware passed to ->middleware() run in order', $order === ['first', 'second', 'handler']);

// --- Routes are matched in registration order (first match wins) ------------------------

$router = new Router();
$router->get('/wildcard/{any}', fn () => 'wildcard');
$router->get('/wildcard/fixed', fn () => 'fixed');

check(
    'first matching route wins even if a more specific one is registered later',
    $router->dispatch('GET', '/wildcard/fixed') === 'wildcard'
);

$router = new Router();
$router->get('/wildcard/fixed', fn () => 'fixed');
$router->get('/wildcard/{any}', fn (array $req) => 'wildcard:' . $req['params']['any']);

check(
    'registering the specific route first lets it win',
    $router->dispatch('GET', '/wildcard/fixed') === 'fixed'
);

echo $__failures === 0 ? "\nAll tests passed.\n" : "\n$__failures test(s) FAILED.\n";
exit($__failures === 0 ? 0 : 1);
