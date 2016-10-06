<?php

class CLIRunner
{
    const CAPTURE_NO = 1;

    const CAPTURE_PIPED = 2;

    const CAPTURE_FILE = 3;

    const TIMEOUT_STREAM_SELECT = 30;

    const SIGKILL = 9;

    protected $temp_dir;

    protected $temp_index;

    protected $temp_files;

    protected $php_executable;

    public function __construct($temp_dir = null, $php_executable = null)
    {
        if ($temp_dir === null) {
            $temp_dir = @sys_get_temp_dir();
        }
        $temp_dir = @realpath($temp_dir);
        if (!is_string($temp_dir) || !is_dir($temp_dir)) {
            throw new Exception('Failed to retrieve the temporary directory');
        }
        $temp_dir = rtrim($temp_dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!is_writable($temp_dir)) {
            throw new Exception('The temporary directory is not writable');
        }
        $this->temp_dir = $temp_dir;
        $this->temp_index = 0;
        $this->temp_files = array();

        if ($php_executable === null) {
            $php_executable = getenv('TEST_PHP_EXECUTABLE');
        }
        $php_executable = @realpath($php_executable);
        if (!is_string($php_executable) || !is_file($php_executable)) {
            throw new Exception('The PHP executable can\'t be found');
        }
        $this->php_executable = $php_executable;
    }

    public function __destruct()
    {
        $this->delete_temp_files();
    }

    protected function delete_temp_files()
    {
        while (!empty($this->temp_files)) {
            $file = array_pop($this->temp_files);
            @unlink($file);
        }
    }

    protected function get_temp_file($contents = null)
    {
        for (; ;) {
            ++$this->temp_index;
            $file = $this->temp_dir.'clirunner-temp'.$this->temp_index.'.php';
            if (!file_exists($file)) {
                break;
            }
        }
        if ($contents === null) {
            @touch($file);
        } else {
            @file_put_contents($file, $contents);
        }
        if (!@is_file($file)) {
            throw new Exception('Failed to create a temporary file.');
        }
        $this->temp_files[] = $file;

        return $file;
    }

    public function run($code, $captureStdOut = self::CAPTURE_PIPED, $captureStdErr = self::CAPTURE_PIPED)
    {
        $error = null;
        try {
            $result = $this->execute($code, $captureStdOut, $captureStdErr);
        } catch (Exception $x) {
            $error = $x;
        }
        $this->delete_temp_files();
        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    protected function escape_file_name($filename)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            if (strpos($filename, ' ') !== false) {
                $filename = '"'.$filename.'"';
            }
        } else {
            $filename = escapeshellarg($filename);
        }

        return $filename;
    }

    private function execute($code, $captureStdOut, $captureStdErr)
    {
        $result = array();

        $code_file = $this->get_temp_file("<?php\n$code");
        $descriptorspec = array(
            0 => array('pipe', 'rb'),
        );
        $cmd = static::escape_file_name($this->php_executable);
        // No configuration (ini) files will be used
        $cmd .= ' -n';
        // Report all errors/warnings/notices
        $cmd .= ' -d error_reporting=-1';
        // Turn off html formatting for errors/warnings
        $cmd .= ' -d html_errors=off';
        // Send errors/warnings to stdout
        $cmd .= ' -d display_errors=stdout';
        // Turn off output buffering
        $cmd .= ' -d output_buffering=false';
        // Set the php script to be run
        $cmd .= ' '.static::escape_file_name($code_file);
        // Set the capture of stdout
        $stdOutFile = null;
        switch ($captureStdOut) {
            case static::CAPTURE_NO:
                break;
            case static::CAPTURE_FILE:
                $stdOutFile = $this->get_temp_file();
                $cmd .= ' >'.static::escape_file_name($stdOutFile);
                break;
            case static::CAPTURE_PIPED:
                $descriptorspec[1] = array('pipe', 'wb');
                $result['stdout'] = '';
                break;
            default:
                throw new Exception('Invalid value of $captureStdOut parameter');
        }
        // Set the capture of stderr
        $stdErrFile = null;
        switch ($captureStdErr) {
            case static::CAPTURE_NO:
                if ($captureStdOut === static::CAPTURE_NO) {
                    throw new Exception('No stdout/stderr will be received!');
                }
                break;
            case static::CAPTURE_FILE:
                $stdErrFile = $this->get_temp_file();
                $cmd .= ' 2>'.static::escape_file_name($stdErrFile);
                break;
            case static::CAPTURE_PIPED:
                if ($captureStdOut === static::CAPTURE_PIPED) {
                    $cmd .= ' 2>&1';
                } else {
                    $result['stderr'] = '';
                }
                $descriptorspec[2] = array('pipe', 'wb');
                break;
            default:
                throw new Exception('Invalid value of $captureStdErr parameter');
        }

        if (!function_exists('proc_open')) {
            throw new Exception('This test requires that proc_open() is available');
        }
        $process = @proc_open($cmd, $descriptorspec, $pipes, null, null, array('suppress_errors' => false, 'bypass_shell' => false, 'blocking_pipes' => false));
        if ($process === false) {
            throw new Exception('proc_open() failed');
        }
        fclose($pipes[0]);
        unset($pipes[0]);

        if ($captureStdOut === static::CAPTURE_PIPED || $captureStdErr === static::CAPTURE_PIPED) {
            for (; ;) {
                $read = $pipes;
                $write = null;
                $except = null;
                $numResources = @stream_select($read, $write, $except, static::TIMEOUT_STREAM_SELECT);
                if ($numResources === false) {
                    break;
                }
                if ($numResources === 0) {
                    @proc_terminate($process, static::SIGKILL);
                    throw new Exception('Process timed out');
                }
                if ($captureStdOut === static::CAPTURE_PIPED) {
                    $chunk = @fread($pipes[1], 8192);
                    if ($chunk === false) {
                        @proc_terminate($process, static::SIGKILL);
                        throw new Exception('Failed to read from process stdout');
                    }
                    if ($chunk === '') {
                        break;
                    }
                    $result['stdout'] .= $chunk;
                } else {
                    $chunk = @fread($pipes[2], 8192);
                    if ($chunk === false) {
                        @proc_terminate($process, static::SIGKILL);
                        throw new Exception('Failed to read from process stderr');
                    }
                    if ($chunk === '') {
                        break;
                    }
                    $result['stderr'] .= $chunk;
                }
            }
        }
        $exitCode = @proc_close($process);
        if ($exitCode === -1) {
            @proc_terminate($process, static::SIGKILL);
            throw new Exception('Failed to wait for the process to terminate');
        }
        if ($captureStdOut === static::CAPTURE_FILE) {
            $result['stdout'] = @file_get_contents($stdOutFile);
            if ($result['stdout'] === false) {
                throw new Exception('Failed to read file containing redirected stdout');
            }
        }
        if ($captureStdErr === static::CAPTURE_FILE) {
            $result['stderr'] = @file_get_contents($stdErrFile);
            if ($result['stderr'] === false) {
                throw new Exception('Failed to read file containing redirected stderr');
            }
        }
        if ($exitCode !== 0) {
            $msg = 'Process failed with return code '.$exitCode;
            if (isset($result['stdout']) && $result['stdout'] !== '') {
                $msg .= "\nstdout: ".$result['stdout'];
            }
            if (isset($result['stderr']) && $result['stderr'] !== '') {
                $msg .= "\nstderr: ".$result['stderr'];
            }
            $msg .= "\n\nCommand line executed:\n".$cmd;
            throw new Exception($msg);
        }

        foreach ($result as $k => $v) {
            if ($v === '') {
                unset($result[$k]);
            }
        }

        switch (count($result)) {
            case 0:
                return '';
            case 1:
                return '>'.str_replace(array("\r\n", "\n"), "\n>", rtrim(array_pop($result)));
            default:
                return $result;
        }
    }
}
