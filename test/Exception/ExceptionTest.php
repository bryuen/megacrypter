<?php

use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    // --- MegaCrypterLinkException ---

    public function testMegaCrypterLinkExceptionLinkError()
    {
        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::LINK_ERROR);

        $this->assertEquals(Utils_MegaCrypter::LINK_ERROR, $exception->getCode());
        $this->assertStringContainsString('Bad', $exception->getMessage());
        $this->assertStringContainsString('MC link', $exception->getMessage());
    }

    public function testMegaCrypterLinkExceptionBlacklisted()
    {
        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::BLACKLISTED_LINK);

        $this->assertEquals(Utils_MegaCrypter::BLACKLISTED_LINK, $exception->getCode());
        $this->assertStringContainsString('Blocked', $exception->getMessage());
    }

    public function testMegaCrypterLinkExceptionExpired()
    {
        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::EXPIRED_LINK);

        $this->assertEquals(Utils_MegaCrypter::EXPIRED_LINK, $exception->getCode());
        $this->assertStringContainsString('Expired', $exception->getMessage());
    }

    public function testMegaCrypterLinkExceptionUnknownCode()
    {
        $exception = new Exception_MegaCrypterLinkException(999);

        $this->assertEquals(999, $exception->getCode());
        $this->assertStringContainsString('MC link', $exception->getMessage());
        $this->assertStringContainsString('999', $exception->getMessage());
    }

    public function testMegaCrypterLinkExceptionImplementsInterface()
    {
        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::LINK_ERROR);
        $this->assertInstanceOf(Exception_iControllerTractableException::class, $exception);
    }

    public function testMegaCrypterLinkExceptionExtendsLinkException()
    {
        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::LINK_ERROR);
        $this->assertInstanceOf(Exception_LinkException::class, $exception);
    }

    // --- MegaLinkException ---

    public function testMegaLinkExceptionEnoent()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::ENOENT);

        $this->assertEquals(Utils_MegaApi::ENOENT, $exception->getCode());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testMegaLinkExceptionEblocked()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::EBLOCKED);

        $this->assertEquals(Utils_MegaApi::EBLOCKED, $exception->getCode());
        $this->assertStringContainsString('blocked', $exception->getMessage());
    }

    public function testMegaLinkExceptionEkey()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::EKEY);

        $this->assertEquals(Utils_MegaApi::EKEY, $exception->getCode());
        $this->assertStringContainsString('Bad', $exception->getMessage());
    }

    public function testMegaLinkExceptionEtempunavail()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::ETEMPUNAVAIL);

        $this->assertEquals(Utils_MegaApi::ETEMPUNAVAIL, $exception->getCode());
        $this->assertStringContainsString('temporarily unavailable', $exception->getMessage());
    }

    public function testMegaLinkExceptionEtoomany()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::ETOOMANY);

        $this->assertEquals(Utils_MegaApi::ETOOMANY, $exception->getCode());
        $this->assertStringContainsString('terminated', $exception->getMessage());
    }

    public function testMegaLinkExceptionEinternal()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::EINTERNAL);

        $this->assertEquals(Utils_MegaApi::EINTERNAL, $exception->getCode());
        $this->assertStringContainsString('cloud temporarily unavailable', $exception->getMessage());
    }

    public function testMegaLinkExceptionUnknownCode()
    {
        $exception = new Exception_MegaLinkException(-999);

        $this->assertEquals(-999, $exception->getCode());
        $this->assertStringContainsString('MEGA file', $exception->getMessage());
        $this->assertStringContainsString('-999', $exception->getMessage());
    }

    public function testMegaLinkExceptionImplementsInterface()
    {
        $exception = new Exception_MegaLinkException(Utils_MegaApi::ENOENT);
        $this->assertInstanceOf(Exception_iControllerTractableException::class, $exception);
    }

    // --- MegaCrypterAPIException ---

    public function testMegaCrypterAPIExceptionCode()
    {
        $exception = new Exception_MegaCrypterAPIException(5);

        $this->assertEquals(5, $exception->getCode());
    }

    public function testMegaCrypterAPIExceptionImplementsInterface()
    {
        $exception = new Exception_MegaCrypterAPIException(1);
        $this->assertInstanceOf(Exception_iControllerTractableException::class, $exception);
    }

    public function testMegaCrypterAPIExceptionCustomHandler()
    {
        $called = false;
        $handler = function() use (&$called) {
            $called = true;
            return 'handled';
        };

        $exception = new Exception_MegaCrypterAPIException(1, $handler);
        $result = $exception->handleIt();

        $this->assertTrue($called);
        $this->assertEquals('handled', $result);
    }

    // --- InvalidRefererException ---

    public function testInvalidRefererExceptionDefault()
    {
        $exception = new Exception_InvalidRefererException();
        $this->assertInstanceOf(Exception_iControllerTractableException::class, $exception);
    }

    public function testInvalidRefererExceptionWithMessage()
    {
        $exception = new Exception_InvalidRefererException(null, 'Invalid domain');
        $this->assertEquals('Invalid domain', $exception->getMessage());
    }

    public function testInvalidRefererExceptionCustomHandler()
    {
        $called = false;
        $handler = function() use (&$called) {
            $called = true;
        };

        $exception = new Exception_InvalidRefererException($handler);
        $exception->handleIt();

        $this->assertTrue($called);
    }

    // --- PreDispatchException ---

    public function testPreDispatchException()
    {
        $called = false;
        $handler = function() use (&$called) {
            $called = true;
            return 'dispatched';
        };

        $exception = new Exception_PreDispatchException($handler, 'Maintenance');
        $this->assertEquals('Maintenance', $exception->getMessage());

        $result = $exception->handleIt();
        $this->assertTrue($called);
        $this->assertEquals('dispatched', $result);
    }

    public function testPreDispatchExceptionImplementsInterface()
    {
        $exception = new Exception_PreDispatchException(function() {});
        $this->assertInstanceOf(Exception_iControllerTractableException::class, $exception);
    }

    public function testPreDispatchExceptionNonCallableHandler()
    {
        $exception = new Exception_PreDispatchException('not-a-callable');
        $this->assertFalse($exception->handleIt());
    }

    // --- LinkException handleIt ---

    public function testLinkExceptionHandleItWithCustomHandler()
    {
        $called = false;
        $handler = function() use (&$called) {
            $called = true;
            return 'custom_result';
        };

        $exception = new Exception_MegaCrypterLinkException(Utils_MegaCrypter::LINK_ERROR, $handler);
        $result = $exception->handleIt();

        $this->assertTrue($called);
        $this->assertEquals('custom_result', $result);
    }
}
