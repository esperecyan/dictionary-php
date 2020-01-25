<?php
namespace esperecyan\dictionary_php\internal;

class TransliteratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string $kanjiAndKanaString
     * @param string $latinAlphabetString
     * @dataProvider stringProvider
     */
    public function testTranslateUsingLatinAlphabet(string $kanjiAndKanaString, string $latinAlphabetString)
    {
        $this->assertSame($latinAlphabetString, Transliterator::translateUsingLatinAlphabet($kanjiAndKanaString));
    }
    
    public function stringProvider(): array
    {
        return [
            ['春と修羅', 'haru-to-shura'],
            ['これは何？', 'kore-wa-nani-?'],
            ['fooｂａｒ', 'foobar'],
            ['a.,。z', 'a-.,.-z'],
            ['太陽', 'taiyo'],
            ['taiyō', 'taiyo'],
            ['①', '1'],
            ['が', 'ga'],
            ['TEST', 'TEST'],
        ];
    }
}
