<?php

use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testConstructorWithEmptyExtra()
    {
        $request = new Utils_Request();
        $this->assertNull($request->getExtraVar('nonexistent'));
    }

    public function testConstructorWithExtra()
    {
        $request = new Utils_Request(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('value1', $request->getExtraVar('key1'));
        $this->assertEquals('value2', $request->getExtraVar('key2'));
    }

    public function testGetExtraVarAll()
    {
        $extra = ['a' => 1, 'b' => 2];
        $request = new Utils_Request($extra);
        $this->assertEquals($extra, $request->getExtraVar());
    }

    public function testGetExtraVarNonexistent()
    {
        $request = new Utils_Request(['key' => 'value']);
        $this->assertNull($request->getExtraVar('missing'));
    }

    public function testGetVarReturnsGet()
    {
        $_GET = ['param' => 'value'];
        $request = new Utils_Request();
        $this->assertEquals('value', $request->getVar('param'));
        $_GET = [];
    }

    public function testGetVarReturnsAllGet()
    {
        $_GET = ['a' => '1', 'b' => '2'];
        $request = new Utils_Request();
        $this->assertEquals($_GET, $request->getVar());
        $_GET = [];
    }

    public function testGetVarNonexistent()
    {
        $_GET = [];
        $request = new Utils_Request();
        $this->assertNull($request->getVar('missing'));
    }

    public function testGetPostVarReturnsPost()
    {
        $_POST = ['field' => 'data'];
        $request = new Utils_Request();
        $this->assertEquals('data', $request->getPostVar('field'));
        $_POST = [];
    }

    public function testGetPostVarReturnsAllPost()
    {
        $_POST = ['x' => 'y'];
        $request = new Utils_Request();
        $this->assertEquals($_POST, $request->getPostVar());
        $_POST = [];
    }

    public function testGetPostVarNonexistent()
    {
        $_POST = [];
        $request = new Utils_Request();
        $this->assertNull($request->getPostVar('missing'));
    }

    public function testGetServerVar()
    {
        $request = new Utils_Request();
        $this->assertIsArray($request->getServerVar());
    }

    public function testGetServerVarSpecific()
    {
        $_SERVER['TEST_VAR'] = 'test_value';
        $request = new Utils_Request();
        $this->assertEquals('test_value', $request->getServerVar('TEST_VAR'));
        unset($_SERVER['TEST_VAR']);
    }

    public function testGetServerVarNonexistent()
    {
        $request = new Utils_Request();
        $this->assertNull($request->getServerVar('DEFINITELY_NOT_SET_' . mt_rand()));
    }

    public function testGetEnvVar()
    {
        $request = new Utils_Request();
        $result = $request->getEnvVar();
        $this->assertIsArray($result);
    }

    public function testGetFileVar()
    {
        $_FILES = [];
        $request = new Utils_Request();
        $this->assertEquals([], $request->getFileVar());
    }

    public function testGetFileVarNonexistent()
    {
        $_FILES = [];
        $request = new Utils_Request();
        $this->assertNull($request->getFileVar('missing'));
    }
}
