<?php
namespace esperecyan\dictionary_php\fileinfo;

class FinfoTest extends \PHPUnit\Framework\TestCase
{
    /** @var Fileinfo 拡張モジュールでエラーが発生するファイルの中身。 */
    const CONTENTS = "`\r\n`\r\n";
    
    public function testBuffer()
    {
        $this->assertSame('text/plain', (new Finfo(FILEINFO_MIME_TYPE))->buffer(self::CONTENTS));
        
        $this->assertSame('text/plain', (new Finfo())->buffer(self::CONTENTS, FILEINFO_MIME_TYPE));
        
        $finfo = new Finfo();
        $finfo->set_flags(FILEINFO_MIME_TYPE);
        $this->assertSame('text/plain', $finfo->buffer(self::CONTENTS));
        
        $this->assertSame('audio/mp4', $finfo->buffer(file_get_contents(__DIR__ . '/../resources/mpeg4-aac.m4a')));
    }
    
    public function testFile()
    {
        $fp = tmpfile();
        fwrite($fp, self::CONTENTS);
        $filename = stream_get_meta_data($fp)['uri'];
        
        $this->assertSame('text/plain', (new Finfo(FILEINFO_MIME_TYPE))->file($filename));
        
        $this->assertSame('text/plain', (new Finfo())->file($filename, FILEINFO_MIME_TYPE));
        
        $finfo = new Finfo();
        $finfo->set_flags(FILEINFO_MIME_TYPE);
        $this->assertSame('text/plain', $finfo->file($filename));
        
        $this->assertSame('audio/mp4', $finfo->file(__DIR__ . '/../resources/mpeg4-aac.m4a'));
    }
}
