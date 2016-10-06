--TEST--
Test stream_vt100_support on POSIX and compatible Windows versions
--SKIPIF--
<?php
if (stripos(PHP_OS, 'WIN') === 0) {
    if (version_compare(
        PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD,
        '10.0.10586'
    ) < 0) {
        echo "skip Only for Windows systems >= 10.0.10586";
    }
}
?>
--FILE--
<?php
include 'tests/cli_runner.php';

function buildCode($stream, $toStdErr)
{
    $code = <<<'EOT'
$initialState = stream_vt100_support(<STREAM_CONSTANT>);
echo 'Deactivating VT100 support: '; var_dump(stream_vt100_support(<STREAM_CONSTANT>, false));
echo '    Value using stream name: '; var_dump(stream_vt100_support('<STREAM_NAME>'));
echo '    Value using stream constant: '; var_dump(stream_vt100_support(<STREAM_CONSTANT>));
echo '    Value using fopen: '; var_dump(stream_vt100_support(fopen('<STREAM_NAME>', 'wb')));
echo 'Activating VT100 support: '; var_dump(stream_vt100_support(<STREAM_CONSTANT>, true));
echo '    Value using stream name: '; var_dump(stream_vt100_support('<STREAM_NAME>'));
echo '    Value using stream constant: '; var_dump(stream_vt100_support(<STREAM_CONSTANT>));
echo '    Value using fopen: '; var_dump(stream_vt100_support(fopen('<STREAM_NAME>', 'wb')));
stream_vt100_support(<STREAM_CONSTANT>, $initialState);
EOT
    ;
    switch ($stream) {
        case 'stdout':
            $code = str_replace(array('<STREAM_NAME>', '<STREAM_CONSTANT>'), array('php://stdout', 'STDOUT'), $code);
            break;
        case 'stderr':
            $code = str_replace(array('<STREAM_NAME>', '<STREAM_CONSTANT>'), array('php://stderr', 'STDERR'), $code);
            break;
        default:
            throw new Exception('Invalid parameter: '.$stream);

    }
    if ($toStdErr) {
        $code = "ob_start();\n$code\n\$output = ob_get_contents();\nob_end_clean();\n\$stdErr = fopen('php://stderr', 'wb');\nfwrite(\$stdErr, \$output);";
    }

    return $code;
}

$runner = new CLIRunner();

echo "#Controlling unredirected stdout reading piped stderr (should succeed):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_NO, CLIRunner::CAPTURE_PIPED),
    "\n";
echo "#Controlling unredirected stdout reading redirected stderr (should succeed):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_NO, CLIRunner::CAPTURE_FILE),
    "\n";

echo "#Controlling piped stdout reading piped stderr (should fail):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_PIPED, CLIRunner::CAPTURE_PIPED),
    "\n";
echo "#Controlling piped stdout reading redirected stderr (should fail):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_PIPED, CLIRunner::CAPTURE_FILE),
    "\n";

echo "#Controlling redirected stdout reading piped stderr (should fail):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_FILE, CLIRunner::CAPTURE_PIPED),
    "\n";
echo "#Controlling redirected stdout reading redirected stderr (should fail):\n",
    $runner->run(buildCode('stdout', true), CLIRunner::CAPTURE_FILE, CLIRunner::CAPTURE_FILE),
    "\n";

echo "#Controlling unredirected stderr reading piped stdout (should succeed):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_PIPED, CLIRunner::CAPTURE_NO),
    "\n";
echo "#Controlling unredirected stderr reading redirected stdout (should succeed):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_FILE, CLIRunner::CAPTURE_NO),
    "\n";

echo "#Controlling piped stderr reading piped stdout (should fail):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_PIPED, CLIRunner::CAPTURE_PIPED),
    "\n";
echo "#Controlling piped stderr reading redirected stdout (should fail):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_FILE, CLIRunner::CAPTURE_PIPED),
    "\n";

echo "#Controlling redirected stderr reading piped stdout (should fail):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_PIPED, CLIRunner::CAPTURE_FILE),
    "\n";
echo "#Controlling redirected stderr reading redirected stdout (should fail):\n",
    $runner->run(buildCode('stderr', false), CLIRunner::CAPTURE_FILE, CLIRunner::CAPTURE_FILE),
    "\n";
?>
--EXPECT--
#Controlling unredirected stdout reading piped stderr (should succeed):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(true)
>    Value using stream name: bool(true)
>    Value using stream constant: bool(true)
>    Value using fopen: bool(true)
#Controlling unredirected stdout reading redirected stderr (should succeed):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(true)
>    Value using stream name: bool(true)
>    Value using stream constant: bool(true)
>    Value using fopen: bool(true)
#Controlling piped stdout reading piped stderr (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling piped stdout reading redirected stderr (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling redirected stdout reading piped stderr (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling redirected stdout reading redirected stderr (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling unredirected stderr reading piped stdout (should succeed):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(true)
>    Value using stream name: bool(true)
>    Value using stream constant: bool(true)
>    Value using fopen: bool(true)
#Controlling unredirected stderr reading redirected stdout (should succeed):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(true)
>    Value using stream name: bool(true)
>    Value using stream constant: bool(true)
>    Value using fopen: bool(true)
#Controlling piped stderr reading piped stdout (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling piped stderr reading redirected stdout (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling redirected stderr reading piped stdout (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
#Controlling redirected stderr reading redirected stdout (should fail):
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
