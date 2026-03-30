<?php

use PHPUnit\Framework\TestCase;

class CryptToolsTest extends TestCase
{
    public function testAesCbcEncryptDecryptRoundTrip()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $plaintext = 'Hello, World! This is a test message.';

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesCbcEncryptDecryptWithNullIv()
    {
        $key = openssl_random_pseudo_bytes(32);
        $plaintext = 'Test with null IV';

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesCbcEncryptDecryptWithoutPkcs7()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        // Data must be a multiple of block size (16) for no-padding mode
        $plaintext = str_pad('TestData', 16, "\0");

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv, false);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv, false);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesCbcEncryptProducesDifferentOutputWithDifferentKeys()
    {
        $key1 = openssl_random_pseudo_bytes(32);
        $key2 = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $plaintext = 'Same plaintext for different keys';

        $encrypted1 = Utils_CryptTools::aesCbcEncrypt($plaintext, $key1, $iv);
        $encrypted2 = Utils_CryptTools::aesCbcEncrypt($plaintext, $key2, $iv);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testAesCbcEncryptProducesDifferentOutputWithDifferentIvs()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv1 = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $iv2 = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $plaintext = 'Same plaintext for different IVs';

        $encrypted1 = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv1);
        $encrypted2 = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv2);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testAesCbcWith128BitKey()
    {
        $key = openssl_random_pseudo_bytes(16); // 128-bit key
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-128-CBC'));
        $plaintext = 'Testing with 128-bit key';

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesCbcDecryptMCRYPT()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        // Simulate data that was encrypted with mcrypt (null padded)
        // Padding must be 1-7 null bytes (the regex strips at most 7)
        $plaintext = 'TestMcryptData!!'; // 16 bytes exactly, no padding needed
        $padded_plaintext = $plaintext . "\0\0\0\0\0"; // 21 bytes, padded to 32 with 11 null bytes but only 5 added
        // For a proper test: use a 13-byte string + 3 null bytes = 16 bytes
        $plaintext2 = 'McryptCompat!'; // 13 bytes
        $padded2 = str_pad($plaintext2, 16, "\0"); // 3 null bytes appended

        $encrypted = Utils_CryptTools::aesCbcEncrypt($padded2, $key, $iv, false);
        $decrypted = Utils_CryptTools::aesCbcDecryptMCRYPT($encrypted, $key, $iv);

        $this->assertEquals($plaintext2, $decrypted);
    }

    public function testAesCbcDecryptMCRYPTWithNullIv()
    {
        $key = openssl_random_pseudo_bytes(32);
        // 14 bytes + 2 null bytes = 16 bytes (1 block)
        $plaintext = 'MCryptNullIvT!'; // 14 bytes
        $padded_plaintext = str_pad($plaintext, 16, "\0");

        $encrypted = Utils_CryptTools::aesCbcEncrypt($padded_plaintext, $key, null, false);
        $decrypted = Utils_CryptTools::aesCbcDecryptMCRYPT($encrypted, $key);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesEcbDecryptRoundTrip()
    {
        $key = openssl_random_pseudo_bytes(16);
        $plaintext = 'ECB mode test data';

        $encrypted = openssl_encrypt($plaintext, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $decrypted = Utils_CryptTools::aesEcbDecrypt($encrypted, $key);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesEcbDecryptWithoutPkcs7()
    {
        $key = openssl_random_pseudo_bytes(16);
        // Must be multiple of block size for no-padding
        $plaintext = str_pad('ECBnoPad', 16, "\0");

        $encrypted = openssl_encrypt($plaintext, 'AES-128-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        $decrypted = Utils_CryptTools::aesEcbDecrypt($encrypted, $key, false);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testHashEqualsWithIdenticalStrings()
    {
        $this->assertTrue(Utils_CryptTools::hash_equals('hello', 'hello'));
    }

    public function testHashEqualsWithDifferentStrings()
    {
        $this->assertFalse(Utils_CryptTools::hash_equals('hello', 'world'));
    }

    public function testHashEqualsWithDifferentLengths()
    {
        $this->assertFalse(Utils_CryptTools::hash_equals('hello', 'hi'));
    }

    public function testHashEqualsWithEmptyStrings()
    {
        $this->assertTrue(Utils_CryptTools::hash_equals('', ''));
    }

    public function testHashEqualsWithBinaryData()
    {
        $bin1 = hex2bin('0123456789abcdef');
        $bin2 = hex2bin('0123456789abcdef');
        $bin3 = hex2bin('0123456789abcde0');

        $this->assertTrue(Utils_CryptTools::hash_equals($bin1, $bin2));
        $this->assertFalse(Utils_CryptTools::hash_equals($bin1, $bin3));
    }

    public function testHashEqualsWithSingleCharDifference()
    {
        $this->assertFalse(Utils_CryptTools::hash_equals('abcdef', 'abcde0'));
    }

    public function testAesCbcEncryptDecryptEmptyString()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

        $encrypted = Utils_CryptTools::aesCbcEncrypt('', $key, $iv);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv);

        $this->assertEquals('', $decrypted);
    }

    public function testAesCbcEncryptDecryptLargeData()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $plaintext = str_repeat('A', 10000);

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testAesCbcEncryptDecryptBinaryData()
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $plaintext = openssl_random_pseudo_bytes(100);

        $encrypted = Utils_CryptTools::aesCbcEncrypt($plaintext, $key, $iv);
        $decrypted = Utils_CryptTools::aesCbcDecrypt($encrypted, $key, $iv);

        $this->assertEquals($plaintext, $decrypted);
    }
}
