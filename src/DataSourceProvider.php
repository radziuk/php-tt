<?php

namespace Radziuk\PhpTT;

class DataSourceProvider
{
    private $data_dir = null;

    private $alert_callback = null;

    /**
     * @param string $dataSource
     * @param string $methodName
     * @return array
     * @php-tt-exact-mock $this->data_dir >>> '/var/www'
     * @php-tt-mock @file_exists >>> true
     * @php-tt-exact-mock require $file >>> ['key' => ['hello'], 'key2' => ['hello2']]
     * @php-tt-mock alert >>> null
     * @php-tt-assert 'Any.key', 'default' >>> ['hello']
     * @php-tt-assert 'Any', 'key2' >>> ['hello2']
     * @php-tt-assert 'Any.way', 'default' >>> []
     * @php-tt-assert-not-equals 'Any.way', 'default' >>> 123
     */
    public function getDataFromDataSource(string $dataSource, string $methodName): array
    {
        if (!$this->data_dir) {
            $this->alert("Data dir for data source $dataSource is not specified. If you want to use  data source please specify \$data_dir when creating the object. Skipping");
            return [];
        }
        $dataDir = $this->data_dir;

        $fileName = $this->getFilenameFromDatasource($dataSource);
        $file = $dataDir . '/' . $fileName;

        if (!file_exists($file)) {
            $this->alert("Data source file $file not found. Skipping");
            return [];
        }

        $dataArray = require $file;

        $dataKey = $this->getDataKeyFromDataSource($dataSource, $methodName);

        if (!array_key_exists($dataKey, $dataArray)) {
            $this->alert("Data is not set for key $dataKey in file $fileName. Skipping");
            return [];
        }

        return $dataArray[$dataKey];
    }

    /**
     * @param string $dataSource
     * @return string
     * @php-tt-data php_tt_data
     */
    public function getFilenameFromDatasource(string $dataSource): string
    {
        $exploded = explode('.', $dataSource);
        return array_shift($exploded) . '.php';
    }

    /**
     * @param string $dataSource
     * @param string $methodName
     * @return string
     * @php-tt-data php_tt_data
     */
    public function getDataKeyFromDataSource(string $dataSource, ?string $methodName = null): string|array
    {
        $exploded = explode('.', $dataSource);
        array_shift($exploded);
        if (count($exploded) === 0) {
            if (null === $methodName) {
                throw new TtException("Method key can not be determined for dataSource $dataSource");
            }
            return $methodName;
        }

        return count($exploded) === 1 ? array_pop($exploded) : $exploded;
    }


    /**
     * @param string $dataSource
     * @param string|null $defaultKey
     * @return string
     * @throws TtException
     * @php-tt-mock getDataDir >>> '/var/www'
     * @php-tt-mock @file_exists >>> true
     * @php-tt-assert 'Data.test' >>> "(include '/var/www/Data.php')['test']"
     * @php-tt-assert 'Datadata.testtest' >>> "(include '/var/www/Datadata.php')['testtest']"
     * @php-tt-assert 'Datadata_2_.testtest_' >>> "(include '/var/www/Datadata_2_.php')['testtest_']"
     * @php-tt-assert #php_tt_data.getDataSourceInclude.1 >>> #php_tt_data.getDataSourceInclude.2
     * @Todo: change @file_exists mock to false, and test TtException
     */
    public function getDataSourceInclude(string $dataSource, ?string $defaultKey = null): string
    {
        $fileName = $this->getFilenameFromDatasource($dataSource);
        $filePath = $this->getDataDir() . '/' . $fileName;
        if (!file_exists($filePath)) {
            throw new TtException("Filename $filePath does not exist");
        }
        $dataKey = $this->getDataKeyFromDataSource($dataSource, $defaultKey);

        return $this->makeInclude($filePath, $dataKey);
    }

    /**
     * @param string $string
     * @return string
     * @throws TtException
     * @php-tt-assert 'hello' >>> 'hello'
     * @php-tt-mock getDataSourceInclude >>> @@2
     * @php-tt-assert "hello, #dataSource.1, world", '#replace' >>> "hello, #replace, world"
     * @php-tt-assert "hello, #dataSource.1, world #data.hello.2", '#replace' >>> "hello, #replace, world #replace"
     * @php-tt-assert #php_tt_data._replace.0, '#replace' >>> #php_tt_data._replace.1
     */
    public function replaceHashtaggedDatasourcesWithInclude(string $string, ?string $defaultKey = null): string
    {
        $preg = "/#\w[\w.]*/";
        if (preg_match_all($preg, $string, $matches)) {
            foreach($matches[0] as $match) {
                $dataSource = preg_replace('/^#/', '', $match);
                $replace = $this->getDataSourceInclude($dataSource, $defaultKey);
                $string = $this->replace_first_occurrence($match, $replace, $string);
            }
        }
        return $string;
    }

    /**
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     * @php-tt-assert 'hello', 'world', 'hello, world, hello' >>> 'world, world, hello'
     */
    private function replace_first_occurrence(string $search, string $replace, string $subject): string{
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject; // Return the original string if the search string is not found
    }

    /**
     * @param string $filePath
     * @param array|string $key
     * @return string
     * @php-tt-assert "/var/www/Test.php", 1 >>> "(include '/var/www/Test.php')['1']"
     * @php-tt-assert "/var/www/Test.php", ['test', 1] >>> "(include '/var/www/Test.php')['test']['1']"
     * @php-tt-assert "/var/www/Test.php", "hello" >>> "(include '/var/www/Test.php')['hello']"
     */
    private function makeInclude(string $filePath, array|string|int $key): string
    {
        $result = sprintf("(include '%s')", $filePath);
        if (!is_array($key)) {
            $key = [$key];
        }

        foreach($key as $k) {
            $result .= sprintf("['%s']", $k);
        }

        return $result;
    }

    public function setDataDir(string $data_dir): self
    {
        $this->data_dir = $data_dir;

        return $this;
    }

    public function getDataDir()
    {
        return $this->data_dir;
    }

    public function alert(string $string): void
    {
        if (null !== $this->alert_callback && is_callable($this->alert_callback)) {
            ($this->alert_callback)($string);

            return;
        }

        echo "Alert: " . $string . "\n";
    }

    public function setAlertCallback(callable $alertCallback): self
    {
        $this->alert_callback = $alertCallback;

        return $this;
    }
}