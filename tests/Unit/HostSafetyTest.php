<?php

namespace Tests\Unit;

use App\Support\HostSafety;
use PHPUnit\Framework\TestCase;

class HostSafetyTest extends TestCase
{
    public function test_rejects_loopback(): void
    {
        $this->assertFalse(HostSafety::isSafePublicHost('127.0.0.1'));
    }

    public function test_rejects_private_ranges(): void
    {
        $this->assertFalse(HostSafety::isSafePublicHost('10.0.0.5'));
        $this->assertFalse(HostSafety::isSafePublicHost('192.168.1.1'));
        $this->assertFalse(HostSafety::isSafePublicHost('172.16.0.1'));
    }

    public function test_rejects_link_local_and_cloud_metadata(): void
    {
        $this->assertFalse(HostSafety::isSafePublicHost('169.254.169.254'));
    }

    public function test_accepts_public_ip(): void
    {
        $this->assertTrue(HostSafety::isSafePublicHost('8.8.8.8'));
    }

    public function test_rejects_unresolvable_hostname(): void
    {
        $this->assertFalse(HostSafety::isSafePublicHost('this-host-does-not-resolve.invalid'));
    }
}
