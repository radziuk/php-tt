<?php

namespace Aradziuk\PhpTT;

class StringReplacer
{

    private array $replaced = [];

    private string $replace_pattern = "!@#$~*^%s^*$#@!";

    public function __construct()
    {
    }
    /**
     * @param string $string
     * @return string
     * @php-tt-before $object->setPattern('#%s')
     * @php-tt-assert "hello" >>> "hello"
     * @php-tt-assert "'hello', world" >>> "#1, world"
     * @php-tt-assert "'hello', 'world'" >>> "#1, #2"
     * @php-tt-assert "'hello', 'world'man" >>> "#1, #2man"
     * @php-tt-assert '"hello", "world"man' >>> "#1, #2man"
     * @php-tt-assert-preg '"hello", "world"man' >>> "/^.*$/"
     * @php-tt-assert-exception "'l'et's go"
     */
    public function replaceStrings(string $string): string
    {
        $replaced = '';
        while($replaced != $string) {
            if ($replaced !== '') {
                $string = $replaced;
            }
            $replaced = $this->replaceStringOnce($string);
        }

        return $replaced;
    }

    /**
     * @param string $string
     * @return string
     * @throws TtException
     * @php-tt-before $object->setPattern('#%s')
     * @php-tt-assert "hello" >>> "hello"
     * @php-tt-assert "'hello'" >>> "#1"
     * @php-tt-assert "'hello', world" >>> "#1, world"
     * @php-tt-assert "@@!'hello', world" >>> "@@!#1, world"
     * @php-tt-assert "@@!'hello', world" >>> "@@!#1, world"
     * @php-tt-assert "@@!'hello', world '123'" >>> "@@!#1, world '123'"
     * @php-tt-assert '@@!"hello", world "123"' >>> '@@!#1, world "123"'
     * @php-tt-assert-exception "let's go"
     * @php-tt-assert-exception '"'
     * @php-tt-assert-exception "let's go" >>> \Aradziuk\PhpTT\TtException::class
     * @php-tt-assert-exception-contains '"' >>> "Missing"
     */
    private function replaceStringOnce(string $string): string
    {
        $arr = str_split($string);
        $lookFor = ["'", "\""];
        $found = false;
        $foundAt = null;
        $substr = '';
        $replace_pattern = $this->replace_pattern;
        foreach($arr as $i => $char) {
            if (in_array($char, $lookFor)) {
                if ($found) {
                    $substr .= $char;
                    $count = count($this->replaced) + 1;
                    $replaceWith = sprintf($replace_pattern, $count);
                    $this->replaced[$replaceWith] = $substr;
                    return substr_replace($string, $replaceWith, $foundAt, strlen($substr));
                } else {
                    $lookFor = [$char];
                    $foundAt = $i;
                    $found = $char;
                }
            }
            if ($found) {
                $substr .= $char;
            }
        }

        if ($found) {
            throw new TtException("Missing end quote");
        }

        return $string;
    }

    public function replaceBack(string $string): string
    {
        return strtr($string, $this->replaced);
    }

    public function setPattern(string $pattern): self
    {
        $this->replace_pattern = $pattern;

        return $this;
    }
}
