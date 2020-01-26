<?php
namespace esperecyan\dictionary_php\fileinfo;

class FinfoTest extends \PHPUnit\Framework\TestCase
{
    public function testBuffer()
    {
        $finfo = new Finfo();
        $finfo->set_flags(FILEINFO_MIME_TYPE);
        $this->assertSame('audio/mp4', $finfo->buffer(file_get_contents(__DIR__ . '/../resources/mpeg4-aac.m4a')));
    }
    
    public function testFile()
    {
        $finfo = new Finfo();
        $finfo->set_flags(FILEINFO_MIME_TYPE);
        $this->assertSame('audio/mp4', $finfo->file(__DIR__ . '/../resources/mpeg4-aac.m4a'));
    }
}
