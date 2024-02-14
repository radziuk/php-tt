<?php

namespace Aradziuk\PhpTT;

class TtGoParser
{

    public function __construct(protected StringReplacer $stringReplacer)
    {
    }

    /**
     * @param string $ttGo
     * @return array|string[]
     * @php-tt-go '@php-tt-go 1,2 >>> 3' >>> [[1, 2], 3, 'equals']
     * @php-tt-go '@php-tt-go "hello" >>> "world"' >>> [['hello'], 'world', 'equals']
     * @php-tt-go '@php-tt-go-exception "hello"' >>> [['hello'], null, 'exception']
     */
    public function parse(string $ttGo)
    {
        $command = $this->getTtGoCommand($ttGo);
        $ttGo = preg_replace(sprintf('/^@php-tt-go-%s/', $command), '', $ttGo);
        $ttGo = preg_replace('/^@php-tt-go/', '', $ttGo);
        $result = $this->processCleanTtGo($ttGo);


        return array_merge($result, [$command]);
    }

    /**
     * @param string $ttGo
     * @return string
     * @php-tt-assert "@php-tt-go-use 'hello' >>> 'world'" >>> 'use'
     * @php-tt-assert "@php-tt-go 'hello' >>> 'world'" >>> 'equals'
     * @php-tt-assert "@php-tt-go-use-help 'hello' >>> 'world'" >>> 'use-help'
     */
    private function getTtGoCommand(string $ttGo): string
    {
        if (preg_match('/^@php-tt-go-([a-zA-Z1-9-]+)/', $ttGo, $match)) {
            return $match[1];
        }
        return 'equals';
    }

    /**
     * @param string $ttGo
     * @return array
     * @php-tt-go '123 >>> 321' >>> [[123], 321]
     * @php-tt-go '"123", [] >>> "hello"' >>> [['123', []], 'hello']
     */
    private function processCleanTtGo(string $ttGo): array
    {
        $delim = '>>>';
        $preparedStr = $this->replaceStrings($ttGo);
        if (!str_contains($preparedStr, $delim)) {
            $left = $this->makeLeft($ttGo);
            return [$left, null];
        }

        [$left, $right] = explode($delim, $preparedStr);


        $left = $this->makeLeft(trim($this->replaceBack($left)));
        $right = $this->makeRight(trim($this->replaceBack($right)));

        return [$left, $right];
    }

    /**
     * @param string $left
     * @return array
     * @php-tt-go "'hello', 'world'" >>> ['hello', 'world']
     * @php-tt-go "" >>> []
     * @php-tt-go "''" >>> ['']
     */
    private function makeLeft(string $left): array
    {
        return eval(sprintf("return [%s];", $left));
    }

    private function makeRight(string $right): mixed
    {
        if (preg_match('/^@.*/', $right)) {

        }
        return eval(sprintf("return %s;", $right));
    }

    private function replaceStrings(string $string): string
    {
        return $this->stringReplacer->replaceStrings($string);
    }

    private function replaceBack(string $string): string
    {
        return $this->stringReplacer->replaceBack($string);
    }
}
