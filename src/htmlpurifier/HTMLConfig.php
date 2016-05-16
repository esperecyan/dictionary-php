<?php
namespace esperecyan\dictionary_php\htmlpurifier;

/**
 * HTML Standard の要素・属性を ezyang/htmlpurifier で利用できるようにする。
 */
class HTMLConfig extends \HTMLPurifier_HTML5Config
{
    public static function create($config = null, \HTMLPurifier_ConfigSchema $schema = null)
    {
        $configObj = parent::create($config, $schema);
        $def = $configObj->getHTMLDefinition(true);
        
        $def->addElement('audio', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
            'src' => 'URI',
            'preload' => 'Enum#auto,metadata,none',
            'controls' => 'Bool',
        ]);
        $def->addElement('bdi', 'Inline', 'Inline', 'Common');
        $def->addElement('data', 'Inline', 'Inline', 'Common', ['value' => 'Text']);
        
        $def->manager->addModule(new \HTMLPurifier_HTMLModule_Ruby());
        $def->addElement('ruby', 'Inline', 'Custom: ((Inline | #PCDATA)+, (rt+ | (rp, (rt+, rp?)+)))+', 'Common');
        
        $def->getAnonymousModule()->attr_collections['Core']['translate'] = 'Enum#,yes,no';
        $def->getAnonymousModule()->attr_collections['Core']['dir'] = 'Enum#ltr,rtl,auto';
        $def->addAttribute('ol', 'reversed', 'Bool');

        return $configObj;
    }
}
