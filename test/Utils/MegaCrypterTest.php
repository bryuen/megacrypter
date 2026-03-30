<?php

use PHPUnit\Framework\TestCase;

class MegaCrypterTest extends TestCase
{
    public function testGetOptionalFlags()
    {
        $expected_flags = ['EXTRAINFO', 'HIDENAME', 'PASSWORD', 'EXPIRE', 'NOEXPIRETOKEN', 'REFERER', 'EMAIL', 'ZOMBIE'];

        $optional_flags = $this->_getOptionalFields();

        $this->assertIsArray($optional_flags);

        $this->assertEquals($expected_flags, array_keys($optional_flags));

    }

    public function testPack()
    {
        $expected_packs = [

            'EXTRAINFO' => ['in' => ['hola'], 'out' => pack('C', strlen('hola')-1).'hola'],

            'PASSWORD' => ['in' => ['mypassword', 'mysalt'], 'out' => pack('C', Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2 - 1) . hash_pbkdf2('sha256', 'mypassword', 'mysalt', pow(2, Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2), 0, true) . 'mysalt'],

            'EXPIRE' => ['in' => [1452961699], 'out' => pack('NN', (1452961699 >> 32) & 0xFFFFFFFF, 1452961699 & 0xFFFFFFFF)],

            'REFERER' => ['in' => ['www.foo.com'], 'out' => pack('C', strlen('www.foo.com')-1) . 'www.foo.com'],

            'EMAIL' => ['in' => ['foo@foo.com'], 'out' => pack('C', strlen('foo@foo.com')-1) . 'foo@foo.com'],

            'ZOMBIE' => ['in' => ['127.0.0.1'], 'out' => pack('CCCC', 127,0,0,1)]

        ];

        $optional_flags = $this->_getOptionalFields();

        foreach($optional_flags as $flag => $val) {

            if(array_key_exists($flag, $expected_packs)) {

                $this->assertEquals($expected_packs[$flag]['out'], call_user_func_array($val['pack'], $expected_packs[$flag]['in']));
            }
        }
    }

    public function testUnpack()
    {
        $expected_unpacks = [

            'EXTRAINFO' => ['in' => [pack('C', strlen('hola')-1).'hola'], 'out' => 'hola'],

            'PASSWORD' => ['in' => [($password_pack = pack('C', Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2 - 1) . ($hash_pbkdf2=hash_pbkdf2('sha256', 'mypassword', md5('mysalt', true), pow(2, Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2), 0, true)) . md5('mysalt', true))], 'out' => ['iterations' => Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2, 'pbkdf2_hash' => $hash_pbkdf2, 'salt' => md5('mysalt', true) ]],

            'EXPIRE' => ['in' => [pack('NN', (1452961699 >> 32) & 0xFFFFFFFF, 1452961699 & 0xFFFFFFFF)], 'out' => 1452961699],

            'REFERER' => ['in' => [pack('C', strlen('www.foo.com')-1) . 'www.foo.com'], 'out' => 'www.foo.com'],

            'EMAIL' => ['in' => [pack('C', strlen('foo@foo.com')-1) . 'foo@foo.com'], 'out' => 'foo@foo.com'],

            'ZOMBIE' => ['in' => [pack('CCCC', 127,0,0,1)],'out' => '127.0.0.1']

        ];

        $optional_flags = $this->_getOptionalFields();

        $offset = 0;

        foreach($optional_flags as $flag => $val) {

            if(array_key_exists($flag, $expected_unpacks)) {

                $this->assertEquals($expected_unpacks[$flag]['out'], call_user_func_array($val['unpack'], array_merge($expected_unpacks[$flag]['in'], [&$offset])));
                $offset = 0;
            }
        }
    }

    public function testEncryptDecryptLink()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        $clink = $method->invoke(new Utils_MegaCrypter, $link,
            [
                'EXTRAINFO' => 'hola',
                'HIDENAME' => true,
                'PASSWORD' => 'mypassword',
                'EXPIRE' => 2452961699,
                'NOEXPIRETOKEN' => true,
                'REFERER' => 'www.foo.com',
                'EMAIL' => 'foo@foo.com',
                'ZOMBIE' => '127.0.0.1'

            ]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        preg_match('/^.*?!(?P<data>[0-9a-z_-]+)!(?P<hash>[0-9a-f]+)/i', trim(str_replace('/', '', $clink)), $match);

        $dlink = Utils_MegaCrypter::decryptLink($clink);

        $this->assertEquals('RF1GiAzT', $dlink['file_id']);
        $this->assertEquals('JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4', $dlink['file_key']);
        $this->assertEquals('hola', $dlink['extra_info']);
        $this->assertEquals(true, $dlink['hide_name']);
        $this->assertIsArray($dlink['pass']);
        $this->assertEquals(2452961699, $dlink['expire']);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $this->assertEquals(hash_hmac('sha256', substr(Utils_MiscTools::urlBase64Decode($match['data']), 0, $iv_length), GENERIC_PASSWORD, true), base64_decode($dlink['no_expire_token']));
        $this->assertEquals('www.foo.com', $dlink['referer']);
        $this->assertEquals('foo@foo.com', $dlink['email']);
        $this->assertEquals('127.0.0.1', $dlink['zombie']);
    }

    public function testEncryptDecryptLinkMinimal()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        $clink = $method->invoke(new Utils_MegaCrypter, $link, []);

        $dlink = Utils_MegaCrypter::decryptLink($clink);

        $this->assertEquals('RF1GiAzT', $dlink['file_id']);
        $this->assertEquals('JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4', $dlink['file_key']);
        $this->assertFalse($dlink['extra_info']);
        $this->assertFalse($dlink['hide_name']);
        $this->assertFalse($dlink['pass']);
        $this->assertFalse($dlink['expire']);
        $this->assertFalse($dlink['no_expire_token']);
        $this->assertFalse($dlink['referer']);
        $this->assertFalse($dlink['email']);
        $this->assertFalse($dlink['zombie']);
    }

    public function testDecryptInvalidLinkThrowsException()
    {
        $this->expectException(Exception_MegaCrypterLinkException::class);
        $this->expectExceptionCode(Utils_MegaCrypter::LINK_ERROR);

        Utils_MegaCrypter::decryptLink('this-is-not-a-valid-link');
    }

    public function testDecryptTamperedLinkThrowsException()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        $clink = $method->invoke(new Utils_MegaCrypter, $link, []);

        // Tamper with the hash
        $tampered = preg_replace('/![0-9a-f]+$/i', '!0000000000', $clink);

        $this->expectException(Exception_MegaCrypterLinkException::class);
        $this->expectExceptionCode(Utils_MegaCrypter::LINK_ERROR);

        Utils_MegaCrypter::decryptLink($tampered);
    }

    public function testEncryptDecryptWithExpiredLink()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        // Use an expiration time in the past
        $clink = $method->invoke(new Utils_MegaCrypter, $link, [
            'EXPIRE' => 1000000000,
        ]);

        $this->expectException(Exception_MegaCrypterLinkException::class);
        $this->expectExceptionCode(Utils_MegaCrypter::EXPIRED_LINK);

        Utils_MegaCrypter::decryptLink($clink);
    }

    public function testEncryptDecryptWithZombieWrongIp()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        $clink = $method->invoke(new Utils_MegaCrypter, $link, [
            'ZOMBIE' => '10.0.0.1',
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $this->expectException(Exception_MegaCrypterLinkException::class);
        $this->expectExceptionCode(Utils_MegaCrypter::LINK_ERROR);

        Utils_MegaCrypter::decryptLink($clink);
    }

    public function testEncryptLinkInvalidFormatThrowsException()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $this->expectException(Exception_MegaCrypterLinkException::class);
        $this->expectExceptionCode(Utils_MegaCrypter::LINK_ERROR);

        $method->invoke(new Utils_MegaCrypter, 'not-a-valid-mega-link', []);
    }

    public function testEncryptDecryptWithOnlyExtraInfo()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_encryptLink'
        );

        $method->setAccessible(TRUE);

        $link = 'https://mega.nz/#!RF1GiAzT!JznAr3lWn-A28Sp6CqmqnrEJymNtkgkESSwfunSRJf4';

        $clink = $method->invoke(new Utils_MegaCrypter, $link, [
            'EXTRAINFO' => 'test extra info',
        ]);

        $dlink = Utils_MegaCrypter::decryptLink($clink);

        $this->assertEquals('test extra info', $dlink['extra_info']);
        $this->assertFalse($dlink['pass']);
        $this->assertFalse($dlink['hide_name']);
    }

    public function testConstants()
    {
        $this->assertEquals(14, Utils_MegaCrypter::PBKDF2_ITERATIONS_LOG2);
        $this->assertEquals(86400, Utils_MegaCrypter::ZOMBIE_LINK_TTL);
        $this->assertEquals(255, Utils_MegaCrypter::MAX_FILE_NAME_BYTES);
        $this->assertEquals(3600, Utils_MegaCrypter::CACHE_BLACKLISTED_TTL);
        $this->assertEquals(0, Utils_MegaCrypter::BLACKLIST_LEVEL_OFF);
        $this->assertEquals(1, Utils_MegaCrypter::BLACKLIST_LEVEL_MC);
        $this->assertEquals(2, Utils_MegaCrypter::BLACKLIST_LEVEL_MEGA);
        $this->assertEquals(21, Utils_MegaCrypter::INTERNAL_ERROR);
        $this->assertEquals(22, Utils_MegaCrypter::LINK_ERROR);
        $this->assertEquals(23, Utils_MegaCrypter::BLACKLISTED_LINK);
        $this->assertEquals(24, Utils_MegaCrypter::EXPIRED_LINK);
    }

    private function _getOptionalFields()
    {
        $method = new ReflectionMethod(
            'Utils_MegaCrypter', '_getOptionalFields'
        );

        $method->setAccessible(TRUE);

        $optional_flags = $method->invoke(new Utils_MegaCrypter);

        return $optional_flags;
    }
}
