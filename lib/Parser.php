<?php

namespace TimTegeler\Routerunner;

use TimTegeler\Routerunner\Exception\ParseException;

/**
 * Class Parser
 * @package TimTegeler\Routerunner
 */
class Parser
{
    const SEPARATOR_OF_CLASS_AND_METHOD = "->";
    const HTTP_METHOD = '(?<httpMethod>GET|POST|\*)';
    const URI = '(?<url>(\/[a-zA-Z0-9]+|\/\[string\]|\/\[numeric\]|\/)*(#[a-zA-Z0-9]+)?)';
    const _CALLABLE = '(?<callable>[a-zA-Z]+[_a-zA-Z0-9]*->[_a-zA-Z]+[_a-zA-Z0-9]*)';
    const ROUTE_FORMAT = '^%s[ \t]*%s[ \t]*%s^';
    /**
     * @var bool
     */
    private static $caching = False;

    /**
     * @param boolean $caching
     */
    public static function setCaching($caching)
    {
        self::$caching = $caching;
    }

    /**
     * @return string
     */
    private static function getRegularExpression()
    {
        return $routeRegularExpression = sprintf(self::ROUTE_FORMAT, self::HTTP_METHOD, self::URI, self::_CALLABLE);
    }

    /**
     * @param $route
     * @return Route
     * @throws ParseException
     */
    public static function createRoute($route)
    {
        $regularExpression = self::getRegularExpression();
        if (preg_match($regularExpression, $route, $parts) === 1) {
            array_shift($parts);
            list($controller, $method) = self::generateCallback($parts['callable']);
            return new Route($parts['httpMethod'], $parts['url'], new Callback($controller, $method));
        } else {
            throw new ParseException("Line doesn't matches Pattern");
        }
    }

    /**
     * @param $filename
     * @return array
     * @throws ParseException
     */
    public static function parse($filename)
    {
        if (self::$caching && Cache::useable()) {
            // caching is enabled and the cache is useable
            if (Cache::filled()) {
                // cache is filled
                // reading routes from cache
                list($cacheTimestamp, $routes) = Cache::read();
                // getting timestamp from file
                $routesTimestamp = self::getTimestamp($filename, TRUE);
                if (self::needRecache($cacheTimestamp, $routesTimestamp)) {
                    // routes need recache
                    // writing routes to cache
                    Cache::write(array($routesTimestamp, $routes));
                }
            } else {
                // cache is not filled
                $routesTimestamp = self::getTimestamp($filename, TRUE);
                // arsing routes
                $routes = self::parseRoutes($filename);
                // writing routes to cache
                Cache::write(array($routesTimestamp, $routes));
            }
        } else {
            // caching is disabled or cache is not useable
            // parsing routes
            $routes = self::parseRoutes($filename);
        }
        return $routes;
    }

    /**
     * @param $filename
     * @return array
     * @throws ParseException
     */
    private static function parseRoutes($filename)
    {
        $routes = array();

        self::fileUseable($filename);

        if (($file = @fopen($filename, "r")) !== FALSE) {
            while (($route = fgets($file)) !== FALSE) {
                $routes[] = self::createRoute($route);
            }
        } else {
            throw new ParseException(sprintf("Error while reading file (%s).", $filename));
        }
        fclose($file);

        return $routes;
    }

    /**
     * @param $callable
     * @return array
     */
    private static function generateCallback($callable)
    {
        return explode(self::SEPARATOR_OF_CLASS_AND_METHOD, $callable);
    }

    /**
     * @param $filename
     * @param bool $clearCache
     * @return bool
     * @throws ParseException
     */
    private static function fileUseable($filename, $clearCache = FALSE)
    {
        if ($clearCache) clearstatcache(True, $filename);
        if (!file_exists($filename)) throw new ParseException(sprintf("File (%s) doesn't exist.", $filename));
        if (!is_readable($filename)) throw new ParseException(sprintf("File (%s) isn't readable.", $filename));
        return true;
    }

    /**
     * @param $filename
     * @param bool $clearCache
     * @return int
     * @throws ParseException
     */
    private static function getTimestamp($filename, $clearCache = FALSE)
    {
        self::fileUseable($filename, $clearCache);
        return filemtime($filename);
    }

    /**
     * @param $cacheTimestamp
     * @param $routesTimestamp
     * @return bool
     */
    private static function needRecache($cacheTimestamp, $routesTimestamp)
    {
        return $cacheTimestamp !== $routesTimestamp;
    }
}