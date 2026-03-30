<?php

use PHPUnit\Framework\TestCase;

class MiscToolsTest extends TestCase
{
    // --- urlBase64Encode / urlBase64Decode ---

    public function testUrlBase64EncodeDecodeRoundTrip()
    {
        $data = 'Hello, World!';
        $encoded = Utils_MiscTools::urlBase64Encode($data);
        $decoded = Utils_MiscTools::urlBase64Decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function testUrlBase64EncodingRemovesUnsafeChars()
    {
        $data = openssl_random_pseudo_bytes(100);
        $encoded = Utils_MiscTools::urlBase64Encode($data);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    public function testUrlBase64DecodeWithStandardBase64()
    {
        $data = 'Test data with special chars: +/=';
        $standard_b64 = base64_encode($data);
        $url_b64 = str_replace(['+', '/', '='], ['-', '_', ''], $standard_b64);

        $decoded = Utils_MiscTools::urlBase64Decode($url_b64);
        $this->assertEquals($data, $decoded);
    }

    public function testUrlBase64EncodeEmpty()
    {
        $this->assertEquals('', Utils_MiscTools::urlBase64Encode(''));
    }

    public function testUrlBase64DecodeEmpty()
    {
        $this->assertEquals('', Utils_MiscTools::urlBase64Decode(''));
    }

    public function testUrlBase64EncodeBinaryData()
    {
        $data = hex2bin('0123456789abcdef');
        $encoded = Utils_MiscTools::urlBase64Encode($data);
        $decoded = Utils_MiscTools::urlBase64Decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    // --- i32a2Bin / bin2i32a ---

    public function testI32a2BinAndBin2i32aRoundTrip()
    {
        $i32a = [0x12345678, 0x9ABCDEF0, 0x11223344, 0x55667788];
        $bin = Utils_MiscTools::i32a2Bin($i32a);
        $result = Utils_MiscTools::bin2i32a($bin);

        $this->assertEquals($i32a, $result);
    }

    public function testI32a2BinSingleElement()
    {
        $i32a = [0x01020304];
        $bin = Utils_MiscTools::i32a2Bin($i32a);

        $this->assertEquals(4, strlen($bin));
        $this->assertEquals("\x01\x02\x03\x04", $bin);
    }

    public function testBin2i32aPadding()
    {
        // 3 bytes should be padded to 4 bytes (one 32-bit integer)
        $bin = "\x01\x02\x03";
        $result = Utils_MiscTools::bin2i32a($bin);

        $this->assertCount(1, $result);
    }

    public function testI32a2UrlBase64RoundTrip()
    {
        $i32a = [0x12345678, 0x9ABCDEF0];
        $encoded = Utils_MiscTools::i32a2UrlBase64($i32a);
        $decoded = Utils_MiscTools::urlBase642i32a($encoded);

        $this->assertEquals($i32a, $decoded);
    }

    // --- truncateText ---

    public function testTruncateTextShortText()
    {
        $text = 'Short text';
        $this->assertEquals($text, Utils_MiscTools::truncateText($text, 50));
    }

    public function testTruncateTextLongText()
    {
        $text = 'This is a very long text that should be truncated because it exceeds the maximum length allowed';
        $truncated = Utils_MiscTools::truncateText($text, 30);

        $this->assertStringContainsString(' ... ', $truncated);
        $this->assertLessThanOrEqual(35, strlen($truncated)); // 30 + separator length
    }

    public function testTruncateTextExactLength()
    {
        $text = 'Exact';
        // truncateText subtracts separator length first, so max_length needs to be larger
        $this->assertEquals($text, Utils_MiscTools::truncateText($text, 20));
    }

    public function testTruncateTextCustomSeparator()
    {
        $text = 'This is a very long text that needs to be truncated to fit in a small space';
        $truncated = Utils_MiscTools::truncateText($text, 30, '...');

        $this->assertStringContainsString('...', $truncated);
    }

    // --- formatBytes ---

    public function testFormatBytesZero()
    {
        $this->assertEquals('0 B', Utils_MiscTools::formatBytes(0));
    }

    public function testFormatBytesBytes()
    {
        $this->assertEquals('500 B', Utils_MiscTools::formatBytes(500));
    }

    public function testFormatBytesKilobytes()
    {
        $this->assertEquals('1 KB', Utils_MiscTools::formatBytes(1024));
    }

    public function testFormatBytesMegabytes()
    {
        $this->assertEquals('1 MB', Utils_MiscTools::formatBytes(1024 * 1024));
    }

    public function testFormatBytesGigabytes()
    {
        $this->assertEquals('1 GB', Utils_MiscTools::formatBytes(1024 * 1024 * 1024));
    }

    public function testFormatBytesTerabytes()
    {
        $this->assertEquals('1 TB', Utils_MiscTools::formatBytes(1024 * 1024 * 1024 * 1024));
    }

    public function testFormatBytesWithPrecision()
    {
        $this->assertEquals('1.5 KB', Utils_MiscTools::formatBytes(1536, 1));
    }

    public function testFormatBytesNegative()
    {
        $this->assertEquals('0 B', Utils_MiscTools::formatBytes(-100));
    }

    // --- isCacheableError ---

    public function testIsCacheableErrorBlocked()
    {
        $this->assertTrue(Utils_MiscTools::isCacheableError(Utils_MegaApi::EBLOCKED));
    }

    public function testIsCacheableErrorKey()
    {
        $this->assertTrue(Utils_MiscTools::isCacheableError(Utils_MegaApi::EKEY));
    }

    public function testIsCacheableErrorTooMany()
    {
        $this->assertTrue(Utils_MiscTools::isCacheableError(Utils_MegaApi::ETOOMANY));
    }

    public function testIsCacheableErrorNotCacheable()
    {
        $this->assertFalse(Utils_MiscTools::isCacheableError(Utils_MegaApi::EINTERNAL));
        $this->assertFalse(Utils_MiscTools::isCacheableError(Utils_MegaApi::ENOENT));
        $this->assertFalse(Utils_MiscTools::isCacheableError(Utils_MegaApi::EAGAIN));
    }

    // --- hideFileName ---

    public function testHideFileNameWithoutSalt()
    {
        $filename = 'TestMovie.S01E01.720p.mkv';
        $hidden = Utils_MiscTools::hideFileName($filename);

        $this->assertStringContainsString('************', $hidden);
        $this->assertStringContainsString('.mkv', $hidden);
    }

    public function testHideFileNameWithSalt()
    {
        $filename = 'TestMovie.S01E01.720p.mkv';
        $hidden = Utils_MiscTools::hideFileName($filename, 'mysalt');

        $this->assertStringContainsString('.mkv', $hidden);
        $this->assertStringNotContainsString('TestMovie', $hidden);
    }

    public function testHideFileNamePreservesExtension()
    {
        $filename = 'document.pdf';
        $hidden = Utils_MiscTools::hideFileName($filename);

        $this->assertStringEndsWith('.pdf', $hidden);
    }

    // --- isStreameableFile ---

    public function testIsStreameableFileMp4()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('movie.mp4'));
    }

    public function testIsStreameableFileMkv()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('video.mkv'));
    }

    public function testIsStreameableFileMp3()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('music.mp3'));
    }

    public function testIsStreameableFileAvi()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('video.avi'));
    }

    public function testIsStreameableFileWebm()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('video.webm'));
    }

    public function testIsStreameableFileFlac()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('audio.flac'));
    }

    public function testIsStreameableFileNotStreamable()
    {
        $this->assertEquals(0, Utils_MiscTools::isStreameableFile('document.pdf'));
        $this->assertEquals(0, Utils_MiscTools::isStreameableFile('image.jpg'));
        $this->assertEquals(0, Utils_MiscTools::isStreameableFile('archive.zip'));
        $this->assertEquals(0, Utils_MiscTools::isStreameableFile('code.php'));
    }

    public function testIsStreameableFileCaseInsensitive()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('video.MP4'));
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('audio.FLAC'));
    }

    public function testIsStreameableFileWithSpaces()
    {
        $this->assertEquals(1, Utils_MiscTools::isStreameableFile('  video.mp4  '));
    }

    // --- extractHostFromUrl ---

    public function testExtractHostFromUrlHttps()
    {
        $this->assertEquals('example.com', Utils_MiscTools::extractHostFromUrl('https://example.com/path'));
    }

    public function testExtractHostFromUrlHttp()
    {
        $this->assertEquals('example.com', Utils_MiscTools::extractHostFromUrl('http://example.com/path'));
    }

    public function testExtractHostFromUrlWithWww()
    {
        $this->assertEquals('www.example.com', Utils_MiscTools::extractHostFromUrl('https://www.example.com/path'));
    }

    public function testExtractHostFromUrlIgnoreWww()
    {
        $this->assertEquals('example.com', Utils_MiscTools::extractHostFromUrl('https://www.example.com/path', true));
    }

    public function testExtractHostFromUrlSubdomain()
    {
        $this->assertEquals('sub.example.com', Utils_MiscTools::extractHostFromUrl('https://sub.example.com'));
    }

    public function testExtractHostFromUrlNoProtocol()
    {
        $this->assertEquals('example.com', Utils_MiscTools::extractHostFromUrl('example.com/path'));
    }

    public function testExtractHostFromUrlInvalid()
    {
        $this->assertNull(Utils_MiscTools::extractHostFromUrl('not-a-url'));
    }

    // --- extractLinks ---

    public function testExtractLinksFromText()
    {
        // extractLinks separates by newlines or end-of-string, not spaces
        $text = "http://example.com\nhttps://other.com/path";
        $links = Utils_MiscTools::extractLinks($text);

        $this->assertNotNull($links);
        $this->assertCount(2, $links);
        $this->assertStringContainsString('http://example.com', $links[0]);
        $this->assertStringContainsString('https://other.com/path', $links[1]);
    }

    public function testExtractLinksNoLinks()
    {
        $this->assertNull(Utils_MiscTools::extractLinks('No links here'));
    }

    public function testExtractLinksSingleLink()
    {
        $links = Utils_MiscTools::extractLinks('Check https://example.com/test');
        $this->assertCount(1, $links);
    }

    // --- rimplode ---

    public function testRimplodeSimpleArray()
    {
        $result = Utils_MiscTools::rimplode(',', ['a', 'b', 'c']);
        $this->assertEquals('a,b,c', $result);
    }

    public function testRimplodeNestedArray()
    {
        $result = Utils_MiscTools::rimplode(',', ['a', ['b', 'c'], 'd']);
        $this->assertEquals('a,b,c,d', $result);
    }

    public function testRimplodeEmptyArray()
    {
        $result = Utils_MiscTools::rimplode(',', []);
        $this->assertNull($result);
    }

    // --- unescapeUnicodeChars ---

    public function testUnescapeUnicodeChars()
    {
        $this->assertEquals('Hello', Utils_MiscTools::unescapeUnicodeChars('\u0048\u0065\u006c\u006c\u006f'));
    }

    public function testUnescapeUnicodeCharsNoEscapes()
    {
        $this->assertEquals('Plain text', Utils_MiscTools::unescapeUnicodeChars('Plain text'));
    }

    // --- rCount ---

    public function testRCountFlatArray()
    {
        $this->assertEquals(3, Utils_MiscTools::rCount(['a', 'b', 'c']));
    }

    public function testRCountNestedArray()
    {
        $this->assertEquals(4, Utils_MiscTools::rCount(['a', ['b', 'c'], 'd']));
    }

    public function testRCountEmptyArray()
    {
        $this->assertEquals(0, Utils_MiscTools::rCount([]));
    }

    public function testRCountDeeplyNested()
    {
        $this->assertEquals(3, Utils_MiscTools::rCount([['a'], [['b']], 'c']));
    }

    // --- getMaxStringLength ---

    public function testGetMaxStringLengthSimple()
    {
        $this->assertEquals(5, Utils_MiscTools::getMaxStringLength(['ab', 'cde', 'fghij']));
    }

    public function testGetMaxStringLengthNested()
    {
        $this->assertEquals(6, Utils_MiscTools::getMaxStringLength(['ab', ['cde', 'fghijk']]));
    }

    public function testGetMaxStringLengthEmpty()
    {
        $this->assertEquals(0, Utils_MiscTools::getMaxStringLength([]));
    }

    // --- addExtraInfoToFilename ---

    public function testAddExtraInfoToFilename()
    {
        $result = Utils_MiscTools::addExtraInfoToFilename('video.mp4', 'Season 1');
        $this->assertStringContainsString('Season_1', $result);
        $this->assertStringContainsString('video.mp4', $result);
        $this->assertStringContainsString('__', $result);
    }

    public function testAddExtraInfoToFilenameEmpty()
    {
        $result = Utils_MiscTools::addExtraInfoToFilename('video.mp4', '');
        $this->assertEquals('video.mp4', $result);
    }

    public function testAddExtraInfoToFilenameNull()
    {
        $result = Utils_MiscTools::addExtraInfoToFilename('video.mp4', null);
        $this->assertEquals('video.mp4', $result);
    }
}
