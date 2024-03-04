<?php

namespace Radziuk\PhpTT;

trait ClassTrait
{
    /**
     * @param string $class
     * @param string $methodName
     * @param array $mocks
     * @param string $additional
     * @return array
     * @php-tt-mock md5 >>> '123'
     * @php-tt-assert "Hello\\World", '', [], '' >>> ['World__123', 'World']
     */
    protected function makeNewClassName(string $class, string $methodName, array $mocks, string $additional): array
    {
        $exploded = explode("\\", $class);
        $shortenned = array_pop($exploded);
        $md5 = md5($class . $methodName . print_r($mocks, true));

        $newClass =  $shortenned . '_' . $methodName . '_' . $additional . $md5;

        return [$newClass, $shortenned];
    }

    protected function replaceClassName(string $shortenned, string $newClass, string $source): string
    {
        return preg_replace(sprintf('/\bclass %s/', $shortenned), sprintf('class %s', $newClass), $source);
    }

    /**
     * @param string $class
     * @return bool
     * @php-tt-assert 'class { hello' >>> true
     * @php-tt-assert 'Radziuk\PhpTT' >>> false
     */
    protected function isAnonymousClass(string $class): bool
    {
        return preg_match('/^class\s\{/', $class);
    }

    /**
     * @param string $originalTraitFullClass
     * @param string $fullTraitClass
     * @param string $source
     * @return string
     * @php-tt-assert-contains 'TestTrait', 'TestTrait_123', #php_tt_data.class_with_trait_source.1 >>> 'use TestTrait_123'
     * @php-tt-assert-contains "Hello\\World\\TestTrait", "Hello\\TestTrait_123", #php_tt_data.class_with_trait_source.1 >>> "use Hello\\TestTrait_123"
     * @php-tt-assert-contains "Hello\\World\\TestTrait", "Hello\\TestTrait_123", #php_tt_data.class_with_trait_source.2 >>> "use Hello\\TestTrait_123"
     */
    protected function replaceUsesTrait(string $originalTraitFullClass, string $fullTraitClass, string $source): string
    {

        $source2 = $this->replaceTraitRegex($originalTraitFullClass, $fullTraitClass, $source);
        if ($source2 !== $source) {
            return $source2;
        }
        $exploded = explode("\\", $originalTraitFullClass);
        $shortenned = array_pop($exploded);
        return $this->replaceTraitRegex($shortenned, $fullTraitClass, $source);
    }

    /**
     * @param string $find
     * @param string $replace
     * @param string $source
     * @return string
     * @php-tt-assert 'TestTrait', 'TestTrait2', #php_tt_data.class_with_many_traits.1
     */
    protected function replaceTraitRegex(string $find, string $replace, string $source): string
    {
        $csPattern = sprintf('/use\s+([^;]*\b%s\b[^;]*);/', preg_quote($find));
        if (preg_match($csPattern, $source)) {
            $replacement = function ($matches) use($find, $replace) {
                // Split the matched string by commas, trim whitespace, and replace TraitA with TraitB
                $traits = array_map('trim', explode(',', $matches[1]));
                $traits = str_replace($find, $replace, $traits);
                return 'use ' . implode(', ', $traits) . ';';
            };

            return preg_replace_callback($csPattern, $replacement, $source);
        }

        return preg_replace(sprintf('/use\s+%s/', preg_quote($find)), 'use ' . $replace, $source);
    }
}