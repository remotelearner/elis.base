<?php

// Include _modified_ config.php with global $CFG
require_once(dirname(__FILE__) .'/../../config.php'); 

if (!isset($CFG)) global $CFG; // required  when running tests from Moodle /admin/reports

require_once($CFG->dirroot .'/lib/base32.php');

class base32class {

    /**
     * @assert ('0') == 'ga'
     * @assert ('0000') == 'gaydama'
     * @assert ('00000') == 'gaydambq'
     * @assert ('1') == 'ge'
     * @assert ('101') == 'geydc'
     * @assert ('1111') == 'geytcmi'
     * @assert ('A') == 'ie'
     * @assert ('a') == 'me'
     * @assert ('ABC') == 'ifbeg'
     * @assert ('abc') == 'mfrgg'
     * @assert (' ') == 'ea'
     * @assert ('-_=+~!@#$%^&*()') == 'fvpt2k36efacgjbflytcukbj'
     * @assert ('0123456789') == 'gaytemzugu3doobz'
     */
    public function encode( $instring )
    {
        return base32_encode($instring);
    }

    /**
     * @assert ('o') throws Exception
     * @assert ('ioi') throws Exception
     * @assert ('ioioii') throws Exception
     * @assert ('ioiioiioi') throws Exception
     * @assert ('iiioiiioiiioiiioiii') throws Exception
     * @assert ('oI') throws Exception
     * @assert ('oL') throws Exception
     * @assert ('oO') throws Exception
     * @assert ('oU') throws Exception
     * @assert ('o0') throws Exception
     * @assert ('o1') throws Exception
     * @assert ('o8') throws Exception
     * @assert ('o9') throws Exception
     * @assert ('o-') throws Exception
     * @assert ('o_') throws Exception
     * @assert ('o=') throws Exception
     * @assert ('o+') throws Exception
     * @assert ('o~') throws Exception
     * @assert ('o!') throws Exception
     * @assert ('o@') throws Exception
     * @assert ('o#') throws Exception
     * @assert ('o$') throws Exception
     * @assert ('o%') throws Exception
     * @assert ('o^') throws Exception
     * @assert ('o&') throws Exception
     * @assert ('o*') throws Exception
     * @assert ('o(') throws Exception
     * @assert ('o)') throws Exception
     * @assert ('oA') throws Exception
     * @assert ('oB') throws Exception
     * @assert ('oC') throws Exception
     * ...
     * @assert ('oZ') throws Exception
     * @assert ('o2') throws Exception
     * @assert ('o3') throws Exception
     * @assert ('o4') == 'w'
     * @assert ('o5') throws Exception
     * @assert ('o6') throws Exception
     * @assert ('o7') throws Exception
     * @assert ('oi') == 'r'
     * @assert ('ii') == 'B'
     * @assert ('ae') == "\x01"
     * @assert ('ai') == "\x02"
     * @assert ('ioiio') == "\x43\x90\x87"
     * ...
     */
    public function decode( $instring )
    {
        return Base32_decode($instring);
    }

}

if (!defined('PHPUnit_MAIN_METHOD') && !defined('PHPUNIT_SCRIPT') && !defined('MOODLE_INTERNAL')) {
    // This section for command-line use only!
    if ($argc < 3) {
        echo "usage: ". basename(__FILE__)." -e|-d {string}\n";
    }

    //echo "Number of args: $argc ... ";
    //var_dump($argv);

    printf($argv[2] . "\n");

    $b32cl = new base32class();
    $result = ($argv[1] == '-e') ? $b32cl->encode($argv[2])
                                 : $b32cl->decode($argv[2]);
    echo $result . ' (';
    $slen = strlen($result);
    for ($i = 0; $i < $slen; ++$i) {
        echo ord(substr($result, $i, 1)); 
        if ($i < ($slen - 1))  echo ', ';
    }
    echo ")\n";
}

