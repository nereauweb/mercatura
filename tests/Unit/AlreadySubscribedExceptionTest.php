<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AlreadySubscribedException;
use PHPUnit\Framework\TestCase;

class AlreadySubscribedExceptionTest extends TestCase
{
    public function test_is_a_distinct_runtime_exception(): void
    {
        $exception = new AlreadySubscribedException('already subscribed');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertNotSame(\RuntimeException::class, AlreadySubscribedException::class);
        $this->assertSame('already subscribed', $exception->getMessage());
    }
}
