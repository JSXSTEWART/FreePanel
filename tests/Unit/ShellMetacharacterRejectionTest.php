<?php

namespace Tests\Unit;

use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for the shell-injection fixes landed in the
 * production-readiness remediation pass. Each service now validates its
 * untrusted inputs via `assertValid…` helpers before any `Process::run`
 * call. These tests invoke those helpers directly so they do not require
 * the host tools the services wrap to be installed.
 *
 * If one of these tests fails, the corresponding service has re-introduced
 * a string-interpolated shell command — revert or re-escape before merging.
 */
class ShellMetacharacterRejectionTest extends TestCase
{
    /**
     * Call a protected/private method by name for assertion purposes.
     */
    private function invoke(object $instance, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    public static function shellMetacharacterPayloads(): array
    {
        return [
            'semicolon' => ['ABC123; rm -rf /'],
            'ampersand' => ['ABC123 && id'],
            'backtick' => ['ABC`id`'],
            'dollar-subshell' => ['ABC$(id)'],
            'pipe' => ['ABC | cat /etc/shadow'],
            'newline' => ["ABC\nrm -rf /"],
            'null-byte' => ["ABC\0"],
            'space' => ['ABC 123'],
            'slash' => ['../etc/passwd'],
        ];
    }

    /**
     * @dataProvider shellMetacharacterPayloads
     */
    public function test_mail_queue_rejects_shell_metacharacters_in_queue_id(string $payload): void
    {
        $controller = new \App\Http\Controllers\Api\V1\Admin\MailQueueController;

        try {
            $this->invoke($controller, 'assertValidQueueId', [$payload]);
            $this->fail("assertValidQueueId accepted injection payload: {$payload}");
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    /**
     * @dataProvider shellMetacharacterPayloads
     */
    public function test_systemd_manager_rejects_shell_metacharacters_in_service_name(string $payload): void
    {
        $manager = new \App\Services\System\SystemdManager;

        $this->expectException(\InvalidArgumentException::class);
        $this->invoke($manager, 'assertValidServiceName', [$payload]);
    }

    public function test_systemd_manager_accepts_valid_unit_names(): void
    {
        $manager = new \App\Services\System\SystemdManager;

        // Should not throw.
        $this->invoke($manager, 'assertValidServiceName', ['nginx']);
        $this->invoke($manager, 'assertValidServiceName', ['php8.2-fpm']);
        $this->invoke($manager, 'assertValidServiceName', ['getty@tty1']);
        $this->addToAssertionCount(3);
    }

    /**
     * @dataProvider shellMetacharacterPayloads
     */
    public function test_cron_service_rejects_shell_metacharacters_in_system_user(string $payload): void
    {
        $service = new \App\Services\System\CronService;

        $this->expectException(\InvalidArgumentException::class);
        $this->invoke($service, 'assertValidSystemUser', [$payload]);
    }

    /**
     * @dataProvider shellMetacharacterPayloads
     */
    public function test_pureftpd_rejects_shell_metacharacters_in_username(string $payload): void
    {
        $service = new \App\Services\Ftp\PureFtpdService;

        $this->expectException(\InvalidArgumentException::class);
        $this->invoke($service, 'assertValidUsername', [$payload]);
    }

    public function test_pureftpd_rejects_control_chars_in_password(): void
    {
        $service = new \App\Services\Ftp\PureFtpdService;

        $this->expectException(\InvalidArgumentException::class);
        $this->invoke($service, 'assertValidPassword', ["good\nsecond:line"]);
    }
}
