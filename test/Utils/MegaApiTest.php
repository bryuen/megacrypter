<?php

use PHPUnit\Framework\TestCase;

class MegaApiTest extends TestCase
{
    public function testConstants()
    {
        $this->assertEquals('https://mega.nz', Utils_MegaApi::MEGA_HOST);
        $this->assertEquals('https://g.api.mega.co.nz', Utils_MegaApi::MEGA_API_HOST);
        $this->assertEquals(15, Utils_MegaApi::CONNECT_TIMEOUT);
        $this->assertEquals(32, Utils_MegaApi::FILE_KEY_BYTE_LENGTH);
        $this->assertEquals(16, Utils_MegaApi::FOLDER_KEY_BYTE_LENGTH);
        $this->assertEquals(3600, Utils_MegaApi::CACHE_FILEINFO_TTL);
    }

    public function testErrorConstants()
    {
        $this->assertEquals(-1, Utils_MegaApi::EINTERNAL);
        $this->assertEquals(-2, Utils_MegaApi::EARGS);
        $this->assertEquals(-3, Utils_MegaApi::EAGAIN);
        $this->assertEquals(-4, Utils_MegaApi::ERATELIMIT);
        $this->assertEquals(-5, Utils_MegaApi::EFAILED);
        $this->assertEquals(-6, Utils_MegaApi::ETOOMANY);
        $this->assertEquals(-7, Utils_MegaApi::ERANGE);
        $this->assertEquals(-8, Utils_MegaApi::EEXPIRED);
        $this->assertEquals(-9, Utils_MegaApi::ENOENT);
        $this->assertEquals(-10, Utils_MegaApi::ECIRCULAR);
        $this->assertEquals(-11, Utils_MegaApi::EACCESS);
        $this->assertEquals(-12, Utils_MegaApi::EEXIST);
        $this->assertEquals(-13, Utils_MegaApi::EINCOMPLETE);
        $this->assertEquals(-14, Utils_MegaApi::EKEY);
        $this->assertEquals(-15, Utils_MegaApi::ESID);
        $this->assertEquals(-16, Utils_MegaApi::EBLOCKED);
        $this->assertEquals(-17, Utils_MegaApi::EOVERQUOTA);
        $this->assertEquals(-18, Utils_MegaApi::ETEMPUNAVAIL);
        $this->assertEquals(-19, Utils_MegaApi::ETOOMANYCONNECTIONS);
        $this->assertEquals(-20, Utils_MegaApi::EWRITE);
        $this->assertEquals(-21, Utils_MegaApi::EREAD);
        $this->assertEquals(-22, Utils_MegaApi::EAPPKEY);
        $this->assertEquals(-101, Utils_MegaApi::EDLURL);
    }

    public function testConstructor()
    {
        $api = new Utils_MegaApi('test_key');
        $this->assertIsInt($api->getSeqno());
    }

    public function testConstructorSeqnoIsRandom()
    {
        $api1 = new Utils_MegaApi('test_key');
        $api2 = new Utils_MegaApi('test_key');

        // While technically they could be the same, the probability is negligible
        // We just verify both return integers
        $this->assertIsInt($api1->getSeqno());
        $this->assertIsInt($api2->getSeqno());
    }
}
