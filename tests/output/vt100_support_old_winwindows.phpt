--TEST--
Test stream_vt100_support on incompatible Windows versions
--SKIPIF--
<?php
if (stripos(PHP_OS, 'WIN') !== 0) {
    echo "skip Only for Windows systems";
} elseif (version_compare(
    PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD,
	 '10.0.10586'
) >= 0) {
	echo "skip Only for Windows systems < 10.0.10586";
}
?>
--FILE--
<?php
include 'tests/cli_runner.php';

$runner = new CLIRunner();

echo "#Controlling unredirected stdout reading piped stderr (should succeed):\n",
    $runner->run(<<<EOT

\$initialState = stream_vt100_support(STDOUT);
echo 'Deactivating VT100 support: '; var_dump(stream_vt100_support(STDOUT, false));
echo '    Value using stream name: '; var_dump(stream_vt100_support('php://stdout'));
echo '    Value using stream constant: '; var_dump(stream_vt100_support(STDOUT));
echo '    Value using fopen: '; var_dump(stream_vt100_support(fopen('php://stdout', 'wb')));
echo 'Activating VT100 support: '; var_dump(stream_vt100_support(STDOUT, true));
echo '    Value using stream name: '; var_dump(stream_vt100_support('php://stdout'));
echo '    Value using stream constant: '; var_dump(stream_vt100_support(STDOUT));
echo '    Value using fopen: '; var_dump(stream_vt100_support(fopen('php://stdout', 'wb')));
stream_vt100_support(STDOUT, \$initialState);

EOT
    , CLIRunner::CAPTURE_NO, CLIRunner::CAPTURE_PIPED),
    "\n";

?>
--EXPECT--
>Deactivating VT100 support: bool(true)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
>Activating VT100 support: bool(false)
>    Value using stream name: bool(false)
>    Value using stream constant: bool(false)
>    Value using fopen: bool(false)
