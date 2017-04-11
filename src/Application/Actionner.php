<?php
namespace Bow\Application;

use Bow\Http\Response;
use Bow\Exception\RouterException;

class Actionner
{
    /**
     * @var array All define namesapce
     */
    private static $names;

    /**
     * Lanceur de callback
     *
     * @param callable|string|array $actions
     * @param mixed $param
     * @param array $names
     * @throws RouterException
     * @return mixed
     */
    public static function call($actions, $param = null, array $names = [])
    {
        $param = is_array($param) ? $param : [$param];
        $functions = [];

        if (! isset($names['namespace'])) {
            return static::exec($actions, $param);
        }

        static::$names = $names;

        if (! isset($names['namespace'])) {
            throw new RouterException('Le namespace d\'autoload n\'est pas défini dans le fichier de configuration');
        }

        $middlewares = [];

        if (is_callable($actions)) {
            return call_user_func_array($actions, $param);
        }

        if (is_string($actions)) {
            $function = static::controller($actions);
            return call_user_func_array($function['controller'], array_merge($param, $function['injections']));
        }

        if (! is_array($actions)) {
            throw new \InvalidArgumentException('Le premier parametre doit etre un tableau, une chaine, une closure', E_USER_ERROR);
        }

        if (array_key_exists('middleware', $actions)) {
            $middlewares = $actions['middleware'];
            unset($actions['middleware']);
        }

        foreach ($actions as $key => $action) {
            if ($key != 'uses' || !is_int($key)) {
                continue;
            }

            if (is_int($key)) {
                if (is_callable($action)) {
                    array_push($functions, $action);
                    continue;
                }
                if (is_string($action)) {
                    array_push($functions, static::controller($action));
                    continue;
                }
            }

            if (isset($action['with']) && isset($action['call'])) {
                if (is_string($action['call'])) {
                    $controller = $action['with'].'@'.$action['call'];
                    array_push($functions, static::controller($controller));
                    continue;
                }
                foreach($action['call'] as $method) {
                    $controller = $action['with'].'@'.$method;
                    array_push($functions,  static::controller($controller));
                }
                continue;
            }
        }

        // Status permettant de bloquer la suite du programme.
        $status = true;

        if (! is_array($middlewares)) {
            $middlewares = [$middlewares];
        }

        // Collecteur de middleware
        $middlewares_collection = [];

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            if (! array_key_exists($middleware, $names['middlewares'])) {
                throw new RouterException($middleware . ' n\'est pas un middleware définir.', E_ERROR);
            }
            // On vérifie si le middleware définie est une middleware valide.
            if (! class_exists($names['middlewares'][$middleware])) {
                throw new RouterException($names['middlewares'][$middleware] . ' n\'est pas un class middleware.');
            }
            $middlewares_collection[] = $names['middlewares'][$middleware];
        }

        $next = false;
        // Exécution du middleware
        foreach ($middlewares_collection as $middleware) {
            $injections = static::injector($middleware, 'handle');
            $status = call_user_func_array([new $middleware(), 'handle'], array_merge($injections, [function () use (& $next) {
                return $next = true;
            }], $param));
            if ($status === true && $next) {
                $next = false;
                continue;
            }
            if (($status instanceof \StdClass) || is_array($status) || (!($status instanceof Response))) {
                if (! empty($status)) {
                    die(json_encode($status));
                }
            }
            if (is_bool($status)) {
                $status = '';
            }
            return $status;
        }

        // Lancement de l'éxècution de la liste des actions definir
        // Fonction a éxècuté suivant un ordre
        if (! empty($functions)) {
            foreach($functions as $function) {
                $status = call_user_func_array(
                    $function['controller'],
                    array_merge($function['injections'], $param)
                );
            }
        }
        return $status;
    }

    /**
     * Permet de lance un middleware
     *
     * @param string $middleware
     * @param array $param
     * @param \Closure|null $callback
     * @return bool
     */
    public static function middleware($middleware, $param, \Closure $callback = null)
    {
        $next = false;
        $instance = new $middleware();
        $injections = static::injector($middleware, 'handle');

        $status = call_user_func_array([$instance, 'handle'], array_merge([$injections, function () use (& $next) {
            return $next = true;
        }], $param));

        if (is_callable($callback)) {
            $callback();
        }

        return $next && $status === true;
    }

    /**
     * Permet de faire un injection
     *
     * @param string $classname
     * @param string $method
     * @return array
     */
    public static function injector($classname, $method)
    {
        $params = [];
        $reflection = new \ReflectionClass($classname);
        $parts = preg_split('/(\n|\*)+/', $reflection->getMethod($method)->getDocComment());
        foreach ($parts as $value) {
            if (preg_match('/^@param\s+(.+)/', trim($value), $match)) {
                list($class, $variable) = preg_split('/\s+/', $match[1], 2);
                if (class_exists($class, true)) {
                    if (! in_array(strtolower($class), ['string', 'array', 'bool', 'int', 'integer', 'double', 'float', 'callable', 'object', 'stdclass', '\closure', 'closure'])) {
                        $params[] = new $class();
                    }
                }
            }
        }
        return $params;
    }

    /**
     * Next, lance successivement une liste de fonction.
     *
     * @param array|callable $arr
     * @param array|callable $arg
     * @return mixed
     */
    private static function exec($arr, $arg)
    {
        if (is_callable($arr)) {
            return call_user_func_array($arr, $arg);
        }

        if (is_array($arr)) {
            return call_user_func_array($arr, $arg);
        }

        // On lance la loader de controller si $cb est un String
        $controller = static::controller($arr);

        if (is_array($controller)) {
            return call_user_func_array(
                $controller['controller'],
                array_merge($controller['injections'], $arg)
            );
        }

        return false;
    }

    /**
     * Charge les controlleurs
     *
     * @param string $controllerName. Le nom du controlleur a utilisé
     *
     * @return array
     */
    private static function controller($controllerName)
    {
        // Récupération de la classe et de la methode à lancer.
        if (is_null($controllerName)) {
            return null;
        }

        list($class, $method) = preg_split('/\.|@/', $controllerName);
        $class = static::$names['namespace']['controller'] . '\\' . ucfirst($class);

        $injections = static::injector($class, $method);

        return [
            'controller' => [new $class(), $method],
            'injections' => $injections
        ];
    }
}