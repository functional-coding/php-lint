<?php

if (isset($argv[1])) {
    $path = $argv[1];
} else {
    throw new \Exception('path is required.');
}

$path = str_replace('/', DIRECTORY_SEPARATOR, $path);

if (is_dir($path)) {
    $files  = array_filter(glob($path.DIRECTORY_SEPARATOR.'**'.DIRECTORY_SEPARATOR.'*Service.php'), 'is_file');
} elseif (is_file($path)) {
    $files = [$path];
} else {
    throw new \Exception('path is not exist.');
}

foreach ($files as $file) {
    $source = file_get_contents($file);
    $source = str_replace("\r\n", "\n", $source);

    echo $file.PHP_EOL;

    foreach ([
        'getBindNames',
        'getCallbacks',
        'getLoaders',
        'getPromiseLists',
        'getRuleLists',
        'getTraits',
    ] as $method) {
        $serviceFnRegEx = "/public static function $method\(\)\n\s{4}\{\n\s{8}return \[\n\s{12}([\s\S]{1,}?)\,\n\s{8}\];\n\s{4}}/";
        $j = [];
        preg_match($serviceFnRegEx, $source, $j);

        if (!empty($j)) {
            $serviceFnCode = $j[1];
            if (in_array($method, ['getCallbacks', 'getLoaders'])) {
                $arrCodes = preg_split("/\,\n{1,}\s{12}(?=')/", $serviceFnCode);

                foreach ($arrCodes as $i => $arrCode) {
                    $argsRegEx = "/^('[a-z_.]{1,}\') \=\> function \(([\s\S]{0,}?)\)/";
                    $k = [];
                    preg_match($argsRegEx, $arrCode, $k);
                    $args = preg_split('/\s*,\s*/', $k[2]);
                    sort($args);
                    $arrCodes[$i] = preg_replace($argsRegEx, $k[1].' => function ('.implode(', ', $args).')', $arrCode);
                }
            } elseif (in_array($method, ['getBindNames', 'getPromiseLists', 'getRuleLists', 'getTraits'])) {
                $arrCodes = preg_split("/\,\n{1,}\s{12}/", $serviceFnCode);
            }

            sort($arrCodes);

            $replace = "public static function $method()\n    {\n        return [\n            ".implode(",".(in_array($method, ['getTraits'])?"\n":"\n\n")."            ", $arrCodes).",\n        ];\n    }";
            $source = preg_replace($serviceFnRegEx, $replace, $source);
        }
    }

    file_put_contents($file, $source);
}