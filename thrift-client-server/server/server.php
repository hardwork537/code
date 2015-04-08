<?php

/*
 * This is not a stand-alone server listening over a socket.  It
 * needs a web server behind it.
 */

require_once( 'content.php' );

if (php_sapi_name() == 'cli') {
    ini_set("display_errors", "stderr");
}

$GLOBALS['THRIFT_ROOT'] = '../lib/php/src';

require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/transport/TPhpStream.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/transport/TBufferedTransport.php';

$GEN_DIR = '../gen-php';

require_once $GEN_DIR . '/zemanta/zemanta.php';

class ZemantaAnalyzer implements zemantaIf {

    protected $log = array();

    public function analyze($text) {
        $content = new Content($text);
        $behaviors = $content->analyze(true);

        $zemanta = new zemanta_ZemantaAnalysis();
        $zemanta->dmoz = array();
        $zemanta->freebase = array();

        # error_log( 'SERVER::Final behaviors: ' . json_encode( $behaviors ) );

        /** Final processing to get to the format Thrift expects */
        foreach ($behaviors as $source => $behavior) {
            $property = strtolower($source);
            $class = 'zemanta_' . ucwords($property);

            foreach ($behavior as $name => $detail) {
                error_log('Attempting to instantiate a new ' . $class . ' object');

                $analysis = new $class();
                $analysis->topic = $name;
                $analysis->categories = $detail['categories'];
                $analysis->confidence = $detail['confidence'];

                if (array_key_exists('id', $detail)) {
                    $analysis->id = $detail['id'];
                }

                array_push($zemanta->$property, $analysis);
            }
        }

        # error_log( 'SERVER::Final stack: ' . json_encode( $analysis ) );

        return $zemanta;
    }

}

;

header('Content-Type', 'application/x-thrift');

$handler = new ZemantaAnalyzer();
$processor = new zemantaProcessor($handler);

$transport = new TBufferedTransport(new TPhpStream(TPhpStream::MODE_R | TPhpStream::MODE_W));
$protocol = new TBinaryProtocol($transport, true, true);

$transport->open();
$processor->process($protocol, $protocol);
$transport->close();
