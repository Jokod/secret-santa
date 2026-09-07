<?php

namespace App\Tests\Unit;

use App\Entity\Exclusion;
use App\Entity\Participant;
use App\Repository\ExclusionRepository;
use PHPUnit\Framework\TestCase;

final class ExclusionRepositoryTest extends TestCase
{
    public function testGetForbiddenTargetsMapSkipsNullIds(): void
    {
        $source = (new Participant())->setName('Unpersisted Source')->setEmail('us@test.com')->setTokenSecret('us');
        $target = (new Participant())->setName('Unpersisted Target')->setEmail('ut@test.com')->setTokenSecret('ut');
        $exclusion = (new Exclusion())->setSource($source)->setTarget($target);

        $repo = $this->getMockBuilder(ExclusionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAll'])
            ->getMock();
        $repo->expects(self::once())->method('findAll')->willReturn([$exclusion]);

        self::assertSame([], $repo->getForbiddenTargetsMap());
    }
}
