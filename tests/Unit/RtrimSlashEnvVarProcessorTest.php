<?php

namespace App\Tests\Unit;

use App\DependencyInjection\RtrimSlashEnvVarProcessor;
use PHPUnit\Framework\TestCase;

final class RtrimSlashEnvVarProcessorTest extends TestCase
{
    public function testRtrimsTrailingSlashes(): void
    {
        $processor = new RtrimSlashEnvVarProcessor();
        $getEnv = static fn (string $name): string => 'https://santa.wip/';

        self::assertSame('https://santa.wip', $processor->getEnv('rtrimslash', 'DEFAULT_URI', $getEnv));
    }

    public function testLeavesUriWithoutTrailingSlash(): void
    {
        $processor = new RtrimSlashEnvVarProcessor();
        $getEnv = static fn (string $name): string => 'https://santa.wip';

        self::assertSame('https://santa.wip', $processor->getEnv('rtrimslash', 'DEFAULT_URI', $getEnv));
    }

    public function testProvidedTypes(): void
    {
        self::assertSame(['rtrimslash' => 'string'], RtrimSlashEnvVarProcessor::getProvidedTypes());
    }
}
