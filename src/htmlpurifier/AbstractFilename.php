<?php
namespace esperecyan\dictionary_php\htmlpurifier;

abstract class AbstractFilename extends \HTMLPurifier_AttrDef
{
    /** @var string 要素名に対応するフィールド名。 */
    const FIELD_NAME = '';
    
    /** @var string[] */
    protected $filenames = [];
    
    /**
     * @param string[] $filenames
     */
    public function __construct(array $filenames = [])
    {
        $this->filenames = $filenames;
    }
    
    public function validate($string, $config, $context)
    {
        return (new \esperecyan\dictionary_php\validator\FileLocationValidator(
            static::FIELD_NAME,
            $this->filenames
        ))->validate($string) ? $string : false;
    }
}
