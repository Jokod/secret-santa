<?php

namespace App\Tests\Unit;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSetsReferrerPolicyOnMainRequest(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new Response());

        (new SecurityHeadersSubscriber())->onResponse($event);

        self::assertSame('no-referrer', $event->getResponse()->headers->get('Referrer-Policy'));
    }

    public function testIgnoresSubRequests(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, new Request(), HttpKernelInterface::SUB_REQUEST, new Response());

        (new SecurityHeadersSubscriber())->onResponse($event);

        self::assertNull($event->getResponse()->headers->get('Referrer-Policy'));
    }

    public function testSubscribesToResponseEvent(): void
    {
        self::assertArrayHasKey(
            \Symfony\Component\HttpKernel\KernelEvents::RESPONSE,
            SecurityHeadersSubscriber::getSubscribedEvents()
        );
    }
}
