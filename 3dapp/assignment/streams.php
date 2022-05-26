<?php

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Utils;


function load_file_stream($filename)
{
    $file = (__DIR__ .'/'. $filename);
    $stream = new LazyOpenStream($file, 'r');
    return $stream;
}

function do_replacement($stream, $insertion)
{
    // end of stream identifier
    $done = false;
    $combi = null;
    $idx = 0;
    // buffer for finding template literal {{%%%}}
    $ptr = '';
    $buffer = '';
    // we use an extra pair of braces
    // this only matches the inner part
    $template_literal = '{%%%}';

    have_stream:
    if ($combi != null) {
        // delegate if already have stream
        return $combi;
    }

    find_trigger:
    while (!$stream->eof()) {
        $ptr = $stream->read(1);
        $buffer = $buffer . $ptr;
        $idx = strlen($buffer);
        // we remove the triggering { later with substr
        if (strcmp('{', $ptr) == 0) {
            break;
        }
    }

    should_stop:
    if ($stream->eof()) {
        // we either found a { or and EOF
        $done = true;
    }

    find_inner:
    // see if the coming bytes match our mask
    if (!$done && !(strcmp($template_literal, $ptr = $stream->read(strlen($template_literal))) == 0)) {
        // rewind to triggering character
        $stream->seek($idx);
        // continue searching from the next index
        goto find_trigger;
    }

    find_end:
    if (!$done && !((strcmp('}', $ptr = $stream->read(1)) == 0))) {
        $stream->seek($idx);
        goto find_trigger;
    }

    $prefix = Utils::streamFor(substr($buffer, 0, strlen($buffer) - 1));
    $template = Utils::streamFor($insertion);
    $rest_str = Utils::copyToString($stream);
    $rest = Utils::streamFor($rest_str);

    bail:
    if ($done) {
        $combi = new AppendStream([$prefix, $rest]);
        goto have_stream;
    }

    cache_stream:
    $combi =  new AppendStream([$prefix, $template, $rest]);
    // $this->combi = new AppendStream([$prefix, $template, $rest]);
    goto have_stream;
}

