<?php

declare(strict_types=1);

namespace Tests\Helper;

/**
 * Helpers to generate disposable fake "ffmpeg" binaries for tests.
 * Each method writes a POSIX shell script to a temp file and returns its path.
 * Point your config('services.ffmpeg.bin') to the returned path.
 */
class FfmpegBinaryFaker
{
    /** Creates a script file in the system temp dir and returns its path. */
    private function writeScript(string $body): string
    {
        $script = sys_get_temp_dir().'/fake_ffmpeg_'.bin2hex(random_bytes(6)).'.sh';
        file_put_contents($script, $body);
        @chmod($script, 0755);
        return $script;
    }

    /**
     * Success: writes a tiny payload to the *last* CLI argument (dst path) and exits 0.
     * Use this for "happy path" tests.
     */
    public function success(string $payload = 'FAKE'): string
    {
        $payload = str_replace("'", "'\"'\"'", $payload); // shell-escape single quotes
        $code = <<<'SH'
#!/usr/bin/env sh
# Determine destination path = last argument
dst=""
for arg in "$@"; do
  dst="$arg"
done
SH;
        $code .= "\nprintf '%s' '{$payload}' > \"\$dst\"\nexit 0\n";
        return $this->writeScript($code);
    }

    /**
     * Failure: writes an error message to STDERR and exits with the given code (default 1).
     * Use this to test error handling/logging.
     */
    public function fail(int $exitCode = 1, string $stderr = 'ffmpeg: simulated error'): string
    {
        $stderr = str_replace("'", "'\"'\"'", $stderr);
        $code = <<<SH
#!/usr/bin/env sh
echo '{$stderr}' 1>&2
exit {$exitCode}
SH;
        return $this->writeScript($code);
    }

    /**
     * Slow success: sleeps for N seconds, then behaves like success().
     * Useful to exercise timeout/idle-timeout behavior (set timeouts > seconds).
     */
    public function slowSuccess(int $seconds = 2, string $payload = 'FAKE'): string
    {
        $payload = str_replace("'", "'\"'\"'", $payload);
        $code = <<<'SH'
#!/usr/bin/env sh
dst=""
for arg in "$@"; do
  dst="$arg"
done
SH;
        $code .= "\nsleep {$seconds}\nprintf '%s' '{$payload}' > \"\$dst\"\nexit 0\n";
        return $this->writeScript($code);
    }

    /**
     * Zero-output + exit 0: does not create the destination file but exits successfully.
     * Lets you test the "process ok but file missing" branch.
     */
    public function zeroOutputZeroExit(): string
    {
        $code = <<<'SH'
#!/usr/bin/env sh
# Intentionally produce no output file but exit 0
exit 0
SH;
        return $this->writeScript($code);
    }

    /**
     * Success with noisy STDERR: writes some lines to STDERR, still creates the dst file and exits 0.
     * Good to verify your stderr logging/tailing.
     */
    public function stderrNoiseSuccess(int $lines = 3, string $payload = 'FAKE'): string
    {
        $payload = str_replace("'", "'\"'\"'", $payload);
        $code = <<<'SH'
#!/usr/bin/env sh
dst=""
for arg in "$@"; do
  dst="$arg"
done
i=1
SH;
        $code .= "\nwhile [ \$i -le {$lines} ]; do echo \"noise line \$i\" 1>&2; i=\$((i+1)); done\n";
        $code .= "printf '%s' '{$payload}' > \"\$dst\"\nexit 0\n";
        return $this->writeScript($code);
    }

    /**
     * Success + argument logging: writes dst file and logs all CLI args to a temp log file.
     * Returns an array with [ 'bin' => path to script, 'log' => path to log file ].
     */
    public function successWithArgLog(string $payload = 'FAKE'): array
    {
        $payload = str_replace("'", "'\"'\"'", $payload);
        $log = sys_get_temp_dir().'/fake_ffmpeg_args_'.bin2hex(random_bytes(6)).'.log';
        $logEscaped = str_replace("'", "'\"'\"'", $log);

        $code = <<<'SH'
#!/usr/bin/env sh
# Log all args
SH;
        $code .= "\nprintf '%s\\n' \"\$@\" > '{$logEscaped}'\n";
        $code .= <<<'SH'
# Determine destination path = last argument
dst=""
for arg in "$@"; do
  dst="$arg"
done
SH;
        $code .= "\nprintf '%s' '{$payload}' > \"\$dst\"\nexit 0\n";

        return [
            'bin' => $this->writeScript($code),
            'log' => $log,
        ];
    }
}
