<?php

namespace franciscoblancojn\wordpress_utils;

if (!class_exists("FWUComponent") && function_exists("add_action")) {
    abstract class FWUComponent
    {
        protected static $rendered = [];

        abstract public static function html(...$args): string;
        abstract public static function css(): string;
        abstract public static function js(): string;

        public static function render(...$args): void
        {
            $class = static::class;
            echo static::html(...$args);
            if (empty(self::$rendered[$class])) {
                echo static::css();
                echo static::js();
                self::$rendered[$class] = true;
            }
        }

        public static function reset(): void
        {
            $class = static::class;
            unset(self::$rendered[$class]);
        }
    }
}
